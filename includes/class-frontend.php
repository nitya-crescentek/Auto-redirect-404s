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
     * Handle 404 logging and redirects.
     */
    public function handle_404_redirect() {
        if (!is_404()) {
            return;
        }

        // Machine-facing requests must keep their real 404 status.
        if ($this->is_excluded_request()) {
            return;
        }

        $current_url = $this->get_current_url();

        // Logging is independent of redirecting, so it runs first and runs even
        // when redirects are switched off.
        $this->maybe_log_404($current_url);

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
        if ($this->is_same_destination($current_url, $redirect_url)) {
            return;
        }

        /**
         * Filter whether a 404 request should be redirected.
         *
         * @since 1.2.0
         *
         * @param bool   $should_redirect Whether to perform the redirect.
         * @param string $current_url     The 404 URL that was requested.
         * @param string $redirect_url    The configured destination.
         */
        if (!apply_filters('r404c_should_redirect', true, $current_url, $redirect_url)) {
            return;
        }

        // Log the redirect for debugging (only when WP_DEBUG is on)
        $this->maybe_log_redirect($current_url, $redirect_url);

        // Perform redirect
        wp_redirect($redirect_url, $redirect_code);
        exit;
    }

    /**
     * Whether this request should be left alone entirely.
     *
     * Feeds, sitemaps, robots.txt, REST and cron requests all rely on a real
     * 404 status; redirecting them confuses crawlers and breaks integrations.
     *
     * @return bool
     */
    private function is_excluded_request() {
        $excluded = false;

        // Only ordinary page views should ever be redirected.
        $method = isset($_SERVER['REQUEST_METHOD'])
            ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
            : 'GET';

        if (!in_array($method, array('GET', 'HEAD'), true)) {
            $excluded = true;
        }

        if (!$excluded && (defined('REST_REQUEST') && REST_REQUEST)) {
            $excluded = true;
        }

        if (!$excluded && (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            $excluded = true;
        }

        if (!$excluded && function_exists('wp_doing_cron') && wp_doing_cron()) {
            $excluded = true;
        }

        if (!$excluded && wp_doing_ajax()) {
            $excluded = true;
        }

        if (!$excluded && (is_feed() || is_trackback() || is_robots())) {
            $excluded = true;
        }

        // is_favicon() only exists from WordPress 5.4.
        if (!$excluded && function_exists('is_favicon') && is_favicon()) {
            $excluded = true;
        }

        // Core XML sitemaps (WordPress 5.5+).
        if (!$excluded && '' !== (string) get_query_var('sitemap')) {
            $excluded = true;
        }

        /**
         * Filter whether a 404 request is excluded from logging and redirecting.
         *
         * @since 1.2.0
         *
         * @param bool $excluded Whether the request is excluded.
         */
        return (bool) apply_filters('r404c_exclude_request', $excluded);
    }

    /**
     * Record the 404 in the log table when logging is enabled.
     *
     * @param string $current_url The URL that 404'd.
     * @return void
     */
    private function maybe_log_404($current_url) {
        if (!class_exists('R404C_Logger') || !R404C_Logger::is_enabled()) {
            return;
        }

        $referrer = '';
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referrer = esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER']));
        }

        /**
         * Filter whether a specific 404 should be logged.
         *
         * @since 1.2.0
         *
         * @param bool   $should_log  Whether to log this request.
         * @param string $current_url The URL that 404'd.
         */
        if (!apply_filters('r404c_should_log', true, $current_url)) {
            return;
        }

        R404C_Logger::record($current_url, $referrer);
    }

    /**
     * Get current URL
     */
    private function get_current_url() {
        $protocol = is_ssl() ? 'https://' : 'http://';

        // Safely get HTTP_HOST
        $host = '';
        if (isset($_SERVER['HTTP_HOST'])) {
            $host = sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST']));
        }

        // Safely get REQUEST_URI
        $request_uri = '';
        if (isset($_SERVER['REQUEST_URI'])) {
            $request_uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
        }

        return $protocol . $host . $request_uri;
    }

    /**
     * Whether the request and the redirect target are effectively the same page.
     *
     * Compares host and path only, ignoring scheme, trailing slash, case and
     * query string. The previous exact-string comparison missed http/https and
     * trailing-slash variants, which produced redirect loops when the configured
     * destination did not itself exist.
     *
     * @param string $current_url  The requested URL.
     * @param string $redirect_url The configured destination.
     * @return bool
     */
    private function is_same_destination($current_url, $redirect_url) {
        $current  = wp_parse_url($current_url);
        $redirect = wp_parse_url($redirect_url);

        if (empty($current) || empty($redirect)) {
            // Fall back to the original string comparison if parsing fails.
            return $this->normalize_url($current_url) === $this->normalize_url($redirect_url);
        }

        $current_host  = isset($current['host']) ? strtolower($current['host']) : '';
        $redirect_host = isset($redirect['host']) ? strtolower($redirect['host']) : $current_host;

        if ($current_host !== $redirect_host) {
            return false;
        }

        $current_path  = isset($current['path']) ? $current['path'] : '/';
        $redirect_path = isset($redirect['path']) ? $redirect['path'] : '/';

        return $this->normalize_path($current_path) === $this->normalize_path($redirect_path);
    }

    /**
     * Normalize a URL path for comparison.
     *
     * @param string $path Path component.
     * @return string
     */
    private function normalize_path($path) {
        $path = rawurldecode((string) $path);
        $path = strtolower(rtrim($path, '/'));

        return '' === $path ? '/' : $path;
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

        // Use WordPress logging instead of error_log for better compatibility
        if (function_exists('wp_debug_log')) {
            wp_debug_log(sprintf(
                '[404 Redirect] Redirecting from %s to %s',
                $from_url,
                $to_url
            ));
        }
    }
}
