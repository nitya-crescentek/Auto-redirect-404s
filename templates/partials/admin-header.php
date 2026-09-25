<?php
/**
 * Page header and tab navigation shared by every tab.
 *
 * @package Redirect404Custom
 * @since 1.3.0
 *
 * @var string $active_tab            'settings', 'redirects' or 'logs'.
 * @var string $enabled               Catch-all 404 redirect, 'on' or 'off'.
 * @var string $logging_enabled       404 logging, 'on' or 'off'.
 * @var int    $active_redirect_count Enabled Redirection Manager rules.
 * @var int    $log_count             Number of logged 404 URLs.
 * @var string $settings_url          URL of the settings tab.
 * @var string $redirects_url         URL of the Redirection Manager tab.
 * @var string $logs_url              URL of the logs tab.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$r404c_features = array(
    'settings'  => array(
        'url'   => $settings_url,
        'icon'  => 'dashicons-admin-home',
        'title' => __('404 Auto Redirect', 'auto-redirect-404s'),
        'on'    => 'on' === $enabled,
        'state' => 'on' === $enabled ? __('Active', 'auto-redirect-404s') : __('Off', 'auto-redirect-404s'),
    ),
    'redirects' => array(
        'url'   => $redirects_url,
        'icon'  => 'dashicons-randomize',
        'title' => __('Redirection Manager', 'auto-redirect-404s'),
        'on'    => $active_redirect_count > 0,
        'state' => sprintf(
            /* translators: %s: number of active redirect rules */
            _n('%s active rule', '%s active rules', $active_redirect_count, 'auto-redirect-404s'),
            number_format_i18n($active_redirect_count)
        ),
    ),
    'logs'      => array(
        'url'   => $logs_url,
        'icon'  => 'dashicons-visibility',
        'title' => __('404 Monitor', 'auto-redirect-404s'),
        'on'    => 'on' === $logging_enabled,
        'state' => 'on' === $logging_enabled
            ? sprintf(
                /* translators: %s: number of logged 404 URLs */
                _n('Watching · %s URL logged', 'Watching · %s URLs logged', $log_count, 'auto-redirect-404s'),
                number_format_i18n($log_count)
            )
            : __('Off', 'auto-redirect-404s'),
    ),
);
?>

<div class="r404c-header">
    <div class="r404c-header-brand">
        <span class="r404c-header-icon dashicons dashicons-randomize" aria-hidden="true"></span>
        <div class="r404c-header-text">
            <h1>
                <?php esc_html_e('Auto Redirect 404s', 'auto-redirect-404s'); ?>
                <span class="r404c-version">v<?php echo esc_html(R404C_VERSION); ?></span>
            </h1>
            <p class="r404c-tagline">
                <?php esc_html_e('Catch every 404, redirect old URLs with 301 rules, and monitor broken links, all in one lightweight plugin.', 'auto-redirect-404s'); ?>
            </p>
        </div>
    </div>

    <ul class="r404c-header-features">
        <?php foreach ($r404c_features as $r404c_key => $r404c_feature) : ?>
            <li>
                <a href="<?php echo esc_url($r404c_feature['url']); ?>"
                   class="r404c-feature<?php echo $r404c_feature['on'] ? ' is-on' : ''; ?><?php echo $active_tab === $r404c_key ? ' is-current' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($r404c_feature['icon']); ?>" aria-hidden="true"></span>
                    <span class="r404c-feature-text">
                        <strong><?php echo esc_html($r404c_feature['title']); ?></strong>
                        <span class="r404c-feature-state"><?php echo esc_html($r404c_feature['state']); ?></span>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<hr class="wp-header-end" />

<nav class="nav-tab-wrapper r404c-tabs" aria-label="<?php esc_attr_e('Auto Redirects sections', 'auto-redirect-404s'); ?>">
    <a href="<?php echo esc_url($settings_url); ?>" class="nav-tab<?php echo 'settings' === $active_tab ? ' nav-tab-active' : ''; ?>">
        <?php esc_html_e('Settings', 'auto-redirect-404s'); ?>
    </a>
    <a href="<?php echo esc_url($redirects_url); ?>" class="nav-tab<?php echo 'redirects' === $active_tab ? ' nav-tab-active' : ''; ?>">
        <?php esc_html_e('Redirection Manager', 'auto-redirect-404s'); ?>
        <?php if ($active_redirect_count > 0) : ?>
            <span class="r404c-count"><?php echo esc_html(number_format_i18n($active_redirect_count)); ?></span>
        <?php endif; ?>
    </a>
    <a href="<?php echo esc_url($logs_url); ?>" class="nav-tab<?php echo 'logs' === $active_tab ? ' nav-tab-active' : ''; ?>">
        <?php esc_html_e('404 Logs', 'auto-redirect-404s'); ?>
        <?php if ($log_count > 0) : ?>
            <span class="r404c-count"><?php echo esc_html(number_format_i18n($log_count)); ?></span>
        <?php endif; ?>
    </a>
</nav>
