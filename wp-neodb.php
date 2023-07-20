<?php
/*
Plugin Name: WP-NeoDB
Plugin URI: https://github.com/ann61c/wp-neodb/
Description: This plugin integrates NeoDB into WordPress, allowing users to display their marked movies, TV shows, and music.
Version: 1.0
Author: Taco
License: GPL2
*/


// Activation and deactivation hooks
function wp_neodb_activate() {
    // This code runs when the plugin is activated
}

function wp_neodb_deactivate() {
    // This code runs when the plugin is deactivated
}

register_activation_hook(__FILE__, 'wp_neodb_activate');
register_deactivation_hook(__FILE__, 'wp_neodb_deactivate');

// Enqueue CSS and JavaScript files
function wp_neodb_enqueue_scripts() {
    wp_enqueue_style('wp-neodb-styles', plugin_dir_url(__FILE__) . 'styles.css');
    wp_enqueue_script('wp-neodb-scripts', plugin_dir_url(__FILE__) . 'scripts.js', array('jquery'), '1.0', true);
}

add_action('wp_enqueue_scripts', 'wp_neodb_enqueue_scripts');

// Shortcode
function wp_neodb_shortcode($atts) {
    $data = get_neodb_data();
    if ($data === false) {
        return 'Failed to fetch data from NeoDB API.';
    }

    // Process $data and generate HTML
    $output = '';
    // Add code here to generate your HTML from the $data array.

    return $output;
}

add_shortcode('wp-neodb', 'wp_neodb_shortcode');

// Function to interact with the NeoDB API
function get_neodb_data() {
    $response = wp_remote_get('https://neodb-api-url.com/endpoint');

    if (is_wp_error($response)) {
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    return $data;
}