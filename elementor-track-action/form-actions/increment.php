<?php

if (!defined('ABSPATH')) {
	exit;
}

require_once plugin_dir_path(__FILE__) . '../function-call-tracker.php';

class Increment_Tracker extends \ElementorPro\Modules\Forms\Classes\Integration_Base
{
	public function get_name()
	{
		return 'increment_tracker';
	}

	public function get_label()
	{
		return esc_html__('Track Action', 'elementor-forms-increment-tracker');
	}

	public function run($record, $ajax_handler)
	{
		$settings = $record->get( 'form_settings' );

		if ( empty( $settings['increment-tracker-id'] ) ) {
			return;
		}

		$track = new FunctionCallTracker();
		$id = $track->get_integer_id_from_string($settings['increment-tracker-id']);
		$track->track_function_call($id);
	}

	public function register_settings_section($widget)
	{
		$widget->start_controls_section(
			'section-increment-tracker',
			[
				'label' => esc_html__('Track Action', 'elementor-forms-increment-tracker'),
				'condition' => [
					'submit_actions' => $this->get_name(),
				],
			]
		);

		$widget->add_control(
			'increment-tracker-id',
			[
				'label' => esc_html__('Tracker ID', 'elementor-forms-increment-tracker'),
				'type' => \Elementor\Controls_Manager::TEXT,
				'placeholder' => 'Name ID',
				'description' => esc_html__('Enter the name of the action to track'),
			]
		);

		$widget->end_controls_section();
	}

	public function on_export($element)
	{
		unset($element['increment-tracker-id']);

		return $element;
	}

}