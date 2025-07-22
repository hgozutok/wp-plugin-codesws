<?php
/**
 * CodesWholesale Settings Manager
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings management class
 */
class CWS_Settings {
    
    /**
     * Instance
     * @var CWS_Settings
     */
    private static $instance = null;
    
    /**
     * Settings cache
     * @var array
     */
    private $settings_cache = array();
    
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
        $this->load_settings();
        add_action('wp_ajax_cws_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_cws_test_connection', array($this, 'ajax_test_connection'));
    }
    
    /**
     * Load all settings from database
     */
    private function load_settings() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cws_settings';
        $results = $wpdb->get_results("SELECT setting_name, setting_value FROM $table_name", ARRAY_A);
        
        foreach ($results as $setting) {
            $this->settings_cache[$setting['setting_name']] = $setting['setting_value'];
        }
    }
    
    /**
     * Get setting value
     */
    public function get($key, $default = '') {
        return isset($this->settings_cache[$key]) ? $this->settings_cache[$key] : $default;
    }
    
    /**
     * Set setting value
     */
    public function set($key, $value) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cws_settings';
        
        $result = $wpdb->replace(
            $table_name,
            array(
                'setting_name' => $key,
                'setting_value' => $value,
                'updated_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s')
        );
        
        if ($result !== false) {
            $this->settings_cache[$key] = $value;
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete setting
     */
    public function delete($key) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cws_settings';
        
        $result = $wpdb->delete(
            $table_name,
            array('setting_name' => $key),
            array('%s')
        );
        
        if ($result !== false) {
            unset($this->settings_cache[$key]);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get all settings
     */
    public function get_all() {
        return $this->settings_cache;
    }
    
    /**
     * Get settings grouped by category
     */
    public function get_settings_schema() {
        return array(
            'api' => array(
                'title' => __('API Configuration', 'codeswholesale-sync'),
                'description' => __('Configure your CodesWholesale API credentials and connection settings.', 'codeswholesale-sync'),
                'fields' => array(
                    'cws_api_environment' => array(
                        'title' => __('Environment', 'codeswholesale-sync'),
                        'type' => 'select',
                        'options' => array(
                            'sandbox' => __('Sandbox (Testing)', 'codeswholesale-sync'),
                            'live' => __('Live (Production)', 'codeswholesale-sync')
                        ),
                        'description' => __('Choose your API environment. Use Sandbox for testing.', 'codeswholesale-sync'),
                        'default' => 'sandbox'
                    ),
                    'cws_client_id' => array(
                        'title' => __('Client ID', 'codeswholesale-sync'),
                        'type' => 'text',
                        'description' => __('Your CodesWholesale API Client ID.', 'codeswholesale-sync'),
                        'required' => true
                    ),
                    'cws_client_secret' => array(
                        'title' => __('Client Secret', 'codeswholesale-sync'),
                        'type' => 'password',
                        'description' => __('Your CodesWholesale API Client Secret.', 'codeswholesale-sync'),
                        'required' => true
                    )
                )
            ),
            'sync' => array(
                'title' => __('Synchronization Settings', 'codeswholesale-sync'),
                'description' => __('Configure product synchronization and import settings.', 'codeswholesale-sync'),
                'fields' => array(
                    'cws_auto_sync_enabled' => array(
                        'title' => __('Enable Automatic Sync', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Automatically sync products, prices, and stock levels.', 'codeswholesale-sync'),
                        'default' => 'yes'
                    ),
                    'cws_sync_interval' => array(
                        'title' => __('Sync Interval', 'codeswholesale-sync'),
                        'type' => 'select',
                        'options' => array(
                            'hourly' => __('Every Hour', 'codeswholesale-sync'),
                            'twicedaily' => __('Twice Daily', 'codeswholesale-sync'),
                            'daily' => __('Daily', 'codeswholesale-sync')
                        ),
                        'description' => __('How often to sync product data.', 'codeswholesale-sync'),
                        'default' => 'daily'
                    ),
                    'cws_import_filters' => array(
                        'title' => __('Import Filters', 'codeswholesale-sync'),
                        'type' => 'group',
                        'description' => __('Filter which products to import.', 'codeswholesale-sync'),
                        'fields' => array(
                            'platforms' => array(
                                'title' => __('Platforms', 'codeswholesale-sync'),
                                'type' => 'multiselect',
                                'description' => __('Select platforms to import (leave empty for all).', 'codeswholesale-sync')
                            ),
                            'regions' => array(
                                'title' => __('Regions', 'codeswholesale-sync'),
                                'type' => 'multiselect',
                                'description' => __('Select regions to import (leave empty for all).', 'codeswholesale-sync')
                            ),
                            'languages' => array(
                                'title' => __('Languages', 'codeswholesale-sync'),
                                'type' => 'multiselect',
                                'description' => __('Select languages to import (leave empty for all).', 'codeswholesale-sync')
                            )
                        )
                    )
                )
            ),
            'pricing' => array(
                'title' => __('Pricing Settings', 'codeswholesale-sync'),
                'description' => __('Configure pricing rules and markup settings.', 'codeswholesale-sync'),
                'fields' => array(
                    'cws_price_markup_type' => array(
                        'title' => __('Markup Type', 'codeswholesale-sync'),
                        'type' => 'select',
                        'options' => array(
                            'percentage' => __('Percentage', 'codeswholesale-sync'),
                            'fixed' => __('Fixed Amount', 'codeswholesale-sync')
                        ),
                        'description' => __('How to calculate markup on wholesale prices.', 'codeswholesale-sync'),
                        'default' => 'percentage'
                    ),
                    'cws_price_markup_value' => array(
                        'title' => __('Markup Value', 'codeswholesale-sync'),
                        'type' => 'number',
                        'description' => __('Markup percentage or fixed amount to add to wholesale price.', 'codeswholesale-sync'),
                        'default' => '20',
                        'step' => '0.01'
                    ),
                    'cws_enable_charm_pricing' => array(
                        'title' => __('Enable Charm Pricing', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Round prices to end in .99 (e.g., 19.99 instead of 20.00).', 'codeswholesale-sync'),
                        'default' => 'no'
                    ),
                    'cws_currency_conversion' => array(
                        'title' => __('Currency Conversion', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Enable automatic currency conversion if needed.', 'codeswholesale-sync'),
                        'default' => 'yes'
                    )
                )
            ),
            'inventory' => array(
                'title' => __('Inventory Settings', 'codeswholesale-sync'),
                'description' => __('Configure stock management and inventory settings.', 'codeswholesale-sync'),
                'fields' => array(
                    'cws_enable_stock_sync' => array(
                        'title' => __('Enable Stock Sync', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Automatically sync stock levels from CodesWholesale.', 'codeswholesale-sync'),
                        'default' => 'yes'
                    ),
                    'cws_low_stock_threshold' => array(
                        'title' => __('Low Stock Threshold', 'codeswholesale-sync'),
                        'type' => 'number',
                        'description' => __('Send notification when stock falls below this level.', 'codeswholesale-sync'),
                        'default' => '5',
                        'min' => '0'
                    ),
                    'cws_enable_pre_orders' => array(
                        'title' => __('Enable Pre-orders', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Allow importing and selling pre-order products.', 'codeswholesale-sync'),
                        'default' => 'yes'
                    ),
                    'cws_out_of_stock_action' => array(
                        'title' => __('Out of Stock Action', 'codeswholesale-sync'),
                        'type' => 'select',
                        'options' => array(
                            'hide' => __('Hide Product', 'codeswholesale-sync'),
                            'disable' => __('Mark as Out of Stock', 'codeswholesale-sync'),
                            'notify' => __('Notify Only', 'codeswholesale-sync')
                        ),
                        'description' => __('Action to take when a product goes out of stock.', 'codeswholesale-sync'),
                        'default' => 'disable'
                    )
                )
            ),
            'notifications' => array(
                'title' => __('Notifications', 'codeswholesale-sync'),
                'description' => __('Configure email notifications and alerts.', 'codeswholesale-sync'),
                'fields' => array(
                    'cws_notification_email' => array(
                        'title' => __('Notification Email', 'codeswholesale-sync'),
                        'type' => 'email',
                        'description' => __('Email address to receive notifications.', 'codeswholesale-sync'),
                        'default' => get_option('admin_email')
                    ),
                    'cws_notify_sync_errors' => array(
                        'title' => __('Sync Error Notifications', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Send email when sync operations fail.', 'codeswholesale-sync'),
                        'default' => 'yes'
                    ),
                    'cws_notify_low_balance' => array(
                        'title' => __('Low Balance Notifications', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Send email when account balance is low.', 'codeswholesale-sync'),
                        'default' => 'yes'
                    ),
                    'cws_notify_order_failures' => array(
                        'title' => __('Order Failure Notifications', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Send email when order processing fails.', 'codeswholesale-sync'),
                        'default' => 'yes'
                    )
                )
            ),
            'orders' => array(
                'title' => __('Order Settings', 'codeswholesale-sync'),
                'description' => __('Configure order processing and product key delivery settings.', 'codeswholesale-sync'),
                'fields' => array(
                    'cws_auto_process_orders' => array(
                        'title' => __('Auto Process Orders', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Automatically process orders when payment is completed.', 'codeswholesale-sync'),
                        'default' => 'yes'
                    ),
                    'cws_key_delivery_method' => array(
                        'title' => __('Key Delivery Method', 'codeswholesale-sync'),
                        'type' => 'select',
                        'options' => array(
                            'email' => __('Email Only', 'codeswholesale-sync'),
                            'order_page' => __('Order Page Only', 'codeswholesale-sync'),
                            'both' => __('Email + Order Page', 'codeswholesale-sync')
                        ),
                        'description' => __('How to deliver product keys to customers.', 'codeswholesale-sync'),
                        'default' => 'both'
                    ),
                    'cws_order_retry_attempts' => array(
                        'title' => __('Retry Attempts', 'codeswholesale-sync'),
                        'type' => 'number',
                        'description' => __('Number of times to retry failed orders.', 'codeswholesale-sync'),
                        'default' => '3',
                        'min' => '0',
                        'max' => '10'
                    ),
                    'cws_order_retry_delay' => array(
                        'title' => __('Retry Delay (minutes)', 'codeswholesale-sync'),
                        'type' => 'number',
                        'description' => __('Delay between retry attempts in minutes.', 'codeswholesale-sync'),
                        'default' => '5',
                        'min' => '1',
                        'max' => '60'
                    ),
                    'cws_enable_order_webhooks' => array(
                        'title' => __('Enable Order Webhooks', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Enable real-time order status updates via webhooks.', 'codeswholesale-sync'),
                        'default' => 'yes'
                    ),
                    'cws_order_debug_mode' => array(
                        'title' => __('Order Debug Mode', 'codeswholesale-sync'),
                        'type' => 'checkbox',
                        'description' => __('Enable detailed logging for order processing (disable in production).', 'codeswholesale-sync'),
                        'default' => 'no'
                    )
                )
            )
        );
    }
    
    /**
     * AJAX handler for saving settings
     */
    public function ajax_save_settings() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'codeswholesale-sync'));
        }
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        $saved_count = 0;
        
        foreach ($settings as $key => $value) {
            // Sanitize values based on field type
            $sanitized_value = $this->sanitize_setting_value($key, $value);
            
            if ($this->set($key, $sanitized_value)) {
                $saved_count++;
            }
        }
        
        // Refresh API client settings
        CWS_API_Client::get_instance()->refresh_settings();
        
        wp_send_json_success(array(
            'message' => sprintf(__('%d settings saved successfully.', 'codeswholesale-sync'), $saved_count)
        ));
    }
    
    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'codeswholesale-sync'));
        }
        
        $api_client = CWS_API_Client::get_instance();
        $result = $api_client->test_connection();
        
        if ($result['success']) {
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['error']);
        }
    }
    
    /**
     * Sanitize setting value
     */
    private function sanitize_setting_value($key, $value) {
        switch ($key) {
            case 'cws_client_id':
            case 'cws_client_secret':
                return sanitize_text_field($value);
                
            case 'cws_notification_email':
                return sanitize_email($value);
                
            case 'cws_price_markup_value':
            case 'cws_low_stock_threshold':
                return floatval($value);
                
            case 'cws_auto_sync_enabled':
            case 'cws_enable_charm_pricing':
            case 'cws_enable_pre_orders':
            case 'cws_enable_stock_sync':
            case 'cws_currency_conversion':
            case 'cws_notify_sync_errors':
            case 'cws_notify_low_balance':
            case 'cws_notify_order_failures':
                return $value ? 'yes' : 'no';
                
            case 'cws_api_environment':
                return in_array($value, array('sandbox', 'live')) ? $value : 'sandbox';
                
            case 'cws_sync_interval':
                return in_array($value, array('hourly', 'twicedaily', 'daily')) ? $value : 'daily';
                
            case 'cws_price_markup_type':
                return in_array($value, array('percentage', 'fixed')) ? $value : 'percentage';
                
            case 'cws_out_of_stock_action':
                return in_array($value, array('hide', 'disable', 'notify')) ? $value : 'disable';
                
            default:
                if (is_array($value)) {
                    return array_map('sanitize_text_field', $value);
                }
                return sanitize_text_field($value);
        }
    }
    
    /**
     * Get formatted settings for display
     */
    public function get_formatted_settings() {
        $schema = $this->get_settings_schema();
        $formatted = array();
        
        foreach ($schema as $section_key => $section) {
            $formatted[$section_key] = array(
                'title' => $section['title'],
                'description' => $section['description'],
                'fields' => array()
            );
            
            foreach ($section['fields'] as $field_key => $field) {
                $field['value'] = $this->get($field_key, isset($field['default']) ? $field['default'] : '');
                $formatted[$section_key]['fields'][$field_key] = $field;
            }
        }
        
        return $formatted;
    }
} 