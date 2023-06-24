<?php
/**
 * Plugin Name: Bulk EDD License Keys
 * Description: Batch License Generator
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

// generate an N sized alphanumeric license key to use (the chances of collision are astronomical at higher lengths)
function generate_key($length)
{
    $characters = 'abcdef0123456789';
    $string = '';

    $max = strlen($characters) - 1;
    for ($i = 0; $i < $length; $i++) {
        $string .= $characters[mt_rand(0, $max)];
    }

    return $string;
}

add_action('admin_menu', 'license_creator_menu');

// Add a menu item for the plugin
function license_creator_menu()
{
    add_submenu_page('edit.php?post_type=download', 'Bulk EDD License Keys', 'Bulk Licenses', 'manage_options', 'bulk-edd-licenses', 'license_creator_page');
}

function license_creator_page()
{
    if (isset($_POST['license_gen'])) {
        global $wpdb;

        $number_of_licenses = $_POST['num_licenses'];

        $num_characters = $_POST['num_characters'];

        for ($i = 1; $i <= $number_of_licenses; $i++) {
            $license_key = generate_key($num_characters);

            $data = array(
                'license_key' => $license_key,
                'status' => "inactive",
                'download_id' => $_POST['download_id'][0],
                'payment_id' => -1,
                'cart_index' => $i,
                'expiration' => 0,
                'parent' => 0,
                'customer_id' => -1,
                'user_id' => -1,
            );

            $wpdb->insert('wp_edd_licenses', $data);
        }

        echo '<div class="notice notice-success is-dismissible"><p>Licenses Created. Check the database.</p></div>';
    } ?>
	
<div class="wrap">
<h1>Batch License Creator</h1>
<p>Creates batch licenses with no order associations</p>
    <form method="post" action="">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">Number of Licenses</th>
                <td><input type="number" name="num_licenses" min="1" max="1000" required></td>
            </tr>
			<tr valign="top">
                <th scope="row">License Complexity</th>
                <td><input type="number" name="num_characters" min="8" max="32" value="16" required></td>
            </tr>
			
            <tr valign="top">
                <th scope="row">License Key Product</th>
                <td>
                    <?php
                    $downloads = get_posts(array(
                        'post_type' => 'download',
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                    ));
                    foreach ($downloads as $download) {
                        echo '<label>';
                        echo '<input type="checkbox" name="download_id[]" value="' . $download->ID . '"> ';
                        echo $download->post_title;
                        echo '</label><br>';
                    }
                    ?>
                </td>
            </tr>
        </table>

        <?php submit_button('Generate Discount Codes', 'primary', 'license_gen'); ?>
    </form>
</div>
	<?php
}
