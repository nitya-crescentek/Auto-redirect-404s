<?php
/**
 * Frontend functionality
 *
 * @package Redirect404Custom
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend class
 */
class R404C_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('template_redirect', array($this, 'handle_404_redirect'), 1);
    }
    
    /**
     * Handle 404 redirects
     */
    public function handle_404_redirect() {
        if (!is_404()) {
            return;
        }
        
        // Check if redirection is enabled
        $enabled = get_option('r404c_enabled', 'on');
        if ($enabled !== 'on') {
            return;
        }
        
        // Get redirect URL
        $redirect_url = get_option('r404c_redirect_url', home_url());
        if (empty($redirect_url)) {
            return;
        }
        
        // Get redirect type
        $redirect_type = get_option('r404c_redirect_type', '301');
        $redirect_code = $redirect_type === '302' ? 302 : 301;
        
        // Prevent infinite redirects
        $current_url = $this->get_current_url();
        if ($this->normalize_url($current_url) === $this->normalize_url($redirect_url)) {
            return;
        }
        
        // Log the redirect (optional)
        $this->maybe_log_redirect($current_url, $redirect_url);
        
        // Perform redirect
        wp_redirect($redirect_url, $redirect_code);
        exit;
    }
    
    /**
     * Get current URL
     */
    private function get_current_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    
    /**
     * Normalize URL for comparison
     */
    private function normalize_url($url) {
        // Remove trailing slash and convert to lowercase
        return rtrim(strtolower($url), '/');
    }
    
    /**
     * Maybe log redirect for debugging
     */
    private function maybe_log_redirect($from_url, $to_url) {
        // Only log if WP_DEBUG is enabled
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        error_log(sprintf(
            '[404 Redirect] Redirecting from %s to %s',
            $from_url,
            $to_url
        ));
    }
}