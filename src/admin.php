<?php

class WPN_ADMIN extends WPN_NeoDB
{
    public function __construct()
    {
        add_action('wp_ajax_wpn_import', [$this, 'import']);
        add_action('wp_ajax_wpn_delete_subject', [$this, 'ajax_delete_subject']);
        add_action('init', [$this, 'action_handle_posts']);
    }

    private function wpn_remove_images($id)
    {
        $e = ABSPATH . 'douban_cache/' . $id . '.jpg';
        if (!is_file($e)) return;
        unlink($e);
    }

    public function action_handle_posts()
    {
        $sendback = wp_get_referer();
        if (isset($_GET['wpn_action'])  && 'cancel_mark' === $_GET['wpn_action'] && wp_verify_nonce($_GET['_wpnonce'], 'wpn_subject_' . $_GET['subject_id'])) {
            global $wpdb;
            $wpdb->delete(
                $wpdb->douban_faves,
                [
                    'subject_id' => $_GET['subject_id'],
                    'type' => $_GET['subject_type'],
                ]
            );
            wp_redirect($sendback);
            exit;
        }

        if (isset($_GET['wpn_action'])  && 'mark' === $_GET['wpn_action'] && wp_verify_nonce($_GET['_wpnonce'], 'wpn_subject_' . $_GET['subject_id'])) {
            global $wpdb;
                $wpdb->insert(
                $wpdb->douban_faves,
                [
                    'subject_id' => $_GET['subject_id'],
                    'type' => $_GET['subject_type'],
                    'create_time' => current_time('mysql', 1),
                    'status' => 'done'
                ]
            );
            wp_redirect($sendback);
            exit;
        }


        if (isset($_GET['wpn_action'])  && 'sync_subject' === $_GET['wpn_action'] && wp_verify_nonce($_GET['_wpnonce'], 'wpn_subject_' . $_GET['subject_id'])) {
            $this->sync_subject($_GET['subject_id'], $_GET['subject_type']);
            wp_redirect($sendback);
            exit;
        }


        if (isset($_GET['wpn_action']) && 'empty_log' === $_GET['wpn_action']) {
            global $wpdb;
            $wpdb->query("TRUNCATE TABLE $wpdb->douban_log");
            wp_redirect($sendback);
            exit;
        }


        if (isset($_POST['wpn_action']) && 'edit_fave' === $_POST['wpn_action']) {
            global $wpdb;
            if (isset($_POST['status']) && $_POST['status']) {
                // Validate status against allowed values
                $allowed_statuses = ['mark', 'doing', 'done', 'dropped'];
                $status_value = in_array($_POST['status'], $allowed_statuses) ? $_POST['status'] : 'done';
                
                $wpdb->update(
                    $wpdb->douban_faves,
                    [
                        'remark' => sanitize_textarea_field($_POST['remark']),
                        'score' => intval($_POST['score']),
                        'create_time' => get_gmt_from_date(sanitize_text_field($_POST['create_time'])),
                        'status' => $status_value,
                    ],
                    [
                        'id' => intval($_POST['fave_id']),
                    ]
                );
            } else {
                $wpdb->delete(
                    $wpdb->douban_faves,
                    [
                        'subject_id' => $_GET['subject_id'],
                        'type' => $_GET['subject_type'],
                    ]
                );
            }
            $link = array(
                'page'                  => 'subject',
            );
            $link = add_query_arg($link, admin_url('admin.php'));
            wp_redirect($link);
            exit;
        }

        if (isset($_POST['wpn_action']) && 'edit_subject' === $_POST['wpn_action']) {
            global $wpdb;
            $subject_id = intval($_POST['subject_id']);
            $subject = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE id = {$subject_id}");
            $this->wpn_remove_images($subject->douban_id);
            $wpdb->update(
                $wpdb->douban_movies,
                [
                    'name' => sanitize_text_field($_POST['name']),
                    'douban_score' => floatval($_POST['douban_score']),
                    'card_subtitle' => sanitize_textarea_field($_POST['card_subtitle']),
                    'poster' => esc_url_raw($_POST['poster'])
                ],
                [
                    'id' => $subject_id,
                ]
            );
            $link = array(
                'page' => 'subject_all',
            );
            $link = add_query_arg($link, admin_url('admin.php'));
            wp_redirect($link);
            exit;
        }

        if (isset($_GET['wpn_action']) && 'refresh_from_source' === $_GET['wpn_action']) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'refresh_source_' . $_GET['subject_id'])) {
                wp_die('Security check failed');
            }

            global $wpdb;
            $subject_id = intval($_GET['subject_id']);
            $source = sanitize_text_field($_GET['source']);
            $subject = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE id = {$subject_id}");

            if ($subject) {
                // Fetch fresh data from the selected source
                $fresh_data = null;
                switch ($source) {
                    case 'douban':
                        if ($subject->douban_id) {
                            $fresh_data = $this->fetch_subject($subject->douban_id, $subject->type);
                        }
                        break;
                    case 'neodb':
                        if ($subject->neodb_id) {
                            // Determine NeoDB type from WP-NeoDB type
                            $neodb_type_map = ['movie' => 'movie', 'book' => 'book', 'music' => 'album', 'game' => 'game', 'drama' => 'performance'];
                            $neodb_type = $neodb_type_map[$subject->type] ?? 'movie';
                            $fresh_data = $this->fetch_neodb_subject($subject->neodb_id, $neodb_type);
                        }
                        break;
                    case 'tmdb':
                        if ($subject->tmdb_id && $subject->tmdb_type) {
                            $fresh_data = $this->fetch_tmdb_subject($subject->tmdb_id, $subject->tmdb_type);
                        }
                        break;
                }

                if ($fresh_data) {
                    // Full update: manual refresh should overwrite existing data
                    $update_data = [
                        'name' => $fresh_data->name ?? $subject->name,
                        'poster' => $fresh_data->poster ?? $subject->poster,
                        'douban_score' => $fresh_data->douban_score ?? $subject->douban_score,
                        'link' => $fresh_data->link ?? $subject->link,
                        'year' => $fresh_data->year ?? $subject->year,
                        'pubdate' => $fresh_data->pubdate ?? $subject->pubdate,
                        'card_subtitle' => $fresh_data->card_subtitle ?? $subject->card_subtitle
                    ];
                    
                    $wpdb->update($wpdb->douban_movies, $update_data, ['id' => $subject_id]);
                    $this->add_log($subject->type, 'refresh', $source, "Refreshed subject ID {$subject_id} from {$source}");
                }
            }

            // Redirect back to edit page
            $link = add_query_arg([
                'page' => 'subject_all',
                'action' => 'edit_subject',
                'subject_id' => $subject_id
            ], admin_url('admin.php'));
            wp_redirect($link);
            exit;
        }

        if (isset($_GET['wpn_action']) && 'delete_subject' === $_GET['wpn_action']) {
            global $wpdb;
            $subject_id = intval($_GET['subject_id']);
            $subject = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE id = {$subject_id}");
            $this->wpn_remove_images($subject->douban_id);

            $wpdb->delete(
                $wpdb->douban_faves,
                [
                    'subject_id' => $_GET['subject_id'],
                    'type' => 'movie',
                ]
            );

            $wpdb->delete(
                $wpdb->douban_movies,
                [
                    'id' => $_GET['subject_id'],
                ]
            );


            $link = array(
                'page'                  => 'subject_all',
            );
            $link = add_query_arg($link, admin_url('admin.php'));
            wp_redirect($link);
            exit;
        }



        // if (isset($_POST['crontrol_action']) && 'export-event-csv' === $_POST['crontrol_action']) {

        //     $type = isset($_POST['crontrol_hooks_type']) ? $_POST['crontrol_hooks_type'] : 'all';
        //     $headers = array(
        //         'hook',
        //         'arguments',
        //         'next_run',
        //         'next_run_gmt',
        //         'action',
        //         'recurrence',
        //         'interval',
        //     );
        //     $filename = sprintf(
        //         'cron-events-%s-%s.csv',
        //         $type,
        //         gmdate('Y-m-d-H.i.s')
        //     );
        //     $csv = fopen('php://output', 'w');

        //     if (false === $csv) {
        //         wp_die(esc_html__('Could not save CSV file.', 'wp-crontrol'));
        //     }

        //     header('Content-Type: text/csv; charset=utf-8');
        //     header(
        //         sprintf(
        //             'Content-Disposition: attachment; filename="%s"',
        //             esc_attr($filename)
        //         )
        //     );

        //     fputcsv($csv, $headers);

        //     if (isset($events[$type])) {
        //         foreach ($events[$type] as $event) {
        //             $row = array();
        //             fputcsv($csv, $row);
        //         }
        //     }

        //     fclose($csv);

        //     exit;
        // }
    }

    // public function import()
    // {
    //     global $wpdb;
    //     if (!isset($_FILES['file'])) {
    //         wp_send_json_error(esc_html__('File missing', 'mmp'));
    //     }

    //     $details = array();
    //     $file = $_FILES['file']['tmp_name'];
    //     $handle = fopen($file, 'r');
    //     while (($data = fgetcsv($handle)) !== false) {
    //         $douban_id = explode('/', $data['6'])[4];
    //         if ($douban_id) {
    //             $movie = $wpdb->get_results("SELECT * FROM wp_douban_movies WHERE douban_id = '{$douban_id}'");
    //             $movie = $movie[0];
    //             if ($movie->name == '未知电影' || $movie->name == '未知电视剧') {
    //                 $wpdb->update('wp_douban_movies', ['name' => trim(explode('/',  $data['0'])[0]), 'poster' => str_replace('webp', 'jpg', $data['7'])], ['douban_id' => $douban_id]);
    //             }
    //         }
    //         $details[] = $data;
    //     }
    //     fclose($handle);

    //     wp_send_json_success(array(
    //         'details' => $details
    //     ));
    // }

    public function ajax_delete_subject()
    {
        // Verify nonce
        $subject_id = intval($_POST['subject_id']);
        check_ajax_referer('wpn_delete_subject_' . $subject_id, 'nonce');

        global $wpdb;
        $subject = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE id = '{$subject_id}'");
        
        if (!$subject) {
            wp_send_json_error(['message' => '条目不存在']);
            return;
        }

        // Remove cached images
        $this->wpn_remove_images($subject->douban_id);
        
        // Also remove NeoDB and TMDB cached images if they exist
        if ($subject->neodb_id) {
            $this->wpn_remove_images('neodb_' . $subject->neodb_id);
        }
        if ($subject->tmdb_id) {
            $this->wpn_remove_images('tmdb' . $subject->tmdb_id);
        }

        // Delete from faves table
        $wpdb->delete(
            $wpdb->douban_faves,
            [
                'subject_id' => $subject_id,
                'type' => $subject->type,
            ]
        );

        // Delete from movies table
        $deleted = $wpdb->delete(
            $wpdb->douban_movies,
            [
                'id' => $subject_id,
            ]
        );

        if ($deleted) {
            wp_send_json_success(['message' => '条目已删除']);
        } else {
            wp_send_json_error(['message' => '删除失败']);
        }
    }
}
