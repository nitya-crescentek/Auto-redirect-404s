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
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_init', array($this, 'handle_log_actions'));
        add_action('admin_post_r404c_export_logs', array($this, 'export_logs_csv'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . plugin_basename(R404C_PLUGIN_FILE), array($this, 'add_settings_link'));
        add_filter('plugin_row_meta', array($this, 'addon_plugin_links'), 10, 2);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('404 Redirect Settings', 'auto-redirect-404s'),
            __('Auto 404 Redirects', 'auto-redirect-404s'),
            'manage_options',
            'auto-redirect-404s',
            array($this, 'settings_page')
        );
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
     * @return string Either 'settings' or 'logs'.
     */
    private function get_current_tab() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'settings';

        return 'logs' === $tab ? 'logs' : 'settings';
    }

    /**
     * Build a URL back to this settings screen.
     *
     * @param array $args Extra query args.
     * @return string
     */
    private function page_url($args = array()) {
        $args = array_merge(array('page' => 'auto-redirect-404s'), $args);

        return add_query_arg($args, admin_url('options-general.php'));
    }

    /**
     * Handle log delete / clear / bulk-delete requests.
     *
     * Runs on admin_init so a redirect is still possible before output starts.
     */
    public function handle_log_actions() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- page routing only; each branch verifies its own nonce.
        if (!isset($_GET['page']) || 'auto-redirect-404s' !== sanitize_key(wp_unslash($_GET['page']))) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified per branch below.
        $action = isset($_REQUEST['r404c_action']) ? sanitize_key(wp_unslash($_REQUEST['r404c_action'])) : '';

        // Bulk actions come from WP_List_Table's own action/action2 selects.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $bulk = '';
        if (isset($_REQUEST['action']) && '-1' !== $_REQUEST['action']) {
            $bulk = sanitize_key(wp_unslash($_REQUEST['action']));
        }
        if ('' === $bulk && isset($_REQUEST['action2']) && '-1' !== $_REQUEST['action2']) {
            $bulk = sanitize_key(wp_unslash($_REQUEST['action2']));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

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

        fclose($output);
        exit;
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
        if ('settings_page_auto-redirect-404s' !== $hook) {
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
            'home_url' => home_url()
        ));
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url($this->page_url()),
            esc_html__('Settings', 'auto-redirect-404s')
        );
        array_unshift($links, $settings_link);
        return $links;
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

        // Shared header data used by both tabs.
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

        $log_count = R404C_Logger::count_logs();

        $settings_url = $this->page_url();
        $logs_url     = $this->page_url(array('tab' => 'logs'));

        if ('logs' === $active_tab) {
            $this->render_log_notice();

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

        // Get pages for dropdown
        $pages = get_pages(array(
            'post_status' => 'publish',
            'number' => 100
        ));

        include R404C_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    /**
     * Print the admin notice produced by a log action redirect.
     */
    private function render_log_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only, action already performed and nonce-checked.
        $notice = isset($_GET['r404c_notice']) ? sanitize_key(wp_unslash($_GET['r404c_notice'])) : '';

        if ('deleted' === $notice) {
            $message = __('Selected log entries deleted.', 'auto-redirect-404s');
        } elseif ('cleared' === $notice) {
            $message = __('All 404 logs have been cleared.', 'auto-redirect-404s');
        } else {
            return;
        }

        printf(
            '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
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
            // wp_unslash only here; sanitize_patterns() cleans each line.
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
     * A bare host like "example.com" is upgraded to http://example.com, but an
     * explicit non-http scheme is rejected outright. Blindly prefixing http://
     * onto "javascript:alert(1)" would otherwise store the mangled string
     * "http://javascript:alert(1)" rather than reporting invalid input.
     *
     * @param string $raw Raw user input.
     * @return string A http/https URL, or '' when the input cannot be used.
     */
    private function normalize_redirect_url($raw) {
        $url = trim(sanitize_text_field($raw));

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
