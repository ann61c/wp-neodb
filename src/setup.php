<?php

add_action('admin_menu', 'db_menu');
function db_menu()
{
    add_menu_page('WP DOUBAN 设置', 'WP_DOUBAN', 'manage_options', 'wpdouban', 'db_setting_page', 'dashicons-chart-pie');
    add_submenu_page('wpdouban', 'WP DOUBAN 设置', '参数设置', 'manage_options', 'wpdouban', 'db_setting_page');
    add_submenu_page('wpdouban', '我的条目', '我的条目', 'manage_options', 'subject', 'db_subject_page');
    add_submenu_page('wpdouban', '所有条目', '所有条目', 'manage_options', 'subject_all', 'db_all_subject_page');
    add_submenu_page('wpdouban', '插件日志', '插件日志', 'manage_options', 'log', 'db_log_page');
    add_submenu_page(null, '编辑条目', '编辑条目', 'manage_options', 'subject_edit', 'db_edit_subject_page');

    add_action('admin_init', 'db_setting_group');
}

function db_setting_group()
{
    register_setting('db_setting_group', 'db_setting');
}

function db_edit_subject_page()
{
    wp_enqueue_style('wpd-admin-subject-edit', WPD_URL . '/assets/css/admin-subject-edit.css', [], WPD_VERSION);
    wp_enqueue_script('wpd-admin-subject-edit', WPD_URL . '/assets/js/admin-subject-edit.js', ['jquery'], WPD_VERSION, true);

    $subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
    wp_localize_script('wpd-admin-subject-edit', 'wpd_subject_edit', [
        'rest_url' => rest_url('wpd/v1/preview-source'),
        'subject_id' => $subject_id,
        'nonce' => wp_create_nonce('wp_rest')
    ]);

    @include WPD_PATH . '/tpl/tpl-subject-edit.php';
}

function db_log_page()
{
    @include WPD_PATH . '/tpl/tpl-log.php';
}

function db_all_subject_page()
{
    wp_enqueue_style('wpd-admin-delete', WPD_URL . '/assets/css/admin-delete.css', [], WPD_VERSION);
    wp_enqueue_script('wpd-admin-delete', WPD_URL . '/assets/js/admin-delete.js', ['jquery'], WPD_VERSION, true);
    
    @include WPD_PATH . '/tpl/tpl-subject-all.php';
}

function db_subject_page()
{
    @include WPD_PATH . '/tpl/tpl-subject.php';
}


function db_setting_page()
{
    @include WPD_PATH . '/tpl/tpl-setting.php';
}

function db_get_setting($key = NULL)
{
    $setting = get_option('db_setting');
    if (isset($setting[$key])) {
        return $setting[$key];
    } else {
        return false;
    }
}

function db_delete_setting()
{
    delete_option('db_setting');
}

function db_setting_key($key)
{
    if ($key) {
        return "db_setting[$key]";
    }

    return false;
}
function db_update_setting($setting)
{
    update_option('db_setting', $setting);
}
