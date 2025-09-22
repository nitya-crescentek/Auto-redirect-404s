<?php
/**
 * Settings helper class
 *
 * @package Redirect404Custom
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class
 */
class R404C_Settings {
    
    /**
     * Get setting value
     */
    public static function get($key, $default = '') {
        return get_option('r404c_' . $key, $default);
    }
    
    /**
     * Set setting value
     */
    public static function set($key, $value) {
        return update_option('r404c_' . $key, $value);
    }
    
    /**
     * Get all settings
     */
    public static function get_all() {
        return array(
            'enabled' => self::get('enabled', 'on'),
            'redirect_url' => self::get('redirect_url', home_url()),
            'redirect_type' => self::get('redirect_type', '301')
        );
    }
    
    /**
     * Reset to defaults
     */
    public static function reset() {
        self::set('enabled', 'on');
        self::set('redirect_url', home_url());
        self::set('redirect_type', '301');
    }
    
    /**
     * Validate settings
     */
    public static function validate($settings) {
        $errors = array();
        
        // Validate redirect URL
        if (!empty($settings['redirect_url'])) {
            $url = $settings['redirect_url'];
            
            // Add protocol if missing
            if (!preg_match('/^https?:\/\//', $url)) {
                $url = 'http://' . $url;
            }
            
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                $errors[] = __('Please enter a valid redirect URL.', 'auto-redirect-404s');
            }
        }
        
        // Validate redirect type
        if (!in_array($settings['redirect_type'], array('301', '302'))) {
            $errors[] = __('Invalid redirect type selected.', 'auto-redirect-404s');
        }
        
        return $errors;
    }
}