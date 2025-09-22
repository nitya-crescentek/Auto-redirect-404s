<?php
/**
 * Plugin Name: Auto Redirect 404 to Custom URL
 * Description: Redirects all 404 errors to a custom URL or home page. Helps fix 404 errors in Google Search Console with proper SEO redirects.
 * Version: 1.0.0
 * Author: Nitya Saha
 * Author URI: https://codesocials.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-redirect-404s
 * Domain Path: /languages
 * 
 * @package Redirect404Custom
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('R404C_VERSION', '1.0.0');
define('R404C_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('R404C_PLUGIN_URL', plugin_dir_url(__FILE__));
define('R404C_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class Redirect_404_Custom {
    
    /**
     * Plugin instance
     * @var Redirect_404_Custom
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
     * @return Redirect_404_Custom
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
        $this->includes();
        
        // Initialize components
        if (is_admin()) {
            new R404C_Admin();
        }
        
        new R404C_Frontend();
        
        // Register activation hook
        register_activation_hook(R404C_PLUGIN_FILE, array($this, 'activate'));
        
        // Register deactivation hook
        register_deactivation_hook(R404C_PLUGIN_FILE, array($this, 'deactivate'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        require_once R404C_PLUGIN_DIR . 'includes/class-admin.php';
        require_once R404C_PLUGIN_DIR . 'includes/class-frontend.php';
        require_once R404C_PLUGIN_DIR . 'includes/class-settings.php';
    }
    
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $defaults = array(
            'r404c_enabled' => 'on',
            'r404c_redirect_url' => home_url(),
            'r404c_redirect_type' => '301'
        );
        
        foreach ($defaults as $key => $value) {
            if (false === get_option($key)) {
                add_option($key, $value);
            }
        }
        
        // Clear any cached redirects
        wp_cache_flush();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        wp_cache_flush();
    }
}

/**
 * Initialize the plugin
 */
function redirect_404_custom_init() {
    return Redirect_404_Custom::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'redirect_404_custom_init');