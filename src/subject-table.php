<?php

/**
 * List table for cron schedules.
 */

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Cron schedule list table class.
 */
class Subject_List_Table extends \WP_List_Table
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
        return 'name';
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

        $subject_type = !empty($_GET['subject_type']) && $_GET['subject_type'] != 'all' ? sanitize_text_field($_GET['subject_type']) : '';
        $search = !empty($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = !empty($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $source = !empty($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
        
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

        // Map column names to table fields if necessary
        $order_field = $orderby;
        if ($orderby == 'create_time' || $orderby == 'status' || $orderby == 'score') {
            $order_field = 'f.' . $orderby;
        } elseif ($orderby == 'name') {
            $order_field = 'm.name';
        }

        $query = "SELECT m.*, f.create_time, f.remark, f.score, f.status FROM $wpdb->douban_movies m LEFT JOIN $wpdb->douban_faves f ON m.id = f.subject_id WHERE f.id IS NOT NULL";
        $params = [];

        if ($subject_type) {
            $query .= " AND f.type = %s";
            $params[] = $subject_type;
        }
        if ($search) {
            $query .= " AND m.name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }
        if ($status) {
            $query .= " AND f.status = %s";
            $params[] = $status;
        }
        
        // Source filter
        if ($source) {
            if ($source === 'douban') {
                $query .= " AND m.douban_id > 0";
            } elseif ($source === 'neodb') {
                $query .= " AND m.neodb_id != '' AND m.neodb_id IS NOT NULL";
            } elseif ($source === 'tmdb') {
                $query .= " AND m.tmdb_id > 0";
            }
        }



        $query .= " ORDER BY {$order_field} {$order} LIMIT 40 OFFSET {$offset}";

        $subjects = $wpdb->get_results($wpdb->prepare($query, $params));

        $this->items = $subjects;

        $this->set_pagination_args(array(
            'total_items' => $this->get_subject_count($subject_type, $status, $source),
            'per_page'    => 40,
        ));
    }

    /**
     * Gets the names of the sortable columns.
     *
     * @return array<string,array<int,string|bool>> Array of sortable columns.
     */
    public function get_sortable_columns()
    {
        return array(
            'name'        => array('name', false),
            'status'      => array('status', false),
            'score'       => array('score', false),
            'create_time' => array('create_time', true),
        );
    }

    public function get_views()
    {

        $views = array();
        $hooks_type = (!empty($_GET['subject_type']) ? $_GET['subject_type'] : 'all');

        $types = array(
            'all'      => '所有条目',
            'movie' => '电影',
            'book'     => '图书',
            'music'   => '音乐',
            'game'   => '游戏',
            'drama'   => '舞台剧',
            'podcast' => '播客',
        );

        /**
         * Filters the filter types on the cron event listing screen.
         *
         * See the corresponding `crontrol/filtered-events` filter to adjust the filtered events.
         *
         * @since 1.11.0
         *
         * @param string[] $types      Array of filter names keyed by filter name.
         * @param string   $hooks_type The current filter name.
         */
        $types = apply_filters('crontrol/filter-types', $types, $hooks_type);

        $url = admin_url('admin.php?page=subject');

        /**
         * @var array<string,string> $types
         */
        foreach ($types as $key => $type) {


            $link = ('all' === $key) ? $url : add_query_arg('subject_type', $key, $url);

            $views[$key] = sprintf(
                '<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
                esc_url($link),
                $hooks_type === $key ? ' class="current"' : '',
                esc_html($type),
                $this->get_subject_count($key)
            );
        }

        return $views;
    }

    protected function get_subject_count($type, $status = '', $source = '')
    {
        global $wpdb;
        $filter = $type && $type != 'all' ? " AND f.type = '{$type}'" : '';
        $filter .= !empty($_GET['s']) ? " AND m.name LIKE '%{$_GET['s']}%'" : '';
        $filter .= $status ? " AND f.status = '{$status}'" : "";
        
        // Source filter
        if ($source) {
            if ($source === 'douban') {
                $filter .= " AND m.douban_id > 0";
            } elseif ($source === 'neodb') {
                $filter .= " AND m.neodb_id != '' AND m.neodb_id IS NOT NULL";
            } elseif ($source === 'tmdb') {
                $filter .= " AND m.tmdb_id > 0";
            }
        }
        
        $subjects = $wpdb->get_results("SELECT f.id FROM $wpdb->douban_faves f LEFT JOIN $wpdb->douban_movies m ON f.subject_id = m.id WHERE 1=1{$filter}");
        return count($subjects);
    }
    
    protected function get_status_count($type, $status, $source = '')
    {
        return $this->get_subject_count($type, $status, $source);
    }
    
    protected function get_source_count($type, $status, $source)
    {
        return $this->get_subject_count($type, $status, $source);
    }

    // protected function extra_tablenav($which)
    // {
    //     wp_nonce_field('crontrol-export-event-csv', 'crontrol_nonce');
    //     printf(
    //         '<input type="hidden" name="crontrol_hooks_type" value="%s"/>',
    //         esc_attr(isset($_GET['crontrol_hooks_type']) ? sanitize_text_field(wp_unslash($_GET['crontrol_hooks_type'])) : 'all')
    //     );
    //     printf(
    //         '<input type="hidden" name="s" value="%s"/>',
    //         esc_attr(isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '')
    //     );
    //     printf(
    //         '<button class="button" type="submit" name="crontrol_action" value="export-event-csv">%s</button>',
    //         esc_html__('Export', 'wp-crontrol')
    //     );
    // }

    // private function table_data()
    // {
    //     return [];
    // }

    private function wpn_save_images($id, $url, $type = "")
    {
        $e = ABSPATH . 'douban_cache/' . $type . $id . '.jpg';
        if (!is_file($e)) {

            $referer = 'https://m.douban.com';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_REFERER, $referer); // 设置Referer头信息
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (iPhone; CPU iPhone OS 12_0 like Mac OS X) AppleWebKit/604.1.38 (KHTML, like Gecko) Version/12.0 Mobile/15A372 Safari/604.1');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $imageData = curl_exec($ch);
            curl_close($ch);
            file_put_contents($e, $imageData);
        }
        $url = home_url('/') . 'douban_cache/' . $type . $id . '.jpg';
        return $url;
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'genre':
                return $this->get_genres($item->id);
            case 'status':
                if ($item->status == 'done') {
                    return '已看';
                } else if ($item->status == 'mark') {
                    return '想看';
                } else if ($item->status == 'doing') {
                    return '在看';
                } else if ($item->status == 'dropped') {
                    return '不看了';
                }

            case 'poster':
                $cache_prefix = $item->neodb_id ? 'neodb_' : ($item->tmdb_type ? 'tmdb' : '');
                $cache_id = $item->neodb_id ? $item->neodb_id : $item->douban_id;
                return '<img src="' . $this->wpn_save_images($cache_id, $item->poster, $cache_prefix) . '" width="100" referrerpolicy="no-referrer">';
            case 'tmdb_type':
                $sources = [];
                if ($item->douban_id) $sources[] = '豆瓣';
                if ($item->neodb_id) $sources[] = 'NeoDB';
                if ($item->tmdb_id) $sources[] = 'TMDB';
                return implode(', ', $sources);
            case 'name':
                $out = $item->name;
                if (!empty($item->is_top250)) {
                    $out .= ' <span class="wpn-top250">Top250</span>';
                }
                return $out;
            case 'douban_score':
            case 'card_subtitle':
            case 'remark':
            case 'score':
                return $item->$column_name;
            case 'create_time':
                return wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->create_time));
            default:
                return print_r($item, true);
        }
    }

    public function views()
    {
        $views = $this->get_views();
        $status_views = $this->get_status_views();
        $source_views = $this->get_source_views();

        if (empty($views) && empty($status_views) && empty($source_views)) {
            return;
        }

        echo '<ul class="subsubsub">';
        if (!empty($views)) {
            echo '<li>' . implode(" |</li>\n<li>", $views) . '</li>';
        }
        echo '</ul>';
        
        if (!empty($status_views)) {
            echo '<ul class="subsubsub">';
            echo '<li>' . implode(" |</li>\n<li>", $status_views) . '</li>';
            echo '</ul>';
        }
        
        if (!empty($source_views)) {
            echo '<ul class="subsubsub">';
            echo '<li>' . implode(" |</li>\n<li>", $source_views) . '</li>';
            echo '</ul>';
        }
    }
    
    protected function get_status_views()
    {
        $current_status = !empty($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $current_source = !empty($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
        $subject_type = !empty($_GET['subject_type']) ? sanitize_text_field($_GET['subject_type']) : 'all';
        
        $base_url = admin_url('admin.php?page=subject');
        if ($subject_type && $subject_type !== 'all') {
            $base_url = add_query_arg('subject_type', $subject_type, $base_url);
        }
        if ($current_source) {
            $base_url = add_query_arg('source', $current_source, $base_url);
        }
        
        $status_filters = [
            '' => '所有状态',
            'mark' => '想看',
            'doing' => '在看',
            'done' => '已看',
            'dropped' => '不看了'
        ];
        
        $views = [];
        foreach ($status_filters as $key => $label) {
            $url = $base_url;
            if ($key) {
                $url = add_query_arg('status', $key, $url);
            }
            
            $count = $this->get_status_count($subject_type, $key, $current_source);
            
            $views[$key ?: 'all_status'] = sprintf(
                '<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
                esc_url($url),
                $current_status === $key ? ' class="current"' : '',
                esc_html($label),
                $count
            );
        }
        
        return $views;
    }
    
    protected function get_source_views()
    {
        $current_status = !empty($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $current_source = !empty($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
        $subject_type = !empty($_GET['subject_type']) ? sanitize_text_field($_GET['subject_type']) : 'all';
        
        $base_url = admin_url('admin.php?page=subject');
        if ($subject_type && $subject_type !== 'all') {
            $base_url = add_query_arg('subject_type', $subject_type, $base_url);
        }
        if ($current_status) {
            $base_url = add_query_arg('status', $current_status, $base_url);
        }
        
        $source_filters = [
            '' => '所有来源',
            'douban' => '豆瓣',
            'neodb' => 'NeoDB',
            'tmdb' => 'TMDB'
        ];
        
        $views = [];
        foreach ($source_filters as $key => $label) {
            $url = $base_url;
            if ($key) {
                $url = add_query_arg('source', $key, $url);
            }
            
            $count = $this->get_source_count($subject_type, $current_status, $key);
            
            $views[$key ?: 'all_source'] = sprintf(
                '<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
                esc_url($url),
                $current_source === $key ? ' class="current"' : '',
                esc_html($label),
                $count
            );
        }
        
        return $views;
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
        // $link = array(
        //     'page'                  => 'crontrol_admin_manage_page',
        //     'crontrol_action'       => 'run-cron',
        //     'crontrol_id'           => rawurlencode($event->hook),
        //     'crontrol_sig'          => rawurlencode($event->sig),
        //     'crontrol_next_run_utc' => rawurlencode($event->time),
        // );
        // $link = add_query_arg($link, admin_url('tools.php'));

        // $links[] = "<a href='" . esc_url($link) . "'>" . esc_html__('Edit', 'wp-crontrol') . '</a>';

        $link = array(
            'page'                  => 'subject',
            'wpn_action'       => 'cancel_mark',
            'subject_id'           => rawurlencode($event->id),
            'subject_type'          => rawurlencode($event->type),
        );
        $link = add_query_arg($link, admin_url('admin.php'));
        $link = wp_nonce_url($link, "wpn_subject_{$event->id}");

        $links[] = "<a href='" . esc_url($link) . "'>取消标记</a>";

        $link = array(
            'page'                  => 'subject_edit',
            'subject_id'           => rawurlencode($event->id),
            'subject_type'          => rawurlencode($event->type),
            'action' => 'edit_fave'
        );
        $link = add_query_arg($link, admin_url('admin.php'));
        $links[] = "<a href='" . esc_url($link) . "'>编辑</a>";

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
            'name'     => '标题',
            'poster' => '封面',
            'tmdb_type' => '来源',
            'douban_score' => '评分',
            'genre' => '分类',
            'card_subtitle' => '描述',
            'create_time' => '时间',
            'status' => '状态',
            'remark' => '我的短评',
            'score' => '我的评分'
        );
    }

    protected function get_genres($movie_id) {
        global $wpdb;
        $genres = $wpdb->get_results("SELECT name FROM $wpdb->douban_genres WHERE movie_id = $movie_id");
        $names = [];
        foreach ($genres as $g) {
            $names[] = $g->name;
        }
        return implode(', ', $names);
    }
}
