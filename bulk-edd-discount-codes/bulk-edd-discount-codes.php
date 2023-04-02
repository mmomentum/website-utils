<?php
/**
 * Plugin Name: Bulk EDD Discount Codes
 * Description: Generates bulk EDD discount codes.
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

// Hook into the admin menu
add_action( 'admin_menu', 'bulk_edd_discount_codes_menu' );

// Add a menu item for the plugin
function lese_bulk_edd_discount_codes_menu() {
    add_submenu_page(
        'edit.php?post_type=download',
        'Bulk EDD Discount Codes',
        'Bulk Discount Codes',
        'manage_options',
        'bulk-edd-discount-codes',
        'lese_bulk_edd_discount_codes_page'
    );
}

// Display the plugin settings page
function lese_bulk_edd_discount_codes_page() {
    // If the form has been submitted, generate the discount codes
    if ( isset( $_POST['generate_codes'] ) ) {
        // Number of discount codes to generate
        $number_of_codes = $_POST['number_of_codes'];

        // Characters and numbers to use for the discount code
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // Loop through and generate the discount codes
        for ( $i = 1; $i <= $number_of_codes; $i++ ) {
            $code = '';
			
            for ( $j = 0; $j < 5; $j++ ) {
                $code .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
            }

            $args = array(
                'name' => 'Free Product Discount',
                'code' => $code,
                'amount' => '100',
                'type' => 'percent',
                'uses' => '0',
                'max_uses' => '1',
                'start' => '',
                'expiration' => '',
                'is_single_use' => 'yes',
                'product_reqs' => array(),
                'product_exclusions' => array(),
                'min_price' => '',
                'max_price' => '',
                'is_not_global' => 'yes',
                'description' => ''
            );

            // If specific download IDs are specified as requirements, add them to the discount code arguments
            if ( isset( $_POST['download_ids'] ) ) {
                $download_ids = array_map( 'absint', $_POST['download_ids'] );
                $args['product_reqs'] = $download_ids;
            }

			// If a name is set, set the discount codes to the specified name
			if ( isset( $_POST['discount_name'] ) ) {
				$name = $_POST['discount_name'];
				$args['name'] = $name;
			}

            edd_store_discount( $args );
        }

        // Display a success message
        echo '<div class="notice notice-success is-dismissible"><p>Discount codes generated successfully!</p></div>';
    }
    ?>

<div class="wrap">
    <h1>Bulk EDD Discount Codes</h1>

    <form method="post" action="">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Number of Discount Codes</th>
                <td><input type="number" name="number_of_codes" min="1" max="1000" required></td>
            </tr>
            <tr valign="top">
                <th scope "row">Discount Code Custom Name (optional)</th>
                <td><input type="Text" id="discount_name" name="discount_name" value="Free Product Discount"></td>
            </tr>
            <tr valign="top">
                <th scope="row">Product Requirements (optional)</th>
                <td>
                    <?php
        $downloads = get_posts( array(
            'post_type' => 'download',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ) );
        foreach ( $downloads as $download ) {
            echo '<label>';
            echo '<input type="checkbox" name="download_ids[]" value="' . $download->ID . '"> ';
            echo $download->post_title;
            echo '</label><br>';
        }
        ?>
                </td>
            </tr>
        </table>

        <?php submit_button( 'Generate Discount Codes', 'primary', 'generate_codes' ); ?>
    </form>
</div>
    <?php
}
