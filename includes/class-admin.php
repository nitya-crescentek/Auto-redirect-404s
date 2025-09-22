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
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . plugin_basename(R404C_PLUGIN_FILE), array($this, 'add_settings_link'));
        add_filter( 'plugin_row_meta', array( $this, 'addon_plugin_links' ), 10, 2 );
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
            admin_url('options-general.php?page=auto-redirect-404s'),
            __('Settings', 'auto-redirect-404s')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    public function addon_plugin_links( $links, $file ) {
        if ( $file !== plugin_basename( R404C_PLUGIN_FILE ) ) {
            return $links;
        }
        $links[] = __( '<a href="https://buymeacoffee.com/nityasaha" style="font-weight:bold;color:#00d300;font-size:15px;">Donate</a>', 'auto-redirect-404s' );
        $links[] = __( 'Made with Love ❤️', 'auto-redirect-404s' );

        return $links;
    }
    
    /**
     * Settings page content
     */
    public function settings_page() {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'auto-redirect-404s'));
        }

        // Handle form submission
        if (isset($_POST['submit']) && isset($_POST['r404c_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['r404c_nonce']));
            if (wp_verify_nonce($nonce, 'r404c_save_settings')) {
                $this->save_settings();
            }
        }
        
        // Get current settings
        $enabled = get_option('r404c_enabled', 'on');
        $redirect_url = get_option('r404c_redirect_url', home_url());
        $redirect_type = get_option('r404c_redirect_type', '301');
        
        // Get pages for dropdown
        $pages = get_pages(array(
            'post_status' => 'publish',
            'number' => 100
        ));
        
        include R404C_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        // Validate and sanitize
        $enabled = isset($_POST['r404c_enabled']) ? 'on' : 'off';
        
        // Sanitize URL input
        $redirect_url = '';
        if (isset($_POST['r404c_redirect_url'])) {
            $redirect_url = sanitize_text_field(wp_unslash($_POST['r404c_redirect_url']));
        }
        
        // Sanitize redirect type input
        $redirect_type = '301';
        if (isset($_POST['r404c_redirect_type'])) {
            $redirect_type = sanitize_text_field(wp_unslash($_POST['r404c_redirect_type']));
        }
        
        // Validate URL
        if (!empty($redirect_url)) {
            if (!filter_var($redirect_url, FILTER_VALIDATE_URL)) {
                // Try adding protocol if missing
                if (!preg_match('/^https?:\/\//', $redirect_url)) {
                    $redirect_url = 'http://' . $redirect_url;
                }
                
                // Validate again
                if (!filter_var($redirect_url, FILTER_VALIDATE_URL)) {
                    add_settings_error(
                        'r404c_messages',
                        'r404c_message',
                        __('Please enter a valid URL.', 'auto-redirect-404s'),
                        'error'
                    );
                    return;
                }
            }
        }
        
        // Save options
        update_option('r404c_enabled', $enabled);
        update_option('r404c_redirect_url', $redirect_url);
        update_option('r404c_redirect_type', $redirect_type);
        
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
     * Sanitize URL
     */
    public function sanitize_url($input) {
        $url = sanitize_text_field($input);
        
        if (!empty($url) && !preg_match('/^https?:\/\//', $url)) {
            $url = 'http://' . $url;
        }
        
        return esc_url_raw($url);
    }
    
    /**
     * Sanitize redirect type
     */
    public function sanitize_redirect_type($input) {
        $allowed = array('301', '302');
        return in_array($input, $allowed) ? $input : '301';
    }
}