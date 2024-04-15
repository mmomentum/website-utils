<?php

class FunctionCallTracker
{
    private $table_name;
    private $mapping_name;

    public function __construct()
    {
        global $wpdb;

        $this->table_name = $wpdb->prefix . 'lese_tracking_data';
        $this->mapping_name = $wpdb->prefix . 'lese_tracking_mapping';

        $this->create_function_calls_table();
        $this->create_id_mapping_table();
    }

    private function create_function_calls_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $this->table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            data_date date NOT NULL,
            tracked_id int NOT NULL,
            value int NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_id_date (data_date, tracked_id)
        ) $charset_collate;";

        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    // creates the table for mapping the IDs up to their string representations
    private function create_id_mapping_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $this->mapping_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            integer_id int NOT NULL,
            string_representation varchar(255) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_integer_id (integer_id)
        ) $charset_collate;";

        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function get_integer_id_from_string($string_representation)
    {
        global $wpdb;
        $integer_id = $wpdb->get_var($wpdb->prepare("SELECT integer_id FROM $this->mapping_name WHERE string_representation = %s", $string_representation));
        return $integer_id;
    }

    // Function to get string representation from integer ID
    public function get_string_from_integer_id($integer_id)
    {
        global $wpdb;
        $string_representation = $wpdb->get_var($wpdb->prepare("SELECT string_representation FROM $this->mapping_name WHERE integer_id = %d", $integer_id));
        return $string_representation;
    }

    public function track_function_call($integer_id)
    {
        global $wpdb;
        // Get the corresponding string representation from the mapping table

        // Continue with tracking using the string representation
        $today = date('Y-m-d');
        $existing_count = $wpdb->get_var($wpdb->prepare("SELECT value FROM $this->table_name WHERE data_date = %s AND tracked_id = %s", $today, $integer_id));
        if ($existing_count !== null) {
            $wpdb->update(
                $this->table_name,
                array('value' => $existing_count + 1),
                array(
                    'data_date' => $today,
                    'tracked_id' => $integer_id
                )
            );
        } else {
            $wpdb->insert(
                $this->table_name,
                array(
                    'data_date' => $today,
                    'tracked_id' => $integer_id,
                    'value' => 1,
                )
            );
        }
    }

    public function get_total_calls_today($tracked_id)
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare("SELECT value FROM {$this->table_name} WHERE data_date = %s AND tracked_id = %d", date('Y-m-d'), $tracked_id));
    }

    public function display_function_calls_admin_page()
    {
        global $wpdb;
        // for adding extra entries to the mapping table
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // Process form submission
            if (isset($_POST['submit'])) {
                $integer_id = intval($_POST['integer_id']);
                $string_representation = sanitize_text_field($_POST['string_representation']);
                // Insert or update the mapping in the database
                if ($integer_id && $string_representation) {
                    $wpdb->replace(
                        $this->mapping_name,
                        array(
                            'integer_id' => $integer_id,
                            'string_representation' => $string_representation,
                        )
                    );
                }
            }
        }
        // HTML output for the table appending & display
        ?>
        <div class="wrap">
            <h1>ID Mapping</h1>
            <form method="post" action="">
                <label for="integer_id">Integer ID:</label>
                <input type="number" id="integer_id" name="integer_id" required>
                <label for="string_representation">String Representation:</label>
                <input type="text" id="string_representation" name="string_representation" required>
                <input type="submit" name="submit" value="Save Mapping">
            </form>
            <h2>Current Mappings</h2>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Integer ID</th>
                        <th>String Representation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $mappings = $wpdb->get_results("SELECT * FROM $this->mapping_name");
                    foreach ($mappings as $mapping) {
                        echo "<tr><td>{$mapping->integer_id}</td><td>{$mapping->string_representation}</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}