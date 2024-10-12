<?php
/**
 * Plugin Name: Bulk EDD License Keys
 * Description: Batch License Generator
 * Version: 1.2
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

// Ensure the file is not accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Add admin menu item
add_action('admin_menu', 'edd_bulk_license_menu');
function edd_bulk_license_menu() {
    add_submenu_page(
        'edit.php?post_type=download',  // Parent slug (EDD main menu)
        'Bulk License Key Generator',   // Page title
        'Bulk License Keys',            // Menu title
        'manage_options',               // Capability
        'edd-bulk-license-generator',   // Menu slug
        'edd_bulk_license_generator_page'  // Callback function
    );
}

// Display form in the admin page
function edd_bulk_license_generator_page() {
    ?>
    <div class="wrap">
        <h1>Bulk License Key Generator</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Key Length</th>
                    <td><input type="number" name="key_length" value="20" min="5" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Number of Keys</th>
                    <td><input type="number" name="num_keys" value="10" min="1" required /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Product</th>
                    <td>
                        <select name="product_id" required>
                            <?php
                            // Fetch all EDD products
                            $products = get_posts(array(
                                'post_type' => 'download',
                                'numberposts' => -1
                            ));
                            foreach ($products as $product) {
                                echo '<option value="' . $product->ID . '">' . $product->post_title . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
            <p class="submit"><input type="submit" name="generate_keys" class="button-primary" value="Generate License Keys" /></p>
        </form>
    </div>
    <?php

    // Handle form submission
    if (isset($_POST['generate_keys'])) {
        $key_length = intval($_POST['key_length']);
        $num_keys = intval($_POST['num_keys']);
        $product_id = intval($_POST['product_id']);

        if ($key_length > 0 && $num_keys > 0 && $product_id > 0) {
            edd_generate_license_keys($key_length, $num_keys, $product_id);
        }
    }
}

// Function to generate random keys with lowercase alphanumeric characters
function edd_generate_license_keys($key_length, $num_keys, $product_id) {
    global $wpdb;

    $characters = '0123456789abcdef';  // Only lowercase alphanumeric characters

    for ($i = 0; $i < $num_keys; $i++) {
        // Generate random license key
        $license_key = '';
        for ($j = 0; $j < $key_length; $j++) {
            $license_key .= $characters[mt_rand(0, strlen($characters) - 1)];
        }

        // Insert into the EDD license keys table from EDD Software Licensing add-on
        $wpdb->insert(
            $wpdb->prefix . 'edd_licenses',  // EDD License table (for Software Licensing add-on)
            array(
                'license_key'        => $license_key,
                'download_id' => $product_id,
                'status'     => 'inactive',  // default status
                'date_created' => current_time('mysql')
            ),
            array(
                '%s',
                '%d',
                '%s',
                '%s'
            )
        );
    }

    echo '<div class="notice notice-success"><p>' . $num_keys . ' license keys generated for product ID ' . $product_id . '.</p></div>';
}