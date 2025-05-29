<?php
/**
 * Plugin Name: EDD Customer Cleanup
 * Description: Batch cleanup of guest customers who only have free downloads
 * Version: 1.1.0
 * Author: Aidan Baker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class EDD_Customer_Cleanup {
    
    private $batch_size = 50; // Process 50 customers per batch
    private $required_products = array(); // Product IDs that customers must own to be retained
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_start_cleanup', array($this, 'start_cleanup'));
        add_action('wp_ajax_stop_cleanup', array($this, 'stop_cleanup'));
        add_action('edd_customer_cleanup_batch', array($this, 'process_batch'));
        
        // Add admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }
    
    public function init() {
        // Check if EDD is active
        if (!class_exists('Easy_Digital_Downloads')) {
            add_action('admin_notices', array($this, 'edd_missing_notice'));
            return;
        }
    }
    
    public function edd_missing_notice() {
        echo '<div class="notice notice-error"><p>EDD Customer Cleanup requires Easy Digital Downloads to be active.</p></div>';
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=download',
            'Customer Cleanup',
            'Customer Cleanup',
            'manage_shop_settings',
            'edd-customer-cleanup',
            array($this, 'admin_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'edd-customer-cleanup') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'edd_cleanup_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('edd_cleanup_nonce')
        ));
    }
    
    public function admin_page() {
        $is_running = wp_next_scheduled('edd_customer_cleanup_batch');
        $stats = $this->get_cleanup_stats();
        
        ?>
        <div class="wrap">
            <h1>EDD Customer Cleanup</h1>
            
            <div class="card">
                <h2>Cleanup Statistics</h2>
                <p><strong>Total Customers:</strong> <?php echo number_format($stats['total_customers']); ?></p>
                <p><strong>Guest Customers:</strong> <?php echo number_format($stats['guest_customers']); ?></p>
                <p><strong>Registered Customers:</strong> <?php echo number_format($stats['registered_customers']); ?></p>
                <p><strong>Estimated Deletable:</strong> <?php echo number_format($stats['deletable_estimate']); ?></p>
                <p><strong>Last Processed ID:</strong> <?php echo get_option('edd_cleanup_last_processed_id', 0); ?></p>
            </div>
            
            <div class="card">
                <h2>Required Products</h2>
                <p>Customers must own at least one of these products to be retained:</p>
                <form method="post" action="">
                    <?php wp_nonce_field('edd_cleanup_settings', 'edd_cleanup_nonce'); ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Product IDs</th>
                            <td>
                                <input type="text" name="required_products" value="<?php echo esc_attr(implode(',', $this->get_required_products())); ?>" class="regular-text" />
                                <p class="description">Comma-separated list of product IDs (e.g., 123,456,789)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Batch Size</th>
                            <td>
                                <input type="number" name="batch_size" value="<?php echo $this->batch_size; ?>" min="10" max="500" />
                                <p class="description">Number of customers to process per batch (10-500)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Delete User Accounts</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="delete_user_accounts" value="1" <?php checked(get_option('edd_cleanup_delete_user_accounts', 0)); ?> />
                                    Also delete WordPress user accounts for customers who don't own required products
                                </label>
                                <p class="description"><strong>Warning:</strong> This will permanently delete WordPress user accounts. Use with extreme caution!</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Exclude User Roles</th>
                            <td>
                                <?php 
                                $excluded_roles = get_option('edd_cleanup_excluded_roles', array('administrator', 'editor', 'shop_manager'));
                                $all_roles = wp_roles()->get_names();
                                foreach ($all_roles as $role_key => $role_name): ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" name="excluded_roles[]" value="<?php echo esc_attr($role_key); ?>" 
                                               <?php checked(in_array($role_key, $excluded_roles)); ?> />
                                        <?php echo esc_html($role_name); ?>
                                    </label>
                                <?php endforeach; ?>
                                <p class="description">User accounts with these roles will never be deleted, regardless of purchase history.</p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button('Save Settings'); ?>
                </form>
            </div>
            
            <div class="card">
                <h2>Cleanup Control</h2>
                <?php if ($is_running): ?>
                    <p><strong>Status:</strong> <span style="color: green;">Running</span></p>
                    <p>Next batch scheduled for: <?php echo date('Y-m-d H:i:s', $is_running); ?></p>
                    <button id="stop-cleanup" class="button button-secondary">Stop Cleanup</button>
                <?php else: ?>
                    <p><strong>Status:</strong> <span style="color: red;">Stopped</span></p>
                    <button id="start-cleanup" class="button button-primary">Start Cleanup</button>
                    <button id="reset-progress" class="button button-secondary">Reset Progress</button>
                <?php endif; ?>
                
                <div id="cleanup-log" style="margin-top: 20px; max-height: 300px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd;">
                    <h4>Recent Activity</h4>
                    <?php echo $this->get_cleanup_log(); ?>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#start-cleanup').click(function() {
                if (confirm('Are you sure you want to start the customer cleanup? This will permanently delete customer and order data.')) {
                    $.post(edd_cleanup_ajax.ajax_url, {
                        action: 'start_cleanup',
                        nonce: edd_cleanup_ajax.nonce
                    }, function(response) {
                        location.reload();
                    });
                }
            });
            
            $('#stop-cleanup').click(function() {
                $.post(edd_cleanup_ajax.ajax_url, {
                    action: 'stop_cleanup',
                    nonce: edd_cleanup_ajax.nonce
                }, function(response) {
                    location.reload();
                });
            });
            
            $('#reset-progress').click(function() {
                if (confirm('Reset cleanup progress? This will start from the beginning.')) {
                    $.post(edd_cleanup_ajax.ajax_url, {
                        action: 'reset_progress',
                        nonce: edd_cleanup_ajax.nonce
                    }, function(response) {
                        location.reload();
                    });
                }
            });
        });
        </script>
        <?php
        
        // Handle settings form submission
        if (isset($_POST['edd_cleanup_nonce']) && wp_verify_nonce($_POST['edd_cleanup_nonce'], 'edd_cleanup_settings')) {
            $this->save_settings();
        }
    }
    
    private function save_settings() {
        if (isset($_POST['required_products'])) {
            $products = array_map('intval', array_filter(explode(',', sanitize_text_field($_POST['required_products']))));
            update_option('edd_cleanup_required_products', $products);
        }
        
        if (isset($_POST['batch_size'])) {
            $batch_size = max(10, min(500, intval($_POST['batch_size'])));
            update_option('edd_cleanup_batch_size', $batch_size);
            $this->batch_size = $batch_size;
        }
        
        // Save delete user accounts setting
        update_option('edd_cleanup_delete_user_accounts', isset($_POST['delete_user_accounts']) ? 1 : 0);
        
        // Save excluded roles
        $excluded_roles = isset($_POST['excluded_roles']) ? array_map('sanitize_text_field', $_POST['excluded_roles']) : array();
        // Always ensure administrator is protected
        if (!in_array('administrator', $excluded_roles)) {
            $excluded_roles[] = 'administrator';
        }
        update_option('edd_cleanup_excluded_roles', $excluded_roles);
        
        echo '<div class="notice notice-success"><p>Settings saved!</p></div>';
    }
    
    public function start_cleanup() {
        check_ajax_referer('edd_cleanup_nonce', 'nonce');
        
        if (!current_user_can('manage_shop_settings')) {
            wp_die('Unauthorized');
        }
        
        // Schedule the first batch
        if (!wp_next_scheduled('edd_customer_cleanup_batch')) {
            wp_schedule_single_event(time() + 30, 'edd_customer_cleanup_batch');
            $this->log_activity('Cleanup started by ' . wp_get_current_user()->display_name);
        }
        
        wp_die('Started');
    }
    
    public function stop_cleanup() {
        check_ajax_referer('edd_cleanup_nonce', 'nonce');
        
        if (!current_user_can('manage_shop_settings')) {
            wp_die('Unauthorized');
        }
        
        // Clear scheduled event
        wp_clear_scheduled_hook('edd_customer_cleanup_batch');
        $this->log_activity('Cleanup stopped by ' . wp_get_current_user()->display_name);
        
        wp_die('Stopped');
    }
    
    public function process_batch() {
        global $wpdb;
        
        $batch_size = get_option('edd_cleanup_batch_size', $this->batch_size);
        $last_processed_id = get_option('edd_cleanup_last_processed_id', 0);
        $required_products = $this->get_required_products();
        
        if (empty($required_products)) {
            $this->log_activity('ERROR: No required products specified. Cleanup stopped.');
            return;
        }
        
        // Get batch of customers
        $customers = $wpdb->get_results($wpdb->prepare("
            SELECT id, user_id, email 
            FROM {$wpdb->prefix}edd_customers 
            WHERE id > %d 
            ORDER BY id ASC 
            LIMIT %d
        ", $last_processed_id, $batch_size));
        
        if (empty($customers)) {
            $this->log_activity('Cleanup completed! No more customers to process.');
            delete_option('edd_cleanup_last_processed_id');
            return;
        }
        
        $deleted_count = 0;
        $processed_count = 0;
        
        foreach ($customers as $customer) {
            $processed_count++;
            
            // Always update the last processed ID to ensure progress continues
            update_option('edd_cleanup_last_processed_id', $customer->id);
            
            // Check if customer owns any required products
            $owns_required_product = $this->customer_owns_required_product($customer->id, $required_products);
            
            if (!$owns_required_product) {
                $should_delete = true;
                $user_deleted = false;
                
                // If customer has a WordPress user account, check if we should delete it
                if (!empty($customer->user_id)) {
                    $delete_user_accounts = get_option('edd_cleanup_delete_user_accounts', 0);
                    
                    if ($delete_user_accounts) {
                        // Check if user has protected role
                        if ($this->user_has_protected_role($customer->user_id)) {
                            $should_delete = false;
                            $this->log_activity("Skipped user ID {$customer->user_id} (protected role) - Customer ID: {$customer->id}");
                        } else {
                            // Delete the WordPress user account
                            $user_deleted = $this->delete_wordpress_user($customer->user_id);
                            if ($user_deleted) {
                                $this->log_activity("Deleted WordPress user ID {$customer->user_id} - Customer ID: {$customer->id}");
                            }
                        }
                    } else {
                        // User account deletion is disabled, skip this customer
                        $should_delete = false;
                    }
                }
                
                if ($should_delete) {
                    // Delete customer and their orders
                    $this->delete_customer_and_orders($customer->id);
                    $deleted_count++;
                }
            }
        }
        
        $this->log_activity("Processed {$processed_count} customers, deleted {$deleted_count}");
        
        // Schedule next batch if there are more customers
        if (count($customers) == $batch_size) {
            wp_schedule_single_event(time() + 60, 'edd_customer_cleanup_batch'); // 1 minute delay
        } else {
            $this->log_activity('Cleanup completed!');
            delete_option('edd_cleanup_last_processed_id');
        }
    }
    
    private function customer_owns_required_product($customer_id, $required_products) {
        global $wpdb;
        
        $product_ids_placeholder = implode(',', array_fill(0, count($required_products), '%d'));
        
        $query = $wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}edd_order_items oi
            INNER JOIN {$wpdb->prefix}edd_orders o ON oi.order_id = o.id
            WHERE o.customer_id = %d 
            AND oi.product_id IN ({$product_ids_placeholder})
            AND o.status IN ('complete', 'publish')
        ", array_merge(array($customer_id), $required_products));
        
        return $wpdb->get_var($query) > 0;
    }
    
    private function delete_customer_and_orders($customer_id) {
        global $wpdb;
        
        // Get all orders for this customer
        $order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}edd_orders WHERE customer_id = %d
        ", $customer_id));
        
        // Delete order items, adjustments, and addresses for each order
        foreach ($order_ids as $order_id) {
            $wpdb->delete($wpdb->prefix . 'edd_order_items', array('order_id' => $order_id));
            $wpdb->delete($wpdb->prefix . 'edd_order_adjustments', array('object_id' => $order_id));
            $wpdb->delete($wpdb->prefix . 'edd_order_addresses', array('order_id' => $order_id));
        }
        
        // Delete orders
        $wpdb->delete($wpdb->prefix . 'edd_orders', array('customer_id' => $customer_id));
        
        // Delete customer addresses
        $wpdb->delete($wpdb->prefix . 'edd_customer_addresses', array('customer_id' => $customer_id));
        
        // Delete customer email addresses
        $wpdb->delete($wpdb->prefix . 'edd_customer_email_addresses', array('customer_id' => $customer_id));
        
        // Delete the customer
        $wpdb->delete($wpdb->prefix . 'edd_customers', array('id' => $customer_id));
    }
    
    private function user_has_protected_role($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return true; // If we can't get the user, protect them by default
        }
        
        $excluded_roles = get_option('edd_cleanup_excluded_roles', array('administrator', 'editor', 'shop_manager'));
        $user_roles = $user->roles;
        
        // Check if user has any protected roles
        foreach ($user_roles as $role) {
            if (in_array($role, $excluded_roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function delete_wordpress_user($user_id) {
        if (!function_exists('wp_delete_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }
        
        // Double-check the user doesn't have a protected role before deletion
        if ($this->user_has_protected_role($user_id)) {
            return false;
        }
        
        // Delete user and reassign their content to admin (user ID 1)
        // You can change this to null if you want to delete their content entirely
        $result = wp_delete_user($user_id, 1);
        
        return $result;
    }
    
    private function get_required_products() {
        if (empty($this->required_products)) {
            $this->required_products = get_option('edd_cleanup_required_products', array());
        }
        return $this->required_products;
    }
    
    private function get_cleanup_stats() {
        global $wpdb;
        
        $total_customers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}edd_customers");
        $guest_customers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}edd_customers WHERE user_id = 0 OR user_id IS NULL");
        $registered_customers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}edd_customers WHERE user_id > 0");
        
        // Rough estimate of deletable customers
        $required_products = $this->get_required_products();
        $deletable_estimate = 0;
        
        if (!empty($required_products)) {
            $product_ids_placeholder = implode(',', array_fill(0, count($required_products), '%d'));
            
            // Count guests without required products
            $deletable_guests = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT c.id)
                FROM {$wpdb->prefix}edd_customers c
                LEFT JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id AND o.status IN ('complete', 'publish')
                LEFT JOIN {$wpdb->prefix}edd_order_items oi ON o.id = oi.order_id AND oi.product_id IN ({$product_ids_placeholder})
                WHERE (c.user_id = 0 OR c.user_id IS NULL)
                AND oi.id IS NULL
            ", $required_products));
            
            $deletable_estimate = $deletable_guests;
            
            // If user account deletion is enabled, also count registered users without required products
            if (get_option('edd_cleanup_delete_user_accounts', 0)) {
                $deletable_registered = $wpdb->get_var($wpdb->prepare("
                    SELECT COUNT(DISTINCT c.id)
                    FROM {$wpdb->prefix}edd_customers c
                    LEFT JOIN {$wpdb->prefix}edd_orders o ON c.id = o.customer_id AND o.status IN ('complete', 'publish')
                    LEFT JOIN {$wpdb->prefix}edd_order_items oi ON o.id = oi.order_id AND oi.product_id IN ({$product_ids_placeholder})
                    WHERE c.user_id > 0
                    AND oi.id IS NULL
                ", $required_products));
                
                $deletable_estimate += $deletable_registered;
            }
        }
        
        return array(
            'total_customers' => $total_customers,
            'guest_customers' => $guest_customers,
            'registered_customers' => $registered_customers,
            'deletable_estimate' => $deletable_estimate
        );
    }
    
    private function log_activity($message) {
        $log = get_option('edd_cleanup_log', array());
        $log[] = array(
            'time' => current_time('mysql'),
            'message' => $message
        );
        
        // Keep only last 50 log entries
        if (count($log) > 50) {
            $log = array_slice($log, -50);
        }
        
        update_option('edd_cleanup_log', $log);
    }
    
    private function get_cleanup_log() {
        $log = get_option('edd_cleanup_log', array());
        if (empty($log)) {
            return '<p>No activity yet.</p>';
        }
        
        $output = '';
        foreach (array_reverse($log) as $entry) {
            $output .= '<p><strong>' . $entry['time'] . ':</strong> ' . esc_html($entry['message']) . '</p>';
        }
        
        return $output;
    }
}

// Initialize the plugin
new EDD_Customer_Cleanup();

// Activation hook to create necessary options
register_activation_hook(__FILE__, function() {
    if (!get_option('edd_cleanup_required_products')) {
        add_option('edd_cleanup_required_products', array());
    }
    if (!get_option('edd_cleanup_batch_size')) {
        add_option('edd_cleanup_batch_size', 50);
    }
    if (!get_option('edd_cleanup_delete_user_accounts')) {
        add_option('edd_cleanup_delete_user_accounts', 0);
    }
    if (!get_option('edd_cleanup_excluded_roles')) {
        add_option('edd_cleanup_excluded_roles', array('administrator', 'editor', 'shop_manager'));
    }
});

// Deactivation hook to clean up scheduled events
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('edd_customer_cleanup_batch');
});