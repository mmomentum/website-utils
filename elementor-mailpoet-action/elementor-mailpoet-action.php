<?php
/**
 * Plugin Name: Elementor Mailpoet Subscribe Action
 * Description: Adds users to a mailpoet list after a form submission. counts them as subscribed without needing confirmation.
 * Version: 1.1
 * Author: Aidan Baker
 * Author URI: https://lese.io
 *
 * Requires Plugins: elementor
 * Elementor tested up to: 3.20.0
 * Elementor Pro tested up to: 3.20.0
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

/**
 * Add new subscriber to Mailpoet.
 *
 * @since 1.0.0
 * @param ElementorPro\Modules\Forms\Registrars\Form_Actions_Registrar $form_actions_registrar
 * @return void
 */
function add_new_mailpoet_confirm_form_action($form_actions_registrar)
{

	include_once (__DIR__ . '/form-actions/mailpoet-confirm.php');

	$form_actions_registrar->register(new \Mailpoet_Confirm_After_Submit());

}
add_action('elementor_pro/forms/actions/register', 'add_new_mailpoet_confirm_form_action');

// when the plugin is initialized, this gets called to generate an initial table of tempmail domains to use. you can go into your database and add more when the need arises
function create_temporary_email_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lese_temporary_email_domains'; // create a table name with the WP prefix

    // SQL statement to create the table if it doesn't exist
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        domain varchar(255) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY domain (domain)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // insert default domains
    $default_domains = [
        'mailinator.com', '10minutemail.com', 'guerrillamail.com', 'yopmail.com', 'temp-mail.org',
        'trashmail.com', 'dispostable.com', 'maildrop.cc', 'getnada.com', 'throwawaymail.com',
        'moakt.com', 'sharklasers.com', 'anonymbox.com', 'mytemp.email', 'tempail.com'
    ];

    // check if the table is empty
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    if ($count == 0) {
        foreach ($default_domains as $domain) {
            $wpdb->insert(
                $table_name,
                ['domain' => $domain], // insert domain into the table
                ['%s']
            );
        }
    }
}
register_activation_hook(__FILE__, 'create_temporary_email_table');