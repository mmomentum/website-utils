<?php
/**
 * Plugin Name: EDD License Retrocreator
 * Description: Temporary License creation system for all of the old purchases that don't have them
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

// Define the activation function
function retroactive_license_generate() 
{
    $db_offset = get_option( 'license_creator_db_offset', 0 );

	$payments = edd_get_payments(array(
        'number' => 1000,
	    'offset' => $db_offset));

    global $wpdb;

    foreach($payments as $payment) 
    {
        $payment_id = $payment->ID;

        $payment_meta = edd_get_payment_meta($payment_id);

        $date = $payment_meta["date"];
        $customer_id = $payment_meta["user_info"]["id"];
        $customer_email = $payment_meta["user_info"]["email"];

        for($i = 0; $i<count($payment_meta["downloads"]); $i++) 
        {
            $download = $payment_meta["downloads"][$i];

            // product ID
            $download_id = $download["id"];

            if(download_id_match($download_id))
            {
                $license_key =  generate_key();
            
                $user_id = check_user_email($customer_email);

                $data = array(
                'license_key' => $license_key,
                'status' => "active",
                'download_id' => $download_id,
                'payment_id' => $payment_id,
                'cart_index' => $i,
                'date_created' => $date,
                'expiration' => 0,
                'parent' => 0,
                'customer_id' => $customer_id,
                'user_id' => $user_id);

                $wpdb->insert('wp_edd_licenses', $data);
            }
        }
    }
}

// generate a 16-digit alphanumeric license key to use (the chances of collision are astronomical)
function generate_key() {
    $characters = 'abcdef0123456789';
    $string = '';

    $max = strlen($characters) - 1;
    for ($i = 0; $i < 24; $i++) {
        $string .= $characters[mt_rand(0, $max)];
    }

    return $string;
}

// checks if a user email (in the payment meta) matches one in the wp_users table. if no match then
// we return -1, if there IS a match, we return that row's user ID.
function check_user_email($email) {
    global $wpdb;
    
    // Prepare the query
    $query = $wpdb->prepare(
        "SELECT ID FROM {$wpdb->prefix}users WHERE user_email = %s",
        $email
    );
    
    // Run the query
    $result = $wpdb->get_var($query);
    
    // Check the result
    if ($result !== null) {
        // Match found, return the user ID
        return $result;
    } else {
        // No match found, return -1
        return -1;
    }
}

function download_id_match($download_id) {
        switch ($download_id) {
        case 121:
        case 405:
            return true;
        default:
            return false;
    }
}

function retro_create_batch() {	
	$db_offset = get_option( 'license_creator_db_offset', 0 );
    $new_value = $db_offset + 1000;
    update_option( 'license_creator_db_offset', $new_value );

    
    retroactive_license_generate();
}

// INTERFACE-SIDE (we only do sub-sections of the orders at a time (1k or so)) to avoid timeouts

add_action( 'admin_menu', 'license_retrocreator_menu' );

// Add a menu item for the plugin
function license_retrocreator_menu() {
    add_submenu_page(
        'edit.php?post_type=download',
        'EDD License Retrocreator',
        'License Retrocreator',
        'manage_options',
        'license-retrocreator',
        'license_retrocreator_page'
    );
}

function license_retrocreator_page() {
    if ( isset( $_POST['license_gen'] ) ) {	
		retro_create_batch();	
		echo '<div class="notice notice-success is-dismissible"><p>One Batch Done.</p></div>';
	}
	
	?>
<div class="wrap">
<h1>License Retrocreator</h1>
<p>Creates licenses for previous orders in small batches (you may need to press this button a lot...)</p>
    <form method="post" action="">
        <?php submit_button( 'Generate', 'primary', 'license_gen' ); ?>
    </form>
</div>
	
	<?php
}
