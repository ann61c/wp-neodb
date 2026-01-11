<?php

add_action('admin_menu', 'db_menu');
function db_menu()
{
    add_menu_page('WP NeoDB 设置', 'WP NeoDB', 'manage_options', 'wpneodb', 'db_setting_page', 'dashicons-chart-pie');
    add_submenu_page('wpneodb', 'WP NeoDB 设置', '参数设置', 'manage_options', 'wpneodb', 'db_setting_page');
    add_submenu_page('wpneodb', '我的条目', '我的条目', 'manage_options', 'subject', 'db_subject_page');
    add_submenu_page('wpneodb', '所有条目', '所有条目', 'manage_options', 'subject_all', 'db_all_subject_page');
    add_submenu_page('wpneodb', '插件日志', '插件日志', 'manage_options', 'log', 'db_log_page');
    add_submenu_page(null, '编辑条目', '编辑条目', 'manage_options', 'subject_edit', 'db_edit_subject_page');

    add_action('admin_init', 'db_setting_group');
    add_action('admin_enqueue_scripts', 'db_admin_scripts');
}

function db_admin_scripts($hook) {
    if (strpos($hook, 'page_subject') !== false || strpos($hook, 'page_subject_all') !== false) {
        wp_enqueue_style('wpn-admin-common', WPN_URL . '/assets/css/admin-common.css', [], WPN_VERSION);
    }
    
    if (strpos($hook, 'page_subject_all') !== false) {
        wp_enqueue_style('wpn-admin-delete', WPN_URL . '/assets/css/admin-delete.css', [], WPN_VERSION);
        wp_enqueue_script('wpn-admin-delete', WPN_URL . '/assets/js/admin-delete.js', ['jquery'], WPN_VERSION, true);
    }

    if (strpos($hook, 'page_subject_edit') !== false) {
        wp_enqueue_style('wpn-admin-subject-edit', WPN_URL . '/assets/css/admin-subject-edit.css', [], WPN_VERSION);
        wp_enqueue_script('wpn-admin-subject-edit', WPN_URL . '/assets/js/admin-subject-edit.js', ['jquery'], WPN_VERSION, true);

        $subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
        $action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : 'edit_fave';
        wp_localize_script('wpn-admin-subject-edit', 'wpn_subject_edit', [
            'rest_url' => rest_url('wpn/v1/preview-source'),
            'subject_id' => $subject_id,
            'action' => $action,
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }
}

function db_setting_group()
{
    register_setting('db_setting_group', 'db_setting');
}

function db_edit_subject_page()
{
    @include WPN_PATH . '/tpl/tpl-subject-edit.php';
}


function db_log_page()
{
    @include WPN_PATH . '/tpl/tpl-log.php';
}

function db_all_subject_page()
{
    @include WPN_PATH . '/tpl/tpl-subject-all.php';
}


function db_subject_page()
{
    @include WPN_PATH . '/tpl/tpl-subject.php';
}


function db_setting_page()
{
    @include WPN_PATH . '/tpl/tpl-setting.php';
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
