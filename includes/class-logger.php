<?php
/**
 * 404 log storage and retrieval.
 *
 * Stores one row per unique 404 URL with a hit counter, so a crawler hammering
 * the same missing path can never bloat the table. The table is hard-capped at
 * R404C_LOG_MAX_ROWS rows; the least recently seen entries are trimmed away.
 *
 * @package Redirect404Custom
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Every query below targets this plugin's own custom table. A table name is an
// identifier, and $wpdb->prepare() cannot parameterise identifiers, so it has to
// be interpolated. All caller-supplied values are still passed as placeholders,
// and ORDER BY columns are constrained to a fixed allowlist by sanitize_orderby().
// Object caching is not applicable: these are admin-screen reads and per-request
// writes against a log table that changes on every 404.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

/**
 * Logger class
 */
class R404C_Logger {

    /**
     * Maximum number of rows retained in the log table.
     */
    const MAX_ROWS = 1000;

    /**
     * Run the row-cap trim once every this many new URLs.
     */
    const TRIM_INTERVAL = 20;

    /**
     * Schema version. Bump when the table definition changes.
     */
    const DB_VERSION = '1';

    /**
     * Option holding the installed schema version.
     */
    const DB_VERSION_OPTION = 'r404c_db_version';

    /**
     * Per-request cache for table_exists(). Null until first checked.
     *
     * @var bool|null
     */
    private static $table_exists = null;

    /**
     * Get the fully qualified log table name.
     *
     * @return string
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'r404c_logs';
    }

    /**
     * Whether the log table exists.
     *
     * The schema stamp is an autoloaded option, so the common case costs no
     * query at all. Passing $force runs a real SHOW TABLES check; admin screens
     * do this so a manually dropped table is detected and rebuilt.
     *
     * @param bool $force Skip the option fast path and query the schema.
     * @return bool
     */
    public static function table_exists($force = false) {
        if (!$force && null !== self::$table_exists) {
            return self::$table_exists;
        }

        // Fast path: the stamp is only written once the table really exists.
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
     * Create or upgrade the log table.
     *
     * Safe to call repeatedly; dbDelta only applies differences.
     *
     * @return bool True when the table exists after the call.
     */
    public static function install_table() {
        global $wpdb;

        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url_hash char(32) NOT NULL,
            url varchar(2000) NOT NULL,
            referrer varchar(512) NOT NULL DEFAULT '',
            hit_count bigint(20) unsigned NOT NULL DEFAULT 1,
            first_seen datetime NOT NULL,
            last_seen datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY url_hash (url_hash),
            KEY last_seen (last_seen),
            KEY hit_count (hit_count)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Reset the cached existence check so callers see the new state.
        self::flush_table_cache();

        if (self::table_exists(true)) {
            // Autoloaded on purpose: table_exists() reads it on the frontend.
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION, true);
            return true;
        }

        delete_option(self::DB_VERSION_OPTION);

        return false;
    }

    /**
     * Drop the cached table_exists() result.
     *
     * Only needed after install/uninstall inside a single request.
     */
    public static function flush_table_cache() {
        self::$table_exists = null;
    }

    /**
     * Whether 404 logging is switched on.
     *
     * @return bool
     */
    public static function is_enabled() {
        return get_option('r404c_logging_enabled', 'off') === 'on';
    }

    /**
     * Record a 404 hit.
     *
     * Existing URLs increment their counter; new URLs insert a row and may
     * trigger a trim. Never throws — a logging failure must not affect the
     * visitor's request.
     *
     * @param string $url      Request path (with query string) that 404'd.
     * @param string $referrer Referring URL, may be empty.
     * @return void
     */
    public static function record($url, $referrer = '') {
        if (!self::is_enabled() || !self::table_exists()) {
            return;
        }

        $url = self::normalize_url($url);
        if ('' === $url) {
            return;
        }

        $referrer = self::normalize_referrer($referrer);
        $hash     = md5($url);
        $now      = current_time('mysql');

        global $wpdb;
        $table = self::table_name();

        // A logging failure (dropped table, read-only replica) must never break
        // the visitor's request or print SQL errors onto the page.
        $suppress = $wpdb->suppress_errors(true);

        $existing_id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE url_hash = %s LIMIT 1", $hash)
        );

        if ($existing_id) {
            // hit_count must increment atomically, so this cannot use $wpdb->update().
                $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET hit_count = hit_count + 1, last_seen = %s, referrer = %s WHERE id = %d",
                    $now,
                    $referrer,
                    (int) $existing_id
                )
            );

            $wpdb->suppress_errors($suppress);
            return;
        }

        $inserted = $wpdb->insert(
            $table,
            array(
                'url_hash'   => $hash,
                'url'        => $url,
                'referrer'   => $referrer,
                'hit_count'  => 1,
                'first_seen' => $now,
                'last_seen'  => $now,
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s')
        );

        $insert_id = (int) $wpdb->insert_id;

        $wpdb->suppress_errors($suppress);

        // A duplicate-key collision from a concurrent request is harmless: the
        // other request already created the row.
        //
        // Trimming is amortised over every TRIM_INTERVAL new URLs rather than
        // run on each insert, so a bot scanning thousands of missing paths does
        // not pay for a COUNT and a DELETE every time. The table can drift a
        // little above the cap between trims, which is harmless.
        if ($inserted && 0 === $insert_id % self::TRIM_INTERVAL) {
            self::maybe_trim();
        }
    }

    /**
     * Trim the table back to MAX_ROWS, dropping least-recently-seen entries.
     *
     * @return void
     */
    public static function maybe_trim() {
        global $wpdb;
        $table = self::table_name();

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        if ($total <= self::MAX_ROWS) {
            return;
        }

        // Keep the MAX_ROWS most recently seen rows and drop the rest.
        //
        // This deliberately selects the surviving ids rather than comparing
        // against a last_seen cutoff: last_seen has one-second resolution, so a
        // crawler hitting hundreds of missing URLs in the same second gives
        // every row an identical timestamp and a "< cutoff" delete removes
        // nothing at all. Ordering by (last_seen, id) breaks those ties.
        //
        // The inner query is wrapped in a derived table because MySQL cannot
        // read from the same table it is deleting from in a plain subquery.
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM {$table} ORDER BY last_seen DESC, id DESC LIMIT %d
                    ) AS keep_rows
                )",
                self::MAX_ROWS
            )
        );
    }

    /**
     * Fetch log rows.
     *
     * @param array $args {
     *     @type string $search  Search term matched against url and referrer.
     *     @type string $orderby One of: last_seen, first_seen, hit_count, url.
     *     @type string $order   ASC or DESC.
     *     @type int    $per_page
     *     @type int    $paged
     * }
     * @return array List of row objects.
     */
    public static function get_logs($args = array()) {
        if (!self::table_exists()) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();

        $args = wp_parse_args(
            $args,
            array(
                'search'   => '',
                'orderby'  => 'last_seen',
                'order'    => 'DESC',
                'per_page' => 20,
                'paged'    => 1,
            )
        );

        // sanitize_orderby() already constrains this to a fixed allowlist of
        // column names; esc_sql() makes that guarantee explicit to static
        // analysis as well as to the reader.
        $orderby = esc_sql(self::sanitize_orderby($args['orderby']));
        $order   = esc_sql(strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC');

        $per_page = max(1, min(self::MAX_ROWS, (int) $args['per_page']));
        $offset   = max(0, ((int) $args['paged'] - 1) * $per_page);

        // Two explicit branches rather than one query assembled in a variable:
        // every call to prepare() below receives a string literal, which is what
        // both the sniffs and a human reviewer need in order to verify it.
        // $orderby and $order are constrained to a fixed allowlist above.
        if ('' !== $args['search']) {
            $like = '%' . $wpdb->esc_like($args['search']) . '%';

            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table}
                     WHERE url LIKE %s OR referrer LIKE %s
                     ORDER BY {$orderby} {$order}, id DESC
                     LIMIT %d OFFSET %d",
                    $like,
                    $like,
                    $per_page,
                    $offset
                )
            );
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table}
                 ORDER BY {$orderby} {$order}, id DESC
                 LIMIT %d OFFSET %d",
                $per_page,
                $offset
            )
        );
    }

    /**
     * Count log rows, optionally filtered by a search term.
     *
     * @param string $search Search term.
     * @return int
     */
    public static function count_logs($search = '') {
        if (!self::table_exists()) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();

        if ('' === $search) {
                return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        }

        $like = '%' . $wpdb->esc_like($search) . '%';

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE url LIKE %s OR referrer LIKE %s",
                $like,
                $like
            )
        );
    }

    /**
     * Total number of recorded hits across all rows.
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
     * Get the most frequently hit 404 URLs.
     *
     * @param int $limit Number of rows.
     * @return array
     */
    public static function get_top($limit = 5) {
        if (!self::table_exists()) {
            return array();
        }

        global $wpdb;
        $table = self::table_name();
        $limit = max(1, min(50, (int) $limit));

        return $wpdb->get_results(
            $wpdb->prepare("SELECT url, hit_count FROM {$table} ORDER BY hit_count DESC, last_seen DESC LIMIT %d", $limit)
        );
    }

    /**
     * Delete specific log rows by ID.
     *
     * @param array $ids Row IDs.
     * @return int Number of rows deleted.
     */
    public static function delete($ids) {
        if (!self::table_exists()) {
            return 0;
        }

        $ids = array_filter(array_map('absint', (array) $ids));
        if (empty($ids)) {
            return 0;
        }

        global $wpdb;
        $table = self::table_name();

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        return (int) $wpdb->query(
            // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is built above as one %d per id.
            $wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids)
        );
    }

    /**
     * Remove every log row.
     *
     * @return bool
     */
    public static function clear_all() {
        if (!self::table_exists()) {
            return false;
        }

        global $wpdb;
        $table = self::table_name();

        return false !== $wpdb->query("DELETE FROM {$table}");
    }

    /**
     * Normalize a request URI for storage.
     *
     * Stores a site-relative path so rows stay portable across domain changes.
     *
     * @param string $url Raw request URI.
     * @return string
     */
    private static function normalize_url($url) {
        $url = trim((string) $url);
        if ('' === $url) {
            return '';
        }

        // Strip scheme + host if a full URL was supplied.
        $parts = wp_parse_url($url);
        if (!empty($parts['path'])) {
            $url = $parts['path'];
            if (!empty($parts['query'])) {
                $url .= '?' . $parts['query'];
            }
        }

        // Remove control characters and cap the length to the column width.
        $url = preg_replace('/[\x00-\x1F\x7F]/', '', $url);
        $url = wp_check_invalid_utf8($url, true);

        if ('' === $url || '/' === $url) {
            return '';
        }

        if (strlen($url) > 2000) {
            $url = substr($url, 0, 2000);
        }

        return $url;
    }

    /**
     * Normalize a referrer for storage.
     *
     * @param string $referrer Raw referrer header.
     * @return string
     */
    private static function normalize_referrer($referrer) {
        $referrer = trim((string) $referrer);
        if ('' === $referrer) {
            return '';
        }

        $referrer = esc_url_raw($referrer, array('http', 'https'));
        $referrer = wp_check_invalid_utf8($referrer, true);

        if (strlen($referrer) > 512) {
            $referrer = substr($referrer, 0, 512);
        }

        return $referrer;
    }

    /**
     * Constrain an ORDER BY column to a known allowlist.
     *
     * @param string $orderby Requested column.
     * @return string Safe column name.
     */
    private static function sanitize_orderby($orderby) {
        $allowed = array('last_seen', 'first_seen', 'hit_count', 'url');
        return in_array($orderby, $allowed, true) ? $orderby : 'last_seen';
    }
}
