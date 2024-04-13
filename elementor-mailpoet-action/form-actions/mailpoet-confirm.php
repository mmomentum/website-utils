<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Custom Elementor form action that puts people on a mailpoet list, but doesn't do any confirmation email 
 * and auto-confirms them to recieve further correspondance.
 * 
 */
class Mailpoet_Confirm_After_Submit extends \ElementorPro\Modules\Forms\Classes\Integration_Base
{

	/**
	 * Get action name.
	 *
	 * @since 1.0.0
	 * @access public
	 * @return string
	 */
	public function get_name()
	{
		return 'mailpoet-confirm';
	}

	/**
			  * Get action label.
			  
			  * @since 1.0.0
			  * @access public
			  * @return string
			  */
	public function get_label()
	{
		return esc_html__('Mailpoet Confirm', 'elementor-pro');
	}

	/**
	 * Register action controls.
	 *
	 * Add input fields to allow the user to customize the action settings.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param \Elementor\Widget_Base $widget
	 */
	public function register_settings_section($widget)
	{
		$widget->start_controls_section(
			'section_mailpoet_confirm',
			[
				'label' => esc_html__('MailPoet Confirm', 'elementor-pro'),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		$mailpoet_api = \MailPoet\API\API::MP('v1');

		// use the mailpoet API to get data
		$mailpoet_confirm_lists = $mailpoet_api->getLists();
		$options = [];

		foreach ($mailpoet_confirm_lists as $list) {
			$options[$list['id']] = $list['name'];
		}

		$widget->add_control(
			'mailpoet_confirm_lists',
			[
				'label' => esc_html__('List', 'elementor-pro'),
				'type' => \Elementor\Controls_Manager::SELECT2,
				'label_block' => true,
				'options' => $options,
				'render_type' => 'none',
			]
		);

		// $this->register_fields_map_control( $widget );

		$widget->end_controls_section();
	}

	/**
	 * Run action.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler
	 */
	public function run($record, $ajax_handler)
	{
		$settings = $record->get('form_settings');
		$subscriber['email'] = $record->get('sent_data')['email'];

		$existing_subscriber = false;

		$extra_options['send_confirmation_email'] = false;
		$extra_options['schedule_welcome_email'] = false;
		$extra_options['skip_subscriber_notification'] = false;

		$mailpoet_api = \MailPoet\API\API::MP('v1');

		try {
			$mailpoet_api->addSubscriber($subscriber, (array) $settings['mailpoet_confirm_lists'], $extra_options);
			$existing_subscriber = false;
		} catch (\Exception $exception) {
			$error_string = esc_html__('This subscriber already exists.', 'mailpoet'); // phpcs:ignore WordPress.WP.I18n

			if ($error_string === $exception->getMessage()) {
				$existing_subscriber = true;
			} else {
				throw $exception;
			}
		}

		if ($existing_subscriber) {

			$mailpoet_api->subscribeToLists($subscriber['email'], (array) $settings['mailpoet_confirm_lists'], $extra_options);
		}

		// sets the name to be considered as 'confirmed' in the database
		global $wpdb;

		$table_name = $wpdb->prefix . 'mailpoet_subscribers';

		$email = $subscriber['email'];

		// Check if the table exists
		if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
			// Get the current status of the subscriber
			$current_status = $wpdb->get_var("SELECT status FROM $table_name WHERE email = '$email'");

			// Check if the current status is already 'unsubscribed'
			if ($current_status === 'unsubscribed') {
				return;
			}

			// Prepare data for updating
			$data = array('status' => 'subscribed');

			// Update subscriber status
			$updated = $wpdb->update($table_name, $data, array('email' => $email));
		}
	}

	private function map_fields($record)
	{
		$settings = $record->get('form_settings');
		$fields = $record->get('fields');

		$subscriber = [];

		foreach ($settings['mailpoet_confirm_fields_map'] as $map_item) {
			if (empty($fields[$map_item['local_id']]['value'])) {
				continue;
			}

			$subscriber[$map_item['remote_id']] = $fields[$map_item['local_id']]['value'];
		}

		return $subscriber;
	}

	protected function get_fields_map_control_options()
	{
		$mailpoet_fields = [
			[
				'remote_id' => 'first_name',
				'remote_label' => esc_html__('First Name', 'elementor-pro'),
				'remote_type' => 'text',
			],
			[
				'remote_id' => 'last_name',
				'remote_label' => esc_html__('Last Name', 'elementor-pro'),
				'remote_type' => 'text',
			],
			[
				'remote_id' => 'email',
				'remote_label' => esc_html__('Email', 'elementor-pro'),
				'remote_type' => 'email',
				'remote_required' => true,
			],
		];

		$mailpoet_api = \MailPoet\API\API::MP('v1');

		$fields = $mailpoet_api->getSubscriberFields();

		if (!empty($fields) && is_array($fields)) {
			foreach ($fields as $index => $remote) {
				if (in_array($remote['id'], ['first_name', 'last_name', 'email'])) {
					continue;
				}
				$mailpoet_fields[] = [
					'remote_id' => $remote['id'],
					'remote_label' => $remote['name'],
					'remote_type' => 'text',
				];
			}
		}

		return [
			'default' => $mailpoet_fields,
			'condition' => [
				'mailpoet_confirm_lists!' => '',
			],
		];
	}

	/**
	 * On export.
	 *
	 * Clears Mailpoet confirmation action settings/fields when exporting.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param array $element
	 */
	public function on_export($element)
	{
		unset($element['mailpoet_confirm_lists']);

		return $element;
	}

}