<?php
/**
 * Plugin Name: Username Restriction
 * Description: Prevent user registration if the username contains a restricted term. Basically a very basic anti-spam system.
 * Version: 1.0
 * Author: Aidan Baker
 * Author URI: https://lese.io
 */

// Define global variables
global $lese_db_version;
$lese_db_version = "1.0";

// Define the table name
global $lese_restricted_names_table_name;
$lese_restricted_names_table_name = "lese_restricted_names";

// Run the install function when the plugin is activated
register_activation_hook(__FILE__, "lese_install");

// Add a menu page to the WordPress admin panel
add_action("admin_menu", "lese_menu");

// Define the install function
function lese_install()
{
    global $wpdb;
    global $lese_db_version;
    global $lese_restricted_names_table_name;

    $installed_version = get_option("lese_db_version");

    // Check if the table already exists
    if (
        $wpdb->get_var(
            "SHOW TABLES LIKE '{$lese_restricted_names_table_name}'"
        ) != $lese_restricted_names_table_name
    ) {
        // Define the SQL statement to create the table
        $sql = "CREATE TABLE {$lese_restricted_names_table_name} (
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          name varchar(100) NOT NULL,
          PRIMARY KEY  (id)
        );";

        // Include the WordPress database upgrade script and run the SQL statement
        require_once ABSPATH . "wp-admin/includes/upgrade.php";
        dbDelta($sql);

        // Add some default restricted names to the table
        $default_restricted_names = ["sneed", "feed", "chuck"];
        foreach ($default_restricted_names as $name) {
            $wpdb->insert($lese_restricted_names_table_name, ["name" => $name]);
        }

        // Save the database version number in the options table
        add_option("lese_db_version", $lese_db_version);
    }

    // Upgrade the table if the plugin version has changed
    if ($installed_version != $lese_db_version) {
        // Upgrade the table as needed
        // ...

        // Update the database version number in the options table
        update_option("lese_db_version", $lese_db_version);
    }
}

// Register the setting
function lese_spam_counter_register_settings() {
    register_setting( 'lese_spam_registrations_blocked_counter', 'lese_spam_registrations_blocked_counter' );
}
add_action( 'admin_init', 'lese_spam_counter_register_settings' );

// Define the menu page callback function
function lese_menu_callback()
{
    global $wpdb;
    global $lese_restricted_names_table_name;

    if (isset($_POST["submit"])) {
        // Get the new list of restricted names
        $new_restricted_names = explode("\n", $_POST["restricted_names"]);

        // Delete all existing names from the table
        $wpdb->query("TRUNCATE TABLE {$lese_restricted_names_table_name}");

        // Insert the new names into the table
        foreach ($new_restricted_names as $name) {
            $name = trim($name);
            if ($name) {
                $wpdb->insert($lese_restricted_names_table_name, [
                    "name" => $name,
                ]);
            }
        }

        // Display a success message
        echo '<div id="message" class="updated notice is-dismissible"><p>Restricted names saved.</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button></div>';
    }

    // Get the list of current restricted names
    $restricted_names = $wpdb->get_results(
        "SELECT name FROM {$lese_restricted_names_table_name}"
    );
    $restricted_names_list = "";
    foreach ($restricted_names as $name) {
        $restricted_names_list .= $name->name . "\n";
    }

    // Display the form to edit the list of restricted names. we echo it as html
    echo '<div class="wrap">';
    echo "<h1>Restricted Usernames</h1>";
    echo "<p>Enter spam names here (one per line):";
    echo '<form method="post">';
    echo '<textarea id="restricted_names" name="restricted_names" rows="10" cols="50">' .
        esc_html($restricted_names_list) .
        "</textarea>";
    echo '<p class="submit"><input type="submit" name="submit" class="button button-primary" value="Save Changes"></p>';
    echo "</form>";
	// Also show the amount of registrations blocked (via the stored wp options increment)
	echo '<p>This system has blocked ' . esc_html( get_option( 'lese_spam_registrations_blocked_counter', 0 ) ) . ' spam registrations.</p>';
    echo "</div>";
}

// Define the menu page function
function lese_menu()
{
    add_submenu_page(
        "options-general.php",
        "Restricted Names",
        "Restricted Names",
        "manage_options",
        "lese-restricted-names",
        "lese_menu_callback"
    );
}

// Increments a wordpress option key pair value (how many registration attempts have been blocked)
function lese_increment_counter() {
    $current_value = get_option( 'lese_spam_registrations_blocked_counter', 0 );
    $new_value = $current_value + 1;
    update_option( 'lese_spam_registrations_blocked_counter', $new_value );
}

// Define the username check function
function lese_check_username($user_login, $user_email, $errors)
{
    global $wpdb;
    global $lese_restricted_names_table_name;

    // Get the list of restricted names from the database
    $restricted_names = $wpdb->get_results(
        "SELECT name FROM {$lese_restricted_names_table_name}"
    );

    // Makes sure that the checking will be case-insensitive (the database entries are all lowercase)
    $user_login_lowercase = strtolower($user_login);

    // Check if the username contains any restricted names
    foreach ($restricted_names as $name) {
        if (stripos($user_login_lowercase, $name->name) !== false) {
			// I think that reducing the verbosity so it just doesn't work and doesnt throw an error will increase the likelihood of spammers realising whats happening
            $errors->add("restricted_username_term", __("Stop it."));
			lese_increment_counter();
            break;
        }
    }
}

// Add a filter to check the username during registration
add_action("register_post", "lese_check_username", 10, 3);

?>
