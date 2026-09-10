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
     * Request header that marks the plugin's own loop-protection probe.
     *
     * The probe must never be redirected or logged, otherwise checking the
     * destination would recurse into itself.
     */
    const PROBE_HEADER = 'HTTP_X_R404C_PROBE';

    /**
     * File extensions that should always keep a real 404.
     *
     * Deliberately excludes .html and .htm, which are legitimate permalink
     * suffixes on many sites.
     */
    const SKIP_EXTENSIONS = 'php,phtml,asp,aspx,jsp,cgi,pl,css,js,mjs,map,json,xml,txt,ico,png,jpg,jpeg,gif,bmp,svg,webp,avif,tif,tiff,woff,woff2,ttf,eot,otf,pdf,zip,gz,tar,rar,7z,mp3,mp4,m4a,webm,ogg,ogv,wav,avi,mov,wmv,flv,csv,doc,docx,xls,xlsx,ppt,pptx,psd,ai,eps,dmg,exe,apk,bin,sql,env,yml,yaml,ini,log,bak';

    /**
     * Path prefixes that should always keep a real 404.
     */
    const SKIP_PREFIXES = '/wp-json/,/wp-admin/,/wp-includes/,/wp-content/,/.well-known/,/feed/,/comments/feed/';

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
        $path        = $this->get_request_path();

        // Static files, system paths and user-defined patterns are left alone
        // entirely: no redirect and no log entry.
        if ($this->is_skipped_path($path)) {
            return;
        }

        if ($this->matches_exclusion_pattern($path)) {
            return;
        }

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

        // Redirecting to a destination that is itself missing would bounce the
        // visitor around until the browser gives up.
        if ($this->destination_is_broken($redirect_url)) {
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

        // The plugin's own destination probe must pass straight through.
        if (isset($_SERVER[self::PROBE_HEADER])) {
            $excluded = true;
        }

        // Only ordinary page views should ever be redirected.
        if (!$excluded) {
            $method = isset($_SERVER['REQUEST_METHOD'])
                ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])))
                : 'GET';

            if (!in_array($method, array('GET', 'HEAD'), true)) {
                $excluded = true;
            }
        }

        if (!$excluded && (defined('REST_REQUEST') && REST_REQUEST)) {
            $excluded = true;
        }

        if (!$excluded && (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            $excluded = true;
        }

        if (!$excluded && wp_doing_cron()) {
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
     * Whether a path is a static file or a system path that must keep its 404.
     *
     * Controlled by the "Skip files and system paths" setting.
     *
     * @param string $path Request path, without query string.
     * @return bool
     */
    private function is_skipped_path($path) {
        if (get_option('r404c_skip_assets', 'off') !== 'on') {
            return false;
        }

        if ('' === $path) {
            return false;
        }

        $lower = strtolower($path);

        // Sitemaps generated by SEO plugins, plus the usual root text files.
        if (preg_match('#(^|/)(sitemap[^/]*\.xml(\.gz)?|sitemap_index\.xml|robots\.txt|ads\.txt|security\.txt|favicon\.ico|browserconfig\.xml|manifest\.json)$#', $lower)) {
            return true;
        }

        foreach (explode(',', self::SKIP_PREFIXES) as $prefix) {
            if (0 === strpos($lower, $prefix)) {
                return true;
            }
        }

        // Trailing-slash URLs are page-shaped, never a file request.
        if ('/' === substr($lower, -1)) {
            return false;
        }

        $extension = strtolower(pathinfo(wp_parse_url($lower, PHP_URL_PATH), PATHINFO_EXTENSION));

        if ('' === $extension) {
            return false;
        }

        return in_array($extension, explode(',', self::SKIP_EXTENSIONS), true);
    }

    /**
     * Whether the path matches one of the administrator's exclusion patterns.
     *
     * @param string $path Request path, without query string.
     * @return bool
     */
    private function matches_exclusion_pattern($path) {
        $raw = (string) get_option('r404c_exclusion_patterns', '');

        if ('' === trim($raw)) {
            return false;
        }

        $patterns = preg_split('/[\r\n]+/', $raw);

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);

            if ('' === $pattern) {
                continue;
            }

            if (self::path_matches_pattern($path, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a request path against a single wildcard pattern.
     *
     * Patterns use shell-style wildcards: * for any run of characters and ?
     * for a single character. A pattern with no leading slash or wildcard is
     * anchored to the site root, so "old-page" behaves like "/old-page".
     *
     * @param string $path    Request path.
     * @param string $pattern User-supplied pattern.
     * @return bool
     */
    public static function path_matches_pattern($path, $pattern) {
        if ('' === $pattern) {
            return false;
        }

        if ('/' !== $pattern[0] && '*' !== $pattern[0]) {
            $pattern = '/' . $pattern;
        }

        // preg_quote escapes the wildcards too, so put them back afterwards.
        $regex = preg_quote($pattern, '#');
        $regex = str_replace(array('\*', '\?'), array('.*', '.'), $regex);

        return 1 === preg_match('#^' . $regex . '$#i', $path);
    }

    /**
     * Whether the configured destination is itself missing.
     *
     * Reads a cached verdict only. The HTTP check that produces that verdict
     * deliberately never runs during a visitor request: on a host that blocks
     * loopback requests the probe sits there until it times out, which would
     * add whole seconds to somebody's page load. It is produced instead by the
     * hourly cron event and whenever settings are saved.
     *
     * Absent or unknown means "not broken", so a check that has never run, or
     * cannot run, leaves redirects working exactly as before.
     *
     * @param string $redirect_url Configured destination.
     * @return bool True only when the destination is known to be missing.
     */
    private function destination_is_broken($redirect_url) {
        if (get_option('r404c_loop_protection', 'off') !== 'on') {
            return false;
        }

        return 'broken' === get_transient(self::destination_cache_key($redirect_url));
    }

    /**
     * Transient key holding the verdict for a destination.
     *
     * @param string $redirect_url Destination URL.
     * @return string
     */
    public static function destination_cache_key($redirect_url) {
        return 'r404c_dest_' . md5((string) $redirect_url);
    }

    /**
     * Check the destination and cache the verdict.
     *
     * Only ever called from cron or from an admin request, never from a
     * visitor-facing page load.
     *
     * @param string|null $redirect_url Destination, or null to use the setting.
     * @return bool|null True broken, false reachable, null when undetermined.
     */
    public static function refresh_destination_status($redirect_url = null) {
        if (null === $redirect_url) {
            $redirect_url = get_option('r404c_redirect_url', '');
        }

        $redirect_url = (string) $redirect_url;

        if ('' === $redirect_url || get_option('r404c_loop_protection', 'off') !== 'on') {
            return null;
        }

        $cache_key = self::destination_cache_key($redirect_url);

        $response = wp_remote_head(
            $redirect_url,
            array(
                'timeout'     => 5,
                'redirection' => 3,
                'sslverify'   => false,
                'user-agent'  => 'auto-redirect-404s/' . R404C_VERSION . ' (destination check)',
                'headers'     => array('X-R404C-Probe' => '1'),
            )
        );

        if (is_wp_error($response)) {
            // Undetermined. Forget any previous verdict so the frontend falls
            // back to redirecting normally rather than trusting stale data.
            delete_transient($cache_key);
            return null;
        }

        $code   = (int) wp_remote_retrieve_response_code($response);
        $broken = in_array($code, array(404, 410), true);

        // Outlives the hourly cron interval so a verdict never lapses between
        // runs on a quiet site.
        set_transient($cache_key, $broken ? 'broken' : 'ok', 3 * HOUR_IN_SECONDS);

        return $broken;
    }

    /**
     * Read the cached verdict without triggering a check.
     *
     * @param string $redirect_url Destination URL.
     * @return string|false 'broken', 'ok', or false when never checked.
     */
    public static function get_destination_status($redirect_url) {
        return get_transient(self::destination_cache_key($redirect_url));
    }

    /**
     * Clear the cached destination check.
     *
     * Called when settings are saved so a corrected URL takes effect at once.
     *
     * @param string $redirect_url Destination to forget.
     * @return void
     */
    public static function clear_destination_cache($redirect_url) {
        if ('' === (string) $redirect_url) {
            return;
        }

        delete_transient(self::destination_cache_key($redirect_url));
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
     * Get the requested path, without host or query string.
     *
     * @return string Path beginning with a slash, or '' when unavailable.
     */
    private function get_request_path() {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return '';
        }

        $uri  = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
        $path = wp_parse_url($uri, PHP_URL_PATH);

        if (!is_string($path) || '' === $path) {
            return '';
        }

        return $path;
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
