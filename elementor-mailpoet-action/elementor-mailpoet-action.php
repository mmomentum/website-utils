<?php
/**
 * Plugin Name: Elementor Mailpoet Subscribe Action
 * Description: Adds users to a mailpoet list after a form submission. counts them as subscribed without needing confirmation.
 * Version: 1.0
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