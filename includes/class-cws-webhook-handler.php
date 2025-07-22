<?php
/**
 * CodesWholesale Webhook Handler
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook handler class
 */
class CWS_Webhook_Handler {
    
    /**
     * Instance
     * @var CWS_Webhook_Handler
     */
    private static $instance = null;
    
    /**
     * API Client
     * @var CWS_API_Client
     */
    private $api_client;
    
    /**
     * Settings
     * @var CWS_Settings
     */
    private $settings;
    
    /**
     * Product Sync
     * @var CWS_Product_Sync
     */
    private $product_sync;
    
    /**
     * Price Updater
     * @var CWS_Price_Updater
     */
    private $price_updater;
    
    /**
     * Stock Manager
     * @var CWS_Stock_Manager
     */
    private $stock_manager;
    
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
        $this->api_client = CWS_API_Client::get_instance();
        $this->settings = CWS_Settings::get_instance();
        
        // Hook into WordPress
        add_action('init', array($this, 'init'));
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Initialize other components
        $this->product_sync = CWS_Product_Sync::get_instance();
        $this->price_updater = CWS_Price_Updater::get_instance();
        $this->stock_manager = CWS_Stock_Manager::get_instance();
        
        // Register webhook handlers with CodesWholesale client
        $this->register_webhook_handlers();
    }
    
    /**
     * Register REST API endpoint for webhooks
     */
    public function register_webhook_endpoint() {
        register_rest_route('codeswholesale/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_webhook_request'),
            'permission_callback' => '__return_true', // We'll validate the signature instead
            'args' => array()
        ));
    }
    
    /**
     * Handle incoming webhook request
     */
    public function handle_webhook_request($request) {
        $this->log('Webhook request received', 'info', 'webhook');
        
        try {
            // Get raw body and headers
            $raw_body = $request->get_body();
            $headers = $request->get_headers();
            
            if (empty($raw_body)) {
                throw new Exception('Empty webhook body');
            }
            
            // Verify webhook signature
            $signature = isset($headers['x_signature']) ? $headers['x_signature'][0] : '';
            if (!$this->verify_webhook_signature($raw_body, $signature)) {
                throw new Exception('Invalid webhook signature');
            }
            
            // Get CodesWholesale client for webhook processing
            $client = $this->api_client->get_webhook_client();
            
            if (!$client) {
                throw new Exception('Webhook client not available');
            }
            
            // Process webhook using CodesWholesale SDK
            $client->handle($signature);
            
            $this->log('Webhook processed successfully', 'info', 'webhook');
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Webhook processed successfully'
            ), 200);
            
        } catch (Exception $e) {
            $this->log('Webhook processing failed: ' . $e->getMessage(), 'error', 'webhook');
            
            return new WP_REST_Response(array(
                'success' => false,
                'error' => $e->getMessage()
            ), 400);
        }
    }
    
    /**
     * Register webhook event handlers
     */
    private function register_webhook_handlers() {
        if (!$this->api_client->is_connected()) {
            return;
        }
        
        $client = $this->api_client->get_webhook_client();
        
        if (!$client) {
            return;
        }
        
        // Stock and price changes
        $client->registerStockAndPriceChangeHandler(array($this, 'handle_stock_price_change'));
        
        // Product updates (name, description, images)
        $client->registerUpdateProductHandler(array($this, 'handle_product_update'));
        
        // New products
        $client->registerNewProductHandler(array($this, 'handle_new_product'));
        
        // Hidden products
        $client->registerHidingProductHandler(array($this, 'handle_hidden_product'));
        
        // Pre-order assignments
        $client->registerPreOrderAssignedHandler(array($this, 'handle_preorder_assigned'));
    }
    
    /**
     * Handle stock and price change notifications
     */
    public function handle_stock_price_change($stock_price_changes) {
        $this->log('Processing stock/price change webhook for ' . count($stock_price_changes) . ' products', 'info', 'webhook');
        
        $price_updates = 0;
        $stock_updates = 0;
        
        foreach ($stock_price_changes as $change) {
            try {
                $cws_product_id = $change->getProductId();
                $new_quantity = $change->getQuantity();
                $new_prices = $change->getPrices();
                
                // Get WooCommerce product ID
                $wc_product_id = $this->get_wc_product_by_cws_id($cws_product_id);
                
                if (!$wc_product_id) {
                    $this->log('Product not found in mapping: ' . $cws_product_id, 'warning', 'webhook');
                    continue;
                }
                
                // Update stock if quantity changed
                if ($new_quantity !== null) {
                    $stock_result = $this->update_product_stock($wc_product_id, $new_quantity);
                    if ($stock_result) {
                        $stock_updates++;
                    }
                }
                
                // Update prices if changed
                if (!empty($new_prices)) {
                    $price_result = $this->price_updater->update_single_product_price($wc_product_id);
                    if ($price_result['updated']) {
                        $price_updates++;
                    }
                }
                
            } catch (Exception $e) {
                $this->log('Failed to process stock/price change: ' . $e->getMessage(), 'error', 'webhook');
            }
        }
        
        $this->log("Stock/price webhook completed: $stock_updates stock updates, $price_updates price updates", 'info', 'webhook');
    }
    
    /**
     * Handle product update notifications
     */
    public function handle_product_update($notification) {
        $cws_product_id = $notification->getProductId();
        $this->log('Processing product update webhook: ' . $cws_product_id, 'info', 'webhook');
        
        try {
            $wc_product_id = $this->get_wc_product_by_cws_id($cws_product_id);
            
            if (!$wc_product_id) {
                $this->log('Product not found in mapping: ' . $cws_product_id, 'warning', 'webhook');
                return;
            }
            
            // Sync the product to get latest information
            $this->product_sync->sync_single_product($wc_product_id);
            
            $this->log('Product updated via webhook: ' . $cws_product_id, 'info', 'webhook');
            
        } catch (Exception $e) {
            $this->log('Failed to process product update: ' . $e->getMessage(), 'error', 'webhook');
        }
    }
    
    /**
     * Handle new product notifications
     */
    public function handle_new_product($notification) {
        $cws_product_id = $notification->getProductId();
        $this->log('Processing new product webhook: ' . $cws_product_id, 'info', 'webhook');
        
        try {
            // Check if auto-import is enabled
            if ($this->settings->get('cws_auto_import_new_products', 'no') !== 'yes') {
                $this->log('Auto-import disabled, skipping new product: ' . $cws_product_id, 'info', 'webhook');
                return;
            }
            
            // Check if product already exists
            $existing_wc_id = $this->get_wc_product_by_cws_id($cws_product_id);
            
            if ($existing_wc_id) {
                $this->log('Product already exists: ' . $cws_product_id, 'info', 'webhook');
                return;
            }
            
            // Get the new product from API
            $cws_product = $this->api_client->get_product($cws_product_id);
            
            if (!$cws_product) {
                throw new Exception('Unable to fetch new product from API');
            }
            
            // Check if product matches import filters
            if (!$this->product_matches_filters($cws_product)) {
                $this->log('New product does not match import filters: ' . $cws_product_id, 'info', 'webhook');
                return;
            }
            
            // Import the new product
            $result = $this->product_sync->import_single_product($cws_product);
            
            $this->log('New product imported via webhook: ' . $cws_product_id, 'info', 'webhook');
            
            // Send notification about new product
            $this->send_new_product_notification($cws_product, $result['wc_product_id']);
            
        } catch (Exception $e) {
            $this->log('Failed to process new product: ' . $e->getMessage(), 'error', 'webhook');
        }
    }
    
    /**
     * Handle hidden product notifications
     */
    public function handle_hidden_product($notification) {
        $cws_product_id = $notification->getProductId();
        $this->log('Processing hidden product webhook: ' . $cws_product_id, 'info', 'webhook');
        
        try {
            $wc_product_id = $this->get_wc_product_by_cws_id($cws_product_id);
            
            if (!$wc_product_id) {
                $this->log('Product not found in mapping: ' . $cws_product_id, 'warning', 'webhook');
                return;
            }
            
            $hidden_action = $this->settings->get('cws_hidden_product_action', 'disable');
            
            switch ($hidden_action) {
                case 'delete':
                    // Delete the WooCommerce product
                    wp_delete_post($wc_product_id, true);
                    $this->delete_product_mapping($wc_product_id);
                    break;
                    
                case 'hide':
                    // Set product to private
                    wp_update_post(array(
                        'ID' => $wc_product_id,
                        'post_status' => 'private'
                    ));
                    break;
                    
                case 'disable':
                default:
                    // Set product out of stock
                    $wc_product = wc_get_product($wc_product_id);
                    if ($wc_product) {
                        $wc_product->set_stock_status('outofstock');
                        $wc_product->save();
                    }
                    break;
            }
            
            $this->log("Product hidden via webhook ($hidden_action): " . $cws_product_id, 'info', 'webhook');
            
        } catch (Exception $e) {
            $this->log('Failed to process hidden product: ' . $e->getMessage(), 'error', 'webhook');
        }
    }
    
    /**
     * Handle pre-order assignment notifications
     */
    public function handle_preorder_assigned($notification) {
        $order_id = $notification->getOrderId();
        $this->log('Processing pre-order assignment webhook: ' . $order_id, 'info', 'webhook');
        
        try {
            // Find WooCommerce order by CodesWholesale order ID
            $wc_order_id = $this->get_wc_order_by_cws_id($order_id);
            
            if (!$wc_order_id) {
                $this->log('WooCommerce order not found: ' . $order_id, 'warning', 'webhook');
                return;
            }
            
            // Process the pre-order (fulfill the order)
            $this->process_preorder_fulfillment($wc_order_id, $order_id);
            
            $this->log('Pre-order fulfilled via webhook: ' . $order_id, 'info', 'webhook');
            
        } catch (Exception $e) {
            $this->log('Failed to process pre-order assignment: ' . $e->getMessage(), 'error', 'webhook');
        }
    }
    
    /**
     * Update product stock quantity
     */
    private function update_product_stock($wc_product_id, $new_quantity) {
        $wc_product = wc_get_product($wc_product_id);
        
        if (!$wc_product) {
            return false;
        }
        
        $current_stock = $wc_product->get_stock_quantity();
        
        if ($current_stock != $new_quantity) {
            $wc_product->set_manage_stock(true);
            $wc_product->set_stock_quantity($new_quantity);
            
            if ($new_quantity > 0) {
                $wc_product->set_stock_status('instock');
            } else {
                $wc_product->set_stock_status('outofstock');
            }
            
            $wc_product->save();
            
            // Update metadata
            update_post_meta($wc_product_id, '_cws_stock_quantity', $new_quantity);
            update_post_meta($wc_product_id, '_cws_stock_updated', current_time('mysql'));
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if product matches import filters
     */
    private function product_matches_filters($cws_product) {
        try {
            // Get import filters from settings
            $filter_platforms = $this->settings->get('cws_import_platforms', '');
            $filter_regions = $this->settings->get('cws_import_regions', '');
            $filter_languages = $this->settings->get('cws_import_languages', '');
            
            // If no filters are set, accept all products
            if (empty($filter_platforms) && empty($filter_regions) && empty($filter_languages)) {
                return true;
            }
            
            // Get product description for filtering
            $description_href = $cws_product->getDescriptionHref();
            if (!$description_href) {
                return true; // Can't filter without description
            }
            
            $description = $this->api_client->get_product_description($description_href);
            if (!$description) {
                return true; // Can't filter without description
            }
            
            // Check platform filter
            if (!empty($filter_platforms)) {
                $platforms = is_array($filter_platforms) ? $filter_platforms : explode(',', $filter_platforms);
                $product_platform = $description->getPlatform();
                
                if ($product_platform && !in_array($product_platform, $platforms)) {
                    return false;
                }
            }
            
            // Add more filter checks as needed...
            
            return true;
            
        } catch (Exception $e) {
            // If we can't determine filters, allow the product
            return true;
        }
    }
    
    /**
     * Send new product notification
     */
    private function send_new_product_notification($cws_product, $wc_product_id) {
        if ($this->settings->get('cws_notify_new_products', 'no') !== 'yes') {
            return;
        }
        
        $notification_email = $this->settings->get('cws_notification_email', get_option('admin_email'));
        
        if (empty($notification_email)) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] New Product Imported - %s', 'codeswholesale-sync'),
            get_bloginfo('name'),
            $cws_product->getName()
        );
        
        $message = sprintf(
            __('A new product has been automatically imported from CodesWholesale:', 'codeswholesale-sync') . "\n\n" .
            __('Product Name: %s', 'codeswholesale-sync') . "\n" .
            __('WooCommerce ID: %d', 'codeswholesale-sync') . "\n" .
            __('CodesWholesale ID: %s', 'codeswholesale-sync') . "\n\n" .
            __('Edit product: %s', 'codeswholesale-sync'),
            $cws_product->getName(),
            $wc_product_id,
            $cws_product->getProductId(),
            admin_url('post.php?post=' . $wc_product_id . '&action=edit')
        );
        
        wp_mail($notification_email, $subject, $message);
    }
    
    /**
     * Process pre-order fulfillment
     */
    private function process_preorder_fulfillment($wc_order_id, $cws_order_id) {
        // Get the CodesWholesale order details
        $cws_order = $this->api_client->get_order($cws_order_id);
        
        if (!$cws_order) {
            throw new Exception('Unable to get CodesWholesale order details');
        }
        
        // Get WooCommerce order
        $wc_order = wc_get_order($wc_order_id);
        
        if (!$wc_order) {
            throw new Exception('WooCommerce order not found');
        }
        
        // Extract and send game keys
        $keys_sent = false;
        
        foreach ($cws_order->getProducts() as $product) {
            foreach ($product->getCodes() as $code) {
                if (!$code->isPreOrder()) {
                    // Code is now available
                    $this->send_game_key_to_customer($wc_order, $code);
                    $keys_sent = true;
                }
            }
        }
        
        if ($keys_sent) {
            // Update order status
            $wc_order->update_status('completed', __('Pre-order fulfilled - game keys delivered', 'codeswholesale-sync'));
        }
    }
    
    /**
     * Send game key to customer
     */
    private function send_game_key_to_customer($wc_order, $code) {
        // This would integrate with your key delivery system
        // For now, we'll add it as an order note
        
        if ($code->isText()) {
            $key_value = $code->getCode();
            $wc_order->add_order_note(
                sprintf(__('Game key delivered: %s', 'codeswholesale-sync'), $key_value),
                true // Customer note
            );
        }
        
        // You could also trigger email delivery here
        do_action('cws_game_key_delivered', $wc_order, $code);
    }
    
    /**
     * Get WooCommerce product ID by CodesWholesale product ID
     */
    private function get_wc_product_by_cws_id($cws_product_id) {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT wc_product_id FROM $mapping_table WHERE cws_product_id = %s",
            $cws_product_id
        ));
    }
    
    /**
     * Get WooCommerce order ID by CodesWholesale order ID
     */
    private function get_wc_order_by_cws_id($cws_order_id) {
        global $wpdb;
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_cws_order_id' AND meta_value = %s",
            $cws_order_id
        ));
    }
    
    /**
     * Delete product mapping
     */
    private function delete_product_mapping($wc_product_id) {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        
        $wpdb->delete(
            $mapping_table,
            array('wc_product_id' => $wc_product_id),
            array('%d')
        );
    }
    
    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($payload, $signature) {
        // For now, we'll do basic signature verification
        // In a production environment, you'd implement proper HMAC verification
        
        if (empty($signature)) {
            return false;
        }
        
        // You would implement proper signature verification here
        // based on CodesWholesale's webhook signature scheme
        
        return true;
    }
    
    /**
     * Get webhook statistics for dashboard
     */
    public function get_webhook_statistics() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'cws_sync_log';
        
        $webhooks_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $logs_table 
            WHERE operation_type = 'webhook' 
            AND DATE(created_at) = %s
        ", current_time('Y-m-d')));
        
        $webhook_errors_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $logs_table 
            WHERE operation_type = 'webhook' 
            AND status = 'error' 
            AND DATE(created_at) = %s
        ", current_time('Y-m-d')));
        
        $last_webhook = $wpdb->get_var("
            SELECT MAX(created_at) 
            FROM $logs_table 
            WHERE operation_type = 'webhook'
        ");
        
        return array(
            'webhooks_today' => intval($webhooks_today),
            'webhook_errors_today' => intval($webhook_errors_today),
            'last_webhook' => $last_webhook,
            'webhook_url' => rest_url('codeswholesale/v1/webhook')
        );
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'info', $operation_type = 'webhook', $product_id = null, $wc_product_id = null) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cws_sync_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'operation_type' => $operation_type,
                'product_id' => $product_id,
                'wc_product_id' => $wc_product_id,
                'status' => $level === 'error' ? 'error' : ($level === 'warning' ? 'warning' : 'success'),
                'message' => $message,
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%d', '%s', '%s', '%s')
        );
    }
} 