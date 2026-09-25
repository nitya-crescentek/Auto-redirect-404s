<?php
/**
 * Redirection Manager template
 *
 * @package Redirect404Custom
 * @since 1.3.0
 *
 * @var R404C_Redirects_Table $redirects_table Prepared list table.
 * @var array                 $redirect_counts {all, enabled, disabled}.
 * @var int                   $redirect_hits   Sum of all rule hit counts.
 * @var array                 $form            Values for the add / edit form.
 * @var WP_Error|null         $form_error      Error from a failed save.
 * @var array                 $pages           Published pages for the quick-select dropdown.
 * @var string                $export_url      Nonced URL that downloads the CSV.
 * @var string                $redirects_url   URL of this tab.
 *
 * Plus the header variables documented in partials/admin-header.php.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$r404c_editing      = !empty($form['id']);
$r404c_match_hints  = array(
    'exact'    => array(
        'hint'        => __('Matches this one URL. Not case sensitive, and a trailing slash makes no difference.', 'auto-redirect-404s'),
        'placeholder' => '/old-page',
    ),
    'wildcard' => array(
        'hint'        => __('Use * to match any part of the path, then insert what it matched into the target with $1, $2 … For example /blog/* to /news/$1', 'auto-redirect-404s'),
        'placeholder' => '/old-blog/*',
    ),
    'regex'    => array(
        'hint'        => __('A PCRE regular expression matched against the path, not case sensitive. Use capture groups in the target as $1, $2 … For example ^/(\d{4})/(.+)$ to /$2', 'auto-redirect-404s'),
        'placeholder' => '^/category/(.+)$',
    ),
);
$r404c_current_hint = isset($r404c_match_hints[$form['match_type']]) ? $r404c_match_hints[$form['match_type']] : $r404c_match_hints['exact'];
?>

<div class="wrap r404c-wrap">
    <?php include R404C_PLUGIN_DIR . 'templates/partials/admin-header.php'; ?>

    <div class="r404c-card r404c-redirect-editor" id="r404c-redirect-form">
        <div class="r404c-section-header">
            <div class="r404c-section-heading">
                <h2>
                    <?php
                    echo $r404c_editing
                        ? esc_html__('Edit Redirect', 'auto-redirect-404s')
                        : esc_html__('Add New Redirect', 'auto-redirect-404s');
                    ?>
                </h2>
                <p class="description">
                    <?php esc_html_e('Send visitors and search engines from an old URL to its new home. Rules run on every request, before the catch-all 404 redirect, so they also work for pages that still exist.', 'auto-redirect-404s'); ?>
                </p>
            </div>
        </div>

        <?php if (is_wp_error($form_error)) : ?>
            <div class="notice notice-error inline">
                <p>
                    <?php echo esc_html($form_error->get_error_message()); ?>
                    <?php
                    $r404c_error_data = $form_error->get_error_data();
                    if (is_array($r404c_error_data) && !empty($r404c_error_data['existing_id'])) :
                        ?>
                        <a href="<?php echo esc_url(add_query_arg('edit', (int) $r404c_error_data['existing_id'], $redirects_url) . '#r404c-redirect-form'); ?>">
                            <?php esc_html_e('Edit the existing redirect', 'auto-redirect-404s'); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url($redirects_url); ?>" class="r404c-redirect-form">
            <?php wp_nonce_field('r404c_save_redirect', 'r404c_redirect_nonce'); ?>
            <input type="hidden" name="r404c_redirect_action" value="save" />
            <input type="hidden" name="redirect_id" value="<?php echo esc_attr((int) $form['id']); ?>" />
            <input type="hidden" name="from_log" value="<?php echo esc_attr((int) $form['from_log']); ?>" />

            <div class="r404c-form-grid">
                <div class="r404c-field r404c-field-wide">
                    <label for="r404c_source_url"><?php esc_html_e('Source URL', 'auto-redirect-404s'); ?></label>
                    <input type="text"
                           id="r404c_source_url"
                           name="source_url"
                           class="large-text code"
                           value="<?php echo esc_attr($form['source_url']); ?>"
                           placeholder="<?php echo esc_attr($r404c_current_hint['placeholder']); ?>"
                           spellcheck="false"
                           required />
                    <p class="description" id="r404c_match_hint"><?php echo esc_html($r404c_current_hint['hint']); ?></p>
                </div>

                <div class="r404c-field">
                    <label for="r404c_match_type"><?php esc_html_e('Match Type', 'auto-redirect-404s'); ?></label>
                    <select id="r404c_match_type" name="match_type">
                        <?php foreach (R404C_Redirects::match_types() as $r404c_value => $r404c_label) : ?>
                            <option value="<?php echo esc_attr($r404c_value); ?>"
                                    data-hint="<?php echo esc_attr($r404c_match_hints[$r404c_value]['hint']); ?>"
                                    data-placeholder="<?php echo esc_attr($r404c_match_hints[$r404c_value]['placeholder']); ?>"
                                    <?php selected($form['match_type'], $r404c_value); ?>>
                                <?php echo esc_html($r404c_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="r404c-field r404c-field-wide r404c-target-field">
                    <label for="r404c_target_url"><?php esc_html_e('Target URL', 'auto-redirect-404s'); ?></label>
                    <div class="r404c-target-input">
                        <input type="text"
                               id="r404c_target_url"
                               name="target_url"
                               class="large-text code"
                               value="<?php echo esc_attr($form['target_url']); ?>"
                               placeholder="<?php esc_attr_e('/new-page or https://example.com/page', 'auto-redirect-404s'); ?>"
                               spellcheck="false" />
                        <select id="r404c_target_quick_select" aria-label="<?php esc_attr_e('Pick a page as the target', 'auto-redirect-404s'); ?>">
                            <option value=""><?php esc_html_e('Pick a page…', 'auto-redirect-404s'); ?></option>
                            <option value="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home Page', 'auto-redirect-404s'); ?></option>
                            <?php foreach ($pages as $r404c_page) : ?>
                                <option value="<?php echo esc_url(get_permalink($r404c_page->ID)); ?>">
                                    <?php echo esc_html($r404c_page->post_title); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <p class="description">
                        <?php esc_html_e('A path on this site starting with /, or a full URL to any site.', 'auto-redirect-404s'); ?>
                    </p>
                </div>

                <div class="r404c-field">
                    <label for="r404c_status_code"><?php esc_html_e('Redirect Type', 'auto-redirect-404s'); ?></label>
                    <select id="r404c_status_code" name="status_code">
                        <?php foreach (R404C_Redirects::status_codes() as $r404c_value => $r404c_label) : ?>
                            <option value="<?php echo esc_attr($r404c_value); ?>" <?php selected((int) $form['status_code'], $r404c_value); ?>>
                                <?php echo esc_html($r404c_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="r404c-field">
                    <label for="r404c_query_mode"><?php esc_html_e('Query String', 'auto-redirect-404s'); ?></label>
                    <select id="r404c_query_mode" name="query_mode">
                        <?php foreach (R404C_Redirects::query_modes() as $r404c_value => $r404c_label) : ?>
                            <option value="<?php echo esc_attr($r404c_value); ?>" <?php selected($form['query_mode'], $r404c_value); ?>>
                                <?php echo esc_html($r404c_label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php esc_html_e('Exact match only redirects when the query string in the source matches too.', 'auto-redirect-404s'); ?>
                    </p>
                </div>

                <div class="r404c-field">
                    <span class="r404c-field-label"><?php esc_html_e('Status', 'auto-redirect-404s'); ?></span>
                    <div class="r404c-toggle-container">
                        <label class="r404c-toggle">
                            <input type="checkbox"
                                   id="r404c_is_enabled"
                                   name="is_enabled"
                                   value="1"
                                   <?php checked(!empty($form['is_enabled'])); ?> />
                            <span class="r404c-toggle-slider"></span>
                        </label>
                        <span class="r404c-toggle-label" data-on="<?php esc_attr_e('Enabled', 'auto-redirect-404s'); ?>" data-off="<?php esc_attr_e('Disabled', 'auto-redirect-404s'); ?>"></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($form['from_log'])) : ?>
                <p class="r404c-from-log">
                    <label>
                        <input type="checkbox" name="remove_log" value="1" <?php checked(!empty($form['remove_log'])); ?> />
                        <?php esc_html_e('Remove this URL from the 404 log once the redirect is saved', 'auto-redirect-404s'); ?>
                    </label>
                </p>
            <?php endif; ?>

            <p class="submit r404c-form-actions">
                <?php
                submit_button(
                    $r404c_editing ? __('Update Redirect', 'auto-redirect-404s') : __('Add Redirect', 'auto-redirect-404s'),
                    'primary',
                    'r404c_save_redirect',
                    false
                );
                ?>
                <?php if ($r404c_editing || !empty($form['from_log'])) : ?>
                    <a href="<?php echo esc_url($redirects_url); ?>" class="button"><?php esc_html_e('Cancel', 'auto-redirect-404s'); ?></a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <div class="r404c-logs-wrap r404c-redirects-wrap">
        <div class="r404c-logs-summary">
            <div class="r404c-stat">
                <span class="r404c-stat-value"><?php echo esc_html(number_format_i18n($redirect_counts['all'])); ?></span>
                <span class="r404c-stat-label"><?php esc_html_e('Redirects', 'auto-redirect-404s'); ?></span>
            </div>
            <div class="r404c-stat">
                <span class="r404c-stat-value"><?php echo esc_html(number_format_i18n($redirect_counts['enabled'])); ?></span>
                <span class="r404c-stat-label"><?php esc_html_e('Active', 'auto-redirect-404s'); ?></span>
            </div>
            <div class="r404c-stat">
                <span class="r404c-stat-value"><?php echo esc_html(number_format_i18n($redirect_hits)); ?></span>
                <span class="r404c-stat-label"><?php esc_html_e('Total Hits', 'auto-redirect-404s'); ?></span>
            </div>

            <div class="r404c-logs-actions">
                <?php if ($redirect_counts['all'] > 0) : ?>
                    <a href="<?php echo esc_url($export_url); ?>" class="button">
                        <?php esc_html_e('Export CSV', 'auto-redirect-404s'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <details class="r404c-import">
            <summary><?php esc_html_e('Import redirects from CSV', 'auto-redirect-404s'); ?></summary>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="r404c_import_redirects" />
                <?php wp_nonce_field('r404c_import_redirects', 'r404c_import_nonce'); ?>

                <p class="description">
                    <?php
                    printf(
                        /* translators: 1: CSV column list, 2: maximum file size */
                        esc_html__('Columns, in this order: %1$s. Only the source and target are required; the rest default to 301, exact, ignore and enabled. A header row is skipped automatically, and sources that already have a redirect are left untouched. Maximum file size %2$s.', 'auto-redirect-404s'),
                        '<code>source, target, code, match, query, enabled</code>',
                        esc_html(size_format(R404C_Admin::IMPORT_MAX_BYTES))
                    );
                    ?>
                </p>

                <p class="r404c-import-controls">
                    <input type="file" name="r404c_import_file" accept=".csv,text/csv" required />
                    <?php submit_button(__('Import', 'auto-redirect-404s'), 'secondary', 'r404c_import_submit', false); ?>
                </p>
            </form>
        </details>

        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
            <input type="hidden" name="page" value="<?php echo esc_attr(R404C_Admin::PAGE_SLUG); ?>" />
            <input type="hidden" name="tab" value="redirects" />
            <?php
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
            $r404c_status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
            if ('' !== $r404c_status) :
                ?>
                <input type="hidden" name="status" value="<?php echo esc_attr($r404c_status); ?>" />
            <?php endif; ?>
            <?php
            $redirects_table->views();
            $redirects_table->search_box(__('Search Redirects', 'auto-redirect-404s'), 'r404c-redirect-search');
            $redirects_table->display();
            ?>
        </form>
    </div>
</div>
