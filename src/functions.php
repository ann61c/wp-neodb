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

    public function __construct()
    {
        $this->perpage = $this->db_get_setting('perpage') ? $this->db_get_setting('perpage') : 70;
        $plugin_file = plugin_basename(WPN_PATH . '/wp-neodb.php');

        if (!$this->db_get_setting('disable_scripts')) add_action('wp_enqueue_scripts', [$this, 'wpn_load_scripts']);
        wp_embed_register_handler('doubanlist', '#https?:\/\/(\w+)\.douban\.com\/subject\/(\d+)#i', [$this, 'wp_embed_handler_doubanlist']);
        wp_embed_register_handler('doubanalbum', '#https?:\/\/www\.douban\.com\/(\w+)\/(\d+)#i', [$this, 'wp_embed_handler_doubanablum']);
        wp_embed_register_handler('doubandrama', '#https?:\/\/www\.douban\.com\/location\/(\w+)\/(\d+)#i', [$this, 'wp_embed_handler_doubandrama']);

        wp_embed_register_handler('themoviedb', '#https?:\/\/www\.themoviedb\.org\/(\w+)\/(\d+)#i', [$this, 'wp_embed_handler_the_movie_db']);

        wp_embed_register_handler('neodb', '#https?://neodb\.social/(book|movie|tv|album|game|podcast|performance)/([a-zA-Z0-9]+)/?#i', [$this, 'wp_embed_handler_neodb']);

        add_action('rest_api_init', [$this, 'wpn_register_rest_routes']);
        add_filter("plugin_action_links_{$plugin_file}", [$this, 'plugin_action_links'], 10, 4);
        add_shortcode('wpd', [$this, 'list_shortcode']);
        add_shortcode('wpc', [$this, 'list_collection']);
        add_action('wp_head', [$this, 'db_custom_style']);
    }

    protected function get_genre_mapping()
    {
        return [
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
        if ($this->db_get_setting('css')) echo  '<style>' . $this->db_get_setting('css') . '</style>';
    }

    public function list_shortcode($atts, $content = null)
    {
        extract(shortcode_atts(
            array(
                'types' => '',
                'style' => ''
            ),
            $atts
        ));
        $types = explode(',', $types);
        if (empty($types)) {
            return;
        }
        return $this->render_template($types, $style);
    }

    public function list_collection($atts, $content = null)
    {
        extract(shortcode_atts(
            array(
                'type' => '',
                'start' => '',
                'end' => '',
                'status' => '',
                'style' => ''
            ),
            $atts
        ));
        return $this->render_collection($type, $start, $end, $status, $style);
    }

    function plugin_action_links($actions, $plugin_file, $plugin_data, $context)
    {
        $new = array(
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
                'https://fatesinger.com/101050',
                '帮助'
            ),
        );

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

    public function render_template($include_types = ['movie', 'music', 'book', 'game', 'drama'], $style)
    {
        $types = ['movie', 'music', 'book', 'game', 'drama'];
        $nav = '';
        $i = 0;
        foreach ($types as $type) {
            if (in_array($type, $include_types)) {
                $nav .= '<div class="db--navItem JiEun' . ($i == 0 ? ' current' : '') . '" data-type="' . $type . '">' . $type . '</div>';
                $i++;
            }
        }
        if (count($include_types) == 1) {
            $nav = '';
        }
        $only = count($include_types) == 1 ? " data-type='{$include_types[0]}'" : '';
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
    </div><div class="db--copyright">Rendered by <a href="https://fatesinger.com/101005" target="_blank">WP-NeoDB</a></div></section>';
    }

    public function wpn_register_rest_routes()
    {
        register_rest_route('v1', '/movies', array(
            'methods' => 'GET',
            'callback' => [$this, 'get_subjects'],
            'permission_callback' => '__return_true',
        ));

        register_rest_route('v1', '/movie/genres', array(
            'methods' => 'GET',
            'callback' => [$this, 'get_genres'],
            'permission_callback' => '__return_true',
        ));

        // AJAX data preview endpoint
        register_rest_route('wpn/v1', '/preview-source', array(
            'methods' => 'POST',
            'callback' => [$this, 'preview_source_data'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            }
        ));
    }

    public function get_genres($data)
    {
        $type = $data['type'] ? $data['type'] : 'movie';
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
        $type = $data['type'] ? $data['type'] : 'movie';
        $status = $data['status'] ? $data['status'] : 'done';
        $genre = $data['genre'] ? implode("','", json_decode($data['genre'], true)) : '';
        $endtime = $data['end_time'] ? $data['end_time'] : date('Y-m-d');
        $filterTime = ($data['start_time']) ? " AND f.create_time BETWEEN '{$data['start_time']}' AND '{$endtime}'" : '';
        $top250 = $type == 'book' ? $this->get_collection('book_top250') : $this->get_collection('movie_top250');

        if ($genre) {
            $goods = $wpdb->get_results("SELECT m.*, f.create_time , f.remark , f.status FROM ( $wpdb->douban_movies m LEFT JOIN $wpdb->douban_genres g ON m.id = g.movie_id ) LEFT JOIN $wpdb->douban_faves f ON m.id = f.subject_id WHERE f.type = '{$type}' AND f.status = '{$status}' AND g.name IN ('{$genre}') GROUP BY m.id ORDER BY f.create_time DESC LIMIT {$this->perpage} OFFSET {$offset}");
        } else {
            $goods = $wpdb->get_results("SELECT m.*, f.create_time, f.remark, f.status FROM $wpdb->douban_movies m LEFT JOIN $wpdb->douban_faves f ON m.id = f.subject_id WHERE f.type = '{$type}' AND f.status = '{$status}' {$filterTime} ORDER BY f.create_time DESC LIMIT {$this->perpage} OFFSET {$offset}");
        }

        $data = [];
        foreach ($goods as $good) {
            $good = $this->populate_db_movie_metadata($good);
            $good->create_time = date('Y-m-d', strtotime($good->create_time));
            $data[] = $good;
        }
        return new WP_REST_Response($data);
    }

    function wp_embed_handler_the_movie_db($matches, $attr, $url, $rawattr)
    {
        if ((!is_singular() && !$this->db_get_setting('home_render')) || !$this->db_get_setting('api_key')) return $url;
        $type = $matches[1];
        $id = $matches[2];
        if (!in_array($type, ['tv', 'movie'])) return $url;
        $html = $this->get_the_movie_db_detail($id, $type, $url);  // Pass original URL
        return apply_filters('embed_forbes', $html, $matches, $attr, $url, $rawattr);
    }

    function wp_embed_handler_doubandrama($matches, $attr, $url, $rawattr)
    {
        if (!is_singular() && !$this->db_get_setting('home_render')) return $url;
        $type = $matches[1];
        $id = $matches[2];
        if (!in_array($type, ['drama'])) return $url;
        $html = $this->get_subject_detail($id, $type);
        return apply_filters('embed_forbes', $html, $matches, $attr, $url, $rawattr);
    }

    function wp_embed_handler_doubanablum($matches, $attr, $url, $rawattr)
    {
        if (!is_singular() && !$this->db_get_setting('home_render')) return $url;
        $type = $matches[1];
        $id = $matches[2];
        if (!in_array($type, ['game'])) return $url;
        $html = $this->get_subject_detail($id, $type);
        return apply_filters('embed_forbes', $html, $matches, $attr, $url, $rawattr);
    }

    public function wp_embed_handler_doubanlist($matches, $attr, $url, $rawattr)
    {
        if (!is_singular() && !$this->db_get_setting('home_render')) return $url;
        $type = $matches[1];
        if (!in_array($type, ['movie', 'book', 'music'])) return $url;
        $id = $matches[2];
        $html = $this->get_subject_detail($id, $type, $url);  // Pass original URL
        return apply_filters('embed_forbes', $html, $matches, $attr, $url, $rawattr);
    }

    public function get_the_movie_db_detail($id, $type, $embed_url = null)
    {
        $type = $type ? $type : 'movie';
        $data = $this->fetch_tmdb_subject($id, $type);
        if (!$data) return;
        
        // Override link with embed URL if provided
        if ($embed_url) {
            $data->link = $embed_url;
        }
        
        $cover = $this->db_get_setting('download_image') ? $this->wpn_save_images($id, $data->poster, 'tmdb') : $data->poster;
        $output = '<div class="doulist-item"><div class="doulist-subject"><div class="doulist-post"><img referrerpolicy="no-referrer" src="' .  $cover . '"></div>';
        
        $meta_items = [];
        if (!empty($data->is_top250)) {
            $meta_items[] = 'Top 250';
        }
        if (db_get_setting("show_remark") && $data->fav_time) {
            $meta_items[] = 'Marked ' . date('Y-m-d', strtotime($data->fav_time));
        }
        
        if (!empty($meta_items)) {
            $output .= '<div class="db--viewTime JiEun">' . implode(' · ', $meta_items) . '</div>';
        }

        $output .= '<div class="doulist-content"><div class="doulist-title"><a href="' . ($data->link ? $data->link : "https://www.themoviedb.org/" . $type . "/" . $id) . '" class="cute" target="_blank" rel="external nofollow">' . $data->name . '</a></div>';
        $output .= '<div class="rating"><span class="allstardark"><span class="allstarlight" style="width:' . $data->douban_score * 10 . '%"></span></span><span class="rating_nums"> ' . $data->douban_score . ' </span></div>';
        $output .= '<div class="abstract">';
        $output .= $this->db_get_setting("show_remark") && $data->remark ? $data->remark : $data->card_subtitle;
        $output .= '</div></div></div></div>';
        return $output;
    }

    public function get_subject_detail($id, $type, $embed_url = null)
    {
        $type = $type ? $type : 'movie';
        $data = $this->fetch_subject($id, $type);
        if (!$data) return;
        
        // Override link with embed URL if provided
        if ($embed_url) {
            $data->link = $embed_url;
        }
        
        $cover = $this->db_get_setting('download_image') ? $this->wpn_save_images($id, $data->poster, 'douban') : (in_array($data->type, ['movie', 'book', 'music']) ? "https://dou.img.lithub.cc/" . $type . "/" . $data->douban_id . ".jpg" : $data->poster);
        $output = '<div class="doulist-item"><div class="doulist-subject"><div class="doulist-post"><img referrerpolicy="no-referrer" src="' .  $cover . '"></div>';
        
        $meta_items = [];
        if (!empty($data->is_top250)) {
            $meta_items[] = 'Top 250';
        }
        if ($this->db_get_setting("show_remark") && $data->fav_time) {
            $meta_items[] = 'Marked ' . date('Y-m-d', strtotime($data->fav_time));
        }
        
        if (!empty($meta_items)) {
            $output .= '<div class="db--viewTime JiEun">' . implode(' · ', $meta_items) . '</div>';
        }

        $output .= '<div class="doulist-content"><div class="doulist-title"><a href="' . $data->link . '" class="cute" target="_blank" rel="external nofollow">' . $data->name . '</a></div>';
        $output .= '<div class="rating"><span class="allstardark"><span class="allstarlight" style="width:' . $data->douban_score * 10 . '%"></span></span><span class="rating_nums"> ' . $data->douban_score . ' </span></div>';
        $output .= '<div class="abstract">';
        $output .= $this->db_get_setting("show_remark") && $data->remark ? $data->remark : $data->card_subtitle;
        $output .= '</div></div></div></div>';
        return $output;
    }

    public function sync_subject($id, $type)
    {
        $type = $type ? $type : 'movie';
        global $wpdb;
        $movie = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE `type` = '{$type}' AND id = '{$id}'");
        if (empty($movie)) {
            return false;
        }

        // NOTE: Check NeoDB BEFORE TMDB because NeoDB items may have extracted TMDB IDs
        // We want to sync from NeoDB API, not TMDB API, for NeoDB items

        // Handle NeoDB items
        if ($movie->neodb_id) {
            $neodb_url = $this->db_get_setting('neodb_url') ? $this->db_get_setting('neodb_url') : 'https://neodb.social';
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
            
            if ($data) {
                $description = $data['description'] ?? '';
                if (mb_strlen($description, 'UTF-8') > 250) {
                    $description = mb_substr($description, 0, 247, 'UTF-8') . '...';
                }

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

                $wpdb->update($wpdb->douban_movies, $update_data, ['id' => $movie->id]);
                
                // Log what was updated
                $fields_updated = array_keys($update_data);
                $this->add_log($movie->type, 'sync', 'neodb', "Updated fields: " . implode(', ', $fields_updated));

                // Update genres
                if (isset($data['genre']) && is_array($data['genre'])) {
                    // Delete existing genres
                    $wpdb->delete($wpdb->douban_genres, ['movie_id' => $movie->id]);
                    
                    $genre_map = $this->get_genre_mapping();
                    foreach ($data['genre'] as $genre) {
                        $final_genre = isset($genre_map[$genre]) ? $genre_map[$genre] : $genre;
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
            return false;
        }

        // Handle TMDB items (only if NOT a NeoDB item - checked above)
        if ($movie->tmdb_id) {
            $response = wp_remote_get("https://api.themoviedb.org/3/" . $movie->tmdb_type . "/" . $movie->tmdb_id . "?api_key=" . $this->db_get_setting('api_key') . "&language=zh-CN", ['sslverify' => false]);
            if (is_wp_error($response)) {
                return false;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if ($data) {
                $wpdb->update($wpdb->douban_movies, [
                    'name' => isset($data['title']) ? $data['title'] : $data['name'],
                   'poster' => "https://image.tmdb.org/t/p/original" . $data['poster_path'],
                    'tmdb_id' => $data['id'],  // FIXED: was incorrectly writing to douban_id
                    'douban_score' => $data['vote_average'],
                    'link' => $data['homepage'],
                    'year' => '',
                    'type' => 'movie',
                    'pubdate' => isset($data['release_date']) ? $data['release_date'] : $data['first_air_date'],
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


        $wpdb->update($wpdb->douban_movies, [
            'name' => $data['title'],
            'poster' => $data['pic']['large'],
            'douban_id' => $data['id'],
            'douban_score' => $data['rating']['value'],
            'link' => $data['url'],
            'year' => $data['year'] ? $data['year'] : '',
            'type' => $type,
            'pubdate' => $data['pubdate'] ? $data['pubdate'][0] : '',
            'card_subtitle' => $data['card_subtitle']
        ], ['id' => $movie->id]);
    }

    public function fetch_tmdb_subject($id, $type)
    {
        $type = $type ? $type : 'movie';
        global $wpdb;
        $movie = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE `tmdb_type` = '{$type}' AND tmdb_id = '{$id}'");
        if ($movie) {
            return $this->populate_db_movie_metadata($movie);
        }

        $transient_key = 'wpn_tmdb_' . $type . '_' . $id;
        $cached = get_transient($transient_key);
        if ($cached) return $cached;

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
                'name' => isset($data['title']) ? $data['title'] : $data['name'],
                'poster' => "https://image.tmdb.org/t/p/original" . $data['poster_path'],
                'douban_score' => $data['vote_average'],
                'link' => $data['homepage'],
                'year' => '',
                'type' => 'movie',
                'pubdate' => isset($data['release_date']) ? $data['release_date'] : $data['first_air_date'],
                'card_subtitle' => $data['overview'],
                'tmdb_type' => $type,
                'tmdb_id' => $data['id']  // FIXED: was incorrectly 'douban_id' => $data['id']
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
                }
                $movie_id = $existing_movie->id;
                $this->add_log($type, 'embed', 'tmdb', "merged with existing movie: {$existing_movie->name} (ID: {$existing_movie->id})");
            } else {
                // Insert new entry
                $wpdb->insert($wpdb->douban_movies, $insert_data);
                $movie_id = '';
                if ($wpdb->insert_id) {
                    $movie_id = $wpdb->insert_id;
                    $title = isset($data['title']) ? $data['title'] : $data['name'];
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
                'name' => isset($data['title']) ? $data['title'] : $data['name'],
                'poster' => "https://image.tmdb.org/t/p/original" . $data['poster_path'],
                'douban_id' => $data['id'],
                'douban_score' => $data['vote_average'],
                'link' => $data['homepage'],
                'year' => '',
                'type' => $type,
                'pubdate' => isset($data['release_date']) ? $data['release_date'] : $data['first_air_date'],
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

    public function fetch_subject($id, $type)
    {
        $type = $type ? $type : 'movie';
        global $wpdb;
        $movie = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE `type` = '{$type}' AND douban_id = '{$id}'");
        if ($movie) {
            return $this->populate_db_movie_metadata($movie);
        }

        $transient_key = 'wpn_douban_' . $type . '_' . $id;
        $cached = get_transient($transient_key);
        if ($cached) return $cached;

        if ($type == 'movie') {
            $link = $this->base_url . "movie/" . $id . "?ck=xgtY&for_mobile=1";
        } elseif ($type == 'book') {
            $link = $this->base_url . "book/" . $id . "?ck=xgtY&for_mobile=1";
        } elseif ($type == 'game') {
            $link = $this->base_url . "game/" . $id . "?ck=xgtY&for_mobile=1";
        } elseif ($type == 'drama') {
            $link = $this->base_url . "drama/" . $id . "?ck=xgtY&for_mobile=1";
        } else {
            $link = $this->base_url . "music/" . $id . "?ck=xgtY&for_mobile=1";
        }
        $response = wp_remote_get($link, ['sslverify' => false]);
        if (is_wp_error($response)) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if ($data) {
            // Use universal deduplication helper
            $existing_movie = $this->find_existing_movie(
                $type,
                $data['id'],  // douban_id
                0,  // No TMDB ID from Douban
                null,
                ''  // No NeoDB ID
            );

            $insert_data = [
                'name' => $data['title'],
                'poster' => $data['pic']['large'],
                'douban_id' => $data['id'],
                'douban_score' => $data['rating']['value'],
                'link' => $data['url'],
                'year' => $data['year'] ? $data['year'] : '',
                'type' => $type,
                'pubdate' => isset($data['pubdate']) ? $data['pubdate'][0] : '',
                'card_subtitle' => $data['card_subtitle']
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
                }
                $movie_id = $existing_movie->id;
                $this->add_log($type, 'embed', 'douban', "merged with existing movie: {$existing_movie->name} (ID: {$existing_movie->id})");
            } else {
                // Insert new entry
                $wpdb->insert($wpdb->douban_movies, $insert_data);
                $movie_id = '';
                if ($wpdb->insert_id) {
                    $movie_id = $wpdb->insert_id;
                    $this->add_log($type, 'embed', 'douban', $data['title']);
                }
            }

            // Insert genres
            if ($movie_id && $data['genres']) {
                foreach ($data['genres'] as $genre) {
                    $wpdb->insert(
                        $wpdb->douban_genres,
                        [
                            'movie_id' => $movie_id,
                            'name' => $genre,
                            'type' => $type,
                        ]
                    );
                }
            }

            $result = (object) [
                'id' => $movie_id,
                'name' => $data['title'],
                'poster' => $data['pic']['large'],
                'douban_id' => $data['id'],
                'douban_score' => $data['rating']['value'],
                'link' => $data['url'],
                'year' => $data['year'] ? $data['year'] : '',
                'type' => $type,
                'pubdate' => isset($data['pubdate']) ? $data['pubdate'][0] : '',
                'card_subtitle' => $data['card_subtitle'],
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

    public function fetch_neodb_subject($uuid, $neodb_type)
    {
        global $wpdb;

        // Check if item already exists by neodb_id
        $movie = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE `neodb_id` = '{$uuid}'");
        if ($movie) {
            return $this->populate_db_movie_metadata($movie);
        }

        $transient_key = 'wpn_neodb_' . $uuid;
        $cached = get_transient($transient_key);
        if ($cached) return $cached;

        // Fetch from NeoDB API
        $neodb_url = $this->db_get_setting('neodb_url') ? $this->db_get_setting('neodb_url') : 'https://neodb.social';
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

        // Map NeoDB type to WP-NeoDB type
        $type_map = [
            'book' => 'book',
            'movie' => 'movie',
            'tv' => 'movie',  // TV shows stored as movie
            'album' => 'music',
            'game' => 'game',
            'podcast' => 'podcast',
            'performance' => 'drama'
        ];
        $type = isset($type_map[$neodb_type]) ? $type_map[$neodb_type] : 'movie';

        // Truncate description to prevent insert failure (varchar 256 limit)
        $description = $data['description'] ?? '';
        if (mb_strlen($description, 'UTF-8') > 250) {
            $description = mb_substr($description, 0, 247, 'UTF-8') . '...';
        }


        // Extract external IDs (Douban ID, TMDB ID)
        $external_ids = $this->extract_external_ids_from_neodb($data);

        // Prepare data for insertion/update
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
            'neodb_id' => $uuid
        ];

        // Extract year from different possible fields
        if (isset($data['pub_year'])) {
            $insert_data['year'] = $data['pub_year'];
        } elseif (isset($data['year'])) {
            $insert_data['year'] = $data['year'];
        } elseif (isset($data['release_date'])) {
            $insert_data['year'] = substr($data['release_date'], 0, 4);
            $insert_data['pubdate'] = $data['release_date'];
        }

        // Use universal deduplication helper to check all ID types
        $existing_movie = $this->find_existing_movie(
            $type,
            $external_ids['douban_id'],
            $external_ids['tmdb_id'],
            $external_ids['tmdb_type'],
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
            }
            $movie_id = $existing_movie->id;
            $this->add_log($type, 'embed', 'neodb', "merged with existing movie: {$existing_movie->name} (ID: {$existing_movie->id})");
        } else {
            // Insert into database as new entry
            $wpdb->insert($wpdb->douban_movies, $insert_data);
            $movie_id = $wpdb->insert_id;

            // Log the embed action
            if ($movie_id) {
                $title = $data['display_title'] ?? $data['title'];
                $this->add_log($type, 'embed', 'neodb', $title);
            }
        }

        // Insert genres if available
        if ($movie_id && isset($data['genre']) && is_array($data['genre'])) {
            $genre_map = $this->get_genre_mapping();
            foreach ($data['genre'] as $genre) {
                $final_genre = isset($genre_map[$genre]) ? $genre_map[$genre] : $genre;
                $wpdb->insert(
                    $wpdb->douban_genres,
                    [
                        'movie_id' => $movie_id,
                        'name' => $final_genre,
                        'type' => $type,
                    ]
                );
            }
        }

        // Return formatted object
        $result = (object) array_merge($insert_data, [
            'id' => $movie_id,
            'genres' => $data['genre'] ?? [],
            'fav_time' => '',
            'remark' => '',
            'score' => ''
        ]);
        set_transient($transient_key, $result, 600);
        return $result;
    }

    function wp_embed_handler_neodb($matches, $attr, $url, $rawattr)
    {
        if ((!is_singular() && !$this->db_get_setting('home_render')))
            return $url;

        $neodb_type = $matches[1];  // book, movie, tv, album, etc
        $uuid = $matches[2];         // UUID

        $html = $this->get_neodb_subject_detail($uuid, $neodb_type, $url);  // Pass original URL
        return apply_filters('embed_neodb', $html, $matches, $attr, $url, $rawattr);
    }

    public function get_neodb_subject_detail($uuid, $neodb_type, $embed_url = null)
    {
        $data = $this->fetch_neodb_subject($uuid, $neodb_type);
        if (!$data)
            return '';
        
        // Override link with embed URL if provided
        if ($embed_url) {
            $data->link = $embed_url;
        }

        $cover = $this->db_get_setting('download_image') ? $this->wpn_save_images($uuid, $data->poster, 'neodb_') : $data->poster;
        $output = '<div class="doulist-item"><div class="doulist-subject"><div class="doulist-post"><img referrerpolicy="no-referrer" src="' . $cover . '"></div>';

        $meta_items = [];
        if (!empty($data->is_top250)) {
            $meta_items[] = 'Top 250';
        }
        if ($this->db_get_setting("show_remark") && $data->fav_time) {
            $status_label = 'Marked';
            if (isset($data->status)) {
                $status_map = ['mark' => '想看', 'doing' => '在看', 'done' => '看过', 'dropped' => '不看了'];
                $status_label = $status_map[$data->status] ?? 'Marked';
            }
            $score_text = $data->score ? ' ' . $data->score . '分' : '';
            $meta_items[] = date('Y-m-d', strtotime($data->fav_time)) . ' ' . $status_label . $score_text;
        }

        if (!empty($meta_items)) {
            $output .= '<div class="db--viewTime JiEun">' . implode(' · ', $meta_items) . '</div>';
        }

        $output .= '<div class="doulist-content"><div class="doulist-title"><a href="' . $data->link . '" class="cute" target="_blank" rel="external nofollow">' . $data->name . '</a></div>';
        $output .= '<div class="rating"><span class="allstardark"><span class="allstarlight" style="width:' . $data->douban_score * 10 . '%"></span></span><span class="rating_nums"> ' . $data->douban_score . ' </span></div>';
        $output .= '<div class="abstract">';
        $output .= $this->db_get_setting("show_remark") && $data->remark ? $data->remark : $data->card_subtitle;
        $output .= '</div></div></div></div>';

        return $output;
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
        $url = home_url('/') . 'douban_cache/' . $type . $id . '.jpg';
        return $url;
    }

    public function wpn_load_scripts()
    {
        wp_enqueue_style('wpn-css', WPN_URL . "/assets/css/db.min.css", [], WPN_VERSION, 'screen');
        $dark = $this->db_get_setting('dark_mode') == 'auto'  ? "@media (prefers-color-scheme: dark) {
            :root {
            --db-main-color: rgba(0, 87, 217);
            --db-hover-color: rgba(255, 255, 255, 0.5);
            --db--text-color: rgba(255, 255, 255, 0.8);
            --db--text-color-light: rgba(255, 255, 255, 0.6);
            --db--background-gray: #3c3c3c;
            --db-border-color: rgba(255, 255, 255, 0.1);
        }
    }" : ":root {
        --db-main-color: rgba(0, 87, 217);
        --db-hover-color: rgba(255, 255, 255, 0.5);
        --db--text-color: rgba(255, 255, 255, 0.8);
        --db--text-color-light: rgba(255, 255, 255, 0.6);
        --db--background-gray: #3c3c3c;
        --db-border-color: rgba(255, 255, 255, 0.1);
    }";
        if ($this->db_get_setting('dark_mode') == 'auto' || $this->db_get_setting('dark_mode') == 'dark') wp_add_inline_style('wpn-css', $dark);
        wp_enqueue_script('wpnjs', WPN_URL . "/assets/js/db.min.js", [], WPN_VERSION, true);
        wp_localize_script('wpnjs', 'wpn_base', [
            'api' => get_rest_url(),
            'token' => $this->db_get_setting('token'),
        ]);
    }

    public function get_collections($name = 'movie_top250')
    {
        $url = "{$this->base_url}subject_collection/{$name}/items?start=0&count=250&items_only=1&ck=xgtY&for_mobile=1";
        $response = wp_remote_get($url);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $interests = $data['subject_collection_items'];
        global $wpdb;
        $collection = $this->get_collection($name);
        $collection_id = '';
        if (!$collection) {
            $wpdb->insert($wpdb->douban_collection, [
                'douban_id' => $name,
                'name' => $name,
            ]);
            $collection_id = $wpdb->insert_id;
        } else {
            $collection_id =  $collection->id;
        }

        foreach ($interests as $interest) {
            // Use universal deduplication - check by douban_id, neodb_id, or tmdb_id
            $existing_movie = $this->find_existing_movie(
                $interest['type'],
                $interest['id'],  // douban_id
                0,
                null,
                ''
            );

            // Extract year from card_subtitle (format: "1994 / 美国 / 剧情...")
            $year = '';
            if (preg_match('/^(\d{4})/', $interest['card_subtitle'], $matches)) {
                $year = $matches[1];
            }

            // Prepare Top 250 data
            $top250_data = array(
                'name' => $interest['title'],
                'poster' => $interest['pic']['large'],
                'douban_id' => $interest['id'],
                'douban_score' => $interest['rating']['value'],
                'link' => $interest['url'],
                'year' => $year,
                'type' => $interest['type'],
                'pubdate' => '',
                'card_subtitle' => $interest['card_subtitle'],
            );

            if ($existing_movie) {
                // Movie exists - smart merge to fill in missing fields
                $update_data = $this->smart_merge_movie_data($existing_movie, $top250_data);
                if (!empty($update_data)) {
                    $wpdb->update($wpdb->douban_movies, $update_data, ['id' => $existing_movie->id]);
                }
                $movie_id = $existing_movie->id;
            } else {
                // Insert new movie
                $wpdb->insert($wpdb->douban_movies, $top250_data);
                $movie_id = $wpdb->insert_id;
            }

            // Manage Top 250 relation
            $relation = $wpdb->get_row("SELECT * FROM $wpdb->douban_relation WHERE `movie_id` = {$movie_id} AND `collection_id` = {$collection_id}");

            if (!$relation) {
                $wpdb->insert($wpdb->douban_relation, [
                    'movie_id' => intval($movie_id),
                    'collection_id' => intval($collection_id)
                ]);
            }
        }
    }

    public function preview_source_data($request)
    {
        $subject_id = intval($request['subject_id']);
        $source = sanitize_text_field($request['source']);
        $action = sanitize_text_field($request['action'] ?? 'edit_subject');

        global $wpdb;
        $subject = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE id = {$subject_id}");

        if (!$subject) {
            return [
                'success' => false,
                'message' => 'Subject not found'
            ];
        }

        $fresh_data = null;

        // For edit_fave mode, fetch user marking data
        if ($action == 'edit_fave') {
            switch ($source) {
                case 'neodb':
                    if ($subject->neodb_id && $this->db_get_setting('neodb_token')) {
                        $neodb_url = $this->db_get_setting('neodb_url') ?: 'https://neodb.social';
                        
                        // Use shelf API to get user's mark
                        $shelf_url = rtrim($neodb_url, '/') . "/api/me/shelf/item/" . $subject->neodb_id;
                        $headers = ['Authorization' => 'Bearer ' . $this->db_get_setting('neodb_token')];
                        
                        $response = wp_remote_get($shelf_url, ['headers' => $headers, 'sslverify' => false]);
                        
                        if (!is_wp_error($response)) {
                            $status_code = wp_remote_retrieve_response_code($response);
                            
                            if ($status_code == 200) {
                                $data = json_decode(wp_remote_retrieve_body($response), true);
                                
                                if ($data) {
                                    $status_map = ['complete' => 'done', 'progress' => 'doing', 'wishlist' => 'mark', 'dropped' => 'dropped'];
                                    
                                    // Convert UTC from API to site local time for the datetime-local input
                                    $created_time = isset($data['created_time']) ? get_date_from_gmt($data['created_time'], 'Y-m-d\TH:i:s') : '';
                                    
                                    $fresh_data = (object) [
                                        'create_time' => $created_time,
                                        'status' => $status_map[$data['shelf_type']] ?? 'done',
                                        'remark' => $data['comment_text'] ?? '',
                                        'score' => $data['rating_grade'] ?? ''
                                    ];
                                }
                            } else if ($status_code == 404) {
                                return ['success' => false, 'message' => '您尚未在NeoDB标记此条目'];
                            } else {
                                return ['success' => false, 'message' => 'NeoDB API返回错误: ' . $status_code];
                            }
                        } else {
                            return ['success' => false, 'message' => 'NeoDB API请求失败: ' . $response->get_error_message()];
                        }
                    } else {
                        return ['success' => false, 'message' => '需要配置NeoDB Token才能同步标记数据'];
                    }
                    break;
            }
        } else {
            // edit_subject mode - fetch basic subject info
            switch ($source) {
                case 'douban':
                    if ($subject->douban_id) {
                        $type = $subject->type;
                        if ($type == 'movie') {
                            $link = $this->base_url . "movie/" . $subject->douban_id . "?ck=xgtY&for_mobile=1";
                        } elseif ($type == 'book') {
                            $link = $this->base_url . "book/" . $subject->douban_id . "?ck=xgtY&for_mobile=1";
                        } elseif ($type == 'game') {
                            $link = $this->base_url . "game/" . $subject->douban_id . "?ck=xgtY&for_mobile=1";
                        } elseif ($type == 'drama') {
                            $link = $this->base_url . "drama/" . $subject->douban_id . "?ck=xgtY&for_mobile=1";
                        } else {
                            $link = $this->base_url . "music/" . $subject->douban_id . "?ck=xgtY&for_mobile=1";
                        }
                        
                        $response = wp_remote_get($link, ['sslverify' => false]);
                        if (!is_wp_error($response)) {
                            $data = json_decode(wp_remote_retrieve_body($response), true);
                            if ($data) {
                                $fresh_data = (object) [
                                    'name' => $data['title'],
                                    'poster' => $data['pic']['large'],
                                    'douban_score' => $data['rating']['value'],
                                    'card_subtitle' => $data['card_subtitle']
                                ];
                            }
                        }
                    }
                    break;
                case 'neodb':
                    if ($subject->neodb_id) {
                        $neodb_type_map = ['movie' => 'movie', 'book' => 'book', 'music' => 'album', 'game' => 'game', 'drama' => 'performance'];
                        $neodb_type = $neodb_type_map[$subject->type] ?? 'movie';
                        $url = "https://neodb.social/api/{$neodb_type}/" . $subject->neodb_id;
                        
                        $response = wp_remote_get($url, ['sslverify' => false]);
                        if (!is_wp_error($response)) {
                            $data = json_decode(wp_remote_retrieve_body($response), true);
                            if ($data && !isset($data['error'])) {
                                $fresh_data = (object) [
                                    'name' => $data['title'] ?? '',
                                    'poster' => $data['cover_image_url'] ?? '',
                                    'douban_score' => $data['rating'] ?? '',
                                    'card_subtitle' => $data['brief'] ?? ''
                                ];
                            }
                        }
                    }
                    break;
                case 'tmdb':
                    if ($subject->tmdb_id && $subject->tmdb_type) {
                        $api_key = $this->db_get_setting('api_key');
                        if ($subject->tmdb_type == 'movie') {
                            $url = "https://api.themoviedb.org/3/movie/{$subject->tmdb_id}?api_key={$api_key}&language=zh-CN";
                        } else {
                            $url = "https://api.themoviedb.org/3/tv/{$subject->tmdb_id}?api_key={$api_key}&language=zh-CN";
                        }
                        
                        $response = wp_remote_get($url, ['sslverify' => false]);
                        if (!is_wp_error($response)) {
                            $data = json_decode(wp_remote_retrieve_body($response), true);
                            if ($data && !isset($data['success']) && !isset($data['status_code'])) {
                                $title = isset($data['title']) ? $data['title'] : (isset($data['name']) ? $data['name'] : '');
                                $poster = '';
                                if (isset($data['poster_path']) && $data['poster_path']) {
                                    $poster = "https://image.tmdb.org/t/p/original" . $data['poster_path'];
                                }
                                
                                $fresh_data = (object) [
                                    'name' => $title,
                                    'poster' => $poster,
                                    'douban_score' => $data['vote_average'] ?? '',
                                    'card_subtitle' => $data['overview'] ?? ''
                                ];
                            }
                        }
                    }
                    break;
            }
        }

        if (!$fresh_data) {
            return ['success' => false, 'message' => 'Failed to fetch data from ' . $source];
        }

        // Build response based on action
        $response_data = [];
        if ($action == 'edit_fave') {
            $response_data = [
                'create_time' => $fresh_data->create_time ?? '',
                'status' => $fresh_data->status ?? '',
                'remark' => $fresh_data->remark ?? '',
                'score' => $fresh_data->score ?? ''
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

    private function populate_db_movie_metadata($movie)
    {
        if (!$movie) return null;
        global $wpdb;

        // Populate Genres
        if (empty($movie->genres)) {
            $movie->genres = [];
            $genres = $wpdb->get_results("SELECT * FROM $wpdb->douban_genres WHERE `movie_id` = {$movie->id}");
            if (!empty($genres)) {
                foreach ($genres as $genre) {
                    $movie->genres[] = $genre->name;
                }
            }
        }

        // Populate Favorite data
        $fav = $wpdb->get_row("SELECT * FROM $wpdb->douban_faves WHERE `subject_id` = '{$movie->id}'");
        if ($fav) {
            $movie->fav_time = $fav->create_time;
            $movie->score = $fav->score;
            $movie->remark = $fav->remark;
            $movie->status = $fav->status;
        } else {
            $movie->fav_time = !isset($movie->fav_time) ? "" : $movie->fav_time;
            $movie->score = !isset($movie->score) ? "" : $movie->score;
            $movie->remark = !isset($movie->remark) ? "" : $movie->remark;
        }

        // Handle Image Cache
        if ($this->db_get_setting('download_image')) {
            $cache_id = $movie->douban_id;
            $cache_prefix = '';
            if (!empty($movie->neodb_id)) {
                $cache_id = $movie->neodb_id;
                $cache_prefix = 'neodb_';
            } elseif (!empty($movie->tmdb_id)) {
                $cache_id = $movie->tmdb_id;
                $cache_prefix = 'tmdb';
            }
            $movie->poster = $this->wpn_save_images($cache_id, $movie->poster, $cache_prefix);
        } else if (in_array($movie->type, ['movie', 'book', 'music']) && !empty($movie->douban_id)) {
            // Use image proxy if not downloading
            $movie->poster = "https://dou.img.lithub.cc/" . $movie->type . "/" . $movie->douban_id . ".jpg";
        }
        
        // Check Top 250
        if ($this->db_get_setting('top250') && ($movie->type == 'movie' || $movie->type == 'book') && !empty($movie->douban_id)) {
            $collection_name = $movie->type == 'book' ? 'book_top250' : 'movie_top250';
            $collection = $this->get_collection($collection_name);
            if ($collection) {
                // Cross-source support: check if any record with this douban_id is in Top 250
                $re = $wpdb->get_results("SELECT r.id FROM $wpdb->douban_relation r JOIN $wpdb->douban_movies m ON r.movie_id = m.id WHERE m.douban_id = '{$movie->douban_id}' AND r.collection_id = {$collection->id}");
                $movie->is_top250 = !empty($re);
            }
        }
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
            
            // 豆瓣 ID
            if ($result['douban_id'] === 0 && preg_match('/douban\.com\/subject\/(\d+)/', $url, $matches)) {
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
            if ($movie) return $movie;
        }

        // Priority 2: Check by douban_id
        if ($douban_id > 0) {
            $movie = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $wpdb->douban_movies WHERE type = %s AND douban_id = %d",
                $type,
                $douban_id
            ));
            if ($movie) return $movie;
        }

        // Priority 3: Check by tmdb_id
        if ($tmdb_id > 0 && $tmdb_type) {
            $movie = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $wpdb->douban_movies WHERE tmdb_type = %s AND tmdb_id = %d",
                $tmdb_type,
                $tmdb_id
            ));
            if ($movie) return $movie;
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
        $fillable_fields = ['name', 'poster', 'douban_score', 'link', 'year', 'pubdate', 'card_subtitle'];
        foreach ($fillable_fields as $field) {
            if (isset($new_data[$field]) && !empty($new_data[$field])) {
                // Check if existing field is empty
                if (!isset($existing_movie->$field) || 
                    $existing_movie->$field === '' || 
                    $existing_movie->$field === null || 
                    $existing_movie->$field === '0' ||
                    $existing_movie->$field === 0) {
                    $update_data[$field] = $new_data[$field];
                }
            }
        }

        return $update_data;
    }
}