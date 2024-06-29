<?php

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Custom Elementor form action that puts people on a mailpoet list, but doesn't do any actionation email 
 * and auto-actions them to recieve further correspondance.
 * 
 */
class Mailpoet_Action_After_Submit extends \ElementorPro\Modules\Forms\Classes\Integration_Base
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
		return 'mailpoet-automation-trigger';
	}

	/**
				 * Get action label.
				 
				 * @since 1.0.0
				 * @access public
				 * @return string
				 */
	public function get_label()
	{
		return esc_html__('Mailpoet Automation Trigger', 'elementor-pro');
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
			'section_mailpoet_action',
			[
				'label' => esc_html__('Mailpoet Automation Trigger', 'elementor-pro'),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		$widget->add_control(
			'mailpoet_trigger_id',
			[
				'label' => esc_html__('Trigger ID', 'elementor-forms-increment-tracker'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'trigger_name',
				'description' => esc_html__('Enter the name of the Trigger to fire in this action'),
			]
		);

		$widget->add_control(
			'mailpoet_action_chance',
			[
				'label' => esc_html__('Subscribe Chance', 'elementor-pro'),
				'type' => \Elementor\Controls_Manager::NUMBER,
				'label_block' => true,
				'render_type' => 'none',
				'description' => 'the chance (as a percentage) that the trigger will fire.',
				'default' => '100',
			]
		);

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
		$random_number = rand(0, 100);
		$settings = $record->get('form_settings');

		// if the chance check fails, then no nothing will occur (used for the random 
		// discount code sending for demo plugins)
		if(!($random_number <= $settings['mailpoet_action_chance']))
			return;

		do_action($settings['mailpoet_trigger_id'], $record->get('sent_data')['email']);
	}

	private function map_fields($record)
	{
		$settings = $record->get('form_settings');
		$fields = $record->get('fields');

		$subscriber = [];

		foreach ($settings['mailpoet_action_fields_map'] as $map_item) {
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
				'mailpoet_action_lists!' => '',
			],
		];
	}

	/**
	 * On export.
	 *
	 * Clears Mailpoet actionation action settings/fields when exporting.
	 *
	 * @since 1.0.0
	 * @access public
	 * @param array $element
	 */
	public function on_export($element)
	{
		unset($element['mailpoet_action_lists']);
		unset($element['mailpoet_action_chance']);

		return $element;
	}

}