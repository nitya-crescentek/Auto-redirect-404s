<?php
/**
 * Redirection Manager list table.
 *
 * @package Redirect404Custom
 * @since 1.3.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Renders the manual redirect rules.
 */
class R404C_Redirects_Table extends WP_List_Table {

    /**
     * Constructor.
     */
    public function __construct() {
        parent::__construct(
            array(
                'singular' => 'r404c_redirect',
                'plural'   => 'r404c_redirects',
                'ajax'     => false,
            )
        );
    }

    /**
     * Current status filter.
     *
     * @return string all, enabled or disabled.
     */
    private function get_status_filter() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view state.
        $status = isset($_REQUEST['status']) ? sanitize_key(wp_unslash($_REQUEST['status'])) : 'all';

        return in_array($status, array('enabled', 'disabled'), true) ? $status : 'all';
    }

    /**
     * Columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'          => '<input type="checkbox" />',
            'source_url'  => __('Source', 'auto-redirect-404s'),
            'target_url'  => __('Target', 'auto-redirect-404s'),
            'status_code' => __('Type', 'auto-redirect-404s'),
            'match_type'  => __('Match', 'auto-redirect-404s'),
            'hit_count'   => __('Hits', 'auto-redirect-404s'),
            'last_hit'    => __('Last Hit', 'auto-redirect-404s'),
        );
    }

    /**
     * Sortable columns.
     *
     * @return array
     */
    public function get_sortable_columns() {
        return array(
            'source_url'  => array('source_url', false),
            'target_url'  => array('target_url', false),
            'status_code' => array('status_code', false),
            'hit_count'   => array('hit_count', true),
            'last_hit'    => array('last_hit', true),
        );
    }

    /**
     * Bulk actions.
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'r404c_redirects_enable'  => __('Enable', 'auto-redirect-404s'),
            'r404c_redirects_disable' => __('Disable', 'auto-redirect-404s'),
            'r404c_redirects_reset'   => __('Reset Hits', 'auto-redirect-404s'),
            'r404c_redirects_delete'  => __('Delete', 'auto-redirect-404s'),
        );
    }

    /**
     * All / Enabled / Disabled links.
     *
     * @return array
     */
    protected function get_views() {
        $counts  = R404C_Redirects::counts();
        $current = $this->get_status_filter();
        $labels  = array(
            /* translators: %s: number of rules */
            'all'      => _n_noop('All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', 'auto-redirect-404s'),
            /* translators: %s: number of rules */
            'enabled'  => _n_noop('Enabled <span class="count">(%s)</span>', 'Enabled <span class="count">(%s)</span>', 'auto-redirect-404s'),
            /* translators: %s: number of rules */
            'disabled' => _n_noop('Disabled <span class="count">(%s)</span>', 'Disabled <span class="count">(%s)</span>', 'auto-redirect-404s'),
        );

        $views = array();

        foreach ($labels as $status => $label) {
            $url = add_query_arg(
                array(
                    'page'   => R404C_Admin::PAGE_SLUG,
                    'tab'    => 'redirects',
                    'status' => 'all' === $status ? false : $status,
                ),
                admin_url('admin.php')
            );

            $views[$status] = sprintf(
                '<a href="%s"%s>%s</a>',
                esc_url($url),
                $current === $status ? ' class="current" aria-current="page"' : '',
                sprintf(translate_nooped_plural($label, $counts[$status], 'auto-redirect-404s'), esc_html(number_format_i18n($counts[$status])))
            );
        }

        return $views;
    }

    /**
     * Message shown when there is nothing to list.
     */
    public function no_items() {
        esc_html_e('No redirects yet. Add your first one using the form above.', 'auto-redirect-404s');
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
        $status   = $this->get_status_filter();

        // Read-only list state; the destructive paths are nonce-checked in the
        // admin controller before this table is ever built.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $search  = isset($_REQUEST['s']) ? sanitize_text_field(wp_unslash($_REQUEST['s'])) : '';
        $orderby = isset($_REQUEST['orderby']) ? sanitize_key(wp_unslash($_REQUEST['orderby'])) : 'id';
        $order   = isset($_REQUEST['order']) ? sanitize_key(wp_unslash($_REQUEST['order'])) : 'desc';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $total = R404C_Redirects::count_rules($search, $status);

        $this->items = R404C_Redirects::get_rules(
            array(
                'search'   => $search,
                'status'   => $status,
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
     * Grey out disabled rules.
     *
     * @param object $item Row.
     */
    public function single_row($item) {
        echo $item->is_enabled ? '<tr>' : '<tr class="r404c-row-disabled">';
        $this->single_row_columns($item);
        echo '</tr>';
    }

    /**
     * Checkbox column.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_cb($item) {
        return sprintf(
            '<input type="checkbox" name="redirect_ids[]" value="%d" />',
            (int) $item->id
        );
    }

    /**
     * Source column, with row actions.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_source_url($item) {
        $id = (int) $item->id;

        $base = array(
            'page' => R404C_Admin::PAGE_SLUG,
            'tab'  => 'redirects',
        );

        $edit_url = add_query_arg($base + array('edit' => $id), admin_url('admin.php')) . '#r404c-redirect-form';

        $toggle_url = wp_nonce_url(
            add_query_arg($base + array('r404c_redirect_action' => 'toggle', 'redirect_id' => $id), admin_url('admin.php')),
            'r404c_toggle_redirect_' . $id
        );

        $delete_url = wp_nonce_url(
            add_query_arg($base + array('r404c_redirect_action' => 'delete', 'redirect_id' => $id), admin_url('admin.php')),
            'r404c_delete_redirect_' . $id
        );

        $output = sprintf(
            '<a href="%s" class="row-title"><code>%s</code></a>',
            esc_url($edit_url),
            esc_html($item->source_url)
        );

        if (!$item->is_enabled) {
            $output .= ' <span class="r404c-badge r404c-badge-muted">' . esc_html__('Disabled', 'auto-redirect-404s') . '</span>';
        }

        $actions = array(
            'edit'   => sprintf('<a href="%s">%s</a>', esc_url($edit_url), esc_html__('Edit', 'auto-redirect-404s')),
            'toggle' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($toggle_url),
                $item->is_enabled ? esc_html__('Disable', 'auto-redirect-404s') : esc_html__('Enable', 'auto-redirect-404s')
            ),
        );

        // Only an exact source is a real URL that can be opened.
        if ('exact' === $item->match_type) {
            $actions['test'] = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                esc_url(R404C_Redirects::resolve_target($item->source_url)),
                esc_html__('Test', 'auto-redirect-404s')
            );
        }

        $actions['delete'] = sprintf(
            '<a href="%s" class="submitdelete" onclick="return confirm(\'%s\');">%s</a>',
            esc_url($delete_url),
            esc_js(__('Delete this redirect?', 'auto-redirect-404s')),
            esc_html__('Delete', 'auto-redirect-404s')
        );

        return $output . $this->row_actions($actions);
    }

    /**
     * Target column.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_target_url($item) {
        if (410 === (int) $item->status_code) {
            return '<span class="r404c-muted">' . esc_html__('(no redirect, page is gone)', 'auto-redirect-404s') . '</span>';
        }

        $has_captures = 'exact' !== $item->match_type && preg_match('/\$\d/', $item->target_url);

        // A target containing $1 is a template, not a URL worth linking.
        if ($has_captures) {
            return '<code>' . esc_html($item->target_url) . '</code>';
        }

        return sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer"><code>%s</code></a>',
            esc_url(R404C_Redirects::resolve_target($item->target_url)),
            esc_html($item->target_url)
        );
    }

    /**
     * Redirect type column.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_status_code($item) {
        $code   = (int) $item->status_code;
        $labels = R404C_Redirects::status_codes();

        return sprintf(
            '<span class="r404c-badge r404c-badge-code-%1$d" title="%2$s">%1$d</span>',
            $code,
            esc_attr(isset($labels[$code]) ? $labels[$code] : '')
        );
    }

    /**
     * Match column: match type plus query string handling.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_match_type($item) {
        $types = R404C_Redirects::match_types();
        $modes = R404C_Redirects::query_modes();

        return sprintf(
            '<span class="r404c-badge r404c-badge-%s">%s</span><br /><small class="r404c-muted">%s</small>',
            esc_attr($item->match_type),
            esc_html(isset($types[$item->match_type]) ? $types[$item->match_type] : $item->match_type),
            esc_html(isset($modes[$item->query_mode]) ? $modes[$item->query_mode] : $item->query_mode)
        );
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
     * Last hit column.
     *
     * @param object $item Row.
     * @return string
     */
    public function column_last_hit($item) {
        if (empty($item->last_hit)) {
            return '<span class="r404c-muted">' . esc_html__('Never', 'auto-redirect-404s') . '</span>';
        }

        return R404C_Logs_Table::format_date($item->last_hit);
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
}
