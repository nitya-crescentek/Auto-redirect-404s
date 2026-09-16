<?php
/**
 * Uninstall routine.
 *
 * Runs only when the administrator deletes the plugin, never on deactivation.
 * Removes the options and the log table so nothing is left behind.
 *
 * @package Redirect404Custom
 * @since 1.2.0
 */

// Prevent direct access; WP_UNINSTALL_PLUGIN is only defined by WordPress.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Delete this plugin's data for the current site.
 */
function r404c_uninstall_site() {
    global $wpdb;

    wp_clear_scheduled_hook('r404c_check_destination');

    $options = array(
        'r404c_enabled',
        'r404c_redirect_url',
        'r404c_redirect_type',
        'r404c_logging_enabled',
        'r404c_loop_protection',
        'r404c_skip_assets',
        'r404c_show_top_widget',
        'r404c_exclusion_patterns',
        'r404c_version',
        'r404c_db_version',
    );

    foreach ($options as $option) {
        delete_option($option);
    }

    // A table name is an identifier, so prepare() cannot parameterise it.
    // esc_sql() on a value built purely from $wpdb->prefix and a literal.
    $table = esc_sql($wpdb->prefix . 'r404c_logs');

    // Dropping the plugin's own table is the whole point of an uninstall
    // routine, so the schema-change warning is expected here.
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
    // phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query("DROP TABLE IF EXISTS {$table}");

    // Loop-protection results are cached as transients keyed by destination.
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '\_transient\_r404c\_dest\_%'
            OR option_name LIKE '\_transient\_timeout\_r404c\_dest\_%'"
    );
    // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
}

if (is_multisite()) {
    $r404c_site_ids = get_sites(
        array(
            'fields'        => 'ids',
            'number'        => 0,
            'no_found_rows' => true,
        )
    );

    foreach ($r404c_site_ids as $r404c_site_id) {
        switch_to_blog($r404c_site_id);
        r404c_uninstall_site();
        restore_current_blog();
    }

    unset($r404c_site_ids, $r404c_site_id);
} else {
    r404c_uninstall_site();
}
