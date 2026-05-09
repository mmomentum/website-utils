<?php
/**
 * Plugin Name: EDD / Mailpoet Tagger
 * Description: Tags users subscribed to any lists based on purchase conditions. Also features systems to programatically tag lists based on your EDD customers' previous order history.
 * Version: 1.1
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

    $existing_tag = $wpdb->get_var(
        $wpdb->prepare("
            SELECT tag_id
            FROM wp_mailpoet_subscriber_tag
            WHERE subscriber_id = %d AND tag_id = %d
        ", $subscriber_id, $tag_id)
    );

	// pre-emptively check 
	if (empty($existing_tag)) {
		$data = array('subscriber_id' => $subscriber_id, 'tag_id' => $tag_id);
		
		$wpdb->insert('wp_mailpoet_subscriber_tag', $data);
	}
	else {
		log_message("Skipping: Duplicate tag already exists in database.");
	}
}

// auto-subscribes a subscriber to the most-subscribed list when they are "trash" or have no list
// assignments. skips silently if the subscriber has explicitly unsubscribed.
function lese_edd_mailpoet_maybe_auto_subscribe($subscriber_id, $email) {
    global $wpdb;

    if (!class_exists('\MailPoet\API\API')) {
        return;
    }

    $status = $wpdb->get_var($wpdb->prepare(
        "SELECT status FROM wp_mailpoet_subscribers WHERE id = %d",
        $subscriber_id
    ));

    // respect explicit opt-outs
    if ($status === 'unsubscribed') {
        log_message("Skipping auto-subscribe for $email: subscriber is unsubscribed.");
        return;
    }

    $list_count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM wp_mailpoet_subscriber_segment WHERE subscriber_id = %d",
        $subscriber_id
    ));

    if ($status !== 'trash' && $list_count > 0) {
        return;
    }

    try {
        $mp = \MailPoet\API\API::MP('v1');
        $lists = $mp->getLists();

        if (empty($lists)) {
            log_message("Auto-subscribe skipped for $email: no lists returned from MailPoet API.");
            return;
        }

        usort($lists, function($a, $b) {
            return (int)($b['subscribers'] ?? 0) - (int)($a['subscribers'] ?? 0);
        });

        $target_list = $lists[0];

        $mp->subscribeToList($email, $target_list['id'], [
            'send_confirmation_email'    => false,
            'skip_subscriber_notification' => true,
        ]);

        log_message("Auto-subscribed $email to list ID {$target_list['id']} ({$target_list['name']}).");
    } catch (Exception $e) {
        log_message("Auto-subscribe failed for $email: " . $e->getMessage());
    }
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

		lese_edd_mailpoet_maybe_auto_subscribe($subscriber_id, $email);

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
			
			// Check if this download is a bundle and process bundled products
			if (function_exists('edd_is_bundled_product') && edd_is_bundled_product($download_id)) {
				log_message("Download ID " . $download_id . " is a bundle. Processing bundled products.");
				
				$bundled_products = edd_get_bundled_products($download_id);
				
				if ($bundled_products && is_array($bundled_products)) {
					foreach ($bundled_products as $bundled_product_id) {
						log_message("Processing bundled product ID " . $bundled_product_id . ".");
						
						$bundled_tag_id = get_mailpoet_tag_id($bundled_product_id);
						
						if ($bundled_tag_id) {
							log_message("Found tag ID " . $bundled_tag_id . " for bundled product ID " . $bundled_product_id . ".");
							add_mailpoet_tag_to_subscriber($subscriber_id, $bundled_tag_id);
						} else {
							log_message("No tag ID found for bundled product ID " . $bundled_product_id . ".");
						}
					}
				}
			}
        }
		
		$query = $wpdb->prepare(
			"SELECT SUM(purchase_value) as total_purchase_value
			FROM wp_edd_customers
			WHERE email = %s",
			$email
		);

		// purchase_value in wp_edd_customers hasn't been updated yet when this hook fires,
		// so add the current payment's amount manually to get the true post-purchase total
		$lifetime_value = (float) $wpdb->get_var($query) + (float) edd_get_payment_amount($payment_id);

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

// handles tagging of all edd customers. useful for retroactive tagging. will be very, very slow to run.
function tag_all_customers() {	
	// get a batch of payments
	
	$db_offset = get_option( 'edd_mailpoet_retrotagger_db_offset', 0 );
	$batch_size = 100; // Process 100 at a time to avoid timeouts
	
	$payments = edd_get_payments(array(
		'number' => $batch_size,
		'offset' => $db_offset,
		'status' => 'complete' // Only process completed payments
	));

	if (empty($payments)) {
		// No more payments to process
		return array(
			'complete' => true,
			'processed' => 0,
			'offset' => $db_offset
		);
	}

	// Loop through each payment and get the payment ID
	$processed = 0;
	foreach ($payments as $payment) {
		$payment_id = $payment->ID;
		purchase_subscriber_tag($payment_id);
		$processed++;
	}
	
	// Update offset for next batch
	$new_offset = $db_offset + $processed;
	update_option( 'edd_mailpoet_retrotagger_db_offset', $new_offset );
	
	return array(
		'complete' => false,
		'processed' => $processed,
		'offset' => $new_offset
	);
}

// AJAX handler for retroactive tagging
function edd_mailpoet_ajax_tag_customers() {
	check_ajax_referer('edd_mailpoet_retrotag_nonce', 'nonce');
	
	if (!current_user_can('manage_options')) {
		wp_send_json_error('Insufficient permissions');
		return;
	}
	
	$result = tag_all_customers();
	wp_send_json_success($result);
}

add_action('wp_ajax_edd_mailpoet_tag_customers', 'edd_mailpoet_ajax_tag_customers');

// Reset retroactive tagging counter
function edd_mailpoet_reset_retrotagger() {
	check_ajax_referer('edd_mailpoet_reset_nonce', 'nonce');
	
	if (!current_user_can('manage_options')) {
		wp_send_json_error('Insufficient permissions');
		return;
	}
	
	delete_option('edd_mailpoet_retrotagger_db_offset');
	wp_send_json_success(array('message' => 'Counter reset successfully'));
}

add_action('wp_ajax_edd_mailpoet_reset_retrotagger', 'edd_mailpoet_reset_retrotagger');

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
	global $wpdb;
	
	// Get total number of completed payments
	$total_payments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'edd_payment' AND post_status = 'publish'");
	$current_offset = get_option('edd_mailpoet_retrotagger_db_offset', 0);
	$progress_percentage = $total_payments > 0 ? min(100, round(($current_offset / $total_payments) * 100, 2)) : 0;
	
	?>
<div class="wrap">
    <h1>Mailpoet Tagger</h1>
    <p>Tags mailpoet subscribers based on ownership of products & lifetime customer value. Now supports EDD bundles - when a bundle is purchased, all products within the bundle are tagged individually.</p>
    </br>
    <h3>Retroactive Tagging</h3>
    <p>Tags all customers in the database retroactively. Processes orders in batches to avoid timeouts.</p>
    
    <div id="retrotag-progress" style="margin: 20px 0;">
        <p><strong>Progress:</strong> <span id="progress-text"><?php echo $current_offset; ?> of <?php echo $total_payments; ?> orders processed (<?php echo $progress_percentage; ?>%)</span></p>
        <div style="width: 100%; background-color: #f0f0f0; border-radius: 5px; height: 30px; position: relative;">
            <div id="progress-bar" style="width: <?php echo $progress_percentage; ?>%; background-color: #4CAF50; height: 100%; border-radius: 5px; transition: width 0.3s;"></div>
        </div>
    </div>
    
    <div id="retrotag-status" style="margin: 20px 0; padding: 10px; background: #fff; border-left: 4px solid #00a0d2; display: none;">
        <p id="status-message"></p>
    </div>
    
    <form method="post" action="" id="retrotag-form">
        <?php wp_nonce_field('edd_mailpoet_retrotag_nonce', 'retrotag_nonce'); ?>
        <button type="button" id="start-retrotag" class="button button-primary">
            <?php echo $current_offset > 0 ? 'Continue Retroactive Tagging' : 'Start Retroactive Tagging'; ?>
        </button>
        <button type="button" id="reset-retrotag" class="button" style="margin-left: 10px;">Reset Progress</button>
        <p class="description">Click to process the next batch of orders. You can safely stop and resume at any time.</p>
    </form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    var isProcessing = false;
    var totalPayments = <?php echo $total_payments; ?>;
    var currentOffset = <?php echo $current_offset; ?>;
    
    function updateProgress(offset, processed) {
        currentOffset = offset;
        var percentage = Math.min(100, Math.round((offset / totalPayments) * 100));
        
        $('#progress-text').text(offset + ' of ' + totalPayments + ' orders processed (' + percentage + '%)');
        $('#progress-bar').css('width', percentage + '%');
    }
    
    function showStatus(message, type) {
        var colors = {
            'info': '#00a0d2',
            'success': '#46b450',
            'error': '#dc3232'
        };
        
        $('#retrotag-status')
            .css('border-left-color', colors[type] || colors.info)
            .fadeIn();
        $('#status-message').html(message);
    }
    
    function processBatch() {
        if (isProcessing) return;
        
        isProcessing = true;
        $('#start-retrotag').prop('disabled', true).text('Processing...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'edd_mailpoet_tag_customers',
                nonce: '<?php echo wp_create_nonce('edd_mailpoet_retrotag_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    updateProgress(data.offset, data.processed);
                    
                    if (data.complete) {
                        showStatus('✓ All orders have been processed!', 'success');
                        $('#start-retrotag').text('Retroactive Tagging Complete').prop('disabled', true);
                    } else {
                        showStatus('Processed ' + data.processed + ' orders. Click the button again to continue, or stop here and resume later.', 'info');
                        $('#start-retrotag').prop('disabled', false).text('Process Next Batch');
                    }
                }
                isProcessing = false;
            },
            error: function() {
                showStatus('An error occurred. Please try again.', 'error');
                $('#start-retrotag').prop('disabled', false).text('Retry');
                isProcessing = false;
            }
        });
    }
    
    $('#start-retrotag').on('click', function() {
        processBatch();
    });
    
    $('#reset-retrotag').on('click', function() {
        if (!confirm('Are you sure you want to reset the progress counter? This will start over from the beginning.')) {
            return;
        }
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'edd_mailpoet_reset_retrotagger',
                nonce: '<?php echo wp_create_nonce('edd_mailpoet_reset_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    showStatus('Progress counter has been reset.', 'success');
                    updateProgress(0, 0);
                    $('#start-retrotag').text('Start Retroactive Tagging').prop('disabled', false);
                }
            }
        });
    });
});
</script>
	
	<?php
}