<?php

class WPD_ADMIN extends WPD_Douban
{
    public function __construct()
    {
        add_action('wp_ajax_wpd_import', [$this, 'import']);
        add_action('init', [$this, 'action_handle_posts']);
    }

    private function wpd_remove_images($id)
    {
        $e = ABSPATH . 'douban_cache/' . $id . '.jpg';
        if (!is_file($e)) return;
        unlink($e);
    }

    public function action_handle_posts()
    {
        $sendback = wp_get_referer();
        if (isset($_GET['wpd_action'])  && 'cancel_mark' === $_GET['wpd_action'] && wp_verify_nonce($_GET['_wpnonce'], 'wpd_subject_' . $_GET['subject_id'])) {
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

        if (isset($_GET['wpd_action'])  && 'mark' === $_GET['wpd_action'] && wp_verify_nonce($_GET['_wpnonce'], 'wpd_subject_' . $_GET['subject_id'])) {
            global $wpdb;
            $wpdb->insert(
                $wpdb->douban_faves,
                [
                    'subject_id' => $_GET['subject_id'],
                    'type' => $_GET['subject_type'],
                    'create_time' => current_time('mysql'),
                    'status' => 'done'
                ]
            );
            wp_redirect($sendback);
            exit;
        }


        if (isset($_GET['wpd_action'])  && 'sync_subject' === $_GET['wpd_action'] && wp_verify_nonce($_GET['_wpnonce'], 'wpd_subject_' . $_GET['subject_id'])) {
            $this->sync_subject($_GET['subject_id'], $_GET['subject_type']);
            wp_redirect($sendback);
            exit;
        }


        if (isset($_GET['wpd_action']) && 'empty_log' === $_GET['wpd_action']) {
            global $wpdb;
            $wpdb->query("TRUNCATE TABLE $wpdb->douban_log");
            wp_redirect($sendback);
            exit;
        }


        if (isset($_POST['wpd_action']) && 'edit_fave' === $_POST['wpd_action']) {
            global $wpdb;
            if (isset($_POST['status']) && $_POST['status']) {
                $wpdb->update(
                    $wpdb->douban_faves,
                    [
                        'remark' => $_POST['remark'],
                        'score' => $_POST['score'],
                        'create_time' => $_POST['create_time'],
                        'status' => $_POST['status'],
                    ],
                    [
                        'id' => $_POST['fave_id'],
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

        if (isset($_POST['wpd_action']) && 'edit_subject' === $_POST['wpd_action']) {
            global $wpdb;
            $subject = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE id = '{$_POST['subject_id']}'");
            $this->wpd_remove_images($subject->douban_id);
            $wpdb->update(
                $wpdb->douban_movies,
                [
                    'name' => $_POST['name'],
                    'douban_score' => $_POST['douban_score'],
                    'card_subtitle' => $_POST['card_subtitle'],
                    'poster' => $_POST['poster']
                ],
                [
                    'id' => $_POST['subject_id'],
                ]
            );
            $link = array(
                'page' => 'subject_all',
            );
            $link = add_query_arg($link, admin_url('admin.php'));
            wp_redirect($link);
            exit;
        }

        if (isset($_GET['wpd_action']) && 'refresh_from_source' === $_GET['wpd_action']) {
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
                            // Determine NeoDB type from WP-Douban type
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

        if (isset($_GET['wpd_action']) && 'delete_subject' === $_GET['wpd_action']) {
            global $wpdb;
            $subject = $wpdb->get_row("SELECT * FROM $wpdb->douban_movies WHERE id = '{$_GET['subject_id']}'");
            $this->wpd_remove_images($subject->douban_id);

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
}
