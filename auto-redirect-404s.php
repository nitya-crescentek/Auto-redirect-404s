<?php
/**
 * Plugin Name: Auto Redirect 404 to Custom URL
 * Description: Redirects all 404 errors to a custom URL or home page and logs every broken link. Helps fix 404 errors in Google Search Console with proper SEO redirects.
 * Version: 1.2.1
 * Author: Nitya Saha
 * Author URI: https://nitya.codesocials.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-redirect-404s
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package Redirect404Custom
 * @since 1.0.1
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('R404C_VERSION', '1.2.1');
define('R404C_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('R404C_PLUGIN_URL', plugin_dir_url(__FILE__));
define('R404C_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class R404C_Redirect_404_Custom {

    /**
     * Option storing the version the database was last prepared for.
     */
    const VERSION_OPTION = 'r404c_version';

    /**
     * Cron hook that refreshes the redirect destination check.
     */
    const CRON_HOOK = 'r404c_check_destination';

    /**
     * Options introduced after 1.0.x, with the value to seed them with.
     *
     * These are added on upgrade as well as activation so the settings screen
     * shows real stored values rather than relying on get_option() fallbacks.
     * Every one of them is a safety feature that defaults to on; logging is the
     * exception and is handled separately, because switching it on for an
     * existing site would start writing to a table nobody asked for.
     *
     * @var array
     */
    private static $feature_defaults = array(
        'r404c_loop_protection'    => 'on',
        'r404c_skip_assets'        => 'on',
        'r404c_show_top_widget'    => 'on',
        'r404c_exclusion_patterns' => '',
    );

    /**
     * Plugin instance
     * @var R404C_Redirect_404_Custom
     */
    private static $instance = null;

    /**
     * Get plugin instance
     * @return R404C_Redirect_404_Custom
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {

        // Include required files
        self::includes();

        // Initialize components
        if (is_admin()) {
            new R404C_Admin();

            // Runs on every admin request; cheap no-op once the stored version
            // matches. This is what reaches sites updated through the WordPress
            // updater, where the activation hook never fires.
            add_action('admin_init', array($this, 'maybe_upgrade'), 5);
        }

        new R404C_Frontend();

        // The destination check runs in the background so it can never slow a
        // visitor's request down.
        add_action(self::CRON_HOOK, array('R404C_Frontend', 'refresh_destination_status'));
    }

    /**
     * Make sure the background destination check is scheduled.
     *
     * @return void
     */
    public static function schedule_events() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * Remove the background destination check.
     *
     * @return void
     */
    public static function unschedule_events() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);

        while ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
        }
    }

    /**
     * Include required files
     */
    public static function includes() {
        require_once R404C_PLUGIN_DIR . 'includes/class-logger.php';
        require_once R404C_PLUGIN_DIR . 'includes/class-settings.php';
        require_once R404C_PLUGIN_DIR . 'includes/class-frontend.php';

        if (is_admin()) {
            require_once R404C_PLUGIN_DIR . 'includes/class-logs-table.php';
            require_once R404C_PLUGIN_DIR . 'includes/class-admin.php';
        }
    }

    /**
     * Bring an existing install up to date.
     *
     * Deliberately conservative: an upgrade never changes redirect behaviour and
     * never switches logging on by itself. Sites updating from 1.0.x keep
     * working exactly as before until the administrator opts in.
     */
    public function maybe_upgrade() {
        // Checked on every admin request, before the version short-circuit, so
        // a cron event lost to a cleanup plugin or a restored database comes
        // back on its own. wp_next_scheduled() reads an autoloaded option, so
        // this costs no query.
        self::schedule_events();

        $installed = get_option(self::VERSION_OPTION);

        if ($installed === R404C_VERSION) {
            return;
        }

        // Logging defaults to off for every site that did not just activate the
        // plugin fresh. No table is created until the toggle is switched on.
        // Autoloaded: R404C_Logger::is_enabled() reads it on the frontend.
        if (false === get_option('r404c_logging_enabled', false)) {
            add_option('r404c_logging_enabled', 'off');
        }

        self::seed_feature_defaults();

        // Self-heal: logging is on but the table is missing (restored database,
        // network activation, manual table drop). Recreate it rather than
        // silently dropping hits on the floor.
        if (get_option('r404c_logging_enabled') === 'on' && !R404C_Logger::table_exists(true)) {
            if (!R404C_Logger::install_table()) {
                update_option('r404c_logging_enabled', 'off');
            }
        }

        update_option(self::VERSION_OPTION, R404C_VERSION, false);
    }

    /**
     * Plugin activation.
     *
     * Only fires on a genuine activation click, never on a plugin update, so
     * it is safe to enable logging here for what is effectively a new install.
     *
     * @param bool $network_wide Whether the plugin is being network activated.
     */
    public static function activate($network_wide = false) {
        self::includes();

        if (is_multisite() && $network_wide) {
            $site_ids = get_sites(
                array(
                    'fields'   => 'ids',
                    'number'   => 0,
                    'no_found_rows' => true,
                )
            );

            foreach ($site_ids as $site_id) {
                switch_to_blog($site_id);
                self::activate_single_site();
                restore_current_blog();
            }

            return;
        }

        self::activate_single_site();
    }

    /**
     * Run activation work for the current site.
     */
    private static function activate_single_site() {
        $is_fresh_install = (false === get_option(self::VERSION_OPTION, false))
            && (false === get_option('r404c_redirect_url', false))
            && (false === get_option('r404c_enabled', false));

        // Set default options without clobbering anything already configured.
        $defaults = array(
            'r404c_enabled'       => 'on',
            'r404c_redirect_url'  => home_url(),
            'r404c_redirect_type' => '301',
        );

        foreach ($defaults as $key => $value) {
            if (false === get_option($key, false)) {
                add_option($key, $value);
            }
        }

        self::seed_feature_defaults();
        self::schedule_events();

        if ($is_fresh_install) {
            // New install: turn logging on and build the table.
            $logging = R404C_Logger::install_table() ? 'on' : 'off';
        } else {
            // Re-activation of an existing install: leave logging as it was.
            $logging = get_option('r404c_logging_enabled', 'off');
        }

        if (false === get_option('r404c_logging_enabled', false)) {
            add_option('r404c_logging_enabled', $logging);
        } else {
            update_option('r404c_logging_enabled', $logging);
        }

        update_option(self::VERSION_OPTION, R404C_VERSION, false);
    }

    /**
     * Add any post-1.0.x options that are not present yet.
     *
     * Never overwrites a stored value, so an administrator who has switched a
     * feature off keeps it off across updates.
     *
     * @return void
     */
    private static function seed_feature_defaults() {
        foreach (self::$feature_defaults as $key => $value) {
            if (false === get_option($key, false)) {
                add_option($key, $value);
            }
        }
    }

    /**
     * Plugin deactivation.
     *
     * Log data and settings are intentionally left in place so deactivating and
     * reactivating loses nothing. Cleanup happens in uninstall.php.
     */
    public static function deactivate() {
        // Settings and log data are intentionally left in place. Previous
        // versions also called wp_cache_flush() here, which wiped the entire
        // site object cache for every other plugin.
        self::includes();
        self::unschedule_events();
    }
}

register_activation_hook(__FILE__, array('R404C_Redirect_404_Custom', 'activate'));
register_deactivation_hook(__FILE__, array('R404C_Redirect_404_Custom', 'deactivate'));

/**
 * Initialize the plugin
 */
function r404c_redirect_404_custom_init() {
    return R404C_Redirect_404_Custom::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'r404c_redirect_404_custom_init');
