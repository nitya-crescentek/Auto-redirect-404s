<?php
/**
 * 404 log list table.
 *
 * @package Redirect404Custom
 * @since 1.2.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the logged 404 URLs.
 */
class R404C_Logs_Table extends WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'r404c_log',
                'plural'   => 'r404c_logs',
                'ajax'     => false,
            )
        );
    }

    /**
     * Columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'         => '<input type="checkbox" />',
            'url'        => __('404 URL', 'auto-redirect-404s'),
            'hit_count'  => __('Hits', 'auto-redirect-404s'),
            'referrer'   => __('Last Referrer', 'auto-redirect-404s'),
            'first_seen' => __('First Seen', 'auto-redirect-404s'),
            'last_seen'  => __('Last Seen', 'auto-redirect-404s'),
        );
    }

    /**
     * Sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'url'        => array('url', false),
            'hit_count'  => array('hit_count', true),
            'first_seen' => array('first_seen', false),
            'last_seen'  => array('last_seen', true),
        );
    }

    /**
     * Bulk actions.
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'r404c_delete' => __('Delete', 'auto-redirect-404s'),
        );
    }

    /**
     * Message shown when there is nothing to list.
     */
    public function no_items() {
        esc_html_e('No 404 errors logged yet.', 'auto-redirect-404s');
    }

    /**
     * Load rows into the table.
     */
    public function prepare_items() {
        $this->_column_headers = array(
            $this->get_columns(),
            array(),
            $this->get_sortable_columns(),
        );

        $per_page = 20;
        $paged    = $this->get_pagenum();

        // Read-only list state; the destructive paths are nonce-checked in the
        // admin controller before this table is ever built.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $search  = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : 'last_seen';
        $order   = isset($_REQUEST['order']) ? sanitize_key(wp_unslash($_REQUEST['order'])) : 'desc';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $total = R404C_Logger::count_logs($search);

        $this->items = R404C_Logger::get_logs(
            array(
                'search'   => $search,
                'orderby'  => $orderby,
                'order'    => $order,
                'per_page' => $per_page,
                'paged'    => $paged,
            )
        );

        $this->set_pagination_args(
            array(
                'total_items' => $total,
                'per_page'    => $per_page,
                'total_pages' => (int) ceil($total / $per_page),
            )
        );
    }

    /**
     * Checkbox column.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="log_ids[]" value="%d" />',
            (int) $item->id
        );
    }

    /**
     * URL column, with row actions.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_url($item) {
        $full_url = home_url($item->url);

        $link = sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer"><code>%s</code></a>',
            esc_url($full_url),
            esc_html($item->url)
        );

        $delete_url = wp_nonce_url(
            add_query_arg(
                array(
                    'page'     => 'auto-redirect-404s',
                    'tab'      => 'logs',
                    'r404c_action' => 'delete_log',
                    'log_id'   => (int) $item->id,
                ),
                admin_url('options-general.php')
            ),
            'r404c_delete_log_' . (int) $item->id
        );

        $actions = array(
            'visit'  => sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url($full_url),
                esc_html__('Visit', 'auto-redirect-404s')
            ),
            'delete' => sprintf(
                '<a href="%s" class="submitdelete">%s</a>',
                esc_url($delete_url),
                esc_html__('Delete', 'auto-redirect-404s')
            ),
        );

        return $link . $this->row_actions($actions);
    }

    /**
     * Hit count column.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_hit_count($item) {
        return '<strong>' . esc_html(number_format_i18n((int) $item->hit_count)) . '</strong>';
    }

    /**
     * Referrer column.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_referrer($item) {
        if (empty($item->referrer)) {
            return '<span class="r404c-muted">' . esc_html__('(direct)', 'auto-redirect-404s') . '</span>';
        }

        $host = wp_parse_url($item->referrer, PHP_URL_HOST);
        $label = $host ? $host : $item->referrer;

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer nofollow" title="%s">%s</a>',
            esc_url($item->referrer),
            esc_attr($item->referrer),
            esc_html($label)
        );
    }

    /**
     * First seen column.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_first_seen($item) {
        return $this->format_date($item->first_seen);
    }

    /**
     * Last seen column.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_last_seen($item) {
        return $this->format_date($item->last_seen);
    }

    /**
     * Fallback column renderer.
     *
     * @param object $item        Row.
     * @param string $column_name Column key.
     * @return string
     */
    public function column_default($item, $column_name) {
        return isset($item->$column_name) ? esc_html($item->$column_name) : '';
    }

    /**
     * Render a stored datetime as "x ago" with the absolute value on hover.
     *
     * @param string $mysql_date Datetime in MySQL format, site timezone.
     * @return string
     */
    private function format_date($mysql_date) {
        $timestamp = mysql2date('U', $mysql_date, false);

        if (!$timestamp) {
            return '&mdash;';
        }

        $absolute = mysql2date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $mysql_date
        );

        $now = (int) current_time('timestamp');

        // Only show relative time for the recent past.
        if ($timestamp <= $now && ($now - $timestamp) < DAY_IN_SECONDS) {
            /* translators: %s: human readable time difference, e.g. "5 mins" */
            $relative = sprintf(__('%s ago', 'auto-redirect-404s'), human_time_diff($timestamp, $now));

            return sprintf(
                '<span title="%s">%s</span>',
                esc_attr($absolute),
                esc_html($relative)
            );
        }

        return esc_html($absolute);
    }
}
