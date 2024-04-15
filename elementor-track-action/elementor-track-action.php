<?php
/**
 * Plugin Name: Elementor Action Tracker
 * Description: Tracks submissiona as that get called on a form as an action. To be used in tandem with other actions. Action IDs can be specified.
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

require_once plugin_dir_path(__FILE__) . 'function-call-tracker.php';

register_activation_hook(__FILE__, 'function_call_tracker_activate');

function function_call_tracker_activate()
{
	// Instantiate the FunctionCallTracker class upon plugin activation
	$function_call_tracker = new FunctionCallTracker();
}

// Function to display admin page (example usage)
function display_function_calls_admin_page_example()
{
	// Instantiate the FunctionCallTracker class
	$function_call_tracker = new FunctionCallTracker();

	// Display admin page
	$function_call_tracker->display_function_calls_admin_page();
}

// displays the menu page for tracking
function add_function_calls_admin_page_example()
{
	// Add menu page to admin menu
	add_options_page(
		'Action Tracker Settings',
		'Form Action Tracker',
		'manage_options',
		'form_action_tracker_settings',
		'display_function_calls_admin_page_example'
	);
}

add_action('admin_menu', 'add_function_calls_admin_page_example');

function add_new_increment_track_form_action($form_actions_registrar)
{
	include_once (__DIR__ . '/form-actions/increment.php');

	$form_actions_registrar->register(new \Increment_Tracker());
}

add_action('elementor_pro/forms/actions/register', 'add_new_increment_track_form_action');