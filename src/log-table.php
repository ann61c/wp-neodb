<?php

/**
 * List table for cron schedules.
 */

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Cron schedule list table class.
 */
class Log_Table extends \WP_List_Table
{

    /**
     * Array of cron event schedules that are added by WordPress core.
     *
     * @var array<int,string> Array of schedule names.
     */
    protected static $core_schedules;

    /**
     * Array of cron event schedule names that are in use by events.
     *
     * @var array<int,string> Array of schedule names.
     */
    protected static $used_schedules;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct(array(
            'singular' => 'wp-neodb',
            'plural'   => 'wp-neodbs',
            'ajax'     => false,
            'screen'   => 'wp-neodb',
        ));
    }

    /**
     * Gets the name of the primary column.
     *
     * @return string The name of the primary column.
     */
    protected function get_primary_column_name()
    {
        return 'type';
    }

    /**
     * Prepares the list table items and arguments.
     *
     * @return void
     */
    public function prepare_items()
    {
        global $wpdb;

        $currentPage = $this->get_pagenum();
        $offset = ($currentPage - 1) * 40;

        // Sorting
        $orderby = !empty($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'create_time';
        $order = !empty($_GET['order']) ? strtolower(sanitize_text_field($_GET['order'])) : 'desc';

        // Whitelist orderby
        $sortable = $this->get_sortable_columns();
        if (!array_key_exists($orderby, $sortable)) {
            $orderby = 'create_time';
        }

        // Validate order
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }

        $subjects = $wpdb->get_results("SELECT * FROM $wpdb->douban_log ORDER BY {$orderby} {$order} LIMIT 40 OFFSET {$offset}");

        $this->items = $subjects;

        $this->set_pagination_args(array(
            'total_items' => $this->get_subject_count(),
            'per_page'    => 40,
        ));
    }

    public function get_sortable_columns()
    {
        return array(
            'type'        => array('type', false),
            'action'      => array('action', false),
            'status'      => array('status', false),
            'create_time' => array('create_time', true),
            'account_id'  => array('account_id', false),
        );
    }


    protected function get_subject_count()
    {
        global $wpdb;
        $subjects = $wpdb->get_results("SELECT id FROM $wpdb->douban_log");
        return count($subjects);
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'type':
                $type_labels = [
                    'batch' => '批量',
                    'movie' => '电影',
                    'book' => '图书',
                    'music' => '音乐',
                    'game' => '游戏',
                    'drama' => '舞台剧',
                    'podcast' => '播客',
                ];
                return $type_labels[$item->type] ?? $item->type;
            case 'status':
            case 'message':
            case 'account_id':
                return $item->$column_name;
            case 'create_time':
                return wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->create_time));

            case 'source':
                $source_labels = [
                    'douban' => '豆瓣',
                    'tmdb' => 'TMDB',
                    'neodb' => 'NeoDB',
                ];
                return $source_labels[$item->source] ?? '豆瓣';

            case 'action':
                $action_labels = [
                    'sync' => '同步',
                    'embed' => '嵌入',
                ];
                return $action_labels[$item->action] ?? $item->action;

            default:
                return print_r($item, true);
        }
    }

    protected function extra_tablenav($which)
    {
        $link = array(
            'page'                  => 'log',
            'wpn_action'       => 'empty_log',
        );
        $link = add_query_arg($link, admin_url('admin.php'));
        $link = wp_nonce_url($link, "wpn_empty_log");


        printf(
            '<a href="%s" class="button">清空日志</a>',
            esc_url($link)
        );
    }

    protected function column_cb($event)
    {
        return '<input type="checkbox" name="crontrol_delete[%3$s][%4$s]" value="%5$s" id="%1$s">';
    }

    protected function handle_row_actions($event, $column_name, $primary)
    {
        if ($primary !== $column_name) {
            return '';
        }

        $links = array();


        return $this->row_actions($links);
    }

    /**
     * Returns an array of column names for the table.
     *
     * @return array<string,string> Array of column names keyed by their ID.
     */
    public function get_columns()
    {
        return array(
            'type'     => '类型',
            'source' => '来源',
            'action' => '操作',
            'status' => '状态',
            'message' => '备注',
            'create_time' => '时间',
            'account_id' => 'ID',
        );
    }
}
