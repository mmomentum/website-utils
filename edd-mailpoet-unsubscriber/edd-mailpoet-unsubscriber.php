<?php
/*
Plugin Name: EDD MailPoet Unsubscriber
Description: Unsubscribe users from specific MailPoet lists based on the products purchased in Easy Digital Downloads.
*/

// Include the MailPoet API
require_once (ABSPATH . 'wp-content/plugins/mailpoet/vendor/autoload.php');

// Add a settings page
function edd_mailpoet_unsubscriber_menu()
{
    add_options_page(
        'EDD MailPoet Unsubscriber Settings',
        'EDD MailPoet Unsubscriber',
        'manage_options',
        'edd_mailpoet_unsubscriber_settings',
        'edd_mailpoet_unsubscriber_settings_page_content'
    );
}
add_action('admin_menu', 'edd_mailpoet_unsubscriber_menu');

// Settings page content
function edd_mailpoet_unsubscriber_settings_page_content()
{
    ?>
    <div class="wrap">
        <h2>EDD MailPoet Unsubscriber Settings</h2>
        <form method="post" action="options.php">
            <?php
            settings_fields('edd_mailpoet_unsubscriber_settings_group');
            do_settings_sections('edd_mailpoet_unsubscriber_settings_page');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register and define the settings
function edd_mailpoet_unsubscriber_settings_init()
{
    register_setting('edd_mailpoet_unsubscriber_settings_group', 'edd_mailpoet_unsubscriber_mappings');
    add_settings_section('edd_mailpoet_unsubscriber_settings_section', 'Product to MailPoet List Mappings', 'edd_mailpoet_unsubscriber_settings_section_callback', 'edd_mailpoet_unsubscriber_settings_page');
    add_settings_field('edd_mailpoet_unsubscriber_mapping_field', 'Product Mappings', 'edd_mailpoet_unsubscriber_mapping_field_callback', 'edd_mailpoet_unsubscriber_settings_page', 'edd_mailpoet_unsubscriber_settings_section');
}

// Section callback
function edd_mailpoet_unsubscriber_settings_section_callback()
{
    echo 'Specify which MailPoet list should be unsubscribed from when a specific product is purchased.';
}

// Field callback for the settings page
function edd_mailpoet_unsubscriber_mapping_field_callback()
{
    // Get all product IDs
    $products = get_posts(
        array(
            'post_type' => 'download',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ));

    if ($products) {
        echo '<table class="form-table">';
        foreach ($products as $product) {
            echo '<tr>';
            echo '<th scope="row">Product Name: ' . $product->post_name . ' / ID: ' . $product->id . '</th>';
            echo '<td>';
            edd_mailpoet_unsubscriber_mapping_callback(array('product_id' => $product->id));
            echo '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No products found.</p>';
    }
}

// Field callback for the settings page
function edd_mailpoet_unsubscriber_mapping_callback($args)
{
    $mappings = get_option('edd_mailpoet_unsubscriber_mappings', array());
    $product_id = $args['product_id'];
    $list_id = isset($mappings[$product_id]) ? $mappings[$product_id] : '';

    $lists = edd_mailpoet_get_lists();

    echo '<select name="edd_mailpoet_unsubscriber_mappings[' . esc_attr($product_id) . ']">';
    echo '<option value="">Select a MailPoet list</option>';
    foreach ($lists as $list) {
        echo '<option value="' . esc_attr($list['id']) . '" ' . selected($list_id, $list['id'], false) . '>' . esc_html($list['name']) . '</option>';
    }
    echo '</select>';
}

// Hook into settings initialization
add_action('admin_init', 'edd_mailpoet_unsubscriber_settings_init');

// Function to fetch MailPoet lists
function edd_mailpoet_get_lists()
{
    $mailpoet_api = \MailPoet\API\API::MP('v1');
    $lists = $mailpoet_api->getLists();
    return $lists;
}

// Unsubscribe user from MailPoet list after completing a purchase
function edd_mailpoet_unsubscribe_after_purchase($payment_id)
{
    // Get the email address associated with the purchase
    $email = edd_get_payment_user_email($payment_id);

    // Get product ID from the purchase
    $payment_meta = edd_get_payment_meta($payment_id);
    $download_ids = $payment_meta['downloads'];
    $product_id = $download_ids[0]; // Assuming only one product per purchase for simplicity

    // Get the mappings from settings
    $mappings = get_option('edd_mailpoet_unsubscriber_mappings', array());

    // Check if there's a mapping for the purchased product
    if (isset($mappings[$product_id])) {
        // Get the MailPoet list ID corresponding to the product
        $list_id = $mappings[$product_id];

        // Call MailPoet API to unsubscribe user from the specified list
        $mailpoet_api = \MailPoet\API\API::MP('v1');

        try {
            $subscriber = $mailpoet_api->getSubscriber($email);
        } catch (\Exception $exception) {
            $error_string = esc_html__('Asking for a subscriber that does not exist.', 'mailpoet');

            // if it isn't just that the subscriber doesn't exist, throw the exception
            if ($error_string !== $exception->getMessage()) {
                throw $exception;
            }
        }

        $subscriber = $mailpoet_api->getSubscriber($email);
        $mailpoet_api->unsubscribeFromList($subscriber['id'], $list_id);
    }
}
add_action('edd_complete_purchase', 'edd_mailpoet_unsubscribe_after_purchase');