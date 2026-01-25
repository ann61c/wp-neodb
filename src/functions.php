<?php
/***
 * Core Class
 */
class WPN_NeoDB
{
    const VERSION = '4.4.5';
    private $base_url = 'https://fatesinger.com/dbapi/';
    private $perpage = 70;
    private $uid;
    private static $genre_mapping_cache;
    private static $site_mapping_cache;

    public function __construct()
    {
        $this->perpage = $this->db_get_setting('perpage') ?: 70;
        $plugin_file = plugin_basename(WPN_PATH . '/wp-neodb.php');

        if (!$this->db_get_setting('disable_scripts')) {
            add_action('wp_enqueue_scripts', [$this, 'wpn_load_scripts']);
        }
        
        // Unified Douban handler - matches all Douban URL patterns
        wp_embed_register_handler('douban', '#https?://(?:(\w+)\.douban\.com/subject/(\d+)|www\.douban\.com/(?:location/)?(\w+)/(\d+))#i', [$this, 'wp_embed_handler_douban']);
        
        wp_embed_register_handler('themoviedb', '#https?:\/\/www\.themoviedb\.org\/(\w+)\/(\d+)#i', [$this, 'wp_embed_handler_the_movie_db']);
        wp_embed_register_handler('neodb', '#https?://neodb\.social/(book|movie|tv(?:/season)?|album|game|podcast|performance(?:/production)?)/([a-zA-Z0-9]+)/?#i', [$this, 'wp_embed_handler_neodb']);

        add_action('rest_api_init', [$this, 'wpn_register_rest_routes']);
        add_filter("plugin_action_links_{$plugin_file}", [$this, 'plugin_action_links'], 10, 4);
        add_shortcode('wpd', [$this, 'list_shortcode']);
        add_shortcode('wpc', [$this, 'list_collection']);
        add_action('wp_head', [$this, 'db_custom_style']);
    }

    protected function get_genre_mapping()
    {
        if (self::$genre_mapping_cache === null) {
            self::$genre_mapping_cache = [
                'Animation' => '动画',
                'Sci-Fi' => '科幻',
                'Mystery' => '悬疑',
                'Action' => '动作',
                'Comedy' => '喜剧',
                'Romance' => '爱情',
                'Thriller' => '惊悚',
                'Crime' => '犯罪',
                'Adventure' => '冒险',
                'Fantasy' => '奇幻',
                'Drama' => '剧情',
                'Horror' => '恐怖',
                'War' => '战争',
                'Documentary' => '纪录片',
                'Biography' => '传记',
                'History' => '历史',
                'Family' => '家庭',
                'Musical' => '音乐',
                'Sport' => '运动',
                'Western' => '西部',
                'Suspense' => '悬疑',
            ];
        }
        return self::$genre_mapping_cache;
    }

    protected function get_site_mapping()
    {
        if (self::$site_mapping_cache === null) {
            self::$site_mapping_cache = [
                'douban.com' => ['name' => '豆瓣', 'class' => 'douban'],
                'themoviedb.org' => ['name' => 'TMDB', 'class' => 'tmdb'],
                'imdb.com' => ['name' => 'IMDb', 'class' => 'imdb'],
                'wikidata.org' => ['name' => '维基数据', 'class' => 'wikidata'],
                'spotify.com' => ['name' => 'Spotify', 'class' => 'spotify'],
                'goodreads.com' => ['name' => 'Goodreads', 'class' => 'goodreads'],
                'steampowered.com' => ['name' => 'Steam', 'class' => 'steam'],
                'steamcommunity.com' => ['name' => 'Steam', 'class' => 'steam'],
                'igdb.com' => ['name' => 'IGDB', 'class' => 'igdb'],
                'bangumi.tv' => ['name' => 'Bangumi', 'class' => 'bangumi'],
                'bgm.tv' => ['name' => 'Bangumi', 'class' => 'bangumi'],
                'archiveofourown.org' => ['name' => 'AO3', 'class' => 'ao3'],
                'qidian.com' => ['name' => '起点中文网', 'class' => 'qidian'],
                'jjwxc.net' => ['name' => '晋江文学城', 'class' => 'jjwxc'],
                'boardgamegeek.com' => ['name' => 'BGG', 'class' => 'bgg'],
                'books.com.tw' => ['name' => '博客来', 'class' => 'bookstw'],
                'books.google' => ['name' => 'Google Books', 'class' => 'googlebooks'],
                'bandcamp.com' => ['name' => 'Bandcamp', 'class' => 'bandcamp'],
                'discogs.com' => ['name' => 'Discogs', 'class' => 'discogs'],
                'musicbrainz.org' => ['name' => 'MusicBrainz', 'class' => 'musicbrainz'],
                'openlibrary.org' => ['name' => 'Open Library', 'class' => 'openlibrary'],
                'music.apple.com' => ['name' => 'Apple Music', 'class' => 'apple_music'],
                'xiaoyuzhoufm.com' => ['name' => '小宇宙', 'class' => 'rss'],
                '/^https?:\/\/feed\./i' => ['name' => 'RSS', 'class' => 'rss'],
                'neodb.' => ['name' => 'NeoDB', 'class' => 'fedi'],
                'minreol.dk' => ['name' => 'minreol.dk', 'class' => 'fedi'],
                'eggplant.place' => ['name' => 'eggplant.place', 'class' => 'fedi'],
                'fantastika.social' => ['name' => 'fantastika.social', 'class' => 'fedi'],
            ];
        }
        return self::$site_mapping_cache;
    }

    public function add_log($type = 'movie', $action = 'sync', $source = 'douban', $message = '')
    {
        global $wpdb;
        if (empty($message)) {
            $message = $action . ' success';
        }
        $wpdb->insert($wpdb->douban_log, [
            'type' => $type,
            'action' => $action,
            'source' => $source,
            'create_time' => date('Y-m-d H:i:s'),
            'status' => 'success',
            'message' => $message,
            'account_id' => $this->uid
        ]);
    }

    public function db_custom_style()
    {
        if ($this->db_get_setting('css')) {
            echo  '<style>' . $this->db_get_setting('css') . '</style>';
        }
    }

    public function list_shortcode($atts, $content = null)
    {
        extract(shortcode_atts(
            [
                'types' => '',
                'style' => ''
            ],
            $atts
        ));
        $types = explode(',', $types);
        if ($types === []) {
            return;
        }
        return $this->render_template($types, $style);
    }

    public function list_collection($atts, $content = null)
    {
        extract(shortcode_atts(
            [
                'type' => '',
                'start' => '',
                'end' => '',
                'status' => '',
                'style' => ''
            ],
            $atts
        ));
        return $this->render_collection($type, $start, $end, $status, $style);
    }

    function plugin_action_links($actions, $plugin_file, $plugin_data, $context)
    {
        $new = [
            'crontrol-events'    => sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=wpneodb')),
                '设置'
            ),
            'crontrol-schedules' => sprintf(
                '<a href="%s">%s</a>',
                esc_url(admin_url('admin.php?page=subject')),
                '条目'
            ),
            'crontrol-help' => sprintf(
                '<a href="%s" target="_blank">%s</a>',
                'https://blog.lyc.sh/wp-neodb',
                '帮助'
            ),
        ];

        return array_merge($new, $actions);
    }

    public function db_get_setting($key = NULL)
    {
        $setting = get_option('db_setting');
        if (isset($setting[$key])) {
            return $setting[$key];
        } else {
            return false;
        }
    }

    public function render_collection($type, $start, $end, $status, $style)
    {
        return '<div class="db--collection" data-status="' . $status . '" data-type="' . $type . '" data-start="' . $start . '" data-end="' . $end . '" ' . ($style ? 'data-style="' . $style . '"' : '') . '></div>
        ';
    }

    public function render_template($include_types = ['movie', 'music', 'book', 'game', 'drama'], $style = null)
    {
        $valid_types = ['movie', 'music', 'book', 'game', 'drama'];
        
        // Normalize and filter input types, strictly following input order
        $include_types = array_values(array_intersect(
            array_unique(array_map('trim', $include_types)), 
            $valid_types
        ));

        $nav = '';
        foreach ($include_types as $i => $type) {
            $nav .= '<div class="db--navItem JiEun' . ($i === 0 ? ' current' : '') . '" data-type="' . $type . '">' . $type . '</div>';
        }

        if (count($include_types) === 1) {
            $nav = '';
        }
        $only = count($include_types) === 1 ? " data-type='{$include_types[0]}'" : '';
        $show_type = $this->db_get_setting("show_type") ? '<div class="db--type">
        <div class="db--typeItem" data-status="mark">想看</div>
        <div class="db--typeItem" data-status="doing">在看</div>
        <div class="db--typeItem is-active" data-status="done">看过</div>
        <div class="db--typeItem" data-status="dropped">不看了</div>
    </div>' : '';
        return '<section class="db--container"><nav class="db--nav">' . $nav . '
    </nav>
    <div class="db--genres u-hide">
    </div>
    ' . $show_type . '
    <div class="db--list' . ($style ? ' db--list__' . $style  : '') . '"' . $only . '>
    </div>
    <div class="block-more block-more__centered">
        <div class="lds-ripple">
        </div>
    </div><div class="db--copyright">Rendered by <a href="https://blog.lyc.sh/wp-neodb" target="_blank">WP-NeoDB</a></div></section>';
    }

    public function wpn_register_rest_routes()
    {
        register_rest_route('v1', '/movies', [
            'methods' => 'GET',
            'callback' => [$this, 'get_subjects'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('v1', '/movie/genres', [
            'methods' => 'GET',
            'callback' => [$this, 'get_genres'],
            'permission_callback' => '__return_true',
        ]);

        // AJAX data preview endpoint
        register_rest_route('wpn/v1', '/preview-source', [
            'methods' => 'POST',
            'callback' => [$this, 'preview_source_data'],
            'permission_callback' => fn() => current_user_can('edit_posts')
        ]);
    }

    public function get_genres($data)
    {
        $type = $data['type'] ?: 'movie';
        global $wpdb;
        $goods = $wpdb->get_results("SELECT name FROM $wpdb->douban_genres WHERE `type` = '{$type}' GROUP BY `name`");
        $data = [];
        foreach ($goods as $good) {
            $data[] = $good;
        }
        return new WP_REST_Response($data);
    }

    public function get_collection($douban_id)
    {
        global $wpdb;
        $collection = $wpdb->get_row("SELECT * FROM $wpdb->douban_collection WHERE `douban_id` = '{$douban_id}'");
        if (!$collection) {
            return false;
        } else {
            return $collection;
        }
    }

    public function get_subjects($data)
    {
        global $wpdb;
        $offset = $data['paged'] ? ($data['paged'] - 1) * $this->perpage : 0;
        $type = $data['type'] ?: 'movie';
        $status = $data['status'] ?: 'done';
        $genre = $data['genre'] ? implode("','", json_decode($data['genre'], true)) : '';
        $endtime = $data['end_time'] ?: date('Y-m-d');
        $filterTime = ($data['start_time']) ? " AND f.create_time BETWEEN '{$data['start_time']}' AND '{$endtime}'" : '';
        $type == 'book' ? $this->get_collection('book_top250') : $this->get_collection('movie_top250');

        if ($genre !== '' && $genre !== '0') {
            $goods = $wpdb->get_results("SELECT m.*, f.create_time , f.remark , f.status FROM ( $wpdb->douban_movies m LEFT JOIN $wpdb->douban_genres g ON m.id = g.movie_id ) LEFT JOIN $wpdb->douban_faves f ON m.id = f.subject_id WHERE f.type = '{$type}' AND f.status = '{$status}' AND g.name IN ('{$genre}') GROUP BY m.id ORDER BY f.create_time DESC LIMIT {$this->perpage} OFFSET {$offset}");
        } else {
            $goods = $wpdb->get_results("SELECT m.*, f.create_time, f.remark, f.status FROM $wpdb->douban_movies m LEFT JOIN $wpdb->douban_faves f ON m.id = f.subject_id WHERE f.type = '{$type}' AND f.status = '{$status}' {$filterTime} ORDER BY f.create_time DESC LIMIT {$this->perpage} OFFSET {$offset}");
        }

        $movie_ids = array_column($goods, 'id');
        $genres_by_movie = [];
        if ($movie_ids !== []) {
            $ids_placeholder = implode(',', array_map('intval', $movie_ids));
            $all_genres = $wpdb->get_results(
                "SELECT movie_id, name FROM $wpdb->douban_genres WHERE movie_id IN ($ids_placeholder)"
            );
            foreach ($all_genres as $genre_row) {
                $genres_by_movie[$genre_row->movie_id][] = $genre_row->name;
            }
        }

        // Note: faves data is already joined in the main query above, so no extra query needed

        $data = [];
        foreach ($goods as $good) {
            $good->genres = $genres_by_movie[$good->id] ?? [];
            $good = $this->populate_db_movie_metadata($good);
            $good->create_time = date('Y-m-d', strtotime($good->create_time));
            
            // Generate HTML for frontend
            // Determine cover image URL logic consistent with get_subject_detail
            $cover = $this->db_get_setting('download_image') 
                ? $this->wpn_save_images($good->id, $good->poster, 'douban') 
                : (in_array($good->type, ['movie', 'book', 'music']) && !empty($good->douban_id) 
                    ? "https://dou.img.lithub.cc/" . $good->type . "/" . $good->douban_id . ".jpg" 
                    : $good->poster);
            
            // Use TMDB/NeoDB cache prefix if needed (logic from get_poster_cache_info but simplified here for consistency)
             if ($this->db_get_setting('download_image')) {
                 [$cache_id, $cache_prefix] = $this->get_poster_cache_info($good);
                 $cover = $this->wpn_save_images($cache_id, $good->poster, $cache_prefix);
             }

            $good->html = $this->render_enhanced_item_html($good, $cover);
            
            $data[] = $good;
        }
        return new WP_REST_Response($data);
    }

    /**
     * Unified HTML rendering function for NeoDB-style display
     * Used by get_the_movie_db_detail, get_subject_detail, and get_neodb_subject_detail
     *
     * @param object $data Subject data with metadata
     * @param string $cover Cover image URL
     * @return string HTML output
     */
    private function render_enhanced_item_html($data, $cover)
    {
        $output = '<div class="doulist-item"><div class="doulist-subject"><div class="doulist-post"><img referrerpolicy="no-referrer" src="' . esc_attr($cover) . '"></div>';

        // Meta items (Top 250, marked date, etc.) - NeoDB style (JiEun class used in original plugin)
        $meta_items = [];
        if (!empty($data->is_top250)) {
            $meta_items[] = 'Top 250';
        }
        if ($this->db_get_setting("show_remark") && !empty($data->fav_time)) {
            $status_label = 'Marked';
            if (isset($data->status)) {
                $status_map = ['mark' => '想看', 'doing' => '在看', 'done' => '看过', 'dropped' => '不看了'];
                $status_label = $status_map[$data->status] ?? 'Marked';
            }
            $score_text = empty($data->score) ? '' : ' ' . $data->score . '分';
            $meta_items[] = date('Y-m-d', strtotime($data->fav_time)) . ' ' . $status_label . $score_text;
        }

        if ($meta_items !== []) {
            $output .= '<div class="db--viewTime JiEun">' . implode(' · ', $meta_items) . '</div>';
        }

        $output .= '<div class="doulist-content">';
        
        // Title header: Name (Year) [Type] + Badges
        $output .= '<div class="doulist-title-header">';
        $output .= '<a href="' . esc_url($data->link) . '" class="doulist-title cute" target="_blank" rel="external nofollow">' . esc_html($data->name) . '</a>';
        
        if (!empty($data->year)) {
            $output .= ' <span class="doulist-year">(' . esc_html($data->year) . ')</span>';
        }

        if (!empty($data->type)) {
            $type_map = ['movie' => '影视', 'book' => '书籍', 'music' => '音乐', 'game' => '游戏', 'drama' => '戏剧', 'tv' => '剧集', 'podcast' => '播客'];
            $output .= ' <span class="doulist-category">[' . ($type_map[$data->type] ?? $data->type) . ']</span>';
        }
        
        // External links badges inline inside site-list
        $external_links = $this->parse_external_resources($data);
        if (!empty($external_links)) {
            $output .= '<span class="site-list">';
            foreach ($external_links as $link) {
                $output .= ' <a href="' . esc_url($link['url']) . '" class="' . esc_attr($link['class']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($link['name']) . '</a>';
            }
            $output .= '</span>';
        }
        $output .= '</div>';

        // Subtitle line (Original Title)
        if (!empty($data->orig_title) && $data->orig_title !== $data->name) {
            $output .= '<div class="doulist-subtitle">' . esc_html($data->orig_title) . '</div>';
        }

        // Metadata line: Rating / Type / Director / Actor
        $meta_line = [];
        
        // Add rating
        if (!empty($data->douban_score) && $data->douban_score > 0) {
            $meta_line[] = '<span class="rating-score">' . esc_html($data->douban_score) . '</span>';
        }
        
        // Get genres directly from database
        if (!empty($data->id)) {
            global $wpdb;
            $genres = $wpdb->get_results(
                $wpdb->prepare("SELECT name FROM {$wpdb->douban_genres} WHERE movie_id = %d LIMIT 3", $data->id)
            );
            if (!empty($genres)) {
                $genre_names = array_map(fn($g) => $g->name, $genres);
                $meta_line[] = '类型: ' . esc_html(implode(' / ', $genre_names));
            }
        }

        // Type-specific metadata
        if ($data->type === 'book') {
            // Book: Author / Translator / Publisher / Pub Date
            $author = $this->parse_json_field($data, 'director'); // 'director' stores author for books
            if (!empty($author)) {
                $meta_line[] = '作者: ' . esc_html($this->format_metadata_names($author, 3));
            }
            $translator = $this->parse_json_field($data, 'actor'); // 'actor' stores translator for books
            if (!empty($translator)) {
                $meta_line[] = '译者: ' . esc_html($this->format_metadata_names($translator, 2));
            }
            // Publisher
            if (!empty($data->pub_house)) {
                $meta_line[] = esc_html($data->pub_house);
            }
            // Publication date
            if (!empty($data->pubdate)) {
                $meta_line[] = esc_html($data->pubdate);
            }
        } elseif ($data->type === 'game') {
            // Game: Developer / Publisher / Platform
            $developer = $this->parse_json_field($data, 'director');
            if (!empty($developer)) {
                $meta_line[] = '开发者: ' . esc_html($this->format_metadata_names($developer, 2));
            }
            $platform = $this->parse_json_field($data, 'actor');
            if (!empty($platform)) {
                $meta_line[] = '平台: ' . esc_html($this->format_metadata_names($platform, 5));
            }
        } else {
            // Movie/TV/Podcast/Drama: Director / Actor
            $director_label = ($data->type === 'music') ? '艺术家' : (($data->type === 'podcast') ? '主持人' : '导演');
            $actor_label = ($data->type === 'music') ? '公司' : (($data->type === 'podcast') ? '制作人' : '演员');
            
            $director = $this->parse_json_field($data, 'director');
            if (!empty($director)) {
                $meta_line[] = $director_label . ': ' . esc_html($this->format_metadata_names($director, 2));
            }
            $actor = $this->parse_json_field($data, 'actor');
            if (!empty($actor)) {
                $meta_line[] = $actor_label . ': ' . esc_html($this->format_metadata_names($actor, 3));
            }
        }
        
        if ($meta_line !== []) {
            $output .= '<div class="doulist-meta-line">' . implode(' / ', $meta_line) . '</div>';
        }

        // Abstract/Description
        $abstract = $this->db_get_setting("show_remark") && !empty($data->remark) ? $data->remark : $data->card_subtitle;
        if (!empty($abstract)) {
            $output .= '<div class="abstract">' . esc_html($abstract) . '</div>';
        }
        return $output . '</div></div></div>';
    }

    /**
     * Parse JSON field from data object
     */
    private function parse_json_field($data, $field)
    {
        if (!isset($data->$field) || empty($data->$field)) {
            return [];
        }
        $value = $data->$field;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    }

    /**
     * Format metadata array (extract names from objects if necessary)
     */
    private function format_metadata_names($items, $limit = 3)
    {
        if (empty($items) || !is_array($items)) {
            return '';
        }
        $names = array_map(fn($item) => is_array($item) ? ($item['name'] ?? 'Unknown') : $item, array_slice($items, 0, $limit));
        return implode(' / ', $names);
    }

    /**
     * Parse external resources from data and return formatted links
     */
    private function parse_external_resources($data)
    {
        $links = [];
        
        // Parse from external_resources field
        $resources = $this->parse_json_field($data, 'external_resources');
        $site_map = $this->get_site_mapping();
        
        foreach ($resources as $resource) {
            if (empty($resource['url'])) {
                continue;
            }
            $url = $resource['url'];
            $matched = false;
            
            foreach ($site_map as $key => $site_info) {
                // If key starts with /, treat as regex
                if (str_starts_with($key, '/')) {
                    if (preg_match($key, $url)) {
                        $links[] = ['url' => $url, 'name' => $site_info['name'], 'class' => $site_info['class']];
                        $matched = true;
                        break;
                    }
                } elseif (str_contains($url, $key)) {
                    // Treat as string containment
                    $links[] = ['url' => $url, 'name' => $site_info['name'], 'class' => $site_info['class']];
                    $matched = true;
                    break;
                }
            }
            
            if (!$matched) {
                // For unrecognized URLs, extract hostname as name
                $parsed = parse_url($url);
                $hostname = $parsed['host'] ?? 'Link';
                // Remove 'www.' prefix if present
                $display_name = preg_replace('/^www\\./', '', $hostname);
                $links[] = ['url' => $url, 'name' => $display_name, 'class' => 'external'];
            }
        }

        // Fallback: generate links from IDs if no external_resources
        if ($links === []) {
            if (!empty($data->douban_id)) {
                $douban_type = $data->type === 'book' ? 'book' : 'movie';
                $links[] = ['url' => "https://{$douban_type}.douban.com/subject/{$data->douban_id}/", 'name' => '豆瓣', 'class' => 'douban'];
            }
            if (!empty($data->tmdb_id)) {
                $tmdb_type = $data->tmdb_type ?: 'movie';
                $links[] = ['url' => "https://www.themoviedb.org/{$tmdb_type}/{$data->tmdb_id}", 'name' => 'TMDB', 'class' => 'tmdb'];
            }
            if (!empty($data->neodb_id)) {
                $links[] = ['url' => "https://neodb.social/{$data->type}/{$data->neodb_id}", 'name' => 'NeoDB', 'class' => 'fedi'];
            }
        }

        return $links;
    }
    function wp_embed_handler_the_movie_db($matches, $attr, $url, $rawattr)
    {
        if ((!is_singular() && !$this->db_get_setting('home_render')) || !$this->db_get_setting('api_key')) {
            return $url;
        }
        $type = $matches[1];
        $id = $matches[2];
        if (!in_array($type, ['tv', 'movie'])) {
            return $url;
        }
        $html = $this->get_the_movie_db_detail($id, $type, $url);
        return apply_filters('embed_forbes', $html, $matches, $attr, $url, $rawattr);
    }

    /**
     * Unified Douban handler for all Douban URL patterns
     * Handles: movie/book/music.douban.com/subject/ID, www.douban.com/game/ID, www.douban.com/location/drama/ID
     */
    function wp_embed_handler_douban($matches, $attr, $url, $rawattr)
    {
        if (!is_singular() && !$this->db_get_setting('home_render')) {
            return $url;
        }
        
        // Parse matches based on URL pattern
        // Pattern 1: movie.douban.com/subject/123 -> matches[1]=type, matches[2]=id
        // Pattern 2: www.douban.com/game/123 -> matches[3]=type, matches[4]=id
        // Pattern 2b: www.douban.com/location/drama/123 -> matches[3]=type, matches[4]=id
        
        $type = empty($matches[1]) ? $matches[3] : $matches[1];
        $id = empty($matches[2]) ? $matches[4] : $matches[2];
        
        $html = $this->get_subject_detail($id, $type, $url);
        return apply_filters('embed_forbes', $html, $matches, $attr, $url, $rawattr);
    }

    public function get_the_movie_db_detail($id, $type, $embed_url = null)
    {
        $type = $type ?: 'movie';
        $data = $this->fetch_tmdb_subject($id, $type);
        if (!$data) {
            return null;
        }
        
        // Override link with embed URL if provided
        if ($embed_url) {
            $data->link = $embed_url;
        }
        
        // Ensure link has fallback
        if (empty($data->link)) {
            $data->link = "https://www.themoviedb.org/{$type}/{$id}";
        }
        
        $cover = $this->db_get_setting('download_image') ? $this->wpn_save_images($id, $data->poster, 'tmdb_') : $data->poster;
        return $this->render_enhanced_item_html($data, $cover);
    }

    public function get_subject_detail($id, $type, $embed_url = null)
    {
        $type = $type ?: 'movie';
        $data = $this->fetch_subject($id, $type);
        if (!$data) {
            return null;
        }
        
        // Override link with embed URL if provided
        if ($embed_url) {
            $data->link = $embed_url;
        }
        
        $cover = $this->db_get_setting('download_image') ? $this->wpn_save_images($id, $data->poster, 'douban') : (in_array($data->type, ['movie', 'book', 'music']) ? "https://dou.img.lithub.cc/" . $type . "/" . $data->douban_id . ".jpg" : $data->poster);
        return $this->render_enhanced_item_html($data, $cover);
    }

    public function sync_subject($id, $type)
    {
        $type = $type ?: 'movie';
        global $wpdb;
        $movie = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE `type` = '{$type}' AND id = '{$id}'");
        if (empty($movie)) {
            return false;
        }

        // NOTE: Check NeoDB BEFORE TMDB because NeoDB items may have extracted TMDB IDs
        // We want to sync from NeoDB API, not TMDB API, for NeoDB items

        // Handle NeoDB items
        if ($movie->neodb_id) {
            $neodb_url = $this->db_get_setting('neodb_url') ?: 'https://neodb.social';
            $api_url = rtrim($neodb_url, '/') . "/api/" . $movie->type . "/" . $movie->neodb_id;

            $headers = [];
            if ($this->db_get_setting('neodb_token')) {
                $headers['Authorization'] = 'Bearer ' . $this->db_get_setting('neodb_token');
            }

            $response = wp_remote_get($api_url, ['headers' => $headers, 'sslverify' => false]);
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $this->add_log($movie->type, 'sync', 'neodb', "API Error: {$error_message}");
                return false;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            
            // Debug logging
            $this->add_log($movie->type, 'sync', 'neodb', "API Response received for {$movie->neodb_id}");
            
            if (!$data || !is_array($data)) {
                $this->add_log($movie->type, 'sync', 'neodb', "Empty/Invalid API response");
                return false;
            }
            $description = $data['description'] ?? '';
            // Extract external IDs (Douban ID, TMDB ID)
            $external_ids = $this->extract_external_ids_from_neodb($data);
            // Build update data - only include non-empty values to avoid overwriting existing data
            $update_data = [
                'douban_score' => $data['rating'] ?? 0,
            ];
            // Only update if we have a title
            if (!empty($data['display_title']) || !empty($data['title'])) {
                $update_data['name'] = $data['display_title'] ?? $data['title'];
            }
            // Only update poster if we have a URL
            if (!empty($data['cover_image_url'])) {
                $update_data['poster'] = $data['cover_image_url'];
            }
            // Only update link if we have a URL
            if (!empty($data['url'])) {
                $update_data['link'] = $data['url'];
            }
            // Only update description if not empty
            if (!empty($description)) {
                $update_data['card_subtitle'] = $description;
            }
            // Only update external IDs if we found them (don't overwrite with 0)
            if ($external_ids['douban_id'] > 0) {
                $update_data['douban_id'] = $external_ids['douban_id'];
            }
            if ($external_ids['tmdb_id'] > 0) {
                $update_data['tmdb_id'] = $external_ids['tmdb_id'];
                $update_data['tmdb_type'] = $external_ids['tmdb_type'];
            }
            // Update metadata fields for NeoDB-style display
            // Map generic fields from type-specific NeoDB fields
            $director = $data['director'] ?? $data['directors'] ?? null;
            $actor = $data['actor'] ?? $data['actors'] ?? null;
            
            if ($movie->type === 'book') {
                $director = $data['author'] ?? $data['authors'] ?? $director;
                $actor = $data['translator'] ?? $data['translators'] ?? $actor;
            } elseif ($movie->type === 'game') {
                $director = $data['developer'] ?? $data['developers'] ?? $director;
                $actor = $data['publisher'] ?? $data['publishers'] ?? $actor;
            } elseif ($movie->type === 'music') {
                $director = $data['artist'] ?? $data['artists'] ?? $director;
                $actor = $data['company'] ?? $data['companies'] ?? $actor;
            }

            if (isset($director)) {
                $update_data['director'] = json_encode($director);
            }
            if (isset($actor)) {
                $update_data['actor'] = json_encode($actor);
            }
            if (!empty($data['orig_title'])) {
                $update_data['orig_title'] = $data['orig_title'];
            }
            if (isset($data['external_resources'])) {
                $update_data['external_resources'] = json_encode($data['external_resources']);
            }
            $this->safe_update($wpdb->douban_movies, $update_data, ['id' => $movie->id]);
            // Log what was updated
            $fields_updated = array_keys($update_data);
            $this->add_log($movie->type, 'sync', 'neodb', "Updated fields: " . implode(', ', $fields_updated));
            // Update genres
            if (isset($data['genre']) && is_array($data['genre'])) {
                // Delete existing genres
                $wpdb->delete($wpdb->douban_genres, ['movie_id' => $movie->id]);
                
                $genre_map = $this->get_genre_mapping();
                foreach ($data['genre'] as $genre) {
                    $final_genre = $genre_map[$genre] ?? $genre;
                    $wpdb->insert($wpdb->douban_genres, [
                        'movie_id' => $movie->id,
                        'name' => $final_genre,
                        'type' => $movie->type,
                    ]);
                }
            }
            // Log the sync
            $item_name = $data['display_title'] ?? $data['title'];
            $this->add_log($movie->type, 'sync', 'neodb', "synced {$item_name}");
            return true;
        }

        // Handle TMDB items (only if NOT a NeoDB item - checked above)
        if ($movie->tmdb_id) {
            $response = wp_remote_get("https://api.themoviedb.org/3/" . $movie->tmdb_type . "/" . $movie->tmdb_id . "?api_key=" . $this->db_get_setting('api_key') . "&language=zh-CN", ['sslverify' => false]);
            if (is_wp_error($response)) {
                return false;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ($data) {
                $this->safe_update($wpdb->douban_movies, [
                    'name' => $data['title'] ?? $data['name'],
                   'poster' => "https://image.tmdb.org/t/p/original" . $data['poster_path'],
                    'tmdb_id' => $data['id'],  // FIXED: was incorrectly writing to douban_id
                    'douban_score' => $data['vote_average'],
                    'link' => $data['homepage'],
                    'year' => '',
                    'type' => 'movie',
                    'pubdate' => $data['release_date'] ?? $data['first_air_date'],
                    'card_subtitle' => $data['overview'],
                ], ['id' => $movie->id]);
                return true;
            }
        }

        if ($type == 'movie') {
            $link = $this->base_url . "movie/" . $movie->douban_id . "?ck=xgtY&for_mobile=1";
        } elseif ($type == 'book') {
            $link = $this->base_url . "book/" . $movie->douban_id . "?ck=xgtY&for_mobile=1";
        } elseif ($type == 'game') {
            $link = $this->base_url . "game/" . $movie->douban_id . "?ck=xgtY&for_mobile=1";
        } elseif ($type == 'drama') {
            $link = $this->base_url . "drama/" . $movie->douban_id . "?ck=xgtY&for_mobile=1";
        } else {
            $link = $this->base_url . "music/" . $movie->douban_id . "?ck=xgtY&for_mobile=1";
        }

        $response = wp_remote_get($link, ['sslverify' => false]);
        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data) {
            return false;
        }


        $this->safe_update($wpdb->douban_movies, [
            'name' => $data['title'],
            'poster' => $data['pic']['large'],
            'douban_id' => $data['id'],
            'douban_score' => $data['rating']['value'],
            'link' => $data['url'],
            'year' => $data['year'] ?: '',
            'type' => $type,
            'pubdate' => $data['pubdate'] ? $data['pubdate'][0] : '',
            'card_subtitle' => $data['card_subtitle']
        ], ['id' => $movie->id]);
        return null;
    }

    public function fetch_tmdb_subject($id, $type)
    {
        $type = $type ?: 'movie';
        global $wpdb;
        $movie = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE `tmdb_type` = '{$type}' AND tmdb_id = '{$id}'");
        if ($movie) {
            return $this->populate_db_movie_metadata($movie);
        }

        $transient_key = 'wpn_tmdb_' . $type . '_' . $id;
        $cached = get_transient($transient_key);
        if ($cached) {
            return $cached;
        }

        $response = wp_remote_get("https://api.themoviedb.org/3/" . $type . "/" . $id . "?api_key=" . $this->db_get_setting('api_key') . "&language=zh-CN", ['sslverify' => false]);
        if (is_wp_error($response)) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($data) {
            // Use universal deduplication helper
            $existing_movie = $this->find_existing_movie(
                'movie',  // TMDB is always movie type in our system
                0,  // No douban_id from TMDB
                $data['id'],  // tmdb_id
                $type,  // tmdb_type
                ''  // No neodb_id
            );

            $insert_data = [
                'name' => $data['title'] ?? $data['name'],
                'poster' => "https://image.tmdb.org/t/p/original" . $data['poster_path'],
                'douban_score' => $data['vote_average'],
                'link' => $data['homepage'],
                'year' => '',
                'type' => 'movie',
                'pubdate' => $data['release_date'] ?? $data['first_air_date'],
                'card_subtitle' => $data['overview'],
                'tmdb_type' => $type,
                'tmdb_id' => $data['id'],
                // Metadata fields for TMDB
                'orig_title' => $data['original_title'] ?? $data['original_name'] ?? '',
                'external_resources' => json_encode([['url' => "https://www.themoviedb.org/{$type}/{$data['id']}"]])
            ];

            if ($existing_movie) {
                // Smart merge: only update empty fields
                $update_data = $this->smart_merge_movie_data($existing_movie, $insert_data);
                if (!empty($update_data)) {
                    $wpdb->update(
                        $wpdb->douban_movies,
                        $update_data,
                        ['id' => $existing_movie->id]
                    );
                    // Only log when data is actually updated
                    $this->add_log($type, 'embed', 'tmdb', "updated existing movie: {$existing_movie->name} (ID: {$existing_movie->id})");
                }
                $movie_id = $existing_movie->id;
            } else {
                // Insert new entry
                $this->safe_insert($wpdb->douban_movies, $insert_data);
                $movie_id = '';
                if ($wpdb->insert_id) {
                    $movie_id = $wpdb->insert_id;
                    $title = $data['title'] ?? $data['name'];
                    $this->add_log($type, 'embed', 'tmdb', $title);
                }
            }

            // Insert genres
            if ($movie_id && $data['genres']) {
                foreach ($data['genres'] as $genre) {
                    $wpdb->insert(
                        $wpdb->douban_genres,
                        [
                            'movie_id' => $movie_id,
                            'name' => $genre['name'],
                            'type' => $type,
                        ]
                    );
                }
            }

            $result = (object) [
                'id' => $movie_id,
                'name' => $data['title'] ?? $data['name'],
                'poster' => "https://image.tmdb.org/t/p/original" . $data['poster_path'],
                'douban_id' => 0,
                'douban_score' => $data['vote_average'],
                'link' => $data['homepage'],
                'year' => '',
                'type' => $type,
                'pubdate' => $data['release_date'] ?? $data['first_air_date'],
                'card_subtitle' => $data['overview'],
                'genres' => $data['genres'],
                'fav_time' => '',
                'remark' => '',
                'score' => ''
            ];
            set_transient($transient_key, $result, 600);
            return $result;
        } else {
            return false;
        }
    }

    /**
     * Map Douban API data to database fields
     */
    private function map_douban_data($data, $type)
    {
        return [
            'name' => $data['title'],
            'poster' => $data['pic']['large'],
            'douban_id' => $data['id'],
            'douban_score' => $data['rating']['value'],
            'link' => $data['url'],
            'year' => $data['year'] ?: '',
            'type' => $type,
            'pubdate' => $data['pubdate'][0] ?? '',
            'card_subtitle' => $data['card_subtitle'],
            'director' => isset($data['directors']) ? json_encode(array_map(fn($d) => $d['name'], $data['directors'])) : null,
            'actor' => isset($data['actors']) ? json_encode(array_map(fn($a) => $a['name'], array_slice($data['actors'], 0, 5))) : null,
            'orig_title' => $data['original_title'] ?? '',
            'external_resources' => json_encode([['url' => $data['url']]])
        ];
    }

    public function fetch_subject($id, $type)
    {
        $type = $type ?: 'movie';
        global $wpdb;
        $movie = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE `type` = '{$type}' AND douban_id = '{$id}'");
        if ($movie) {
            return $this->populate_db_movie_metadata($movie);
        }

        $transient_key = 'wpn_douban_' . $type . '_' . $id;
        $cached = get_transient($transient_key);
        if ($cached) {
            return $cached;
        }

        // Simplified URL construction
        $link = $this->base_url . "{$type}/{$id}?ck=xgtY&for_mobile=1";
        $response = wp_remote_get($link, ['sslverify' => false]);
        if (is_wp_error($response)) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data) {
            return false;
        }
        
        // Use universal deduplication helper
        $existing_movie = $this->find_existing_movie($type, $data['id'], 0, null, '');
        
        // Map API data to database fields
        $insert_data = $this->map_douban_data($data, $type);

        if ($existing_movie) {
            $movie_id = $existing_movie->id;
            $update_data = $this->smart_merge_movie_data($existing_movie, $insert_data);
            if (!empty($update_data)) {
                $this->safe_update($wpdb->douban_movies, $update_data, ['id' => $movie_id]);
                $this->add_log($type, 'embed', 'douban', "updated: {$existing_movie->name}");
            }
        } else {
            $this->safe_insert($wpdb->douban_movies, $insert_data);
            $movie_id = $wpdb->insert_id;
            if ($movie_id) {
                $this->add_log($type, 'embed', 'douban', $data['title']);
            }
        }
        
        // Sync genres (same strategy as NeoDB)
        $this->sync_genres($movie_id, $data['genres'] ?? [], $type);
        
        // Build and cache result
        $result = (object) array_merge($insert_data, [
            'id' => $movie_id,
            'genres' => $data['genres'] ?? [],
            'fav_time' => '',
            'remark' => '',
            'score' => ''
        ]);
        set_transient($transient_key, $result, 600);
        return $result;
    }

    /**
     * Map NeoDB API data to internal database format
     * @return array [type, insert_data]
     */
    private function map_neodb_data($data, $neodb_type, $uuid)
    {
        // Normalize type
        $normalized = str_contains($neodb_type, '/') ? explode('/', $neodb_type)[0] : $neodb_type;
        $type_map = [
            'book' => 'book',
            'movie' => 'movie',
            'tv' => 'movie',  // TV shows stored as movie
            'album' => 'music',
            'game' => 'game',
            'podcast' => 'podcast',
            'performance' => 'drama'
        ];
        $type = $type_map[$normalized] ?? 'movie';

        // Extract description
        $description = $data['description'] ?? $data['brief'] ?? '';

        // Extract external IDs
        $external_ids = $this->extract_external_ids_from_neodb($data);

        // Map type-specific fields
        $director = $data['director'] ?? $data['directors'] ?? null;
        $actor = $data['actor'] ?? $data['actors'] ?? null;

        if ($type === 'book') {
            $director = $data['author'] ?? $data['authors'] ?? $director;
            $actor = $data['translator'] ?? $data['translators'] ?? $actor;
        } elseif ($type === 'game') {
            $director = $data['developer'] ?? $data['developers'] ?? $director;
            $actor = $data['publisher'] ?? $data['publishers'] ?? $actor;
        } elseif ($type === 'music') {
            $director = $data['artist'] ?? $data['artists'] ?? $director;
            $actor = $data['company'] ?? $data['companies'] ?? $actor;
        }

        $insert_data = [
            'name' => $data['display_title'] ?? $data['title'],
            'poster' => $data['cover_image_url'] ?? '',
            'douban_id' => $external_ids['douban_id'],
            'tmdb_id' => $external_ids['tmdb_id'],
            'tmdb_type' => $external_ids['tmdb_type'],
            'douban_score' => $data['rating'] ?? 0,
            'link' => $data['url'] ?? '',
            'type' => $type,
            'card_subtitle' => $description,
            'neodb_id' => $uuid,
            'director' => isset($director) ? json_encode($director) : null,
            'actor' => isset($actor) ? json_encode($actor) : null,
            'orig_title' => $data['orig_title'] ?? '',
            'external_resources' => isset($data['external_resources']) ? json_encode($data['external_resources']) : null,
            'pub_house' => $data['pub_house'] ?? '',
        ];

        // Extract year
        if (isset($data['pub_year'])) {
            $insert_data['year'] = $data['pub_year'];
        } elseif (isset($data['year'])) {
            $insert_data['year'] = $data['year'];
        } elseif (isset($data['release_date'])) {
            $insert_data['year'] = substr($data['release_date'], 0, 4);
            $insert_data['pubdate'] = $data['release_date'];
        }
        
        // Book-specific pubdate
        if ($type === 'book' && isset($data['pub_year'])) {
            $pdate = $data['pub_year'];
            if (!empty($data['pub_month'])) {
                $pdate .= ' - ' . $data['pub_month'];
            }
            $insert_data['pubdate'] = $pdate;
        }

        return [$type, $insert_data];
    }

    /**
     * Sync genres (delete old, insert new with mapping)
     */
    private function sync_genres($movie_id, $genres, $type)
    {
        if (empty($genres) || !is_array($genres)) {
            return;
        }
        
        global $wpdb;
        $wpdb->delete($wpdb->douban_genres, ['movie_id' => $movie_id]);
        
        $genre_map = $this->get_genre_mapping();
        $unique_genres = array_unique($genres);
        foreach ($unique_genres as $genre) {
            $final_genre = $genre_map[$genre] ?? $genre;
            $wpdb->insert($wpdb->douban_genres, [
                'movie_id' => $movie_id,
                'name' => $final_genre,
                'type' => $type
            ]);
        }
    }

    public function fetch_neodb_subject($uuid, $neodb_type)
    {
        global $wpdb;

        // Check if item already exists by neodb_id
        $movie = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->douban_movies WHERE `neodb_id` = %s", $uuid));
        
        // If movie exists and has director metadata, return early
        // Note: We only check director because actor field may be empty for some types (e.g., podcasts without producers)
        if ($movie && !empty($movie->director)) {
            return $this->populate_db_movie_metadata($movie);
        }

        $transient_key = 'wpn_neodb_' . $uuid;
        $cached = get_transient($transient_key);
        // Still use cache if available and "complete enough", or if we are just backfilling
        if ($cached && isset($cached->director) && !empty($cached->director)) {
            return $cached;
        }

        // Fetch from NeoDB API
        $neodb_url = $this->db_get_setting('neodb_url') ?: 'https://neodb.social';
        $api_url = rtrim($neodb_url, '/') . "/api/{$neodb_type}/{$uuid}";

        $headers = [];
        if ($this->db_get_setting('neodb_token')) {
            $headers['Authorization'] = 'Bearer ' . $this->db_get_setting('neodb_token');
        }

        $response = wp_remote_get($api_url, ['headers' => $headers, 'sslverify' => false]);
        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data) {
            return false;
        }

        // Fetch from parent if needed (especially for Performance Production)
        // If current item lacks description/brief but has a parent, try to merge from parent
        if (!empty($data['parent_uuid']) && empty($data['description']) && empty($data['brief'])) {
            $parent_uuid = $data['parent_uuid'];
            // Determine parent category (usually 'performance' for drama/production)
            $parent_category = $data['category'] ?? 'performance';
            $parent_api_url = rtrim($neodb_url, '/') . "/api/{$parent_category}/{$parent_uuid}";
            
            $parent_response = wp_remote_get($parent_api_url, ['headers' => $headers, 'sslverify' => false]);
            if (!is_wp_error($parent_response)) {
                $parent_data = json_decode(wp_remote_retrieve_body($parent_response), true);
                if ($parent_data) {
                    // Merge missing fields
                    if (empty($data['description'])) {
                        $data['description'] = $parent_data['description'] ?? '';
                    }
                    if (empty($data['brief'])) {
                        $data['brief'] = $parent_data['brief'] ?? '';
                    }
                    if (empty($data['genre']) && !empty($parent_data['genre'])) {
                        $data['genre'] = $parent_data['genre'];
                    }
                }
            }
        }

        // Map NeoDB data to internal format
        [$type, $insert_data] = $this->map_neodb_data($data, $neodb_type, $uuid);

        // Use universal deduplication helper to check all ID types
        $existing_movie = $this->find_existing_movie(
            $type,
            $insert_data['douban_id'],
            $insert_data['tmdb_id'],
            $insert_data['tmdb_type'],
            $uuid
        );

        if ($existing_movie) {
            // Smart merge: only update empty fields
            $update_data = $this->smart_merge_movie_data($existing_movie, $insert_data);
            if (!empty($update_data)) {
                $wpdb->update(
                    $wpdb->douban_movies,
                    $update_data,
                    ['id' => $existing_movie->id]
                );
                // Only log when data is actually updated
                $this->add_log($type, 'embed', 'neodb', "updated existing movie: {$existing_movie->name} (ID: {$existing_movie->id})");
            }
            $movie_id = $existing_movie->id;
        } else {
            // Insert into database as new entry
            $this->safe_insert($wpdb->douban_movies, $insert_data);
            $movie_id = $wpdb->insert_id;

            // Log the embed action or capture error
            if ($movie_id) {
                $title = $data['display_title'] ?? $data['title'];
                $this->add_log($type, 'embed', 'neodb', $title);
            } else {
                // Root cause detection: missing column or SQL error
                error_log("WP-NeoDB Error: Failed to insert item into database. SQL Error: " . $wpdb->last_error);
                $this->add_log($type, 'error', 'neodb', "DB Insert Failed: " . substr($wpdb->last_error, 0, 100));
            }
        }

        // Sync genres (delete old, insert new with mapping)
        $this->sync_genres($movie_id, $data['genre'] ?? [], $type);

        // Return formatted object
        $result = (object) array_merge($insert_data, [
            'id' => $movie_id,
            'genres' => $data['genre'] ?? [],
            'fav_time' => '',
            'remark' => '',
            'score' => '',
        ]);
        set_transient($transient_key, $result, 600);
        return $result;
    }

    function wp_embed_handler_neodb($matches, $attr, $url, $rawattr)
    {
        if (!is_singular() && !$this->db_get_setting('home_render')) {
            return $url;
        }

        $neodb_type = $matches[1];  // book, movie, tv, album, etc
        $uuid = $matches[2];         // UUID

        $html = $this->get_neodb_subject_detail($uuid, $neodb_type, $url);  // Pass original URL
        return apply_filters('embed_neodb', $html, $matches, $attr, $url, $rawattr);
    }

    public function get_neodb_subject_detail($uuid, $neodb_type, $embed_url = null)
    {
        $data = $this->fetch_neodb_subject($uuid, $neodb_type);
        if (!$data) {
            return '';
        }
        
        // Override link with embed URL if provided
        if ($embed_url) {
            $data->link = $embed_url;
        }

        $cover = $this->db_get_setting('download_image') ? $this->wpn_save_images($uuid, $data->poster, 'neodb_') : $data->poster;
        return $this->render_enhanced_item_html($data, $cover);
    }

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
        return home_url('/') . 'douban_cache/' . $type . $id . '.jpg';
    }

    public function wpn_load_scripts()
    {
        wp_enqueue_style('wpn-css', WPN_URL . "/assets/css/db.min.css", [], WPN_VERSION, 'screen');
        
        // Load dark mode CSS from file instead of inline
        $dark_mode = $this->db_get_setting('dark_mode');
        if ($dark_mode == 'auto') {
            wp_enqueue_style('wpn-dark-auto', WPN_URL . "/assets/css/dark-auto.css", ['wpn-css'], WPN_VERSION);
        } elseif ($dark_mode == 'dark') {
            wp_enqueue_style('wpn-dark', WPN_URL . "/assets/css/dark.css", ['wpn-css'], WPN_VERSION);
        }
        wp_enqueue_script('wpnjs', WPN_URL . "/assets/js/db.min.js", [], WPN_VERSION, true);
        wp_localize_script('wpnjs', 'wpn_base', [
            'api' => get_rest_url(),
            'token' => $this->db_get_setting('token'),
            'neodb_url' => $this->db_get_setting('neodb_url') ?: 'https://neodb.social',
        ]);
    }

    public function get_collections($name = 'movie_top250')
    {
        global $wpdb;
        
        // Fetch collection data from API
        $url = "{$this->base_url}subject_collection/{$name}/items?start=0&count=250&items_only=1&ck=xgtY&for_mobile=1";
        $response = wp_remote_get($url);
        
        if (is_wp_error($response)) {
            error_log("WP-NeoDB: Failed to fetch collection {$name} - " . $response->get_error_message());
            return false;
        }
        
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!isset($data['subject_collection_items'])) {
            error_log("WP-NeoDB: Invalid collection data for {$name}");
            return false;
        }
        
        $interests = $data['subject_collection_items'];
        
        // Get or create collection
        $collection = $this->get_collection($name);
        if (!$collection) {
            $wpdb->insert($wpdb->douban_collection, [
                'douban_id' => $name,
                'name' => $name,
            ]);
            $collection_id = $wpdb->insert_id;
        } else {
            $collection_id = $collection->id;
        }
        
        // Batch check existing relations to reduce queries
        $existing_relations = [];
        $movie_ids_to_check = [];
        
        foreach ($interests as $interest) {
            // Check existing movie
            $existing_movie = $this->find_existing_movie(
                $interest['type'],
                $interest['id'],  // douban_id
                0,
                null,
                ''
            );
            
            // Extract year from card_subtitle (format: "1994 / 美国 / 剧情...")
            $year = '';
            if (preg_match('/^(\d{4})/', $interest['card_subtitle'] ?? '', $matches)) {
                $year = $matches[1];
            }
            
            // Prepare Top 250 data
            $top250_data = [
                'name' => $interest['title'],
                'poster' => $interest['pic']['large'] ?? '',
                'douban_id' => $interest['id'],
                'douban_score' => $interest['rating']['value'] ?? 0,
                'link' => $interest['url'] ?? '',
                'year' => $year,
                'type' => $interest['type'],
                'pubdate' => '',
                'card_subtitle' => $interest['card_subtitle'] ?? '',
            ];
            
            if ($existing_movie) {
                // Movie exists - smart merge to fill in missing fields
                $update_data = $this->smart_merge_movie_data($existing_movie, $top250_data);
                if (!empty($update_data)) {
                    $this->safe_update($wpdb->douban_movies, $update_data, ['id' => $existing_movie->id]);
                }
                $movie_id = $existing_movie->id;
            } else {
                // Insert new movie
                $this->safe_insert($wpdb->douban_movies, $top250_data);
                $movie_id = $wpdb->insert_id;
            }
            
            if ($movie_id) {
                $movie_ids_to_check[] = $movie_id;
            }
        }
        
        // Batch query existing relations
        if ($movie_ids_to_check !== []) {
            $placeholders = implode(',', array_fill(0, count($movie_ids_to_check), '%d'));
            $query = $wpdb->prepare(
                "SELECT movie_id FROM {$wpdb->douban_relation} 
                 WHERE collection_id = %d AND movie_id IN ($placeholders)",
                array_merge([$collection_id], $movie_ids_to_check)
            );
            $existing = $wpdb->get_col($query);
            $existing_relations = array_flip($existing);
        }
        
        // Insert missing relations
        foreach ($movie_ids_to_check as $movie_id) {
            if (!isset($existing_relations[$movie_id])) {
                $wpdb->insert($wpdb->douban_relation, [
                    'movie_id' => intval($movie_id),
                    'collection_id' => intval($collection_id)
                ]);
            }
        }
        
        return true;
    }

    /**
     * Safe insert wrapper - automatically truncates fields before insertion
     */
    private function safe_insert($table, $data)
    {
        global $wpdb;
        
        // Auto-truncate card_subtitle to prevent DB errors
        if (isset($data['card_subtitle'])) {
            $data['card_subtitle'] = $this->truncate_text($data['card_subtitle']);
        }
        
        return $wpdb->insert($table, $data);
    }

    /**
     * Safe update wrapper - automatically truncates fields before update
     */
    private function safe_update($table, $data, $where)
    {
        global $wpdb;
        
        // Auto-truncate card_subtitle to prevent DB errors
        if (isset($data['card_subtitle'])) {
            $data['card_subtitle'] = $this->truncate_text($data['card_subtitle']);
        }
        
        return $wpdb->update($table, $data, $where);
    }

    /**
     * Truncate text to specified length
     */
    private function truncate_text($text, $max_length = 250)
    {
        if (mb_strlen($text, 'UTF-8') > $max_length) {
            return mb_substr($text, 0, $max_length - 3, 'UTF-8') . '...';
        }
        return $text;
    }

    /**
     * Fetch preview data from Douban
     */
    private function fetch_douban_preview($subject)
    {
        if (!$subject->douban_id) {
            return null;
        }

        $type_path = in_array($subject->type, ['movie', 'book', 'game', 'drama']) ? $subject->type : 'music';
        $link = $this->base_url . $type_path . "/" . $subject->douban_id . "?ck=xgtY&for_mobile=1";
        
        $response = wp_remote_get($link, ['sslverify' => false]);
        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data) {
            return null;
        }

        return (object) [
            'name' => $data['title'] ?? '',
            'poster' => $data['pic']['large'] ?? '',
            'douban_score' => $data['rating']['value'] ?? 0,
            'card_subtitle' => $data['card_subtitle'] ?? ''
        ];
    }

    /**
     * Fetch preview data from NeoDB
     */
    private function fetch_neodb_preview($subject)
    {
        if (!$subject->neodb_id) {
            return null;
        }

        $neodb_type_map = ['movie' => 'movie', 'book' => 'book', 'music' => 'album', 'game' => 'game', 'drama' => 'performance'];
        $neodb_type = $neodb_type_map[$subject->type] ?? 'movie';
        $neodb_base = $this->db_get_setting('neodb_url') ?: 'https://neodb.social';
        $url = rtrim($neodb_base, '/') . "/api/{$neodb_type}/" . $subject->neodb_id;
        
        $response = wp_remote_get($url, ['sslverify' => false]);
        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data || isset($data['error'])) {
            return null;
        }

        return (object) [
            'name' => $data['title'] ?? '',
            'poster' => $data['cover_image_url'] ?? '',
            'douban_score' => $data['rating'] ?? 0,
            'card_subtitle' => $data['brief'] ?? ''
        ];
    }

    /**
     * Fetch preview data from TMDB
     */
    private function fetch_tmdb_preview($subject)
    {
        if (!$subject->tmdb_id || !$subject->tmdb_type) {
            return null;
        }

        $api_key = $this->db_get_setting('api_key');
        $path = $subject->tmdb_type == 'movie' ? 'movie' : 'tv';
        $url = "https://api.themoviedb.org/3/{$path}/{$subject->tmdb_id}?api_key={$api_key}&language=zh-CN";
        
        $response = wp_remote_get($url, ['sslverify' => false]);
        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data || isset($data['success']) || isset($data['status_code'])) {
            return null;
        }

        return (object) [
            'name' => $data['title'] ?? $data['name'] ?? '',
            'poster' => empty($data['poster_path']) ? '' : "https://image.tmdb.org/t/p/original" . $data['poster_path'],
            'douban_score' => $data['vote_average'] ?? 0,
            'card_subtitle' => $data['overview'] ?? ''
        ];
    }

    /**
     * Fetch user marking from NeoDB
     */
    private function fetch_neodb_marking($subject)
    {
        if (!$subject->neodb_id || !$this->db_get_setting('neodb_token')) {
            return null;
        }

        $neodb_base = $this->db_get_setting('neodb_url') ?: 'https://neodb.social';
        $shelf_url = rtrim($neodb_base, '/') . "/api/me/shelf/item/" . $subject->neodb_id;
        $headers = ['Authorization' => 'Bearer ' . $this->db_get_setting('neodb_token')];
        
        $response = wp_remote_get($shelf_url, ['headers' => $headers, 'sslverify' => false]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) != 200) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!$data) {
            return null;
        }

        $status_map = ['complete' => 'done', 'progress' => 'doing', 'wishlist' => 'mark', 'dropped' => 'dropped'];
        return (object) [
            'create_time' => isset($data['created_time']) ? get_date_from_gmt($data['created_time'], 'Y-m-d\TH:i:s') : '',
            'status' => $status_map[$data['shelf_type']] ?? 'done',
            'remark' => $data['comment_text'] ?? '',
            'score' => $data['rating_grade'] ?? ''
        ];
    }

    public function preview_source_data($request)
    {
        $subject_id = intval($request['subject_id']);
        $source = sanitize_text_field($request['source']);
        $action = sanitize_text_field($request['action'] ?? 'edit_subject');

        global $wpdb;
        $subject = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->douban_movies WHERE id = %d", $subject_id));

        if (!$subject) {
            return ['success' => false, 'message' => 'Subject not found'];
        }

        // Fetch metadata from selected source
        $fresh_data = null;
        switch ($source) {
            case 'douban':
                $fresh_data = $this->fetch_douban_preview($subject);
                break;
            case 'neodb':
                $fresh_data = $this->fetch_neodb_preview($subject);
                break;
            case 'tmdb':
                $fresh_data = $this->fetch_tmdb_preview($subject);
                break;
        }

        if (!$fresh_data) {
            return ['success' => false, 'message' => "Failed to fetch data from {$source}"];
        }

        // Fetch user markings from NeoDB if in edit_fave mode
        if ($action == 'edit_fave' && $source === 'neodb') {
            $marking = $this->fetch_neodb_marking($subject);
            if ($marking) {
                $fresh_data->create_time = $marking->create_time;
                $fresh_data->status = $marking->status;
                $fresh_data->remark = $marking->remark;
                $fresh_data->score = $marking->score;
            }
        }

        // Prepare response data
        if ($action == 'edit_fave') {
            $response_data = [
                'create_time' => $fresh_data->create_time ?? '',
                'status' => $fresh_data->status ?? '',
                'remark' => $fresh_data->remark ?? '',
                'score' => $fresh_data->score ?? '',
                // Also provide metadata in case user wants to see it
                'name' => $fresh_data->name ?? '',
                'poster' => $fresh_data->poster ?? '',
                'douban_score' => $fresh_data->douban_score ?? '',
                'card_subtitle' => $fresh_data->card_subtitle ?? ''
            ];
        } else {
            $response_data = [
                'name' => $fresh_data->name ?? '',
                'poster' => $fresh_data->poster ?? '',
                'douban_score' => $fresh_data->douban_score ?? '',
                'card_subtitle' => $fresh_data->card_subtitle ?? ''
            ];
        }

        return ['success' => true, 'data' => $response_data, 'source' => $source];
    }

    /**
     * Get cache ID and prefix for poster image
     */
    private function get_poster_cache_info($movie)
    {
        if (!empty($movie->neodb_id)) {
            return [$movie->neodb_id, 'neodb_'];
        } elseif (!empty($movie->tmdb_id)) {
            return [$movie->tmdb_id, 'tmdb'];
        } else {
            return [$movie->douban_id, ''];
        }
    }

    private function populate_db_movie_metadata($movie)
    {
        if (!$movie) {
            return null;
        }
        global $wpdb;

        // Populate Genres (skip if already populated by batch query)
        if (empty($movie->genres)) {
            $movie->genres = [];
            $genres = $wpdb->get_results($wpdb->prepare(
                "SELECT name FROM {$wpdb->douban_genres} WHERE movie_id = %d",
                $movie->id
            ));
            if (!empty($genres)) {
                $movie->genres = array_column($genres, 'name');
            }
        }

        // Populate Favorite data (skip if already populated by JOIN query)
        if (!isset($movie->fav_time)) {
            $fav = $wpdb->get_row($wpdb->prepare(
                "SELECT create_time, score, remark, status FROM {$wpdb->douban_faves} WHERE subject_id = %d",
                $movie->id
            ));
            if ($fav) {
                $movie->fav_time = $fav->create_time;
                $movie->score = $fav->score;
                $movie->remark = $fav->remark;
                $movie->status = $fav->status;
            } else {
                $movie->fav_time = $movie->score = $movie->remark = "";
            }
        } else {
            // Ensure defaults for data from JOIN
            $movie->fav_time ??= "";
            $movie->score ??= "";
            $movie->remark ??= "";
        }

        // Handle Image Cache or Proxy
        if ($this->db_get_setting('download_image')) {
            [$cache_id, $cache_prefix] = $this->get_poster_cache_info($movie);
            $movie->poster = $this->wpn_save_images($cache_id, $movie->poster, $cache_prefix);
        } elseif (in_array($movie->type, ['movie', 'book', 'music']) && !empty($movie->douban_id)) {
            $movie->poster = "https://dou.img.lithub.cc/{$movie->type}/{$movie->douban_id}.jpg";
        }
        
        // Check Top 250
        if ($this->db_get_setting('top250') && in_array($movie->type, ['movie', 'book']) && !empty($movie->douban_id)) {
            $collection_name = $movie->type == 'book' ? 'book_top250' : 'movie_top250';
            $collection = $this->get_collection($collection_name);
            if ($collection) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT 1 FROM {$wpdb->douban_relation} r 
                     JOIN {$wpdb->douban_movies} m ON r.movie_id = m.id 
                     WHERE m.douban_id = %s AND r.collection_id = %d LIMIT 1",
                    $movie->douban_id,
                    $collection->id
                ));
                $movie->is_top250 = (bool)$exists;
            }
        }

        // Parse external resources for frontend consumption
        $movie->formatted_external_resources = $this->parse_external_resources($movie);

        return $movie;
    }

    /**
     * Extract external IDs from NeoDB external_resources
     * 
     * @param array $data NeoDB item data
     * @return array ['douban_id' => int, 'tmdb_id' => int, 'tmdb_type' => string]
     */
    protected function extract_external_ids_from_neodb($data)
    {
        $result = [
            'douban_id' => 0,
            'tmdb_id' => 0,
            'tmdb_type' => null
        ];
        
        if (!isset($data['external_resources']) || !is_array($data['external_resources'])) {
            return $result;
        }
        
        foreach ($data['external_resources'] as $resource) {
            $url = $resource['url'] ?? '';
            
            // 豆瓣 ID (支持通用 subject 及戏剧 drama/location 路径，使用非捕获分组确保 ID 提取位置稳定)
            if ($result['douban_id'] === 0 && preg_match('/douban\.com\/(?:subject|location\/drama)\/(\d+)/', $url, $matches)) {
                $result['douban_id'] = intval($matches[1]);
            }
            
            // TMDB Movie ID
            if ($result['tmdb_id'] === 0 && preg_match('/themoviedb\.org\/movie\/(\d+)/', $url, $matches)) {
                $result['tmdb_id'] = intval($matches[1]);
                $result['tmdb_type'] = 'movie';
            }
            
            // TMDB TV ID
            if ($result['tmdb_id'] === 0 && preg_match('/themoviedb\.org\/tv\/(\d+)/', $url, $matches)) {
                $result['tmdb_id'] = intval($matches[1]);
                $result['tmdb_type'] = 'tv';
            }
        }
        
        return $result;
    }

    /**
     * Find existing movie entry by checking douban_id, tmdb_id, or neodb_id
     * This prevents duplicate entries regardless of embed order
     * 
     * @param string $type Movie type (movie, book, etc.)
     * @param int $douban_id Douban ID (0 if not available)
     * @param int $tmdb_id TMDB ID (0 if not available)
     * @param string $tmdb_type TMDB type (movie/tv)
     * @param string $neodb_id NeoDB UUID (empty if not available)
     * @return object|null Existing movie record or null
     */
    protected function find_existing_movie($type, $douban_id = 0, $tmdb_id = 0, $tmdb_type = null, $neodb_id = '')
    {
        global $wpdb;

        // Priority 1: Check by neodb_id (most specific)
        if (!empty($neodb_id)) {
            $movie = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $wpdb->douban_movies WHERE neodb_id = %s",
                $neodb_id
            ));
            if ($movie) {
                return $movie;
            }
        }

        // Priority 2: Check by douban_id
        if ($douban_id > 0) {
            $movie = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $wpdb->douban_movies WHERE type = %s AND douban_id = %d",
                $type,
                $douban_id
            ));
            if ($movie) {
                return $movie;
            }
        }

        // Priority 3: Check by tmdb_id
        if ($tmdb_id > 0 && $tmdb_type) {
            $movie = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $wpdb->douban_movies WHERE tmdb_type = %s AND tmdb_id = %d",
                $tmdb_type,
                $tmdb_id
            ));
            if ($movie) {
                return $movie;
            }
        }

        return null;
    }

    /**
     * Smart merge movie data - only update empty fields
     * Preserves existing data while filling in missing information
     * 
     * @param object $existing_movie Existing movie record
     * @param array $new_data New data to merge
     * @return array Fields to update (empty if no updates needed)
     */
    protected function smart_merge_movie_data($existing_movie, $new_data)
    {
        $update_data = [];

        // ID fields: always update if new data has it and existing doesn't
        $id_fields = ['douban_id', 'neodb_id', 'tmdb_id', 'tmdb_type'];
        foreach ($id_fields as $field) {
            if (isset($new_data[$field]) && !empty($new_data[$field]) && empty($existing_movie->$field)) {
                $update_data[$field] = $new_data[$field];
            }
        }

        // Content fields: only fill if currently empty/null
        // Note: external_resources is handled separately below
        $fillable_fields = ['name', 'poster', 'douban_score', 'link', 'year', 'pubdate', 'card_subtitle', 'pub_house', 'director', 'actor', 'orig_title'];
        foreach ($fillable_fields as $field) {
            // Check if existing field is empty
            if (isset($new_data[$field]) && !empty($new_data[$field]) && (!isset($existing_movie->$field) || $existing_movie->$field === '' || $existing_movie->$field === null || $existing_movie->$field === '0' || $existing_movie->$field === 0)) {
                $update_data[$field] = $new_data[$field];
            }
        }

        // Special handling for external_resources - merge arrays instead of replacing
        if (isset($new_data['external_resources']) && !empty($new_data['external_resources'])) {
            // Parse existing resources
            $existing_resources = [];
            if (!empty($existing_movie->external_resources)) {
                $decoded = json_decode($existing_movie->external_resources, true);
                if (is_array($decoded)) {
                    $existing_resources = $decoded;
                }
            }
            
            // Parse new resources
            $new_resources = [];
            if (is_string($new_data['external_resources'])) {
                $decoded = json_decode($new_data['external_resources'], true);
                if (is_array($decoded)) {
                    $new_resources = $decoded;
                }
            } elseif (is_array($new_data['external_resources'])) {
                $new_resources = $new_data['external_resources'];
            }
            
            // Merge and deduplicate by hostname (not full URL)
            // This ensures each website appears only once
            $merged = $existing_resources;
            $existing_hosts = [];
            
            // Extract hostnames from existing resources
            foreach ($existing_resources as $resource) {
                if (!empty($resource['url'])) {
                    $parsed = parse_url($resource['url']);
                    if (isset($parsed['host'])) {
                        $existing_hosts[$parsed['host']] = true;
                    }
                }
            }
            
            // Add new resources if their hostname is not already present
            foreach ($new_resources as $resource) {
                if (!empty($resource['url'])) {
                    $parsed = parse_url($resource['url']);
                    if (isset($parsed['host']) && !isset($existing_hosts[$parsed['host']])) {
                        $merged[] = $resource;
                        $existing_hosts[$parsed['host']] = true;  
                    }
                }
            }
            
            // Only update if we added new resources
            if (count($merged) > count($existing_resources)) {
                $update_data['external_resources'] = json_encode($merged);
            }
        }

        return $update_data;
    }

    /**
     * Sync mark to NeoDB
     * 
     * @param string $neodb_id NeoDB item UUID
     * @param string $shelf_type NeoDB shelf type (wishlist|progress|complete|dropped)
     * @param int $rating_grade Rating 0-10 (0 means no rating)
     * @param string $comment_text Comment text
     * @param string $created_time Created time in ISO 8601 format (UTC)
     * @return array ['success' => bool, 'message' => string]
     */
    public function sync_mark_to_neodb($neodb_id, $shelf_type, $rating_grade = 0, $comment_text = '', $created_time = '')
    {
        $token = $this->db_get_setting('neodb_token');
        if (!$token) {
            return ['success' => false, 'message' => 'NeoDB token not configured'];
        }

        if (!$neodb_id) {
            return ['success' => false, 'message' => 'NeoDB ID is required'];
        }

        // Build request body according to NeoDB API spec
        $body = [
            'shelf_type' => $shelf_type,
            'visibility' => 0, // 0 = public
        ];

        // Add optional fields
        if ($rating_grade > 0) {
            $body['rating_grade'] = intval($rating_grade);
        }

        if (!empty($comment_text)) {
            $body['comment_text'] = $comment_text;
        }

        // Convert WordPress GMT time to ISO 8601 format for NeoDB
        if (!empty($created_time)) {
            // $created_time is in GMT format from WordPress
            $timestamp = strtotime($created_time);
            if ($timestamp !== false) {
                $body['created_time'] = gmdate('Y-m-d\TH:i:s\Z', $timestamp);
            }
        }

        $neodb_url = $this->db_get_setting('neodb_url') ?: 'https://neodb.social';
        $api_url = rtrim($neodb_url, '/') . "/api/me/shelf/item/{$neodb_id}";

        $response = wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => 'Request failed: ' . $response->get_error_message()];
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($status_code === 200) {
            return ['success' => true, 'message' => 'Successfully synced to NeoDB'];
        } else {
            $error_data = json_decode($response_body, true);
            $error_msg = $error_data['message'] ?? 'Unknown error';
            return ['success' => false, 'message' => "NeoDB API error ({$status_code}): {$error_msg}"];
        }
    }
}