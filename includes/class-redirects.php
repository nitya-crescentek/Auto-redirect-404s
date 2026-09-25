<?php
/**
 * Manual redirect rules: storage, caching and request matching.
 *
 * Rules live in their own table. Matching runs on every front-end request,
 * so it is built to cost nothing when there are no rules and very little
 * when there are:
 *
 * - An autoloaded option holds the number of enabled exact and pattern rules.
 *   With no rules the matcher returns before touching the database.
 * - Exact rules are found with one indexed query on a hash of the path.
 * - Wildcard and regex rules have to be tested one by one, so the enabled ones
 *   are cached in a single non-autoloaded option, rebuilt on every change.
 *
 * @package Redirect404Custom
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Every query below targets this plugin's own custom table. A table name is an
// identifier, and $wpdb->prepare() cannot parameterise identifiers, so it has to
// be interpolated. All caller-supplied values are still passed as placeholders,
// and ORDER BY columns are constrained to a fixed allowlist by sanitize_orderby().
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Redirect rules class
 */
class R404C_Redirects {

    /**
     * Schema version. Bump when the table definition changes.
     */
    const DB_VERSION = '1';

    /**
     * Option holding the installed schema version.
     */
    const DB_VERSION_OPTION = 'r404c_redirects_db_version';

    /**
     * Autoloaded option: counts of enabled exact and pattern rules.
     */
    const INDEX_OPTION = 'r404c_redirect_index';

    /**
     * Non-autoloaded option: every enabled wildcard and regex rule.
     */
    const PATTERNS_OPTION = 'r404c_redirect_patterns';

    /**
     * Maximum stored length of a source or target.
     */
    const MAX_URL_LENGTH = 2000;

    /**
     * Per-request cache for table_exists(). Null until first checked.
     *
     * @var bool|null
     */
    private static $table_exists = null;

    /**
     * Redirect types offered for a rule.
     *
     * @return array Status code => label.
     */
    public static function status_codes() {
        return array(
            301 => __('301 - Moved Permanently', 'auto-redirect-404s'),
            302 => __('302 - Found (Temporary)', 'auto-redirect-404s'),
            307 => __('307 - Temporary Redirect', 'auto-redirect-404s'),
            308 => __('308 - Permanent Redirect', 'auto-redirect-404s'),
            410 => __('410 - Content Deleted (Gone)', 'auto-redirect-404s'),
        );
    }

    /**
     * Ways a rule's source can be matched.
     *
     * @return array Key => label.
     */
    public static function match_types() {
        return array(
            'exact'    => __('Exact URL', 'auto-redirect-404s'),
            'wildcard' => __('Wildcard', 'auto-redirect-404s'),
            'regex'    => __('Regular Expression', 'auto-redirect-404s'),
        );
    }

    /**
     * Ways a rule can treat the request's query string.
     *
     * @return array Key => label.
     */
    public static function query_modes() {
        return array(
            'ignore' => __('Ignore query string', 'auto-redirect-404s'),
            'pass'   => __('Ignore and pass to target', 'auto-redirect-404s'),
            'exact'  => __('Exact match', 'auto-redirect-404s'),
        );
    }

    /**
     * Get the fully qualified redirects table name.
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'r404c_redirects';
    }

    /**
     * Whether the redirects table exists.
     *
     * Same approach as R404C_Logger::table_exists(): an autoloaded schema stamp
     * answers the common case without a query.
     *
     * @param bool $force Skip the option fast path and query the schema.
     * @return bool
     */
    public static function table_exists($force = false) {
        if (!$force && null !== self::$table_exists) {
            return self::$table_exists;
        }

        if (!$force && get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            self::$table_exists = true;
            return true;
        }

        global $wpdb;
        $table = self::table_name();

        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));

        self::$table_exists = ($found === $table);

        return self::$table_exists;
    }

    /**
     * Create or upgrade the redirects table.
     *
     * @return bool True when the table exists after the call.
     */
    public static function install_table() {
        global $wpdb;

        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_url varchar(2000) NOT NULL,
            source_hash char(32) NOT NULL,
            match_type varchar(10) NOT NULL DEFAULT 'exact',
            query_mode varchar(10) NOT NULL DEFAULT 'ignore',
            target_url varchar(2000) NOT NULL DEFAULT '',
            status_code smallint(3) unsigned NOT NULL DEFAULT 301,
            is_enabled tinyint(1) unsigned NOT NULL DEFAULT 1,
            hit_count bigint(20) unsigned NOT NULL DEFAULT 0,
            last_hit datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY source_lookup (source_hash,match_type,is_enabled),
            KEY is_enabled (is_enabled)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        self::$table_exists = null;

        if (self::table_exists(true)) {
            // Autoloaded on purpose: table_exists() reads it on the frontend.
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION, true);
            return true;
        }

        delete_option(self::DB_VERSION_OPTION);

        return false;
    }

    /* ------------------------------------------------------------------
     * Request matching
     * ------------------------------------------------------------------ */

    /**
     * Find the rule that applies to a request.
     *
     * Exact rules win over pattern rules. Among exact rules, one that matches
     * the query string exactly wins over one that ignores it. Pattern rules are
     * tried oldest first.
     *
     * @param string $path  Raw request path, without query string.
     * @param string $query Raw query string, without the leading '?'.
     * @return array|null {id, target, code} or null when nothing matches.
     */
    public static function match_request($path, $query = '') {
        $index = get_option(self::INDEX_OPTION);

        if (!is_array($index) || (empty($index['exact']) && empty($index['pattern']))) {
            return null;
        }

        if (!self::table_exists()) {
            return null;
        }

        $path  = '' === (string) $path ? '/' : (string) $path;
        $query = (string) $query;

        if (!empty($index['exact'])) {
            $rule = self::match_exact($path, $query);

            if ($rule) {
                return self::build_match($rule, array(), $query);
            }
        }

        if (!empty($index['pattern'])) {
            foreach (self::get_pattern_rules() as $rule) {
                $captures = self::match_pattern($rule, $path, $query);

                if (null !== $captures) {
                    return self::build_match($rule, $captures, $query);
                }
            }
        }

        return null;
    }

    /**
     * Look up an enabled exact rule for a path.
     *
     * @param string $path  Request path.
     * @param string $query Request query string.
     * @return array|null Rule row.
     */
    private static function match_exact($path, $query) {
        global $wpdb;
        $table = self::table_name();

        // A lookup failure must never break the visitor's request.
        $suppress = $wpdb->suppress_errors(true);

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, source_url, query_mode, target_url, status_code FROM {$table}
                 WHERE source_hash = %s AND match_type = 'exact' AND is_enabled = 1
                 ORDER BY id ASC",
                md5(self::path_key($path))
            ),
            ARRAY_A
        );

        $wpdb->suppress_errors($suppress);

        if (empty($rows)) {
            return null;
        }

        $query_key = self::normalize_query($query);
        $fallback  = null;

        foreach ($rows as $row) {
            if ('exact' === $row['query_mode']) {
                list(, $rule_query) = self::split_source($row['source_url']);

                // The most specific match there is, so it wins outright.
                if (self::normalize_query($rule_query) === $query_key) {
                    return $row;
                }

                continue;
            }

            if (null === $fallback) {
                $fallback = $row;
            }
        }

        return $fallback;
    }

    /**
     * Test a wildcard or regex rule against a request.
     *
     * @param array  $rule  Cached rule.
     * @param string $path  Request path.
     * @param string $query Request query string.
     * @return array|null Capture groups on a match, otherwise null.
     */
    private static function match_pattern($rule, $path, $query) {
        $regex = self::pattern_regex($rule['match_type'], $rule['source_url']);

        $subject = rawurldecode($path);

        if ('exact' === $rule['query_mode'] && '' !== $query) {
            $subject .= '?' . rawurldecode($query);
        }

        // A pattern that stopped compiling (or hits the backtrack limit) is a
        // non-match, never a PHP warning on the visitor's page. Patterns are
        // validated on save, so this is a last line of defence.
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        if (1 !== @preg_match($regex, $subject, $captures)) {
            return null;
        }

        return $captures;
    }

    /**
     * Build the PCRE expression for a wildcard or regex source.
     *
     * Regex sources are wrapped in a \x01 delimiter: sanitisation strips
     * control characters, so it can never appear inside the pattern and no
     * escaping is needed.
     *
     * A wildcard's * becomes a capture group, so its matches can be reused as
     * $1, $2 … in the target. The trailing slash is optional, as it is for
     * exact rules.
     *
     * @param string $match_type 'wildcard' or 'regex'.
     * @param string $source     Stored source.
     * @return string
     */
    private static function pattern_regex($match_type, $source) {
        if ('regex' === $match_type) {
            return "\x01" . $source . "\x01i";
        }

        // Compared against the decoded request path, so decode the source too.
        $source = rawurldecode($source);

        if ('*' !== substr($source, -1)) {
            $source = rtrim($source, '/');
        }

        $body = str_replace('\*', '(.*?)', preg_quote($source, '#'));

        return '#^' . $body . '/?$#i';
    }

    /**
     * Turn a matched rule into a redirect instruction.
     *
     * @param array  $rule     Rule row.
     * @param array  $captures Pattern captures; empty for exact rules.
     * @param string $query    Request query string.
     * @return array {id, target, code}
     */
    private static function build_match($rule, $captures, $query) {
        $code   = (int) $rule['status_code'];
        $target = '';

        if (410 !== $code) {
            $target = (string) $rule['target_url'];

            if (!empty($captures)) {
                $target = preg_replace_callback(
                    '/\$(\d)/',
                    function ($m) use ($captures) {
                        if (!isset($captures[$m[1]])) {
                            return '';
                        }

                        // Captures come from the decoded path, so re-encode
                        // them, leaving path separators readable.
                        return str_replace('%2F', '/', rawurlencode($captures[$m[1]]));
                    },
                    $target
                );
            }

            $target = self::resolve_target($target);

            if ('pass' === $rule['query_mode'] && '' !== $query) {
                $fragment = '';
                $hash_pos = strpos($target, '#');

                if (false !== $hash_pos) {
                    $fragment = substr($target, $hash_pos);
                    $target   = substr($target, 0, $hash_pos);
                }

                $target .= (false === strpos($target, '?') ? '?' : '&') . $query . $fragment;
            }
        }

        return array(
            'id'     => (int) $rule['id'],
            'target' => $target,
            'code'   => $code,
        );
    }

    /**
     * Enabled wildcard and regex rules, from the cache.
     *
     * @return array
     */
    private static function get_pattern_rules() {
        $rules = get_option(self::PATTERNS_OPTION, array());

        return is_array($rules) ? $rules : array();
    }

    /**
     * Rebuild the lookup cache from the table.
     *
     * Called after every change to the rules.
     *
     * @return void
     */
    public static function rebuild_cache() {
        if (!self::table_exists(true)) {
            update_option(self::INDEX_OPTION, array('exact' => 0, 'pattern' => 0), true);
            update_option(self::PATTERNS_OPTION, array(), false);
            return;
        }

        global $wpdb;
        $table = self::table_name();

        $exact = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE is_enabled = 1 AND match_type = 'exact'"
        );

        $patterns = $wpdb->get_results(
            "SELECT id, source_url, match_type, query_mode, target_url, status_code FROM {$table}
             WHERE is_enabled = 1 AND match_type IN ('wildcard', 'regex')
             ORDER BY id ASC",
            ARRAY_A
        );

        $patterns = is_array($patterns) ? $patterns : array();

        update_option(self::PATTERNS_OPTION, $patterns, false);

        // Autoloaded: match_request() reads it on every front-end request.
        update_option(
            self::INDEX_OPTION,
            array(
                'exact'   => $exact,
                'pattern' => count($patterns),
            ),
            true
        );
    }

    /**
     * Count a hit against a rule.
     *
     * @param int $id Rule ID.
     * @return void
     */
    public static function record_hit($id) {
        global $wpdb;
        $table = self::table_name();

        $suppress = $wpdb->suppress_errors(true);

        // hit_count must increment atomically, so this cannot use $wpdb->update().
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$table} SET hit_count = hit_count + 1, last_hit = %s WHERE id = %d",
                current_time('mysql'),
                (int) $id
            )
        );

        $wpdb->suppress_errors($suppress);
    }

    /* ------------------------------------------------------------------
     * Saving
     * ------------------------------------------------------------------ */

    /**
     * Validate and normalise a rule submitted from the form or a CSV import.
     *
     * @param array $data Raw values: source_url, target_url, match_type,
     *                    query_mode, status_code, is_enabled.
     * @return array|WP_Error Clean rule, or the reason it was rejected.
     */
    public static function prepare_rule($data) {
        $match_type = isset($data['match_type']) ? (string) $data['match_type'] : 'exact';
        $query_mode = isset($data['query_mode']) ? (string) $data['query_mode'] : 'ignore';
        $code       = isset($data['status_code']) ? (int) $data['status_code'] : 301;

        if (!array_key_exists($match_type, self::match_types())) {
            return new WP_Error('invalid_match', __('Choose a valid match type.', 'auto-redirect-404s'));
        }

        if (!array_key_exists($query_mode, self::query_modes())) {
            return new WP_Error('invalid_query', __('Choose a valid query string option.', 'auto-redirect-404s'));
        }

        if (!array_key_exists($code, self::status_codes())) {
            return new WP_Error('invalid_code', __('Choose a valid redirect type.', 'auto-redirect-404s'));
        }

        $raw_source = isset($data['source_url']) ? $data['source_url'] : '';

        if (strlen(self::clean_input($raw_source)) > self::MAX_URL_LENGTH) {
            return new WP_Error('too_long', __('The source URL is too long.', 'auto-redirect-404s'));
        }

        $source = self::normalize_source($raw_source, $match_type, $query_mode);

        if ('' === $source) {
            return new WP_Error('no_source', __('Enter the source URL to redirect from.', 'auto-redirect-404s'));
        }

        if ('regex' === $match_type && !self::is_valid_regex($source)) {
            return new WP_Error('invalid_regex', __('The regular expression is not valid. Check it for unbalanced brackets or unescaped characters.', 'auto-redirect-404s'));
        }

        $target = '';

        if (410 !== $code) {
            $raw_target = self::clean_input(isset($data['target_url']) ? $data['target_url'] : '');

            if ('' === $raw_target) {
                return new WP_Error('no_target', __('Enter the target URL to redirect to.', 'auto-redirect-404s'));
            }

            if (strlen($raw_target) > self::MAX_URL_LENGTH) {
                return new WP_Error('too_long', __('The target URL is too long.', 'auto-redirect-404s'));
            }

            $target = self::sanitize_target($raw_target);

            if ('' === $target) {
                return new WP_Error('invalid_target', __('The target must be a path on this site starting with /, or a full http:// or https:// URL.', 'auto-redirect-404s'));
            }

            if ('exact' === $match_type && self::points_to_itself($source, $query_mode, $target)) {
                return new WP_Error('self_redirect', __('This redirect would send visitors back to the same URL, creating a loop.', 'auto-redirect-404s'));
            }
        }

        return array(
            'source_url'  => $source,
            'source_hash' => self::source_hash($source, $match_type),
            'match_type'  => $match_type,
            'query_mode'  => $query_mode,
            'target_url'  => $target,
            'status_code' => $code,
            'is_enabled'  => empty($data['is_enabled']) ? 0 : 1,
        );
    }

    /**
     * Insert or update a rule.
     *
     * @param array $data          Raw rule values, see prepare_rule().
     * @param int   $id            Rule to update, or 0 to insert.
     * @param bool  $rebuild_cache Rebuild the lookup cache afterwards. Bulk
     *                             imports pass false and rebuild once at the end.
     * @return int|WP_Error Rule ID, or the reason it was not saved.
     */
    public static function save($data, $id = 0, $rebuild_cache = true) {
        $rule = self::prepare_rule($data);

        if (is_wp_error($rule)) {
            return $rule;
        }

        if (!self::table_exists() && !self::install_table()) {
            return new WP_Error('no_table', __('The redirects table could not be created. Please check your database permissions.', 'auto-redirect-404s'));
        }

        $id = absint($id);

        $duplicate = self::find_duplicate($rule, $id);
        if ($duplicate) {
            return new WP_Error(
                'duplicate',
                __('A redirect for this source URL already exists.', 'auto-redirect-404s'),
                array('existing_id' => $duplicate)
            );
        }

        global $wpdb;
        $table = self::table_name();
        $now   = current_time('mysql');

        $rule['updated_at'] = $now;
        $formats = array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s');

        if ($id) {
            $result = $wpdb->update($table, $rule, array('id' => $id), $formats, array('%d'));
        } else {
            $rule['created_at'] = $now;
            $formats[] = '%s';

            $result = $wpdb->insert($table, $rule, $formats);
            $id     = (int) $wpdb->insert_id;
        }

        if (false === $result || !$id) {
            return new WP_Error('db_error', __('The redirect could not be saved to the database.', 'auto-redirect-404s'));
        }

        if ($rebuild_cache) {
            self::rebuild_cache();
        }

        return $id;
    }

    /**
     * Find an existing rule with the same source.
     *
     * @param array $rule       Prepared rule.
     * @param int   $exclude_id Rule being edited.
     * @return int ID of the duplicate, or 0.
     */
    private static function find_duplicate($rule, $exclude_id) {
        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, source_url, query_mode FROM {$table}
                 WHERE source_hash = %s AND match_type = %s AND id != %d",
                $rule['source_hash'],
                $rule['match_type'],
                (int) $exclude_id
            )
        );

        foreach ((array) $rows as $row) {
            if ('exact' !== $rule['match_type']) {
                // Pattern hashes are of the whole case-folded source.
                return (int) $row->id;
            }

            // Two exact rules on one path only clash when they would both
            // claim the same requests: both ignore the query string, or both
            // require the same one.
            $new_exact = ('exact' === $rule['query_mode']);
            $old_exact = ('exact' === $row->query_mode);

            if (!$new_exact && !$old_exact) {
                return (int) $row->id;
            }

            if ($new_exact && $old_exact) {
                list(, $new_query) = self::split_source($rule['source_url']);
                list(, $old_query) = self::split_source($row->source_url);

                if (self::normalize_query($new_query) === self::normalize_query($old_query)) {
                    return (int) $row->id;
                }
            }
        }

        return 0;
    }

    /**
     * Delete rules by ID.
     *
     * @param array $ids Rule IDs.
     * @return int Number deleted.
     */
    public static function delete($ids) {
        $deleted = self::query_ids("DELETE FROM %s WHERE id IN (%s)", $ids);

        if ($deleted) {
            self::rebuild_cache();
        }

        return $deleted;
    }

    /**
     * Enable or disable rules.
     *
     * @param array $ids     Rule IDs.
     * @param bool  $enabled New state.
     * @return int Number changed.
     */
    public static function set_enabled($ids, $enabled) {
        $sql = $enabled
            ? "UPDATE %s SET is_enabled = 1 WHERE id IN (%s)"
            : "UPDATE %s SET is_enabled = 0 WHERE id IN (%s)";

        $changed = self::query_ids($sql, $ids);

        self::rebuild_cache();

        return $changed;
    }

    /**
     * Reset hit counters.
     *
     * @param array $ids Rule IDs.
     * @return int Number changed.
     */
    public static function reset_hits($ids) {
        return self::query_ids("UPDATE %s SET hit_count = 0, last_hit = NULL WHERE id IN (%s)", $ids);
    }

    /**
     * Run a statement against a list of IDs.
     *
     * @param string $template SQL with %s for the table and %s for the ID list.
     *                         Only ever one of the literals in this class.
     * @param array  $ids      Rule IDs.
     * @return int Rows affected.
     */
    private static function query_ids($template, $ids) {
        if (!self::table_exists()) {
            return 0;
        }

        $ids = array_filter(array_map('absint', (array) $ids));
        if (empty($ids)) {
            return 0;
        }

        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql          = sprintf($template, self::table_name(), $placeholders);

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $sql is a class literal plus one %d per id.
        return (int) $wpdb->query($wpdb->prepare($sql, $ids));
    }

    /* ------------------------------------------------------------------
     * Reading
     * ------------------------------------------------------------------ */

    /**
     * Fetch one rule.
     *
     * @param int $id Rule ID.
     * @return object|null
     */
    public static function get($id) {
        if (!self::table_exists()) {
            return null;
        }

        global $wpdb;
        $table = self::table_name();

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", (int) $id));
    }

    /**
     * Fetch rules for the admin list.
     *
     * @param array $args {
     *     @type string $search  Matched against source and target.
     *     @type string $status  all, enabled or disabled.
     *     @type string $orderby Column from sanitize_orderby().
     *     @type string $order   ASC or DESC.
     *     @type int    $per_page
     *     @type int    $paged
     * }
     * @return array
     */
    public static function get_rules($args = array()) {
        if (!self::table_exists()) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();

        $args = wp_parse_args(
            $args,
            array(
                'search'   => '',
                'status'   => 'all',
                'orderby'  => 'id',
                'order'    => 'DESC',
                'per_page' => 20,
                'paged'    => 1,
            )
        );

        list($where, $params) = self::build_where($args['search'], $args['status']);

        $orderby = esc_sql(self::sanitize_orderby($args['orderby']));
        $order   = esc_sql(strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC');

        $per_page = max(1, (int) $args['per_page']);
        $offset   = max(0, ((int) $args['paged'] - 1) * $per_page);

        $params[] = $per_page;
        $params[] = $offset;

        // $where is assembled from literals only, with every value passed as a
        // placeholder; $orderby and $order are constrained to allowlists above.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d",
                $params
            )
        );
    }

    /**
     * Count rules for the admin list.
     *
     * @param string $search Search term.
     * @param string $status all, enabled or disabled.
     * @return int
     */
    public static function count_rules($search = '', $status = 'all') {
        if (!self::table_exists()) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();

        list($where, $params) = self::build_where($search, $status);

        if (empty($params)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is a literal here.
            return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- see get_rules().
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params));
    }

    /**
     * Build the WHERE clause shared by get_rules() and count_rules().
     *
     * @param string $search Search term.
     * @param string $status all, enabled or disabled.
     * @return array {0: SQL using placeholders, 1: values}
     */
    private static function build_where($search, $status) {
        global $wpdb;

        $clauses = array('1=1');
        $params  = array();

        if ('enabled' === $status) {
            $clauses[] = 'is_enabled = 1';
        } elseif ('disabled' === $status) {
            $clauses[] = 'is_enabled = 0';
        }

        if ('' !== (string) $search) {
            $like      = '%' . $wpdb->esc_like($search) . '%';
            $clauses[] = '(source_url LIKE %s OR target_url LIKE %s)';
            $params[]  = $like;
            $params[]  = $like;
        }

        return array(implode(' AND ', $clauses), $params);
    }

    /**
     * Number of rules by state.
     *
     * @return array {all, enabled, disabled}
     */
    public static function counts() {
        $counts = array('all' => 0, 'enabled' => 0, 'disabled' => 0);

        if (!self::table_exists()) {
            return $counts;
        }

        global $wpdb;
        $table = self::table_name();

        $rows = $wpdb->get_results("SELECT is_enabled, COUNT(*) AS total FROM {$table} GROUP BY is_enabled");

        foreach ((array) $rows as $row) {
            $key           = $row->is_enabled ? 'enabled' : 'disabled';
            $counts[$key]  = (int) $row->total;
            $counts['all'] += (int) $row->total;
        }

        return $counts;
    }

    /**
     * Number of enabled rules, read from the cache without a query.
     *
     * @return int
     */
    public static function active_count() {
        $index = get_option(self::INDEX_OPTION);

        if (!is_array($index)) {
            return 0;
        }

        return (int) (isset($index['exact']) ? $index['exact'] : 0)
            + (int) (isset($index['pattern']) ? $index['pattern'] : 0);
    }

    /**
     * Total hits across all rules.
     *
     * @return int
     */
    public static function total_hits() {
        if (!self::table_exists()) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();

        return (int) $wpdb->get_var("SELECT SUM(hit_count) FROM {$table}");
    }

    /**
     * Every rule, oldest first, for CSV export.
     *
     * @return array
     */
    public static function get_all() {
        if (!self::table_exists()) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();

        return $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC");
    }

    /**
     * Constrain an ORDER BY column to a known allowlist.
     *
     * @param string $orderby Requested column.
     * @return string
     */
    private static function sanitize_orderby($orderby) {
        $allowed = array('id', 'source_url', 'target_url', 'status_code', 'hit_count', 'last_hit');
        return in_array($orderby, $allowed, true) ? $orderby : 'id';
    }

    /* ------------------------------------------------------------------
     * URL helpers
     * ------------------------------------------------------------------ */

    /**
     * Strip control characters and invalid UTF-8 from raw input.
     *
     * Deliberately lighter than sanitize_text_field(), which removes %xx
     * octets and anything that looks like a tag. Both are legitimate in a
     * URL path, and a regex such as (?<slug>...) would be destroyed.
     *
     * @param mixed $raw Raw value.
     * @return string
     */
    public static function clean_input($raw) {
        if (!is_scalar($raw)) {
            return '';
        }

        $value = wp_check_invalid_utf8((string) $raw, true);
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        return trim($value);
    }

    /**
     * Normalise a rule source for storage.
     *
     * Exact and wildcard sources become a site-root-relative path: a pasted
     * full URL loses its scheme and host, a missing leading slash is added and
     * any fragment is dropped. The query string is kept only when the rule
     * matches it exactly, since the other modes never look at it.
     *
     * Regex sources are stored as typed.
     *
     * @param mixed  $raw        Raw input.
     * @param string $match_type exact, wildcard or regex.
     * @param string $query_mode ignore, pass or exact.
     * @return string Normalised source, or '' when unusable.
     */
    public static function normalize_source($raw, $match_type, $query_mode) {
        $source = self::clean_input($raw);

        if ('' === $source || 'regex' === $match_type) {
            return $source;
        }

        if (preg_match('#^https?://#i', $source)) {
            $parts  = wp_parse_url($source);
            $source = (isset($parts['path']) ? $parts['path'] : '/')
                . (isset($parts['query']) ? '?' . $parts['query'] : '');
        }

        $hash_pos = strpos($source, '#');
        if (false !== $hash_pos) {
            $source = substr($source, 0, $hash_pos);
        }

        if ('' === $source) {
            return '';
        }

        if ('/' !== $source[0] && '*' !== $source[0]) {
            $source = '/' . $source;
        }

        list($path, $query) = self::split_source($source);

        $path = str_replace(' ', '%20', $path);

        if ('exact' === $query_mode && '' !== $query) {
            return $path . '?' . $query;
        }

        return $path;
    }

    /**
     * Split a stored source into path and query string.
     *
     * @param string $source Stored source.
     * @return array {0: path, 1: query}
     */
    public static function split_source($source) {
        $parts = explode('?', (string) $source, 2);

        return array($parts[0], isset($parts[1]) ? $parts[1] : '');
    }

    /**
     * Hash used to look a source up.
     *
     * Exact rules hash the case-folded path without its query string, so one
     * indexed lookup finds every candidate for a request path.
     *
     * @param string $source     Normalised source.
     * @param string $match_type exact, wildcard or regex.
     * @return string
     */
    private static function source_hash($source, $match_type) {
        if ('exact' === $match_type) {
            list($path) = self::split_source($source);
            return md5(self::path_key($path));
        }

        return md5(self::lower($source));
    }

    /**
     * Comparable form of a path: decoded, case-folded, no trailing slash.
     *
     * @param string $path URL path.
     * @return string
     */
    public static function path_key($path) {
        $path = self::lower(rawurldecode((string) $path));
        $path = rtrim($path, '/');

        return '' === $path ? '/' : $path;
    }

    /**
     * Comparable form of a query string: decoded pairs in sorted order.
     *
     * @param string $query Query string without the leading '?'.
     * @return string
     */
    public static function normalize_query($query) {
        $query = (string) $query;

        if ('' === $query) {
            return '';
        }

        $pairs = array_filter(explode('&', $query), 'strlen');
        $pairs = array_map('rawurldecode', $pairs);
        sort($pairs, SORT_STRING);

        return implode('&', $pairs);
    }

    /**
     * Sanitise a rule target.
     *
     * Accepts a site-root-relative path, stored as is so it survives a domain
     * change, or a full http(s) URL. Protocol-relative //host URLs are
     * rejected as ambiguous.
     *
     * @param string $raw Cleaned input.
     * @return string Target, or '' when unusable.
     */
    public static function sanitize_target($raw) {
        $target = self::clean_input($raw);

        if ('' === $target || 0 === strpos($target, '//')) {
            return '';
        }

        if ('/' === $target[0]) {
            return (string) esc_url_raw(str_replace(' ', '%20', $target));
        }

        return self::normalize_absolute_url($target);
    }

    /**
     * Normalise a user-supplied absolute URL, or return '' if it is unusable.
     *
     * A bare host like "example.com" is upgraded to http://example.com, but an
     * explicit non-http scheme is rejected outright. Blindly prefixing http://
     * onto "javascript:alert(1)" would otherwise store the mangled string
     * "http://javascript:alert(1)" rather than reporting invalid input.
     *
     * @param string $url Cleaned input.
     * @return string A http/https URL, or ''.
     */
    public static function normalize_absolute_url($url) {
        $url = trim((string) $url);

        if ('' === $url) {
            return '';
        }

        if (preg_match('#^([a-z][a-z0-9+.\-]*)\s*:#i', $url, $matches)) {
            // "example.com:8080" and "localhost:8080" look like a scheme to the
            // pattern above but are really host:port, so allow those through.
            $is_host_port = (bool) preg_match('#^[a-z0-9.\-]+:\d+#i', $url);

            if (!$is_host_port && !in_array(strtolower($matches[1]), array('http', 'https'), true)) {
                return '';
            }
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'http://' . ltrim($url, '/');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return '';
        }

        return esc_url_raw($url, array('http', 'https'));
    }

    /**
     * Turn a stored target into an absolute URL.
     *
     * Relative targets are relative to the domain root, the same base the
     * source is matched against.
     *
     * @param string $target Stored target.
     * @return string
     */
    public static function resolve_target($target) {
        $target = (string) $target;

        if ('' !== $target && '/' === $target[0]) {
            return self::site_origin() . $target;
        }

        return $target;
    }

    /**
     * scheme://host[:port] of the site, without any subdirectory.
     *
     * @return string
     */
    private static function site_origin() {
        $parts  = wp_parse_url(home_url());
        $origin = (isset($parts['scheme']) ? $parts['scheme'] : 'http') . '://'
            . (isset($parts['host']) ? $parts['host'] : '');

        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return $origin;
    }

    /**
     * Whether an exact rule's target is its own source.
     *
     * @param string $source     Normalised source.
     * @param string $query_mode ignore, pass or exact.
     * @param string $target     Sanitised target.
     * @return bool
     */
    private static function points_to_itself($source, $query_mode, $target) {
        $parts = wp_parse_url(self::resolve_target($target));

        if (empty($parts) || !isset($parts['host'])) {
            return false;
        }

        $home_host = wp_parse_url(home_url(), PHP_URL_HOST);

        if (strtolower($parts['host']) !== strtolower((string) $home_host)) {
            return false;
        }

        list($source_path, $source_query) = self::split_source($source);
        $target_path = isset($parts['path']) ? $parts['path'] : '/';

        if (self::path_key($source_path) !== self::path_key($target_path)) {
            return false;
        }

        // A rule that ignores the query string matches the target URL too,
        // whatever query the target adds, so it would redirect forever.
        if ('exact' !== $query_mode) {
            return true;
        }

        $target_query = isset($parts['query']) ? $parts['query'] : '';

        return self::normalize_query($source_query) === self::normalize_query($target_query);
    }

    /**
     * Whether a regex source compiles.
     *
     * @param string $source Regex source.
     * @return bool
     */
    private static function is_valid_regex($source) {
        // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- the warning is the failure signal.
        return false !== @preg_match(self::pattern_regex('regex', $source), '');
    }

    /**
     * Case-fold a string, multibyte-safe where possible.
     *
     * @param string $value Input.
     * @return string
     */
    private static function lower($value) {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }
}
