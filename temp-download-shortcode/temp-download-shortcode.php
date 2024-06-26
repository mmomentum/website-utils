<?php
/**
 * Plugin Name: Temporary Download Shortcode
 * Description: Creates a temporary download link to a resource via use of a [expiring_download_link] shortcode.
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

function expiring_download_link_shortcode($atts)
{
    // Extract shortcode attributes
    $atts = shortcode_atts(
        array(
            'file' => '', // file path on the server
            'text' => 'Download File', // Default link text
            'expires' => 345600, // default expiration time in seconds (4 days)
        ),
        $atts,
        'download_link'
    );

    // Check if file path is provided
    if (empty($atts['file'])) {
        return 'Error: File path not specified.';
    }

    $file_path = ABSPATH . $atts['file'];

    // Check if file exists
    if (!file_exists($file_path)) {
        return 'Error: File not found.';
    }

    // Generate unique token for download link
    $token = md5(uniqid());

    // Calculate expiration time
    $expires = time() + intval($atts['expires']);

    // Save token and expiration time in transient with expiration time
    set_transient('temp_download_link_' . $token, $file_path, $expires);

    // Generate download link with token as query parameter
    $download_url = add_query_arg(array('token' => $token), home_url('/temp_download'));

    $link_text = esc_html($atts['text']);
    // Return download link HTML
    return '<a href="' . esc_url($download_url) . '">' . $link_text . '</a>';
}
add_shortcode('expiring_download_link', 'expiring_download_link_shortcode');

// Function to handle download requests
function custom_handle_download_request()
{
    if (isset($_GET['token'])) {
        if (isset($_GET['eddfile'])) // bail out if it's an EDD file (so it uses the original functionality)
            return;

        // Retrieve file path from transient using token
        $file = get_transient('temp_download_link_' . $_GET['token']);

        // Check if file path is valid
        if ($file && file_exists($file)) {
            // Set appropriate headers

            // use EDD to determine the `Content-Type` header. `edd_get_file_ctype()` has a LOT of possibilities
            $file_extension = edd_get_file_extension($file);
            $ctype = edd_get_file_ctype($file_extension);

            nocache_headers();
            header('Robots: none');
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $ctype);
            header('Content-Disposition: attachment; filename=' . basename($file));
            header('Content-Length: ' . @filesize($file));
            header('Content-Transfer-Encoding: binary');
         
            edd_deliver_download($file);

            exit;
        } else {
            // Invalid token or file not found
            wp_die('Invalid download link.');
        }
    }
}
add_action('init', 'custom_handle_download_request');