<?php

class db_sync extends WPN_NeoDB
{

    private $base_url = 'https://fatesinger.com/dbapi/';
    private $uid;

    public function __construct()
    {
        $this->uid = $this->db_get_setting('id');
        add_action('db_sync', [$this, 'db_sync_data']);
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

        // Map NeoDB shelf types to WP-NeoDB status
        $shelf_status_map = [
            'wishlist' => 'mark',   // 想看
            'progress' => 'doing',  // 在看
            'complete' => 'done',   // 看过
            'dropped'  => 'dropped' // 不看了
        ];

        // Map NeoDB categories to WP-NeoDB types
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
        $processed_count = 0;

        foreach ($shelf_status_map as $shelf_type => $wpn_status) {
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

                        $wpn_type = $category_type_map[$category] ?? 'movie';

                        // Extract external IDs (Douban ID, TMDB ID) first
                        $external_ids = $this->extract_external_ids_from_neodb($item);

                        // Use universal deduplication - check by neodb_id, douban_id, or tmdb_id
                        $movie = $this->find_existing_movie(
                            $wpn_type,
                            $external_ids['douban_id'],
                            $external_ids['tmdb_id'],
                            $external_ids['tmdb_type'],
                            $neodb_id
                        );

                        // Truncate description to prevent insert failure
                        $description = $item['brief'] ?? '';
                        if (mb_strlen($description, 'UTF-8') > 250) {
                            $description = mb_substr($description, 0, 247, 'UTF-8') . '...';
                        }

                        // Prepare sync data
                        $sync_data = [
                            'name' => $item['display_title'] ?? $item['title'],
                            'poster' => $item['cover_image_url'] ?? '',
                            'douban_id' => $external_ids['douban_id'],
                            'tmdb_id' => $external_ids['tmdb_id'],
                            'tmdb_type' => $external_ids['tmdb_type'],
                            'douban_score' => $item['rating'] ?? 0,
                            'link' => $item['url'] ?? '',
                            'year' => $item['year'] ?? '',
                            'type' => $wpn_type,
                            'card_subtitle' => $description,
                            'neodb_id' => $neodb_id,
                        ];

                        if ($movie) {
                            // Movie exists - smart merge to fill in missing fields
                            $update_data = $this->smart_merge_movie_data($movie, $sync_data);
                            if (!empty($update_data)) {
                                $wpdb->update($wpdb->douban_movies, $update_data, ['id' => $movie->id]);
                            }
                            $movie_id = $movie->id;
                        } else {
                            // Insert new item
                            $wpdb->insert($wpdb->douban_movies, $sync_data);
                            $movie_id = $wpdb->insert_id;
                            if ($movie_id) {
                                $processed_count++;
                            }
                        }

                        if ($movie_id) {
                            // Check/update fave record
                            $fav = $wpdb->get_row($wpdb->prepare(
                                "SELECT * FROM $wpdb->douban_faves WHERE subject_id = %d",
                                $movie_id
                            ));

                            if (!$fav) {
                                // Insert new fave
                                $wpdb->insert($wpdb->douban_faves, [
                                    'create_time' => isset($mark['created_time']) ? get_gmt_from_date($mark['created_time']) : current_time('mysql', 1),
                                    'remark' => $mark['comment_text'] ?? '',
                                    'score' => $mark['rating_grade'] ?? '',
                                    'subject_id' => $movie_id,
                                    'type' => $wpn_type,
                                    'status' => $wpn_status,
                                ]);
                                if (!$movie) {
                                    $processed_count++;
                                }
                            } else if ($fav->status != $wpn_status || $fav->remark != ($mark['comment_text'] ?? '')) {
                                // Update existing fave if status or comment changed
                                $wpdb->update($wpdb->douban_faves, [
                                    'create_time' => $mark['created_time'] ?? date('Y-m-d H:i:s'),
                                    'remark' => $mark['comment_text'] ?? '',
                                    'score' => $mark['rating_grade'] ?? '',
                                    'status' => $wpn_status,
                                ], ['id' => $fav->id]);
                                if (!$movie) {
                                    $processed_count++;
                                }
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
        if ($processed_count > 0) {
            $this->add_log('batch', 'sync', 'neodb', "processed {$processed_count} items");
        }

        return $processed_count;
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
                                            'create_time' => get_gmt_from_date($interest['create_time']),
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
