<?php

class db_sync extends WPD_Douban
{

    private $base_url = 'https://fatesinger.com/dbapi/';
    private $uid;

    public function __construct()
    {
        $this->uid = $this->db_get_setting('id');
        add_action('db_sync', [$this, 'db_sync_data']);
    }

    private function get_genre_mapping()
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

    public function db_fecth($start = 0, $type = 'movie', $status = '')
    {
        $url = "{$this->base_url}user/{$this->uid}/interests?count=49&start={$start}&type={$type}&status={$status}";
        $response = wp_remote_get($url);
        $data = json_decode(wp_remote_retrieve_body($response), true);
        $interests = $data['interests'];
        return $interests;
    }

    /**
     * Fetch marks from NeoDB API
     * @param string $shelf_type wishlist|progress|complete
     * @param string $category book|movie|tv|music|game|podcast|performance
     * @param int $page
     * @return array|false
     */
    public function neodb_fetch($shelf_type = 'complete', $category = null, $page = 1)
    {
        $neodb_url = $this->db_get_setting('neodb_url') ? $this->db_get_setting('neodb_url') : 'https://neodb.social';
        $token = $this->db_get_setting('neodb_token');
        
        if (!$token) {
            return false;
        }

        $api_url = rtrim($neodb_url, '/') . "/api/me/shelf/{$shelf_type}?page={$page}";
        if ($category) {
            $api_url .= "&category={$category}";
        }

        $response = wp_remote_get($api_url, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'sslverify' => false
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data;
    }

    /**
     * Sync NeoDB marks
     */
    public function neodb_sync_data()
    {
        $token = $this->db_get_setting('neodb_token');
        if (!$token) {
            return false;
        }

        global $wpdb;

        // Map NeoDB shelf types to WP-Douban status
        $shelf_status_map = [
            'wishlist' => 'mark',   // 想看
            'progress' => 'doing',  // 在看
            'complete' => 'done',   // 看过
            'dropped'  => 'dropped' // 不看了
        ];

        // Map NeoDB categories to WP-Douban types
        $category_type_map = [
            'book' => 'book',
            'movie' => 'movie',
            'tv' => 'movie',
            'music' => 'music',
            'game' => 'game',
            'podcast' => 'podcast',
            'performance' => 'drama',
        ];

        $categories = ['book', 'movie', 'tv', 'music', 'game', 'performance'];
        $sync_count = 0;

        foreach ($shelf_status_map as $shelf_type => $wpd_status) {
            foreach ($categories as $category) {
                $page = 1;
                $has_more = true;

                while ($has_more) {
                    $data = $this->neodb_fetch($shelf_type, $category, $page);
                    
                    if (!$data || empty($data['data'])) {
                        $has_more = false;
                        continue;
                    }

                    foreach ($data['data'] as $mark) {
                        if (!isset($mark['item'])) {
                            continue;
                        }

                        $item = $mark['item'];
                        $neodb_id = $item['uuid'] ?? null;
                        if (!$neodb_id) {
                            continue;
                        }

                        $wpd_type = $category_type_map[$category] ?? 'movie';

                        // Check if item exists in database
                        $movie = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM $wpdb->douban_movies WHERE neodb_id = %s",
                            $neodb_id
                        ));

                        // Truncate description to prevent insert failure
                        $description = $item['brief'] ?? '';
                        if (mb_strlen($description, 'UTF-8') > 250) {
                            $description = mb_substr($description, 0, 247, 'UTF-8') . '...';
                        }

                        if (!$movie) {
                            // Insert new item
                            $wpdb->insert($wpdb->douban_movies, [
                                'name' => $item['display_title'] ?? $item['title'],
                                'poster' => $item['cover_image_url'] ?? '',
                                'douban_id' => 0,
                                'douban_score' => $item['rating'] ?? 0,
                                'link' => $item['url'] ?? '',
                                'year' => $item['year'] ?? '',
                                'type' => $wpd_type,
                                'card_subtitle' => $description,
                                'neodb_id' => $neodb_id,
                            ]);

                            if ($wpdb->insert_id) {
                                $movie_id = $wpdb->insert_id;
                                $sync_count++;
                                $last_synced_name = $item['display_title'] ?? $item['title'];

                                // Insert genres
                                if (isset($item['genre']) && is_array($item['genre'])) {
                                    $genre_map = $this->get_genre_mapping();
                                    foreach ($item['genre'] as $genre) {
                                        $final_genre = isset($genre_map[$genre]) ? $genre_map[$genre] : $genre;
                                        $wpdb->insert($wpdb->douban_genres, [
                                            'movie_id' => $movie_id,
                                            'name' => $final_genre,
                                            'type' => $wpd_type,
                                        ]);
                                    }
                                }

                                // Insert fave record
                                $wpdb->insert($wpdb->douban_faves, [
                                    'create_time' => $mark['created_time'] ?? date('Y-m-d H:i:s'),
                                    'remark' => $mark['comment_text'] ?? '',
                                    'score' => $mark['rating_grade'] ?? '',
                                    'subject_id' => $movie_id,
                                    'type' => $wpd_type,
                                    'status' => $wpd_status,
                                ]);
                            }
                        } else {
                            // Item exists, check/update fave record
                            $movie_id = $movie->id;
                            $fav = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM $wpdb->douban_faves WHERE subject_id = %d",
                                $movie_id
                            ));

                            if (!$fav) {
                                // Insert new fave
                                $wpdb->insert($wpdb->douban_faves, [
                                    'create_time' => $mark['created_time'] ?? date('Y-m-d H:i:s'),
                                    'remark' => $mark['comment_text'] ?? '',
                                    'score' => $mark['rating_grade'] ?? '',
                                    'subject_id' => $movie_id,
                                    'type' => $wpd_type,
                                    'status' => $wpd_status,
                                ]);
                                $sync_count++;
                                $last_synced_name = $item['display_title'] ?? $item['title'];
                            } else if ($fav->status != $wpd_status || $fav->remark != ($mark['comment_text'] ?? '')) {
                                // Update existing fave if status or comment changed
                                $wpdb->update($wpdb->douban_faves, [
                                    'create_time' => $mark['created_time'] ?? date('Y-m-d H:i:s'),
                                    'remark' => $mark['comment_text'] ?? '',
                                    'score' => $mark['rating_grade'] ?? '',
                                    'status' => $wpd_status,
                                ], ['id' => $fav->id]);
                            }
                        }
                    }

                    // Check pagination
                    $page++;
                    $has_more = isset($data['pages']) && $page <= $data['pages'];
                }
            }
        }

        // Log the sync
        if ($sync_count > 0) {
            $log_message = $sync_count == 1 ? "synced {$last_synced_name}" : "synced {$sync_count} items";
            $this->add_log('mixed', 'sync', 'neodb', $log_message);
        }

        return $sync_count;
    }

    public function db_sync_data()
    {

        $sync_types = [
            'movie',
            'music',
            'book',
            'game',
            'drama'
        ];

        $status = [
            'done',
            'doing',
            'mark'
        ];
        global $wpdb;

        if ($this->db_get_setting('top250')) $this->get_collections('movie_top250');
        if ($this->db_get_setting('book_top250')) $this->get_collections('book_top250');
        
        // Sync NeoDB if token is configured
        if ($this->db_get_setting('neodb_token')) {
            $this->neodb_sync_data();
        }

        if (!$this->uid) {
            return false;
        }
        foreach ($sync_types as $type) {
            foreach ($status as $stat) {
                $confition = true;
                $i = 0;
                while ($confition) {
                    $data = $this->db_fecth(49 * $i, $type, $stat);
                    if (empty($data)) {
                        $confition = false;
                    } else {
                        foreach ($data as $interest) {
                            if (!isset($interest['subject'])) {
                                continue;
                            }

                            $movie = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE `type` = '{$type}' AND douban_id = {$interest['subject']['id']}");
                            if (!$movie) {
                                $wpdb->insert(
                                    $wpdb->douban_movies,
                                    [
                                        'name' => $interest['subject']['title'],
                                        'poster' => $interest['subject']['pic']['large'],
                                        'douban_id' => $interest['subject']['id'],
                                        'douban_score' => $interest['subject']['rating']['value'],
                                        'link' => $interest['subject']['url'],
                                        'year' => $interest['subject']['year'],
                                        'type' => $type,
                                        'pubdate' => $interest['subject']['pubdate'] ? $interest['subject']['pubdate'][0] : '',
                                        'card_subtitle' => $interest['subject']['card_subtitle'],
                                    ]
                                );
                                if ($wpdb->insert_id) {
                                    $movie_id = $wpdb->insert_id;
                                    foreach ($interest['subject']['genres'] as $genre) {
                                        $wpdb->insert(
                                            $wpdb->douban_genres,
                                            [
                                                'movie_id' => $movie_id,
                                                'name' => $genre,
                                                'type' => $type,
                                            ]
                                        );
                                    }
                                    $wpdb->insert(
                                        $wpdb->douban_faves,
                                        [
                                            'create_time' => $interest['create_time'],
                                            'remark' => $interest['comment'],
                                            'score' => $interest['rating'] ? $interest['rating']['value'] : '',
                                            'subject_id' => $movie_id,
                                            'type' => $type,
                                            'status' => $interest['status'],
                                        ]
                                    );
                                }
                            } else {
                                $movie_id = $movie->id;
                                $fav = $wpdb->get_row("SELECT * FROM $wpdb->douban_faves WHERE `type` = '{$type}'  AND subject_id = {$movie_id}");
                                if (!$fav) {
                                    $wpdb->insert(
                                        $wpdb->douban_faves,
                                        [
                                            'create_time' => $interest['create_time'],
                                            'remark' => $interest['comment'],
                                            'score' => $interest['rating'] ? $interest['rating']['value'] : '',
                                            'subject_id' => $movie_id,
                                            'status' => $interest['status'],
                                            'type' => $type,
                                        ]
                                    );
                                } else if ($fav->status != $interest['status']) {
                                    $wpdb->update(
                                        $wpdb->douban_faves,
                                        [
                                            'create_time' => $interest['create_time'],
                                            'remark' => $interest['comment'],
                                            'score' => $interest['rating'] ? $interest['rating']['value'] : '',
                                            'status' => $interest['status'],
                                        ],
                                        [
                                            'id' => $fav->id,
                                        ]
                                    );
                                } else {
                                    $confition = false;
                                }
                            }
                        }
                        $i++;
                    }
                }
            }
            $this->add_log($type, 'sync', 'douban');
        }
    }
}

new db_sync();
