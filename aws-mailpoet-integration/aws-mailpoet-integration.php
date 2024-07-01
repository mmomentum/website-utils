<?php
/**
 * Plugin Name: AWS SNS to MailPoet
 * Description: Handles AWS SNS notifications for SES and updates MailPoet user status, because Mailpoet's SES sending action doesn't cover bounce & complaint handling
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class AWSSNSMailPoet {
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
    }

    public function register_endpoints() {
        register_rest_route( 'aws-sns/v1', '/notification', array(
            'methods' => 'POST',
            'callback' => array( $this, 'handle_sns_notification' ),
        ));
    }

    public function handle_sns_notification( $request ) 
	{
        $body = json_decode( $request->get_body(), true );

        if ( isset( $body['Type'] ) && $body['Type'] == 'SubscriptionConfirmation' ) 
		{
            $this->confirm_subscription( $body['SubscribeURL'] );
            return new WP_REST_Response( 'Subscription confirmed', 200 );
        }

        if ( isset( $body['Type'] ) && $body['Type'] == 'Notification' )
		{
            $message = json_decode( $body['Message'], true );

            if ( isset( $message['notificationType'] ) ) 
			{
                $email = '';

                switch ( $message['notificationType'] ) 
				{
                    case 'Bounce':
                        $email = $message['bounce']['bouncedRecipients'][0]['emailAddress'];
						$this->log('Bounce notification for email: ' . $email);
                        $this->update_mailpoet_status( $email, 'bounced' );
                        break;

                    case 'Complaint':
                        $email = $message['complaint']['complainedRecipients'][0]['emailAddress'];
						$this->log('Complaint notification for email: ' . $email);
                        $this->update_mailpoet_status( $email, 'complaint' );
                        break;
                }
            }
        }

        return new WP_REST_Response( 'Notification handled', 200 );
    }

    private function confirm_subscription( $subscribe_url ) {
        $response = wp_remote_get( $subscribe_url );

        if ( is_wp_error( $response ) ) {
            $this->log('AWS SNS subscription confirmation failed: ' . $response->get_error_message());
        } else {
            $this->log('AWS SNS subscription confirmed: ' . $subscribe_url);
        }
    }

    private function update_mailpoet_status( $email, $status ) 
	{
		$mailpoet_api = \MailPoet\API\API::MP('v1');

            if ( $status == 'bounced' ) 
			{
                $mailpoet_api->unsubscribe($email);
				$this->log("Subscriber with email $email has been unsubcribed.");
            } 
			elseif ( $status == 'complaint' ) 
			{
                $mailpoet_api->unsubscribe($email);
				$this->log("Subscriber with email $email has been unsubcribed.");
            }
			else
			{
				$this->log("Status code not found");
			}
    }
	
	private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( $message );
        }
    }
}

new AWSSNSMailPoet();
