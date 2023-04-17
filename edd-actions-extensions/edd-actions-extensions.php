<?php
/**
 * Plugin Name: EDD Actions Extensions
 * Description: Extends upon EDD's extra URL parameter "actions" to let special URLs do more stuff.
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

// allows for adding multiple items to the cart at once. add_to_cart only allows for one at a time
function lese_edd_add_bundle_to_cart() {
    // Check if the add_bundle_to_cart action is triggered
    if ( isset( $_GET['edd_action'] ) && $_GET['edd_action'] == 'add_bundle_to_cart' ) {
        // Check if the download_ids parameter is set and not empty
        if ( isset( $_GET['download_ids'] ) && ! empty( $_GET['download_ids'] ) ) {
            // Split the download IDs into an array
            $download_ids = explode( ',', $_GET['download_ids'] );
            // Loop through each download ID and add it to the cart
            foreach ( $download_ids as $download_id ) {
                edd_add_to_cart( $download_id );
            }
        }
        // Redirect to the cart page after adding the downloads
        wp_redirect( edd_get_checkout_uri() );
        exit;
    }
}

add_action( 'template_redirect', 'lese_edd_add_bundle_to_cart' );