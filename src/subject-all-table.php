<?php

/**
 * List table for cron schedules.
 */

require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';

/**
 * Cron schedule list table class.
 */
class Subject_ALL_Table extends \WP_List_Table
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
        $source = !empty($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
        
        // Sorting
        $orderby = !empty($_GET['orderby']) ? sanitize_text_field($_GET['orderby']) : 'id';
        $order = !empty($_GET['order']) ? strtolower(sanitize_text_field($_GET['order'])) : 'desc';

        // Whitelist orderby
        $sortable = $this->get_sortable_columns();
        if (!array_key_exists($orderby, $sortable)) {
            $orderby = 'id';
        }

        // Validate order
        if (!in_array($order, ['asc', 'desc'])) {
            $order = 'desc';
        }

        // Map column names to table fields
        $order_field = $orderby;
        if ($orderby == 'name') {
            $order_field = 'm.name';
        } elseif ($orderby == 'douban_score') {
            $order_field = 'm.douban_score';
        } elseif ($orderby == 'create_time') {
            $order_field = 'm.create_time';
        } else {
            $order_field = 'm.' . $orderby;
        }

        $top250_id = $wpdb->get_var("SELECT id FROM $wpdb->douban_collection WHERE douban_id = 'movie_top250'");
        
        $query = "SELECT m.*" . ($top250_id ? ", r.collection_id as is_top250" : "") . " FROM $wpdb->douban_movies m";
        if ($top250_id) {
            $query .= " LEFT JOIN $wpdb->douban_relation r ON m.id = r.movie_id AND r.collection_id = $top250_id";
        }
        $query .= " WHERE 1=1";
        
        $params = [];

        if ($subject_type) {
            $query .= " AND m.type = %s";
            $params[] = $subject_type;
        }
        if ($search) {
            $query .= " AND m.name LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
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
            'total_items' => $this->get_subject_count($subject_type, $source),
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
            'name'         => array('name', false),
            'douban_score' => array('douban_score', false),
            'create_time'  => array('create_time', false),
            'id'           => array('id', true),
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

        $url = admin_url('admin.php?page=subject_all');

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

    protected function get_subject_count($type, $source = '')
    {
        global $wpdb;
        $filter = $type && $type != 'all' ? " AND m.type = '{$type}'" : '';
        $filter .= !empty($_GET['s']) ? " AND m.name LIKE '%{$_GET['s']}%'" : '';
        
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
        
        $subjects = $wpdb->get_results("SELECT m.id FROM $wpdb->douban_movies m  WHERE 1=1{$filter}");
        return count($subjects);
    }
    
    protected function get_source_count_for_filter($type, $source)
    {
        return $this->get_subject_count($type, $source);
    }

    public function views()
    {
        $views = $this->get_views();
        $source_views = $this->get_source_views();

        if (empty($views) && empty($source_views)) {
            return;
        }

        echo '<ul class="subsubsub">';
        if (!empty($views)) {
            echo '<li>' . implode(" |</li>\n<li>", $views) . '</li>';
        }
        echo '</ul>';
        
        if (!empty($source_views)) {
            echo '<ul class="subsubsub">';
            echo '<li>' . implode(" |</li>\n<li>", $source_views) . '</li>';
            echo '</ul>';
        }
    }
    
    protected function get_source_views()
    {
        $current_source = !empty($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
        $subject_type = !empty($_GET['subject_type']) ? sanitize_text_field($_GET['subject_type']) : 'all';
        
        $base_url = admin_url('admin.php?page=subject_all');
        if ($subject_type && $subject_type !== 'all') {
            $base_url = add_query_arg('subject_type', $subject_type, $base_url);
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
            
            $count = $this->get_source_count_for_filter($subject_type, $key);
            
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
                return $item->create_time ? wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($item->create_time)) : '';
            case 'genre':
                return $this->get_genres($item->id);

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

            default:
                return print_r($item, true);
        }
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

        global $wpdb;

        $fave = $wpdb->get_results("SELECT * FROM $wpdb->douban_faves WHERE `subject_id` = {$event->id}");

        $links = array();

        $link = array(
            'page'                  => 'subject_all',
            'wpn_action'       => !empty($fave) ? 'cancel_mark' : 'mark',
            'subject_id'           => rawurlencode($event->id),
            'subject_type'          => rawurlencode($event->type),
        );
        $link = add_query_arg($link, admin_url('admin.php'));
        $link = wp_nonce_url($link, "wpn_subject_{$event->id}");

        $links[] = "<a href='" . esc_url($link) . "'>" . (!empty($fave) ? '取消标记' : '标记') . "</a>";


        $link = array(
            'page'                  => 'subject_all',
            'wpn_action'       => 'sync_subject',
            'subject_id'           => rawurlencode($event->id),
            'subject_type'          => rawurlencode($event->type),
        );
        $link = add_query_arg($link, admin_url('admin.php'));
        $link = wp_nonce_url($link, "wpn_subject_{$event->id}");

        $links[] = "<a href='" . esc_url($link) . "'>同步条目</a>";

        $link = array(
            'page'                  => 'subject_edit',
            'subject_id'           => rawurlencode($event->id),
            'subject_type'          => rawurlencode($event->type),
            'action' => 'edit_subject'
        );
        $link = add_query_arg($link, admin_url('admin.php'));
        $link = wp_nonce_url($link, "wpn_subject_{$event->id}");

        $links[] = "<a href='" . esc_url($link) . "'>修改</a>";


        $link = array(
            'page'                  => 'subject_all',
            'subject_id'           => rawurlencode($event->id),
            'subject_type'          => rawurlencode($event->type),
            'wpn_action' => 'delete_subject'
        );
        $link = add_query_arg($link, admin_url('admin.php'));
        $link = wp_nonce_url($link, "wpn_subject_{$event->id}");

        $links[] = sprintf(
            "<a href='#' class='wpn-delete-subject' data-subject-id='%s' data-subject-name='%s' data-nonce='%s' data-fallback-url='%s'>删除</a>",
            esc_attr($event->id),
            esc_attr($event->name),
            wp_create_nonce('wpn_delete_subject_' . $event->id),
            esc_url($link)
        );

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
            'douban_score' => '评分',
            'genre' => '分类',
            'card_subtitle' => '描述',
            'tmdb_type' => '来源'
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
