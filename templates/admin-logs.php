<?php
/**
 * 404 logs template
 *
 * @package Redirect404Custom
 * @since 1.2.0
 *
 * @var R404C_Logs_Table $logs_table      Prepared list table.
 * @var string           $logging_enabled 'on' or 'off'.
 * @var int              $log_count       Number of logged 404 URLs.
 * @var int              $total_hits      Sum of all hit counts.
 * @var string           $settings_url    URL of the settings tab.
 * @var string           $logs_url        URL of the logs tab.
 * @var string           $clear_url       Nonced URL that clears every log row.
 * @var string           $export_url      Nonced URL that downloads the CSV.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('404 Redirect Settings', 'auto-redirect-404s'); ?></h1>

    <h2 class="nav-tab-wrapper r404c-tabs">
        <a href="<?php echo esc_url($settings_url); ?>" class="nav-tab">
            <?php esc_html_e('Settings', 'auto-redirect-404s'); ?>
        </a>
        <a href="<?php echo esc_url($logs_url); ?>" class="nav-tab nav-tab-active">
            <?php esc_html_e('404 Logs', 'auto-redirect-404s'); ?>
            <?php if ($log_count > 0) : ?>
                <span class="r404c-count"><?php echo esc_html(number_format_i18n($log_count)); ?></span>
            <?php endif; ?>
        </a>
    </h2>

    <?php if ('on' !== $logging_enabled) : ?>
        <div class="notice notice-warning">
            <p>
                <?php esc_html_e('404 logging is currently switched off, so no new errors are being recorded.', 'auto-redirect-404s'); ?>
                <a href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Turn it on in Settings', 'auto-redirect-404s'); ?></a>
            </p>
        </div>
    <?php endif; ?>

    <div class="r404c-logs-wrap">
        <div class="r404c-logs-summary">
            <div class="r404c-stat">
                <span class="r404c-stat-value"><?php echo esc_html(number_format_i18n($log_count)); ?></span>
                <span class="r404c-stat-label"><?php esc_html_e('Unique 404 URLs', 'auto-redirect-404s'); ?></span>
            </div>
            <div class="r404c-stat">
                <span class="r404c-stat-value"><?php echo esc_html(number_format_i18n($total_hits)); ?></span>
                <span class="r404c-stat-label"><?php esc_html_e('Total Hits', 'auto-redirect-404s'); ?></span>
            </div>
            <div class="r404c-stat">
                <span class="r404c-stat-value"><?php echo esc_html(number_format_i18n(R404C_Logger::MAX_ROWS)); ?></span>
                <span class="r404c-stat-label"><?php esc_html_e('Row Limit', 'auto-redirect-404s'); ?></span>
            </div>

            <div class="r404c-logs-actions">
                <?php if ($log_count > 0) : ?>
                    <a href="<?php echo esc_url($export_url); ?>" class="button">
                        <?php esc_html_e('Export CSV', 'auto-redirect-404s'); ?>
                    </a>
                    <a href="<?php echo esc_url($clear_url); ?>"
                       class="button r404c-button-danger"
                       onclick="return confirm('<?php echo esc_js(__('Delete every logged 404 entry? This cannot be undone.', 'auto-redirect-404s')); ?>');">
                        <?php esc_html_e('Clear All Logs', 'auto-redirect-404s'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <p class="description r404c-logs-note">
            <?php
            printf(
                /* translators: %s: maximum number of retained log rows */
                esc_html__('Each unique URL is stored once with a hit counter. The %s least recently seen entries are kept; older entries are removed automatically. No IP addresses or personal data are recorded.', 'auto-redirect-404s'),
                esc_html(number_format_i18n(R404C_Logger::MAX_ROWS))
            );
            ?>
        </p>

        <form method="post" action="<?php echo esc_url($logs_url); ?>">
            <input type="hidden" name="page" value="auto-redirect-404s" />
            <input type="hidden" name="tab" value="logs" />
            <?php
            $logs_table->search_box(__('Search URLs', 'auto-redirect-404s'), 'r404c-log-search');
            $logs_table->display();
            ?>
        </form>
    </div>
</div>
