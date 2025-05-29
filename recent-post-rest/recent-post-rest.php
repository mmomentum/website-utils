<?php
/**
 * Plugin Name: Recent Post API
 * Description: Adds a REST API endpoint to get the most recent post.
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Hook to register the REST API route
add_action('rest_api_init', function () {
    register_rest_route('recent-post-api/v1', '/latest-post/', array(
        'methods' => 'GET',
        'callback' => 'get_most_recent_notify_or_latest_post',
        'permission_callback' => '__return_true', // Allow access to all users
    ));
});

// Callback function to return the most recent "notify"-tagged post, or fallback
function get_most_recent_notify_or_latest_post() {
    // First, try to get the most recent post with the "notify" tag
    $args_with_notify = array(
        'numberposts' => 1,
        'post_status' => 'publish',
        'tag' => 'notify',
    );

    $posts = wp_get_recent_posts($args_with_notify);

    // If no post with "notify" tag found, get the most recent post
    if (empty($posts)) {
        $args_fallback = array(
            'numberposts' => 1,
            'post_status' => 'publish',
        );
        $posts = wp_get_recent_posts($args_fallback);
    }

    // Still no post found
    if (empty($posts)) {
        return new WP_Error('no_posts', 'No posts found', array('status' => 404));
    }

    $post = $posts[0];

    $response = array(
        'id' => $post['ID'],
        'title' => $post['post_title'],
        'content' => $post['post_content'],
        'excerpt' => wp_trim_words($post['post_content'], 40, '...'),
        'date' => $post['post_date'],
        'author' => get_the_author_meta('display_name', $post['post_author']),
        'link' => get_permalink($post['ID']),
        'featured_image' => get_the_post_thumbnail_url($post['ID'], 'medium'),
    );

    return rest_ensure_response($response);
}
