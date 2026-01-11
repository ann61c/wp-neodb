<?php
/*
Plugin Name: WP-NeoDB
Plugin URI: https://fatesinger.com/101005
Description: 🎬 📖 🎵 🎮 manage your movie / book / music / game records
Version: 5.0.0
Author: Bigfa
Author URI: https://fatesinger.com
License: MIT
License URI: https://opensource.org/licenses/MIT
Text Domain: wp-neodb
*/

define('WPN_VERSION', '5.0.0');
define('WPN_URL', plugins_url('', __FILE__));
define('WPN_PATH', dirname(__FILE__));
define('WPN_ADMIN_URL', admin_url());

### DB Table Name
global $wpdb;
$wpdb->douban_collection   = $wpdb->prefix . 'douban_collection';
$wpdb->douban_faves   = $wpdb->prefix . 'douban_faves';
$wpdb->douban_genres  = $wpdb->prefix . 'douban_genres';
$wpdb->douban_movies  = $wpdb->prefix . 'douban_movies';
$wpdb->douban_relation  = $wpdb->prefix . 'douban_relation';
$wpdb->douban_log  = $wpdb->prefix . 'douban_log';

/**
 * Remove schedule job
 */
register_deactivation_hook(__FILE__, 'db_deactivation');
function db_deactivation()
{
    wp_clear_scheduled_hook('db_sync');
}

/**
 * Remove schedule job
 * Drop database table
 */
register_uninstall_hook(__FILE__, 'db_uninstall');
function db_uninstall()
{
    wp_clear_scheduled_hook('db_sync');
    // Delete setting
    delete_option('db_setting');
    // Drop database table
    global $wpdb;
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->douban_collection}");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->douban_faves}");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->douban_genres}");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->douban_movies}");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->douban_relation}");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->douban_log}");
}

/**
 * Create Database on install
 * Add schedule job
 * Creat cache folder
 */

register_activation_hook(__FILE__, 'wpn_install');
function wpn_install()
{
    // Create sechedule sync job
    wp_schedule_event(time(), 'hourly', 'db_sync');
    $thumb_path = ABSPATH . "douban_cache/";
    if (file_exists($thumb_path)) {
        if (!is_writeable($thumb_path)) {
            @chmod($thumb_path, 0755);
        }
    } else {
        @mkdir($thumb_path, 0755, true);
    }

    // Create DB Tables (5 Tables)
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $create_table = [];
    $update_table = [];
    $create_table['douban_collection'] = "CREATE TABLE $wpdb->douban_collection (" .
        "id int(10) NOT NULL auto_increment," .
        "name varchar(256) default ''," .
        "poster varchar(256) default ''," .
        "douban_id varchar(32) default ''," .
        "PRIMARY KEY  (id)," .
        "KEY douban_id (douban_id)" .
        ") $charset_collate;";

    $create_table['douban_faves'] = "CREATE TABLE $wpdb->douban_faves (" .
        "id int(10) NOT NULL auto_increment," .
        "subject_id int(10) default 0," .
        "remark varchar(512) default ''," .
        "create_time datetime," .
        "type varchar(16) default ''," .
        "score varchar(16) default ''," .
        "status varchar(16) default ''," .
        "PRIMARY KEY  (id)," .
        "KEY subject_id (subject_id)," .
        "KEY type (type)," .
        "KEY status (status)" .
        ") $charset_collate;";

    $create_table['douban_genres'] = "CREATE TABLE $wpdb->douban_genres (" .
        "id int(10) NOT NULL auto_increment," .
        "movie_id int(10) default 0," .
        "name varchar(16) default ''," .
        "type varchar(16) default 'movie'," .
        "PRIMARY KEY  (id)," .
        "KEY movie_id (movie_id)" .
        ") $charset_collate;";

    $create_table['douban_movies'] = "CREATE TABLE $wpdb->douban_movies (" .
        "id int(10) NOT NULL auto_increment," .
        "name varchar(256)," .
        "poster varchar(512)," .
        "link varchar(256)," .
        //   "`delete` int," .
        "douban_id int," .
        "douban_score varchar(16)," .
        "year varchar(16)," .
        "type varchar(16)," .
        "pubdate varchar(32)," .
        "faves int," .
        "card_subtitle varchar(256)," .
        "tmdb_id int," .
        "tmdb_type varchar(16)," .
        "neodb_id varchar(64)," .
        "PRIMARY KEY (id)," .
        "KEY douban_id (douban_id)," .
        "KEY neodb_id (neodb_id)," .
        "KEY tmdb_id (tmdb_id)," .
        "KEY type (type)" .
        ") $charset_collate;";

    $create_table['douban_relation'] = "CREATE TABLE $wpdb->douban_relation (" .
        "id int(10) NOT NULL auto_increment," .
        "movie_id int default 0," .
        "collection_id int default 0," .
        "PRIMARY KEY  (id)," .
        "KEY movie_id (movie_id)," .
        "KEY collection_id (collection_id)" .
        ") $charset_collate;";

    $create_table['douban_log'] = "CREATE TABLE $wpdb->douban_log (" .
        "id int(10) NOT NULL auto_increment," .
        "type varchar(16)," .
        "action varchar(16)," .
        "source varchar(16) DEFAULT 'douban'," .
        "create_time datetime," .
        "status varchar(16)," .
        "message varchar(256)," .
        "account_id varchar(16)," .
        "collection_id varchar(16)," .
        "subject_id varchar(16)," .
        "PRIMARY KEY (id)" .
        ") $charset_collate;";

    $update_table['douban_movies'] = "ALTER TABLE {$wpdb->douban_movies} ADD COLUMN `tmdb_id` int(10) AFTER `douban_id`, ADD COLUMN `tmdb_type` varchar(16) AFTER `tmdb_id`;";
    $update_table['neodb'] = "ALTER TABLE {$wpdb->douban_movies} ADD COLUMN `neodb_id` varchar(64) AFTER `tmdb_type`;";
    $update_table['log_source'] = "ALTER TABLE {$wpdb->douban_log} ADD COLUMN `source` varchar(16) DEFAULT 'douban' AFTER `action`;";

    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->douban_collection}'") != $wpdb->douban_collection) dbDelta($create_table['douban_collection']);
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->douban_faves}'") != $wpdb->douban_faves) dbDelta($create_table['douban_faves']);
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->douban_genres}'") != $wpdb->douban_genres) dbDelta($create_table['douban_genres']);
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->douban_relation}'") != $wpdb->douban_relation) dbDelta($create_table['douban_relation']);
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->douban_log}'") != $wpdb->douban_log) dbDelta($create_table['douban_log']);
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->douban_movies}'") != $wpdb->douban_movies) {
        dbDelta($create_table['douban_movies']);
    } elseif (!$wpdb->get_results("SHOW COLUMNS FROM {$wpdb->douban_movies} LIKE 'tmdb_id'")) { // update database movie table since 4.4.0
        $wpdb->query($update_table['douban_movies']);
    } elseif (!$wpdb->get_results("SHOW COLUMNS FROM {$wpdb->douban_movies} LIKE 'neodb_id'")) { // update database movie table for NeoDB support
        $wpdb->query($update_table['neodb']);
    }
    
    // Add source column to log table for existing installations
    if ($wpdb->get_var("SHOW TABLES LIKE '{$wpdb->douban_log}'") == $wpdb->douban_log) {
        if (!$wpdb->get_results("SHOW COLUMNS FROM {$wpdb->douban_log} LIKE 'source'")) {
            $wpdb->query($update_table['log_source']);
        }
    }
    update_option('wpn_db_version', WPN_VERSION);
}

/**
 * Load classes
 */
require WPN_PATH . '/src/functions.php';
require WPN_PATH . '/src/setup.php';
require WPN_PATH . '/src/db.php';
require WPN_PATH . '/src/admin.php';

new WPN_NeoDB();
new WPN_ADMIN();

// Auto-update database if needed (hotfix for existing users)
add_action('admin_init', 'wpn_auto_update_db');
function wpn_auto_update_db() {
    $installed_ver = get_option('wpn_db_version');
    if ($installed_ver !== WPN_VERSION) {
        wpn_install();
    }
}
