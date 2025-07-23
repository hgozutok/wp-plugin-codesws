<?php
/**
 * CodesWholesale Admin Interface
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface class
 */
class CWS_Admin {
    
    /**
     * Instance
     * @var CWS_Admin
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // AJAX actions
        add_action('wp_ajax_cws_import_products', array($this, 'ajax_import_products'));
        add_action('wp_ajax_cws_sync_single_product', array($this, 'ajax_sync_single_product'));
        add_action('wp_ajax_cws_get_sync_status', array($this, 'ajax_get_sync_status'));
        add_action('wp_ajax_cws_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_cws_process_pending_orders', array($this, 'ajax_process_pending_orders'));
        add_action('wp_ajax_cws_resend_keys', array($this, 'ajax_resend_keys'));
        add_action('wp_ajax_cws_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_cws_start_import', array($this, 'ajax_start_import'));
        add_action('wp_ajax_cws_sync_products', array($this, 'ajax_sync_products'));
        add_action('wp_ajax_cws_update_prices', array($this, 'ajax_update_prices'));
        add_action('wp_ajax_cws_update_stock', array($this, 'ajax_update_stock'));
        add_action('wp_ajax_cws_get_import_progress', array($this, 'ajax_get_import_progress'));
        add_action('wp_ajax_cws_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_cws_retry_order', array($this, 'ajax_retry_order'));
        add_action('wp_ajax_cws_cancel_order', array($this, 'ajax_cancel_order'));
        add_action('wp_ajax_cws_get_order_details', array($this, 'ajax_get_order_details'));
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        // Check for required dependencies
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
        }
    }
    
    /**
     * Add admin menus
     */
    public function admin_menu() {
        $main_page = add_menu_page(
            __('CodesWholesale Sync', 'codeswholesale-sync'),
            __('CodesWholesale', 'codeswholesale-sync'),
            'manage_options',
            'codeswholesale-sync',
            array($this, 'dashboard_page'),
            'dashicons-update',
            30
        );
        
        add_submenu_page(
            'codeswholesale-sync',
            __('Dashboard', 'codeswholesale-sync'),
            __('Dashboard', 'codeswholesale-sync'),
            'manage_options',
            'codeswholesale-sync',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'codeswholesale-sync',
            __('Settings', 'codeswholesale-sync'),
            __('Settings', 'codeswholesale-sync'),
            'manage_options',
            'codeswholesale-sync-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'codeswholesale-sync',
            __('Import Products', 'codeswholesale-sync'),
            __('Import Products', 'codeswholesale-sync'),
            'manage_options',
            'codeswholesale-sync-import',
            array($this, 'import_page')
        );
        
        add_submenu_page(
            'codeswholesale-sync',
            __('Orders', 'codeswholesale-sync'),
            __('Orders', 'codeswholesale-sync'),
            'manage_options',
            'codeswholesale-sync-orders',
            array($this, 'orders_page')
        );
        
        add_submenu_page(
            'codeswholesale-sync',
            __('Product Mapping', 'codeswholesale-sync'),
            __('Product Mapping', 'codeswholesale-sync'),
            'manage_options',
            'codeswholesale-sync-mapping',
            array($this, 'mapping_page')
        );
        
        add_submenu_page(
            'codeswholesale-sync',
            __('Sync Logs', 'codeswholesale-sync'),
            __('Sync Logs', 'codeswholesale-sync'),
            'manage_options',
            'codeswholesale-sync-logs',
            array($this, 'logs_page')
        );
        
        // Hook for enqueuing scripts on plugin pages
        add_action('load-' . $main_page, array($this, 'load_admin_scripts'));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on plugin pages
        if (strpos($hook, 'codeswholesale-sync') === false) {
            return;
        }
        
        // Styles
        wp_enqueue_style(
            'cws-admin-style',
            CWS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CWS_PLUGIN_VERSION
        );
        
        // Scripts
        wp_enqueue_script(
            'cws-admin-script',
            CWS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-util'),
            CWS_PLUGIN_VERSION,
            true
        );
        
        // Localize script
        wp_localize_script('cws-admin-script', 'cwsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cws_admin_nonce'),
            'strings' => array(
                'confirmClearLogs' => __('Are you sure you want to clear all logs?', 'codeswholesale-sync'),
                'importing' => __('Importing...', 'codeswholesale-sync'),
                'success' => __('Success', 'codeswholesale-sync'),
                'error' => __('Error', 'codeswholesale-sync'),
                'testing' => __('Testing connection...', 'codeswholesale-sync'),
                'connected' => __('Connected successfully!', 'codeswholesale-sync'),
                'connectionFailed' => __('Connection failed', 'codeswholesale-sync')
            )
        ));
    }
    
    /**
     * Load admin scripts
     */
    public function load_admin_scripts() {
        wp_enqueue_media();
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $api_client = CWS_API_Client::get_instance();
        $settings = CWS_Settings::get_instance();
        
        // Get connection status
        $connection_status = $api_client->test_connection();
        
        // Get sync statistics
        $sync_stats = $this->get_sync_statistics();
        
        // Get recent logs
        $recent_logs = $this->get_recent_logs(10);
        
        include CWS_PLUGIN_PATH . 'includes/admin/views/dashboard.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        $settings = CWS_Settings::get_instance();
        $formatted_settings = $settings->get_formatted_settings();
        
        include CWS_PLUGIN_PATH . 'includes/admin/views/settings.php';
    }
    
    /**
     * Import page
     */
    public function import_page() {
        $api_client = CWS_API_Client::get_instance();
        
        $platforms = array();
        $regions = array();
        $languages = array();
        
        if ($api_client->is_connected()) {
            try {
                $platforms = $api_client->get_platforms();
                $regions = $api_client->get_regions();
                $languages = $api_client->get_languages();
            } catch (Exception $e) {
                // Handle error silently, will show in UI
            }
        }
        
        include CWS_PLUGIN_PATH . 'includes/admin/views/import.php';
    }
    
    /**
     * Product mapping page
     */
    public function mapping_page() {
        global $wpdb;
        
        // Get current page
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($paged - 1) * $per_page;
        
        // Get mappings
        $table_name = $wpdb->prefix . 'cws_product_mapping';
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $mappings = $wpdb->get_results($wpdb->prepare(
            "SELECT m.*, p.post_title as wc_product_name 
             FROM $table_name m 
             LEFT JOIN {$wpdb->posts} p ON m.wc_product_id = p.ID 
             ORDER BY m.updated_at DESC 
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        
        $total_pages = ceil($total_items / $per_page);
        
        include CWS_PLUGIN_PATH . 'includes/admin/views/mapping.php';
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        global $wpdb;
        
        // Handle log filtering
        $filter_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        $filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        // Get current page
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $offset = ($paged - 1) * $per_page;
        
        // Build query
        $table_name = $wpdb->prefix . 'cws_sync_log';
        $where_clause = "WHERE 1=1";
        $where_values = array();
        
        if (!empty($filter_type)) {
            $where_clause .= " AND operation_type = %s";
            $where_values[] = $filter_type;
        }
        
        if (!empty($filter_status)) {
            $where_clause .= " AND status = %s";
            $where_values[] = $filter_status;
        }
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM $table_name $where_clause";
        if (!empty($where_values)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $where_values));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }
        
        // Get logs
        $logs_query = "SELECT * FROM $table_name $where_clause ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $logs = $wpdb->get_results($wpdb->prepare($logs_query, $where_values));
        
        $total_pages = ceil($total_items / $per_page);
        
        include CWS_PLUGIN_PATH . 'includes/admin/views/logs.php';
    }
    
    /**
     * Orders page
     */
    public function orders_page() {
        include CWS_PLUGIN_PATH . 'includes/admin/views/orders.php';
    }
    
    /**
     * Get recent CWS orders
     */
    private function get_recent_cws_orders($limit = 50) {
        global $wpdb;
        
        // Get orders that have CWS data
        $orders = wc_get_orders(array(
            'limit' => $limit,
            'meta_query' => array(
                array(
                    'key' => '_cws_processed',
                    'compare' => 'EXISTS'
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        $orders_data = array();
        foreach ($orders as $order) {
            $orders_data[] = array(
                'order_id' => $order->get_id(),
                'cws_status' => $order->get_meta('_cws_status'),
                'cws_orders' => $order->get_meta('_cws_orders'),
                'order' => $order
            );
        }
        
        return $orders_data;
    }
    
    /**
     * Get sync statistics
     */
    private function get_sync_statistics() {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $logs_table = $wpdb->prefix . 'cws_sync_log';
        
        return array(
            'total_mapped' => $wpdb->get_var("SELECT COUNT(*) FROM $mapping_table"),
            'synced_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $logs_table WHERE DATE(created_at) = %s AND status = 'success'",
                current_time('Y-m-d')
            )),
            'errors_today' => $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $logs_table WHERE DATE(created_at) = %s AND status = 'error'",
                current_time('Y-m-d')
            )),
            'last_sync' => $wpdb->get_var("SELECT MAX(created_at) FROM $logs_table WHERE operation_type IN ('import', 'price_update', 'stock_update')")
        );
    }
    
    /**
     * Render a setting field
     */
    public function render_setting_field($field_key, $field) {
        $settings = CWS_Settings::get_instance();
        $value = $settings->get($field_key);
        $field_id = 'cws_setting_' . $field_key;
        $field_name = 'cws_settings[' . $field_key . ']';
        
        echo '<tr>';
        echo '<th scope="row"><label for="' . esc_attr($field_id) . '">' . esc_html($field['title']) . '</label></th>';
        echo '<td>';
        
        switch ($field['type']) {
            case 'text':
                echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;
                
            case 'password':
                echo '<input type="password" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="regular-text" />';
                break;
                
            case 'number':
                $min = isset($field['min']) ? 'min="' . esc_attr($field['min']) . '"' : '';
                $max = isset($field['max']) ? 'max="' . esc_attr($field['max']) . '"' : '';
                $step = isset($field['step']) ? 'step="' . esc_attr($field['step']) . '"' : '';
                echo '<input type="number" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($value) . '" class="small-text" ' . $min . ' ' . $max . ' ' . $step . ' />';
                break;
                
            case 'select':
                echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '">';
                foreach ($field['options'] as $option_value => $option_label) {
                    echo '<option value="' . esc_attr($option_value) . '"' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
                }
                echo '</select>';
                break;
                
            case 'checkbox':
                echo '<label for="' . esc_attr($field_id) . '">';
                echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="1"' . checked($value, 1, false) . ' />';
                echo ' ' . esc_html($field['description']);
                echo '</label>';
                break;
                
            case 'textarea':
                echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" rows="5" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
                break;
        }
        
        if (isset($field['description']) && $field['type'] !== 'checkbox') {
            echo '<p class="description">' . esc_html($field['description']) . '</p>';
        }
        
        echo '</td>';
        echo '</tr>';
    }
    
    /**
     * Get recent logs
     */
    private function get_recent_logs($limit = 10) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cws_sync_log';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    /**
     * AJAX handler for importing products
     */
    public function ajax_import_products() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'codeswholesale-sync'));
        }
        
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        
        try {
            $sync = CWS_Product_Sync::get_instance();
            $result = $sync->import_products($filters, $limit);
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for syncing single product
     */
    public function ajax_sync_single_product() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'codeswholesale-sync'));
        }
        
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error(__('Invalid product ID', 'codeswholesale-sync'));
        }
        
        try {
            $sync = CWS_Product_Sync::get_instance();
            $result = $sync->sync_single_product($product_id);
            
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * AJAX handler for getting sync status
     */
    public function ajax_get_sync_status() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        $stats = $this->get_sync_statistics();
        $recent_logs = $this->get_recent_logs(5);
        
        wp_send_json_success(array(
            'stats' => $stats,
            'recent_logs' => $recent_logs
        ));
    }
    
    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'codeswholesale-sync'));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cws_sync_log';
        
        $result = $wpdb->query("DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cleared %d old log entries.', 'codeswholesale-sync'), $result)
        ));
    }
    
    /**
     * AJAX handler for processing pending orders
     */
    public function ajax_process_pending_orders() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions', 'codeswholesale-sync'));
        }
        
        try {
            $order_manager = CWS_Order_Manager::get_instance();
            $order_manager->process_pending_orders();
            
            wp_send_json_success(array(
                'message' => __('Pending orders processing initiated successfully.', 'codeswholesale-sync')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('Failed to process pending orders: ', 'codeswholesale-sync') . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX handler for resending product keys
     */
    public function ajax_resend_keys() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions', 'codeswholesale-sync'));
        }
        
        $order_id = intval($_POST['order_id']);
        if (!$order_id) {
            wp_send_json_error(__('Invalid order ID', 'codeswholesale-sync'));
        }
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception(__('Order not found', 'codeswholesale-sync'));
            }
            
            $order_data = $order->get_meta('_cws_orders');
            if (empty($order_data)) {
                throw new Exception(__('No CodesWholesale data found for this order', 'codeswholesale-sync'));
            }
            
            $order_manager = CWS_Order_Manager::get_instance();
            $order_manager->deliver_keys_to_customer($order, $order_data);
            
            wp_send_json_success(array(
                'message' => __('Product keys resent successfully.', 'codeswholesale-sync')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => __('Failed to resend keys: ', 'codeswholesale-sync') . $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Test API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'codeswholesale-sync'));
        }
        
        $api_client = CWS_API_Client::get_instance();
        $result = $api_client->test_connection();
        
        if ($result['status'] === 'success') {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * AJAX: Save settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Permission denied', 'codeswholesale-sync'));
        }
        
        if (!isset($_POST['settings']) || !is_array($_POST['settings'])) {
            wp_send_json_error(array('message' => __('Invalid settings data', 'codeswholesale-sync')));
            return;
        }
        
        $settings = CWS_Settings::get_instance();
        $posted_settings = $_POST['settings'];
        
        // Sanitize and save each setting
        foreach ($posted_settings as $key => $value) {
            // Basic sanitization based on setting type
            if (is_string($value)) {
                $value = sanitize_text_field($value);
            } elseif (is_numeric($value)) {
                $value = floatval($value);
            } elseif (is_bool($value)) {
                $value = (bool) $value;
            }
            
            $settings->update_setting($key, $value);
        }
        
        // Refresh API client with new settings
        $api_client = CWS_API_Client::get_instance();
        $api_client->refresh_settings();
        
        wp_send_json_success(array(
            'message' => __('Settings saved successfully', 'codeswholesale-sync')
        ));
    }
    
    /**
     * AJAX: Retry order processing
     */
    public function ajax_retry_order() {
        $order_manager = CWS_Order_Manager::get_instance();
        $order_manager->ajax_retry_order();
    }
    
    /**
     * AJAX: Cancel CodesWholesale order
     */
    public function ajax_cancel_order() {
        $order_manager = CWS_Order_Manager::get_instance();
        $order_manager->ajax_cancel_order();
    }
    
    /**
     * AJAX: Get order details
     */
    public function ajax_get_order_details() {
        $order_manager = CWS_Order_Manager::get_instance();
        $order_manager->ajax_get_order_details();
    }
    
    /**
     * AJAX: Start product import
     */
    public function ajax_start_import() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'codeswholesale-sync'));
        }
        
        // Schedule import job
        wp_schedule_single_event(time(), 'cws_import_products');
        
        wp_send_json_success(array(
            'message' => __('Product import started successfully', 'codeswholesale-sync')
        ));
    }
    
    /**
     * AJAX: Sync products
     */
    public function ajax_sync_products() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'codeswholesale-sync'));
        }
        
        // Schedule sync job
        wp_schedule_single_event(time(), 'cws_sync_products');
        
        wp_send_json_success(array(
            'message' => __('Product sync started successfully', 'codeswholesale-sync')
        ));
    }
    
    /**
     * AJAX: Update prices
     */
    public function ajax_update_prices() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'codeswholesale-sync'));
        }
        
        // Schedule price update job
        wp_schedule_single_event(time(), 'cws_update_prices');
        
        wp_send_json_success(array(
            'message' => __('Price update started successfully', 'codeswholesale-sync')
        ));
    }
    
    /**
     * AJAX: Update stock
     */
    public function ajax_update_stock() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'codeswholesale-sync'));
        }
        
        // Schedule stock update job
        wp_schedule_single_event(time(), 'cws_update_stock');
        
        wp_send_json_success(array(
            'message' => __('Stock update started successfully', 'codeswholesale-sync')
        ));
    }
    
    /**
     * AJAX: Get import progress
     */
    public function ajax_get_import_progress() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'codeswholesale-sync'));
        }
        
        // Get progress from transient or option
        $progress = get_transient('cws_import_progress');
        if (!$progress) {
            $progress = array(
                'status' => 'idle',
                'progress' => 0,
                'total' => 0,
                'current' => 0,
                'message' => __('No import in progress', 'codeswholesale-sync')
            );
        }
        
        wp_send_json_success($progress);
    }
    
    /**
     * Admin notices
     */
    public function admin_notices() {
        $screen = get_current_screen();
        
        if (strpos($screen->id, 'codeswholesale-sync') === false) {
            return;
        }
        
        // Check API connection
        $settings = CWS_Settings::get_instance();
        $client_id = $settings->get('cws_client_id');
        $client_secret = $settings->get('cws_client_secret');
        
        if (empty($client_id) || empty($client_secret)) {
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('CodesWholesale API credentials are not configured.', 'codeswholesale-sync'); ?>
                    <a href="<?php echo admin_url('admin.php?page=codeswholesale-sync-settings'); ?>">
                        <?php _e('Configure now', 'codeswholesale-sync'); ?>
                    </a>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('CodesWholesale Sync requires WooCommerce to be installed and active.', 'codeswholesale-sync'); ?></p>
        </div>
        <?php
    }
} 