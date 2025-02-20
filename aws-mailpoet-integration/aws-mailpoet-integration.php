<?php
/**
 * Plugin Name: AWS SNS to MailPoet
 * Description: Handles AWS SNS notifications for SES and updates MailPoet user status, because Mailpoet's SES sending action doesn't cover bounce & complaint handling
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Register REST API route
add_action('rest_api_init', function () {
    register_rest_route('aws-sns/v1', '/notification', [
        'methods'  => 'POST',
        'callback' => 'aws_sns_mailpoet_handle_notification',
        'permission_callback' => '__return_true', // Allow public access
    ]);
});

function aws_sns_mailpoet_handle_notification(WP_REST_Request $request) {
    global $wpdb;

    // Get raw POST data
    $data = $request->get_body();
    $decoded = json_decode($data, true);

    if (!$decoded || !isset($decoded['Message'])) {
        error_log("AWS SNS: Invalid JSON received");
        return new WP_REST_Response(['error' => 'Invalid JSON'], 400);
    }

    // Decode the actual SES message from SNS payload
    $sesMessage = json_decode($decoded['Message'], true);
    if (!$sesMessage || !isset($sesMessage['notificationType'])) {
        error_log("AWS SNS: Invalid SES message format");
        return new WP_REST_Response(['error' => 'Invalid SES message'], 400);
    }

    // Extract bounced or complained email addresses
    $emails = [];
    if ($sesMessage['notificationType'] === 'Bounce') {
        foreach ($sesMessage['bounce']['bouncedRecipients'] as $recipient) {
            $emails[] = sanitize_email($recipient['emailAddress']);
        }
    } elseif ($sesMessage['notificationType'] === 'Complaint') {
        foreach ($sesMessage['complaint']['complainedRecipients'] as $recipient) {
            $emails[] = sanitize_email($recipient['emailAddress']);
        }
    }

    // If no emails found, return success but log
    if (empty($emails)) {
        error_log("AWS SNS: No email addresses found in the notification");
        return new WP_REST_Response(['message' => 'No affected emails'], 200);
    }

    // Update MailPoet database table
    $table_name = $wpdb->prefix . 'mailpoet_subscribers';

    foreach ($emails as $email) {
        $result = $wpdb->update(
            $table_name,
            ['status' => 'bounced'], 
            ['email' => $email],
            ['%s'],
            ['%s']
        );

        if ($result !== false) {
            error_log("AWS SNS: Marked as bounced in database - $email");
        } else {
            error_log("AWS SNS: Failed to update status for - $email");
        }
    }

    return new WP_REST_Response(['message' => 'Processed successfully'], 200);
}

