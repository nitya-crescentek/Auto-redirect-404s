<?php
/**
 * Admin settings template
 *
 * @package Redirect404Custom
 * @since 1.0.0
 *
 * @var string $enabled         'on' or 'off'.
 * @var string $redirect_url    Configured destination URL.
 * @var string $redirect_type   '301' or '302'.
 * @var string $logging_enabled 'on' or 'off'.
 * @var int    $log_count       Number of logged 404 URLs.
 * @var string $settings_url    URL of the settings tab.
 * @var string $logs_url        URL of the logs tab.
 * @var array  $pages           Published pages for the quick-select dropdown.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('404 Redirect Settings', 'auto-redirect-404s'); ?></h1>

    <?php settings_errors('r404c_messages'); ?>

    <h2 class="nav-tab-wrapper r404c-tabs">
        <a href="<?php echo esc_url($settings_url); ?>" class="nav-tab nav-tab-active">
            <?php esc_html_e('Settings', 'auto-redirect-404s'); ?>
        </a>
        <a href="<?php echo esc_url($logs_url); ?>" class="nav-tab">
            <?php esc_html_e('404 Logs', 'auto-redirect-404s'); ?>
            <?php if ($log_count > 0) : ?>
                <span class="r404c-count"><?php echo esc_html(number_format_i18n($log_count)); ?></span>
            <?php endif; ?>
        </a>
    </h2>

    <div class="r404c-container">
        <div class="r404c-main-content">
            <form method="post" action="">
                <?php wp_nonce_field('r404c_save_settings', 'r404c_nonce'); ?>

                <div class="r404c-section">
                    <div class="r404c-section-header">
                        <div class="r404c-section-heading">
                            <h2><?php esc_html_e('Redirect Configuration', 'auto-redirect-404s'); ?></h2>
                            <p class="description"><?php esc_html_e('Configure how 404 errors should be handled on your website.', 'auto-redirect-404s'); ?></p>
                        </div>

                        <div class="r404c-logging-control">
                            <span class="r404c-logging-title"><?php esc_html_e('404 Logging', 'auto-redirect-404s'); ?></span>

                            <div class="r404c-toggle-container">
                                <label class="r404c-toggle r404c-toggle-sm">
                                    <input type="checkbox"
                                           id="r404c_logging_enabled"
                                           name="r404c_logging_enabled"
                                           <?php checked($logging_enabled, 'on'); ?> />
                                    <span class="r404c-toggle-slider"></span>
                                </label>
                                <span class="r404c-toggle-label"
                                      data-on="<?php esc_attr_e('On', 'auto-redirect-404s'); ?>"
                                      data-off="<?php esc_attr_e('Off', 'auto-redirect-404s'); ?>"></span>
                            </div>

                            <a class="r404c-logs-link" href="<?php echo esc_url($logs_url); ?>">
                                <?php esc_html_e('View Logs', 'auto-redirect-404s'); ?>
                                <?php if ($log_count > 0) : ?>
                                    <span class="r404c-count"><?php echo esc_html(number_format_i18n($log_count)); ?></span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="r404c_enabled"><?php esc_html_e('Enable Redirects', 'auto-redirect-404s'); ?></label>
                            </th>
                            <td>
                                <div class="r404c-toggle-container">
                                    <label class="r404c-toggle">
                                        <input type="checkbox"
                                               id="r404c_enabled"
                                               name="r404c_enabled"
                                               <?php checked($enabled, 'on'); ?> />
                                        <span class="r404c-toggle-slider"></span>

                                    </label>
                                    <span class="r404c-toggle-label" data-on="<?php esc_attr_e('Enabled', 'auto-redirect-404s'); ?>" data-off="<?php esc_attr_e('Disabled', 'auto-redirect-404s'); ?>"></span>
                                </div>
                                <p class="description"><?php esc_html_e('Toggle this to enable or disable 404 redirects.', 'auto-redirect-404s'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="r404c_redirect_url"><?php esc_html_e('Redirect URL', 'auto-redirect-404s'); ?></label>
                            </th>
                            <td>
                                <div class="r404c-url-section">
                                    <div class="r404c-quick-select">
                                        <label for="r404c_quick_select"><?php esc_html_e('Quick Select:', 'auto-redirect-404s'); ?></label>
                                        <select id="r404c_quick_select">
                                            <option value=""><?php esc_html_e('-- Choose a page --', 'auto-redirect-404s'); ?></option>
                                            <option value="<?php echo esc_url(home_url()); ?>"><?php esc_html_e('Home Page', 'auto-redirect-404s'); ?></option>
                                            <?php foreach ($pages as $page) : ?>
                                                <option value="<?php echo esc_url(get_permalink($page->ID)); ?>">
                                                    <?php echo esc_html($page->post_title); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="r404c-url-input">
                                        <input type="url"
                                               id="r404c_redirect_url"
                                               name="r404c_redirect_url"
                                               value="<?php echo esc_url($redirect_url); ?>"
                                               class="large-text"
                                               placeholder="<?php esc_attr_e('Enter redirect URL...', 'auto-redirect-404s'); ?>" />
                                    </div>
                                </div>
                                <p class="description"><?php esc_html_e('404 pages will redirect to this URL. Use the dropdown above for quick selection or enter a custom URL.', 'auto-redirect-404s'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Redirect Type', 'auto-redirect-404s'); ?></th>
                            <td>
                                <div class="r404c-radio-group">
                                    <label class="r404c-radio-option">
                                        <input type="radio"
                                               name="r404c_redirect_type"
                                               value="301"
                                               <?php checked($redirect_type, '301'); ?> />
                                        <span class="r404c-radio-checkmark"></span>
                                        <span class="r404c-radio-label">
                                            <strong><?php esc_html_e('301 - Permanent Redirect', 'auto-redirect-404s'); ?></strong>
                                            <small><?php esc_html_e('(Recommended for SEO)', 'auto-redirect-404s'); ?></small>
                                        </span>
                                    </label>

                                    <label class="r404c-radio-option">
                                        <input type="radio"
                                               name="r404c_redirect_type"
                                               value="302"
                                               <?php checked($redirect_type, '302'); ?> />
                                        <span class="r404c-radio-checkmark"></span>
                                        <span class="r404c-radio-label">
                                            <strong><?php esc_html_e('302 - Temporary Redirect', 'auto-redirect-404s'); ?></strong>
                                            <small><?php esc_html_e('(Use if the redirect is temporary)', 'auto-redirect-404s'); ?></small>
                                        </span>
                                    </label>
                                </div>
                                <p class="description"><?php esc_html_e('301 redirects are permanent and pass SEO value, while 302 redirects are temporary.', 'auto-redirect-404s'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(__('Save Settings', 'auto-redirect-404s'), 'primary', 'submit', false); ?>
            </form>
        </div>

        <div class="r404c-sidebar">
            <?php
            $top_404s = ('on' === $logging_enabled) ? R404C_Logger::get_top(5) : array();
            if (!empty($top_404s)) :
                ?>
                <div class="r404c-sidebar-box r404c-top-box">
                    <h3><?php esc_html_e('Top 404 Errors', 'auto-redirect-404s'); ?></h3>
                    <ul class="r404c-top-list">
                        <?php foreach ($top_404s as $top) : ?>
                            <li>
                                <a href="<?php echo esc_url(home_url($top->url)); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr($top->url); ?>">
                                    <?php echo esc_html($top->url); ?>
                                </a>
                                <span class="r404c-top-hits"><?php echo esc_html(number_format_i18n((int) $top->hit_count)); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="r404c-top-more">
                        <a href="<?php echo esc_url($logs_url); ?>"><?php esc_html_e('View all 404 logs', 'auto-redirect-404s'); ?></a>
                    </p>
                </div>
            <?php endif; ?>

            <div class="r404c-sidebar-box">
                <h3><?php esc_html_e('How it Works', 'auto-redirect-404s'); ?></h3>
                <ul>
                    <li><?php esc_html_e('When a visitor accesses a non-existent page, they get a 404 error', 'auto-redirect-404s'); ?></li>
                    <li><?php esc_html_e('This plugin automatically redirects them to your chosen URL', 'auto-redirect-404s'); ?></li>
                    <li><?php esc_html_e('This improves user experience and helps with SEO', 'auto-redirect-404s'); ?></li>
                </ul>
            </div>

            <div class="r404c-sidebar-box">
                <h3><?php esc_html_e('SEO Benefits', 'auto-redirect-404s'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Reduces bounce rate from 404 errors', 'auto-redirect-404s'); ?></li>
                    <li><?php esc_html_e('Helps with Google Search Console reports', 'auto-redirect-404s'); ?></li>
                    <li><?php esc_html_e('Keeps visitors on your site longer', 'auto-redirect-404s'); ?></li>
                </ul>
            </div>

            <div class="r404c-sidebar-box r404c-status-box">
                <h3><?php esc_html_e('Current Status', 'auto-redirect-404s'); ?></h3>
                <div class="r404c-status">
                    <span class="r404c-status-indicator <?php echo $enabled === 'on' ? 'active' : 'inactive'; ?>"></span>
                    <span class="r404c-status-text">
                        <?php echo $enabled === 'on' ? esc_html__('Active', 'auto-redirect-404s') : esc_html__('Inactive', 'auto-redirect-404s'); ?>
                    </span>
                </div>
                <?php if ($enabled === 'on') : ?>
                    <p><small>
                        <?php
                        printf(
                            /* translators: %s: URL where the user is being redirected */
                            esc_html__('Redirecting to: %s', 'auto-redirect-404s'),
                            esc_url($redirect_url)
                        );
                        ?>
                    </small></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
