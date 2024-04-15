<?php
/**
 * Plugin Name: Mailpoet Discount Shortcode
 * Description: Generates & Embeds an EDD discount code into a mailpoet email
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

add_filter('mailpoet_newsletter_shortcode', 'mailpoet_custom_shortcode', 10, 6);

function generate_key($length)
{
    $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string = '';

    $max = strlen($characters) - 1;
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[mt_rand(0, $max)];
    }

    return $string;
}
function mailpoet_custom_shortcode($shortcode, $newsletter, $subscriber, $queue, $newsletter_body, $arguments)
{
    // always return the shortcode if it doesn't match your own!
    if ($shortcode !== '[custom:discount_code]')
        return $shortcode;

    $code = generate_key(8);

    $current_time = time();

    // timestamp for one week from now
    $one_week_ahead = $current_time + (7 * 24 * 60 * 60);
    $one_week_ahead_formatted = date('Y-m-d H:i:00', $one_week_ahead);

    $discount_args = array(
        'status' => 'active',
        'name' => 'Demo Email Discount',
        'code' => $code,
        'amount' => '30.00',
        'amount_type' => 'percent',
        'product_condition' => 'all',
        'scope' => 'not_global',
        'max_uses' => '1',
        'type' => 'discount',
        'end_date' => $one_week_ahead_formatted
    );

    edd_add_discount($discount_args);

    // return the discount code as text
    return "<b>$code</b>";
}
