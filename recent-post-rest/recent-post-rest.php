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
        'callback' => 'get_most_recent_post',
        'permission_callback' => '__return_true', // Allow access to all users
    ));
});

// Callback function to return the most recent post
function get_most_recent_post() {
    // Get the most recent post
    $args = array(
        'numberposts' => 1, // Only one post
        'post_status' => 'publish', // Only published posts
    );
    
    $recent_posts = wp_get_recent_posts($args);
    
    // If no posts found, return an error
    if (empty($recent_posts)) {
        return new WP_Error('no_posts', 'No posts found', array('status' => 404));
    }

    // Get the first (and only) post
    $post = $recent_posts[0];
    
    // Prepare the response data
    $response = array(
        'id' => $post['ID'],
        'title' => $post['post_title'],
        'content' => $post['post_content'],
        'excerpt' => wp_trim_words($post['post_content'], 40, '...'), // Generate the excerpt
        'date' => $post['post_date'],
        'author' => get_the_author_meta('display_name', $post['post_author']),
        'link' => get_permalink($post['ID']),
        'featured_image' => get_the_post_thumbnail_url($post['ID'], 'medium'), // Get scaled image (medium size)
    );

    return rest_ensure_response($response);
}