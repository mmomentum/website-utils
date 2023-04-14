<?php
/**
 * Plugin Name: EDD / Mailpoet Tagger
 * Description: Tags users subscribed to any lists based on purchase conditions. Also features systems to programatically tag lists based on your EDD customers' previous order history.
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

register_activation_hook( __FILE__, 'lese_edd_mailpoet_init_table' );

// initializes the download / tag id matching table upon plugin activation
function lese_edd_mailpoet_init_table() {
    global $wpdb;
    $table_name = 'wp_lese_edd_mailpoet_reference';

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            edd_download_id mediumint(9) NOT NULL,
            mailpoet_tag_id mediumint(9) NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// logging function
function log_message($message) {
/* 
  $log_file = dirname(__FILE__) . '/log.txt'; // Path to the log file
  $timestamp = date('Y-m-d H:i:s'); // Current date and time
  $log_message = "[$timestamp] $message\n"; // Formatted log message
  
  $result = file_put_contents($log_file, $log_message, FILE_APPEND); // Write message to log file
  
  if ($result === false) 
  {
    error_log("Error writing to log file: $log_file");
    return false; // Return false if there was an error
  }
  */
  return true; // Return true if the message was written successfully
}

// function for returning a mailpoet ID that matches an EDD download ID
function get_mailpoet_tag_id($download_id) {
    global $wpdb;
    $tag_id = $wpdb->get_var($wpdb->prepare("SELECT mailpoet_tag_id FROM wp_lese_edd_mailpoet_reference WHERE edd_download_id = %d", $download_id));

    if (!$tag_id) {
		log_message("Couldn't find an appropriate tag ID for download " . $download_id . ".");
        return false;
    }

	log_message("Found a tag ID for download " . $download_id . ", ID is " . $tag_id . ".");

    return $tag_id;
}

// applies a mailpoet tag to a subscriber id (just adds a row to the mailpoet_subscriber_tag table)
function add_mailpoet_tag_to_subscriber($subscriber_id, $tag_id) {
    global $wpdb;

	log_message("Attempting to assign tag ID " . $tag_id . " to subscriber ID " . $subscriber_id . ".");


    $data = array('subscriber_id' => $subscriber_id, 'tag_id' => $tag_id);

    $wpdb->insert('wp_mailpoet_subscriber_tag', $data);
}

// called when edd_complete_purchase occurs
function purchase_subscriber_tag($payment_id) {
    global $wpdb;

	log_message("edd_complete_purchase action called.");

    // Get the payment data
    $payment_data = edd_get_payment_meta($payment_id);

    // Get the customer's email
    $email = $payment_data['email'];

    // Get the subscriber ID from the wp_mailpoet_subscribers table
    $subscriber_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM wp_mailpoet_subscribers WHERE email = %s", $email));

    if ($subscriber_id) {
		log_message("Subscriber ID: " . $subscriber_id);
		
        // Loop through each item in the purchase
        $downloads = $payment_data['downloads'];
        foreach ($downloads as $download) {
            $download_id = $download['id'];

			log_message("Attempting to find a tag for download ID " . $download_id . ".");

            // Get the tag ID for the download from the get_mailpoet_tag_id function
            $tag_id = get_mailpoet_tag_id($download_id);

            // If the tag ID exists, add it to the wp_mailpoet_subscriber_tag table
            if ($tag_id) {
				log_message("Found tag ID " . $tag_id . " for download ID " . $download_id . ".");
				add_mailpoet_tag_to_subscriber($subscriber_id, $tag_id);
            }
			else
			{
				log_message("Did not find tag ID for download ID " . $download_id . ".");
			}
        }
		
		$query = $wpdb->prepare(
			"SELECT SUM(purchase_value) as total_purchase_value 
			FROM wp_edd_customers 
			WHERE email = %s",
			$email
		);
				
		$lifetime_value = $wpdb->get_var($query);
		
		// if the current user's lifetime purchase worth is greater or equal to 100 we apply a "whale" tag (aka a valuable customer)
		if($lifetime_value >= 100) {
			log_message("Adding whale tag to subscriber " . $email . ".");
			add_mailpoet_tag_to_subscriber($subscriber_id, 1);
		}
		else
		{
			log_message("Lifetime value not sufficient for whale tag.");
		}
    }
	else
	{
		log_message("No Valid Subscriber ID Found for email ". $email . ".");
	}
}

add_action('edd_complete_purchase', 'purchase_subscriber_tag', 10, 3);

// handles tagging of all edd customers. useful for retroactive tagging. we only do customers and not
// every order, because most orders are just free downloads. registered customers are more likely to
// have completed real transactions, and that's what we care about
function tag_all_customers() {
	
// Get all EDD customers
$customers = edd_get_customers();

// Loop through each customer
foreach ($customers as $customer) {
    // Get the customer's email address
    $email = $customer->email;

    // Get all payments for the customer
    $payments = edd_get_payments(array(
        'email' => $email
    ));

    // Loop through each payment and get the payment ID
    foreach ($payments as $payment) {
        $payment_id = $payment->ID;
	
		// run the tagging function
		purchase_subscriber_tag($payment_id);
    }
}
	
}

add_action( 'admin_menu', 'edd_mailpoet_tagger_menu' );

// Add a menu item for the plugin
function edd_mailpoet_tagger_menu() {
    add_submenu_page(
        'edit.php?post_type=download',
        'EDD / Mailpoet Tagger',
        'Mailpoet Tagger',
        'manage_options',
        'edd-mailpoet-tagger',
        'edd_mailpoet_tagger_page'
    );
}

function edd_mailpoet_tagger_page() {
	// perform the tag_all_customers() function if the button is pressed
    if ( isset( $_POST['retroactive_tag'] ) ) {	
		tag_all_customers();
		
		echo '<div class="notice notice-success is-dismissible"><p>Retroactive tagging completed.</p></div>';
	}
	
	?>
<div class="wrap">
    <h1>Mailpoet Tagger</h1>
    <p>Tags mailpoet subscribers based on ownership of products & lifetime customer value.</p>
    </br>
    <h3>Retroactive Tagging</h3>
    <p>Tags all customers in the database retroactively. Has the potential to take awhile, if you have a lot of customers.</p>
    <form method="post" action="">
        <?php submit_button( 'Retroactivly Tag', 'primary', 'retroactive_tag' ); ?>
    </form>
</div>
	
	<?php
}
