<?php
/**
 * Admin functionality
 *
 * @package Redirect404Custom
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class
 */
class R404C_Admin {

    /**
     * Maximum number of exclusion patterns stored.
     */
    const MAX_PATTERNS = 100;

    /**
     * Maximum length of a single exclusion pattern.
     */
    const MAX_PATTERN_LENGTH = 255;

    /**
     * Admin page slug, shared by every tab.
     */
    const PAGE_SLUG = 'auto-redirect-404s';

    /**
     * Hook suffix of the plugin screen.
     */
    const PAGE_HOOK = 'toplevel_page_auto-redirect-404s';

    /**
     * Largest CSV accepted by the redirect import.
     */
    const IMPORT_MAX_BYTES = 2097152;

    /**
     * Most rows read from one CSV import.
     */
    const IMPORT_MAX_ROWS = 5000;

    /**
     * Error from the last failed redirect save, shown above the form.
     *
     * @var WP_Error|null
     */
    private $redirect_error = null;

    /**
     * Values from the last failed redirect save, put back into the form.
     *
     * @var array|null
     */
    private $redirect_form = null;

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'redirect_legacy_url'), 1);
        add_action('admin_page_access_denied', array($this, 'redirect_legacy_url'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_init', array($this, 'handle_log_actions'));
        add_action('admin_init', array($this, 'handle_redirect_actions'));
        add_action('admin_post_r404c_export_logs', array($this, 'export_logs_csv'));
        add_action('admin_post_r404c_export_redirects', array($this, 'export_redirects_csv'));
        add_action('admin_post_r404c_import_redirects', array($this, 'import_redirects_csv'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('submenu_file', array($this, 'highlight_submenu'), 10, 2);
        add_filter('plugin_action_links_' . plugin_basename(R404C_PLUGIN_FILE), array($this, 'add_settings_link'));
        add_filter('plugin_row_meta', array($this, 'addon_plugin_links'), 10, 2);
    }

    /**
     * Add admin menu.
     *
     * One tabbed screen under a top-level "Auto Redirects" menu. The
     * Redirection Manager and 404 Logs submenus are plain links to their tab.
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Auto Redirects', 'auto-redirect-404s'),
            __('Auto Redirects', 'auto-redirect-404s'),
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'settings_page'),
            'dashicons-randomize',
            81
        );

        // Same slug as the parent, so it replaces the auto-generated first item.
        add_submenu_page(
            self::PAGE_SLUG,
            __('Auto Redirects', 'auto-redirect-404s'),
            __('Settings', 'auto-redirect-404s'),
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'settings_page')
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('Redirection Manager', 'auto-redirect-404s'),
            __('Redirection Manager', 'auto-redirect-404s'),
            'manage_options',
            $this->tab_menu_slug('redirects')
        );

        add_submenu_page(
            self::PAGE_SLUG,
            __('404 Logs', 'auto-redirect-404s'),
            __('404 Logs', 'auto-redirect-404s'),
            'manage_options',
            $this->tab_menu_slug('logs')
        );

        // Kept where 1.2.x lived so existing users still find it. It is only
        // a link to the new screen.
        add_options_page(
            __('Auto Redirects', 'auto-redirect-404s'),
            __('Auto 404 Redirects', 'auto-redirect-404s'),
            'manage_options',
            'admin.php?page=' . self::PAGE_SLUG
        );
    }

    /**
     * Menu slug that links straight to a tab.
     *
     * @param string $tab Tab key.
     * @return string
     */
    private function tab_menu_slug($tab) {
        return 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $tab;
    }

    /**
     * Highlight the submenu item for the tab being viewed.
     *
     * @param string|null $submenu_file Current submenu file.
     * @param string      $parent_file  Current parent menu.
     * @return string|null
     */
    public function highlight_submenu($submenu_file, $parent_file) {
        if (self::PAGE_SLUG !== $parent_file) {
            return $submenu_file;
        }

        $tab = $this->get_current_tab();

        return 'settings' === $tab ? $submenu_file : $this->tab_menu_slug($tab);
    }

    /**
     * Send the 1.2.x Settings > Auto 404 Redirects URL to the new screen.
     *
     * Keeps bookmarks and links in old support threads working.
     */
    public function redirect_legacy_url() {
        global $pagenow;

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- routing only.
        if ('options-general.php' !== $pagenow || !isset($_GET['page']) || self::PAGE_SLUG !== sanitize_key(wp_unslash($_GET['page']))) {
            return;
        }

        $args = array();
        if (isset($_GET['tab'])) {
            $args['tab'] = sanitize_key(wp_unslash($_GET['tab']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        wp_safe_redirect($this->page_url($args));
        exit;
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting(
            'r404c_settings_group',
            'r404c_enabled',
            array(
                'sanitize_callback' => array($this, 'sanitize_checkbox')
            )
        );

        register_setting(
            'r404c_settings_group',
            'r404c_redirect_url',
            array(
                'sanitize_callback' => array($this, 'sanitize_url')
            )
        );

        register_setting(
            'r404c_settings_group',
            'r404c_redirect_type',
            array(
                'sanitize_callback' => array($this, 'sanitize_redirect_type')
            )
        );

        register_setting(
            'r404c_settings_group',
            'r404c_logging_enabled',
            array(
                'sanitize_callback' => array($this, 'sanitize_checkbox')
            )
        );

        foreach (array('r404c_loop_protection', 'r404c_skip_assets', 'r404c_show_top_widget') as $toggle) {
            register_setting(
                'r404c_settings_group',
                $toggle,
                array(
                    'sanitize_callback' => array($this, 'sanitize_checkbox')
                )
            );
        }

        register_setting(
            'r404c_settings_group',
            'r404c_exclusion_patterns',
            array(
                'sanitize_callback' => array($this, 'sanitize_patterns')
            )
        );
    }

    /**
     * Get the current settings page tab.
     *
     * @return string One of 'settings', 'redirects' or 'logs'.
     */
    private function get_current_tab() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'settings';

        return in_array($tab, array('redirects', 'logs'), true) ? $tab : 'settings';
    }

    /**
     * Build a URL back to this settings screen.
     *
     * @param array $args Extra query args.
     * @return string
     */
    private function page_url($args = array()) {
        $args = array_merge(array('page' => self::PAGE_SLUG), $args);

        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * Whether the current admin request is for the plugin screen.
     *
     * @return bool
     */
    private function is_plugin_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only.
        return isset($_GET['page']) && self::PAGE_SLUG === sanitize_key(wp_unslash($_GET['page']));
    }

    /**
     * The bulk action chosen in a WP_List_Table, from either select.
     *
     * @return string
     */
    private function get_bulk_action() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- each caller verifies its own nonce.
        if (isset($_REQUEST['action']) && '-1' !== $_REQUEST['action']) {
            return sanitize_key(wp_unslash($_REQUEST['action']));
        }

        if (isset($_REQUEST['action2']) && '-1' !== $_REQUEST['action2']) {
            return sanitize_key(wp_unslash($_REQUEST['action2']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return '';
    }

    /**
     * Handle log delete / clear / bulk-delete requests.
     *
     * Runs on admin_init so a redirect is still possible before output starts.
     */
    public function handle_log_actions() {
        if (!$this->is_plugin_page() || !current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified per branch below.
        $action = isset($_REQUEST['r404c_action']) ? sanitize_key(wp_unslash($_REQUEST['r404c_action'])) : '';

        // Bulk actions come from WP_List_Table's own action/action2 selects.
        $bulk = $this->get_bulk_action();

        $notice = '';

        if ('delete_log' === $action) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- validated immediately below.
            $log_id = isset($_GET['log_id']) ? absint(wp_unslash($_GET['log_id'])) : 0;

            check_admin_referer('r404c_delete_log_' . $log_id);

            if ($log_id && R404C_Logger::delete(array($log_id))) {
                $notice = 'deleted';
            }
        } elseif ('clear_logs' === $action) {
            check_admin_referer('r404c_clear_logs');

            R404C_Logger::clear_all();
            $notice = 'cleared';
        } elseif ('r404c_delete' === $bulk) {
            check_admin_referer('bulk-r404c_logs');

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked on the line above.
            $ids = isset($_REQUEST['log_ids']) ? array_map('absint', (array) wp_unslash($_REQUEST['log_ids'])) : array();

            if (!empty($ids) && R404C_Logger::delete($ids)) {
                $notice = 'deleted';
            }
        } else {
            return;
        }

        wp_safe_redirect(
            $this->page_url(
                array(
                    'tab'          => 'logs',
                    'r404c_notice' => $notice,
                )
            )
        );
        exit;
    }

    /**
     * Stream the log table out as a CSV download.
     */
    public function export_logs_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to export logs.', 'auto-redirect-404s'));
        }

        check_admin_referer('r404c_export_logs');

        $rows = R404C_Logger::get_logs(
            array(
                'orderby'  => 'hit_count',
                'order'    => 'DESC',
                'per_page' => R404C_Logger::MAX_ROWS,
                'paged'    => 1,
            )
        );

        $filename = 'auto-redirect-404s-logs-' . gmdate('Y-m-d') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=' . get_option('blog_charset'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // php://output is the response body, not a file on disk, so WP_Filesystem
        // does not apply here. This streams the CSV straight to the browser.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $output = fopen('php://output', 'w');

        $this->write_csv_row($output, array('URL', 'Hits', 'Referrer', 'First Seen', 'Last Seen'));

        foreach ($rows as $row) {
            $this->write_csv_row(
                $output,
                array(
                    $this->csv_escape($row->url),
                    (int) $row->hit_count,
                    $this->csv_escape($row->referrer),
                    $row->first_seen,
                    $row->last_seen,
                )
            );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($output);
        exit;
    }

    /**
     * Handle Redirection Manager requests: save, toggle, delete and bulk actions.
     *
     * Runs on admin_init so a successful action can redirect before output
     * starts. A failed save keeps its values on this object, and the form is
     * re-rendered with them and the error.
     */
    public function handle_redirect_actions() {
        if (!$this->is_plugin_page() || !current_user_can('manage_options')) {
            return;
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified per branch below.
        $action = isset($_REQUEST['r404c_redirect_action']) ? sanitize_key(wp_unslash($_REQUEST['r404c_redirect_action'])) : '';
        $id     = isset($_REQUEST['redirect_id']) ? absint(wp_unslash($_REQUEST['redirect_id'])) : 0;
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $bulk   = $this->get_bulk_action();
        $notice = array();

        if ('save' === $action) {
            $notice = $this->save_redirect($id);

            if (empty($notice)) {
                // Validation failed: fall through to the page, which shows the error.
                return;
            }
        } elseif ('delete' === $action) {
            check_admin_referer('r404c_delete_redirect_' . $id);

            if ($id && R404C_Redirects::delete(array($id))) {
                $notice = array('r404c_notice' => 'redirect_deleted');
            }
        } elseif ('toggle' === $action) {
            check_admin_referer('r404c_toggle_redirect_' . $id);

            $rule = R404C_Redirects::get($id);

            if ($rule) {
                $enable = !$rule->is_enabled;
                R404C_Redirects::set_enabled(array($id), $enable);
                $notice = array('r404c_notice' => $enable ? 'redirect_enabled' : 'redirect_disabled');
            }
        } elseif (0 === strpos($bulk, 'r404c_redirects_')) {
            check_admin_referer('bulk-r404c_redirects');

            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce checked on the line above.
            $ids = isset($_REQUEST['redirect_ids']) ? array_map('absint', (array) wp_unslash($_REQUEST['redirect_ids'])) : array();

            if (!empty($ids)) {
                switch ($bulk) {
                    case 'r404c_redirects_enable':
                        $count = R404C_Redirects::set_enabled($ids, true);
                        break;
                    case 'r404c_redirects_disable':
                        $count = R404C_Redirects::set_enabled($ids, false);
                        break;
                    case 'r404c_redirects_reset':
                        $count = R404C_Redirects::reset_hits($ids);
                        break;
                    case 'r404c_redirects_delete':
                        $count = R404C_Redirects::delete($ids);
                        break;
                    default:
                        $count = 0;
                }

                $notice = array(
                    'r404c_notice' => str_replace('r404c_redirects_', 'bulk_', $bulk),
                    'r404c_count'  => $count,
                );
            }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only strips noise from a GET search URL.
        } elseif ('redirects' === $this->get_current_tab() && !empty($_GET['_wp_http_referer']) && isset($_SERVER['REQUEST_URI'])) {
            // The list form submits by GET; drop the nonce and referer it
            // carries so search and filter URLs stay short and shareable,
            // the same as core's list screens.
            wp_safe_redirect(remove_query_arg(array('_wp_http_referer', '_wpnonce', 'action', 'action2'), esc_url_raw(wp_unslash($_SERVER['REQUEST_URI']))));
            exit;
        } else {
            return;
        }

        wp_safe_redirect($this->page_url(array_merge(array('tab' => 'redirects'), $notice)));
        exit;
    }

    /**
     * Save the add / edit redirect form.
     *
     * @param int $id Rule being edited, or 0 for a new rule.
     * @return array Notice query args on success, empty array on failure.
     */
    private function save_redirect($id) {
        check_admin_referer('r404c_save_redirect', 'r404c_redirect_nonce');

        // Source and target are passed through raw: R404C_Redirects sanitises
        // each one for its purpose, and sanitize_text_field() would strip %xx
        // octets from paths and angle brackets from regex sources.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $data = array(
            'source_url'  => isset($_POST['source_url']) ? R404C_Redirects::clean_input(wp_unslash($_POST['source_url'])) : '',
            'target_url'  => isset($_POST['target_url']) ? R404C_Redirects::clean_input(wp_unslash($_POST['target_url'])) : '',
            'match_type'  => isset($_POST['match_type']) ? sanitize_key(wp_unslash($_POST['match_type'])) : 'exact',
            'query_mode'  => isset($_POST['query_mode']) ? sanitize_key(wp_unslash($_POST['query_mode'])) : 'ignore',
            'status_code' => isset($_POST['status_code']) ? absint(wp_unslash($_POST['status_code'])) : 301,
            'is_enabled'  => !empty($_POST['is_enabled']),
        );
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        $from_log   = isset($_POST['from_log']) ? absint(wp_unslash($_POST['from_log'])) : 0;
        $remove_log = !empty($_POST['remove_log']);

        $result = R404C_Redirects::save($data, $id);

        if (is_wp_error($result)) {
            $this->redirect_error = $result;
            $this->redirect_form  = array_merge(
                $data,
                array(
                    'id'         => $id,
                    'from_log'   => $from_log,
                    'remove_log' => $remove_log,
                )
            );

            return array();
        }

        // The URL now has a rule, so its 404 log entry is resolved.
        if ($from_log && $remove_log) {
            R404C_Logger::delete(array($from_log));
        }

        return array('r404c_notice' => $id ? 'redirect_updated' : 'redirect_added');
    }

    /**
     * Stream every redirect rule out as a CSV download.
     *
     * The column order matches what import_redirects_csv() reads, so an
     * export can be imported straight back.
     */
    public function export_redirects_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to export redirects.', 'auto-redirect-404s'));
        }

        check_admin_referer('r404c_export_redirects');

        $filename = 'auto-redirect-404s-redirects-' . gmdate('Y-m-d') . '.csv';

        nocache_headers();
        header('Content-Type: text/csv; charset=' . get_option('blog_charset'));
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- see export_logs_csv().
        $output = fopen('php://output', 'w');

        $this->write_csv_row($output, array('source', 'target', 'code', 'match', 'query', 'enabled', 'hits', 'last_hit'));

        foreach (R404C_Redirects::get_all() as $rule) {
            $this->write_csv_row(
                $output,
                array(
                    $this->csv_escape($rule->source_url),
                    $this->csv_escape($rule->target_url),
                    (int) $rule->status_code,
                    $rule->match_type,
                    $rule->query_mode,
                    $rule->is_enabled ? 'yes' : 'no',
                    (int) $rule->hit_count,
                    (string) $rule->last_hit,
                )
            );
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($output);
        exit;
    }

    /**
     * Import redirect rules from an uploaded CSV.
     *
     * Columns: source, target, code, match, query, enabled. Only source is
     * required (and target, unless code is 410); the rest default to 301,
     * exact, ignore and enabled. A header row is detected and skipped.
     * Existing sources are skipped rather than overwritten.
     */
    public function import_redirects_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to import redirects.', 'auto-redirect-404s'));
        }

        check_admin_referer('r404c_import_redirects', 'r404c_import_nonce');

        $fail = function ($reason) {
            wp_safe_redirect(
                $this->page_url(
                    array(
                        'tab'          => 'redirects',
                        'r404c_notice' => 'import_failed',
                        'r404c_reason' => $reason,
                    )
                )
            );
            exit;
        };

        // Each field is checked below; the tmp_name is only ever used after
        // is_uploaded_file() confirms PHP created it for this request.
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
        $file = isset($_FILES['r404c_import_file']) && is_array($_FILES['r404c_import_file']) ? $_FILES['r404c_import_file'] : null;

        if (!$file || !isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])
            || UPLOAD_ERR_OK !== (int) $file['error'] || !is_uploaded_file($file['tmp_name'])) {
            $fail('nofile');
        }

        if ((int) $file['size'] > self::IMPORT_MAX_BYTES) {
            $fail('toobig');
        }

        $extension = strtolower(pathinfo(sanitize_file_name($file['name']), PATHINFO_EXTENSION));
        if (!in_array($extension, array('csv', 'txt'), true)) {
            $fail('badtype');
        }

        if (!R404C_Redirects::table_exists(true) && !R404C_Redirects::install_table()) {
            $fail('nodb');
        }

        // Reading PHP's own upload temp file; WP_Filesystem does not apply.
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $fail('unreadable');
        }

        $added   = 0;
        $skipped = 0;
        $invalid = 0;
        $line    = 0;

        while (false !== ($row = fgetcsv($handle, 0, ',', '"', ''))) {
            $line++;

            if ($line > self::IMPORT_MAX_ROWS) {
                break;
            }

            // fgetcsv() returns array(null) for a blank line.
            if (!is_array($row) || array(null) === $row) {
                continue;
            }

            $row = array_map('trim', array_map('strval', $row));

            if (1 === $line) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);

                if ('source' === strtolower($row[0])) {
                    continue;
                }
            }

            if ('' === $row[0]) {
                continue;
            }

            $enabled = isset($row[5]) ? strtolower($row[5]) : '';

            $result = R404C_Redirects::save(
                array(
                    'source_url'  => $this->csv_unescape($row[0]),
                    'target_url'  => isset($row[1]) ? $this->csv_unescape($row[1]) : '',
                    'status_code' => isset($row[2]) && '' !== $row[2] ? absint($row[2]) : 301,
                    'match_type'  => isset($row[3]) && '' !== $row[3] ? sanitize_key($row[3]) : 'exact',
                    'query_mode'  => isset($row[4]) && '' !== $row[4] ? sanitize_key($row[4]) : 'ignore',
                    'is_enabled'  => '' === $enabled || in_array($enabled, array('1', 'yes', 'true', 'on', 'enabled'), true),
                ),
                0,
                false
            );

            if (!is_wp_error($result)) {
                $added++;
            } elseif ('duplicate' === $result->get_error_code()) {
                $skipped++;
            } else {
                $invalid++;
            }
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
        fclose($handle);

        R404C_Redirects::rebuild_cache();

        wp_safe_redirect(
            $this->page_url(
                array(
                    'tab'           => 'redirects',
                    'r404c_notice'  => 'imported',
                    'r404c_added'   => $added,
                    'r404c_skipped' => $skipped,
                    'r404c_invalid' => $invalid,
                )
            )
        );
        exit;
    }

    /**
     * Undo csv_escape() on an imported cell.
     *
     * @param string $value Cell value.
     * @return string
     */
    private function csv_unescape($value) {
        if (strlen($value) > 1 && "'" === $value[0] && in_array($value[1], array('=', '+', '-', '@'), true)) {
            return substr($value, 1);
        }

        return $value;
    }

    /**
     * Write one CSV row.
     *
     * PHP 8.4 deprecated relying on fputcsv()'s default $escape value, so it is
     * passed explicitly. The empty string disables PHP's non-standard backslash
     * escaping and produces spec-compliant CSV; it needs PHP 7.4, which is the
     * plugin's minimum from 1.2.0.
     *
     * @param resource $handle Open stream.
     * @param array    $row    Cell values.
     * @return void
     */
    private function write_csv_row($handle, $row) {
        fputcsv($handle, $row, ',', '"', '');
    }

    /**
     * Neutralise spreadsheet formula injection in exported cells.
     *
     * A logged URL is attacker-controlled, so a value beginning with =, +, -
     * or @ would execute as a formula when the CSV is opened in Excel.
     *
     * @param string $value Cell value.
     * @return string
     */
    private function csv_escape($value) {
        $value = (string) $value;

        if ('' !== $value && in_array($value[0], array('=', '+', '-', '@', "\t", "\r"), true)) {
            $value = "'" . $value;
        }

        return $value;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (self::PAGE_HOOK !== $hook) {
            return;
        }

        wp_enqueue_style(
            'r404c-admin-style',
            R404C_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            R404C_VERSION
        );

        // The logs tab is a plain WP_List_Table and needs no custom script.
        if ('logs' === $this->get_current_tab()) {
            return;
        }

        wp_enqueue_script(
            'r404c-admin-script',
            R404C_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            R404C_VERSION,
            true
        );

        // Localize script
        wp_localize_script('r404c-admin-script', 'r404c_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('r404c_nonce'),
            'home_url' => home_url(),
            'i18n' => array(
                'source_required' => __('Enter the source URL to redirect from.', 'auto-redirect-404s'),
                'target_required' => __('Enter the target URL to redirect to.', 'auto-redirect-404s'),
            ),
        ));
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $plugin_links = array(
            sprintf(
                '<a href="%s">%s</a>',
                esc_url($this->page_url()),
                esc_html__('Settings', 'auto-redirect-404s')
            ),
            sprintf(
                '<a href="%s">%s</a>',
                esc_url($this->page_url(array('tab' => 'redirects'))),
                esc_html__('Redirects', 'auto-redirect-404s')
            ),
        );

        return array_merge($plugin_links, $links);
    }

    /**
     * Add support links to the plugin row.
     *
     * @param array  $links Existing row meta.
     * @param string $file  Plugin file being rendered.
     * @return array
     */
    public function addon_plugin_links($links, $file) {
        if ($file !== plugin_basename(R404C_PLUGIN_FILE)) {
            return $links;
        }

        $links[] = sprintf(
            '<a href="%s" style="font-weight:bold;color:#00d300;font-size:15px;" target="_blank" rel="noopener noreferrer">%s</a>',
            esc_url('https://buymeacoffee.com/nityasaha'),
            esc_html__('Donate', 'auto-redirect-404s')
        );
        $links[] = esc_html__('Made with Love', 'auto-redirect-404s') . ' &#10084;&#65039;';

        return $links;
    }

    /**
     * Settings page content
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'auto-redirect-404s'));
        }

        // Handle form submission
        if (isset($_POST['submit']) && isset($_POST['r404c_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['r404c_nonce']));
            if (wp_verify_nonce($nonce, 'r404c_save_settings')) {
                $this->save_settings();
            }
        }

        $active_tab = $this->get_current_tab();

        // Shared header data used by every tab.
        $enabled         = get_option('r404c_enabled', 'on');
        $redirect_url    = get_option('r404c_redirect_url', home_url());
        $redirect_type   = get_option('r404c_redirect_type', '301');
        $logging_enabled = get_option('r404c_logging_enabled', 'off');
        $loop_protection = get_option('r404c_loop_protection', 'off');
        $skip_assets     = get_option('r404c_skip_assets', 'off');
        $show_top_widget = get_option('r404c_show_top_widget', 'on');
        $exclusion_patterns = (string) get_option('r404c_exclusion_patterns', '');
        $destination_status = R404C_Frontend::get_destination_status($redirect_url);

        // Self-heal: logging is on but the table has gone (dropped by hand, or
        // a database restored from before it existed). One schema check per
        // view of this screen, never on the frontend.
        if ('on' === $logging_enabled && !R404C_Logger::table_exists(true)) {
            R404C_Logger::install_table();
        }

        $log_count            = R404C_Logger::count_logs();
        $active_redirect_count = R404C_Redirects::active_count();

        $settings_url  = $this->page_url();
        $redirects_url = $this->page_url(array('tab' => 'redirects'));
        $logs_url      = $this->page_url(array('tab' => 'logs'));

        // Get pages for the quick-select dropdowns
        $pages = get_pages(array(
            'post_status' => 'publish',
            'number' => 100
        ));

        if ('redirects' === $active_tab) {
            $this->render_notice();

            // Self-heal, as for the log table above.
            if (!R404C_Redirects::table_exists(true)) {
                R404C_Redirects::install_table();
            }

            $redirects_table = new R404C_Redirects_Table();
            $redirects_table->prepare_items();

            $redirect_counts = R404C_Redirects::counts();
            $redirect_hits   = R404C_Redirects::total_hits();
            $form            = $this->get_redirect_form_values();
            $form_error      = $this->redirect_error;

            $export_url = wp_nonce_url(
                add_query_arg('action', 'r404c_export_redirects', admin_url('admin-post.php')),
                'r404c_export_redirects'
            );

            include R404C_PLUGIN_DIR . 'templates/admin-redirects.php';
            return;
        }

        if ('logs' === $active_tab) {
            $this->render_notice();

            $logs_table = new R404C_Logs_Table();
            $logs_table->prepare_items();

            $total_hits = R404C_Logger::total_hits();

            $clear_url = wp_nonce_url(
                $this->page_url(
                    array(
                        'tab'          => 'logs',
                        'r404c_action' => 'clear_logs',
                    )
                ),
                'r404c_clear_logs'
            );

            $export_url = wp_nonce_url(
                add_query_arg('action', 'r404c_export_logs', admin_url('admin-post.php')),
                'r404c_export_logs'
            );

            include R404C_PLUGIN_DIR . 'templates/admin-logs.php';
            return;
        }

        include R404C_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * Values for the add / edit redirect form.
     *
     * In order of precedence: the values from a save that just failed, the
     * rule being edited, or a source pre-filled from the 404 log.
     *
     * @return array
     */
    private function get_redirect_form_values() {
        $defaults = array(
            'id'          => 0,
            'source_url'  => '',
            'target_url'  => '',
            'match_type'  => 'exact',
            'query_mode'  => 'ignore',
            'status_code' => 301,
            'is_enabled'  => true,
            'from_log'    => 0,
            'remove_log'  => true,
        );

        if (null !== $this->redirect_form) {
            return array_merge($defaults, $this->redirect_form);
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only form pre-fill.
        $edit_id = isset($_GET['edit']) ? absint(wp_unslash($_GET['edit'])) : 0;

        if ($edit_id) {
            $rule = R404C_Redirects::get($edit_id);

            if ($rule) {
                return array_merge(
                    $defaults,
                    array(
                        'id'          => (int) $rule->id,
                        'source_url'  => $rule->source_url,
                        'target_url'  => $rule->target_url,
                        'match_type'  => $rule->match_type,
                        'query_mode'  => $rule->query_mode,
                        'status_code' => (int) $rule->status_code,
                        'is_enabled'  => (bool) $rule->is_enabled,
                    )
                );
            }
        }

        if (isset($_GET['source'])) {
            $source = R404C_Redirects::clean_input(wp_unslash($_GET['source']));

            $defaults['source_url'] = $source;
            $defaults['from_log']   = isset($_GET['from_log']) ? absint(wp_unslash($_GET['from_log'])) : 0;

            // A logged URL with a query string most likely needs exactly that
            // query matched, or the rule would catch the bare path too.
            if (false !== strpos($source, '?')) {
                $defaults['query_mode'] = 'exact';
            }
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        return $defaults;
    }

    /**
     * Print the admin notice produced by a log or redirect action.
     */
    private function render_notice() {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- display-only, action already performed and nonce-checked.
        $notice = isset($_GET['r404c_notice']) ? sanitize_key(wp_unslash($_GET['r404c_notice'])) : '';
        $count  = isset($_GET['r404c_count']) ? absint(wp_unslash($_GET['r404c_count'])) : 0;
        $type   = 'success';

        switch ($notice) {
            case 'deleted':
                $message = __('Selected log entries deleted.', 'auto-redirect-404s');
                break;
            case 'cleared':
                $message = __('All 404 logs have been cleared.', 'auto-redirect-404s');
                break;
            case 'redirect_added':
                $message = __('Redirect added.', 'auto-redirect-404s');
                break;
            case 'redirect_updated':
                $message = __('Redirect updated.', 'auto-redirect-404s');
                break;
            case 'redirect_deleted':
                $message = __('Redirect deleted.', 'auto-redirect-404s');
                break;
            case 'redirect_enabled':
                $message = __('Redirect enabled.', 'auto-redirect-404s');
                break;
            case 'redirect_disabled':
                $message = __('Redirect disabled.', 'auto-redirect-404s');
                break;
            case 'bulk_enable':
                /* translators: %s: number of redirects */
                $message = sprintf(_n('%s redirect enabled.', '%s redirects enabled.', $count, 'auto-redirect-404s'), number_format_i18n($count));
                break;
            case 'bulk_disable':
                /* translators: %s: number of redirects */
                $message = sprintf(_n('%s redirect disabled.', '%s redirects disabled.', $count, 'auto-redirect-404s'), number_format_i18n($count));
                break;
            case 'bulk_reset':
                /* translators: %s: number of redirects */
                $message = sprintf(_n('Hit counter reset for %s redirect.', 'Hit counters reset for %s redirects.', $count, 'auto-redirect-404s'), number_format_i18n($count));
                break;
            case 'bulk_delete':
                /* translators: %s: number of redirects */
                $message = sprintf(_n('%s redirect deleted.', '%s redirects deleted.', $count, 'auto-redirect-404s'), number_format_i18n($count));
                break;
            case 'imported':
                $message = sprintf(
                    /* translators: 1: rules added, 2: duplicates skipped, 3: invalid rows */
                    __('Import finished: %1$s added, %2$s skipped as duplicates, %3$s invalid.', 'auto-redirect-404s'),
                    number_format_i18n(isset($_GET['r404c_added']) ? absint(wp_unslash($_GET['r404c_added'])) : 0),
                    number_format_i18n(isset($_GET['r404c_skipped']) ? absint(wp_unslash($_GET['r404c_skipped'])) : 0),
                    number_format_i18n(isset($_GET['r404c_invalid']) ? absint(wp_unslash($_GET['r404c_invalid'])) : 0)
                );
                break;
            case 'import_failed':
                $type    = 'error';
                $reasons = array(
                    'nofile'     => __('Choose a CSV file to import.', 'auto-redirect-404s'),
                    /* translators: %s: maximum file size, e.g. "2 MB" */
                    'toobig'     => sprintf(__('The file is larger than %s.', 'auto-redirect-404s'), size_format(self::IMPORT_MAX_BYTES)),
                    'badtype'    => __('Only .csv files can be imported.', 'auto-redirect-404s'),
                    'unreadable' => __('The uploaded file could not be read.', 'auto-redirect-404s'),
                    'nodb'       => __('The redirects table could not be created.', 'auto-redirect-404s'),
                );
                $reason  = isset($_GET['r404c_reason']) ? sanitize_key(wp_unslash($_GET['r404c_reason'])) : '';
                $message = __('Import failed.', 'auto-redirect-404s') . ' ' . (isset($reasons[$reason]) ? $reasons[$reason] : '');
                break;
            default:
                return;
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        printf(
            '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
            esc_attr($type),
            esc_html($message)
        );
    }

    /**
     * Save settings
     */
    private function save_settings() {
        // Check nonce
        if (
            !isset($_POST['r404c_nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['r404c_nonce'])), 'r404c_save_settings')
        ) {
            add_settings_error(
                'r404c_messages',
                'r404c_message',
                __('Nonce verification failed. Please try again.', 'auto-redirect-404s'),
                'error'
            );
            return;
        }

        // Capability is checked by settings_page() before this runs, but repeat
        // it here so the method is safe to call from anywhere.
        if (!current_user_can('manage_options')) {
            return;
        }

        // Validate and sanitize
        $enabled = isset($_POST['r404c_enabled']) && sanitize_text_field(wp_unslash($_POST['r404c_enabled'])) === 'on' ? 'on' : 'off';

        $logging_enabled = isset($_POST['r404c_logging_enabled']) && sanitize_text_field(wp_unslash($_POST['r404c_logging_enabled'])) === 'on' ? 'on' : 'off';

        // Unchecked boxes are simply absent from the POST body.
        $toggles = array();
        foreach (array('r404c_loop_protection', 'r404c_skip_assets', 'r404c_show_top_widget') as $toggle) {
            $toggles[$toggle] = isset($_POST[$toggle]) && sanitize_text_field(wp_unslash($_POST[$toggle])) === 'on' ? 'on' : 'off';
        }

        $exclusion_patterns = '';
        if (isset($_POST['r404c_exclusion_patterns'])) {
            // A textarea holding one pattern per line, so it cannot be flattened
            // with sanitize_text_field() here without destroying the newlines.
            // sanitize_patterns() unslashes nothing and sanitises every line
            // individually, capping length and count.
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_patterns() sanitises each line.
            $exclusion_patterns = $this->sanitize_patterns(wp_unslash($_POST['r404c_exclusion_patterns']));
        }

        // Sanitize URL input. An empty field is valid and means "do not
        // redirect"; anything non-empty must resolve to a usable http(s) URL.
        $redirect_url = '';
        $url_supplied = false;
        if (isset($_POST['r404c_redirect_url'])) {
            $raw_url = trim(sanitize_text_field(wp_unslash($_POST['r404c_redirect_url'])));

            if ('' !== $raw_url) {
                $url_supplied = true;
                $redirect_url = $this->normalize_redirect_url($raw_url);
            }
        }

        // Sanitize redirect type input
        $redirect_type = '301';
        if (isset($_POST['r404c_redirect_type'])) {
            $redirect_type = sanitize_text_field(wp_unslash($_POST['r404c_redirect_type']));
        }

        // Reject unusable input rather than saving a mangled value.
        if ($url_supplied && '' === $redirect_url) {
            add_settings_error(
                'r404c_messages',
                'r404c_message',
                __('Please enter a valid http:// or https:// URL.', 'auto-redirect-404s'),
                'error'
            );
            return;
        }

        // Save options
        update_option('r404c_enabled', $enabled);
        update_option('r404c_redirect_url', $redirect_url);
        update_option('r404c_redirect_type', $this->sanitize_redirect_type($redirect_type));

        foreach ($toggles as $toggle => $value) {
            update_option($toggle, $value);
        }

        update_option('r404c_exclusion_patterns', $exclusion_patterns);

        // Re-check the destination straight away rather than waiting for the
        // next cron run, so the admin gets immediate feedback on a fix. This is
        // an admin request, so a slow probe costs nobody a page view.
        R404C_Frontend::clear_destination_cache($redirect_url);
        R404C_Frontend::refresh_destination_status($redirect_url);

        // Create the log table the first time logging is switched on, so
        // existing installs never carry an unused table.
        if ('on' === $logging_enabled && !R404C_Logger::table_exists(true)) {
            if (!R404C_Logger::install_table()) {
                $logging_enabled = 'off';
                add_settings_error(
                    'r404c_messages',
                    'r404c_message',
                    __('404 logging could not be enabled because the log table could not be created. Redirects are unaffected.', 'auto-redirect-404s'),
                    'error'
                );
            }
        }

        update_option('r404c_logging_enabled', $logging_enabled);

        add_settings_error(
            'r404c_messages',
            'r404c_message',
            __('Settings saved successfully!', 'auto-redirect-404s'),
            'updated'
        );
    }

    /**
     * Sanitize checkbox
     */
    public function sanitize_checkbox($input) {
        return $input === 'on' ? 'on' : 'off';
    }

    /**
     * Sanitize URL.
     *
     * Registered as the sanitize_callback for r404c_redirect_url, so it runs on
     * every update_option() for that setting.
     */
    public function sanitize_url($input) {
        return $this->normalize_redirect_url($input);
    }

    /**
     * Normalise a user-supplied redirect URL, or return '' if it is unusable.
     *
     * The rules live in R404C_Redirects::normalize_absolute_url(), shared with
     * Redirection Manager targets.
     *
     * @param string $raw Raw user input.
     * @return string A http/https URL, or '' when the input cannot be used.
     */
    private function normalize_redirect_url($raw) {
        return R404C_Redirects::normalize_absolute_url(sanitize_text_field($raw));
    }

    /**
     * Sanitize the exclusion pattern list.
     *
     * Stored as one pattern per line. Blank lines are dropped, each line is
     * capped in length, and the list is capped in size so a paste accident
     * cannot leave the frontend matching thousands of patterns per request.
     *
     * @param string $input Raw textarea contents.
     * @return string Newline-separated patterns.
     */
    public function sanitize_patterns($input) {
        if (!is_string($input)) {
            return '';
        }

        $lines   = preg_split('/[\r\n]+/', $input);
        $cleaned = array();

        foreach ($lines as $line) {
            $line = trim(sanitize_text_field($line));

            if ('' === $line) {
                continue;
            }

            if (strlen($line) > self::MAX_PATTERN_LENGTH) {
                $line = substr($line, 0, self::MAX_PATTERN_LENGTH);
            }

            $cleaned[] = $line;

            if (count($cleaned) >= self::MAX_PATTERNS) {
                break;
            }
        }

        return implode("\n", array_unique($cleaned));
    }

    /**
     * Sanitize redirect type
     */
    public function sanitize_redirect_type($input) {
        $allowed = array('301', '302');
        return in_array($input, $allowed, true) ? $input : '301';
    }
}
