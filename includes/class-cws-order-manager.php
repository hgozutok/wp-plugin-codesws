<?php
/**
 * CodesWholesale Order Manager
 * 
 * Handles order processing, fulfillment, and delivery of digital products
 * from CodesWholesale to WooCommerce customers.
 * 
 * @package CodesWholesaleSync
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class CWS_Order_Manager {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * API client instance
     */
    private $api_client;
    
    /**
     * Settings instance
     */
    private $settings;
    
    /**
     * Logger instance
     */
    private $logger = null;
    
    /**
     * Whether dependencies are available
     */
    private $dependencies_available = false;
    
    /**
     * Order statuses mapping
     */
    private $order_statuses = array(
        'COMPLETED' => 'completed',
        'CANCELLED' => 'cancelled',
        'ERROR' => 'failed',
        'PENDING' => 'processing'
    );
    
    /**
     * Get singleton instance
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
        $this->dependencies_available = class_exists('Monolog\Logger') && class_exists('CodesWholesale\CodesWholesale');
        $this->init_logger();
        
        // Initialize hooks when WooCommerce is loaded
        add_action('woocommerce_loaded', array($this, 'init_hooks'));
        
        // If WooCommerce is already loaded, init hooks immediately
        if (class_exists('WooCommerce')) {
            $this->init_hooks();
        }
    }
    
    /**
     * Initialize logger
     */
    private function init_logger() {
        if ($this->dependencies_available && class_exists('Monolog\Logger')) {
            try {
                $this->logger = new \Monolog\Logger('cws_orders');
                $log_path = wp_upload_dir()['basedir'] . '/cws-logs/orders.log';
                wp_mkdir_p(dirname($log_path));
                $this->logger->pushHandler(new \Monolog\Handler\StreamHandler($log_path, \Monolog\Logger::INFO));
            } catch (Exception $e) {
                // Fallback to WordPress logging
                $this->logger = null;
            }
        }
    }
    
    /**
     * Initialize WooCommerce hooks
     */
    public function init_hooks() {
        // Order processing hooks
        add_action('woocommerce_order_status_processing', array($this, 'process_order'), 10, 1);
        add_action('woocommerce_order_status_on-hold', array($this, 'process_order'), 10, 1);
        add_action('woocommerce_payment_complete', array($this, 'process_order'), 10, 1);
        
        // Order status synchronization
        add_action('woocommerce_order_status_changed', array($this, 'sync_order_status'), 10, 3);
        
        // Custom order actions
        add_action('wp_ajax_cws_retry_order', array($this, 'ajax_retry_order'));
        add_action('wp_ajax_cws_cancel_order', array($this, 'ajax_cancel_order'));
        add_action('wp_ajax_cws_get_order_details', array($this, 'ajax_get_order_details'));
        
        // Email delivery
        add_action('woocommerce_email_order_details', array($this, 'add_keys_to_email'), 20, 4);
        
        // Order meta display
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_order_meta'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_customer_keys'));
        
        // Cron jobs for order processing
        add_action('cws_process_pending_orders', array($this, 'process_pending_orders'));
        add_action('cws_sync_order_status', array($this, 'sync_all_order_statuses'));
    }
    
    /**
     * Process WooCommerce order
     */
    public function process_order($order_id) {
        // Check if dependencies are available
        if (!$this->dependencies_available) {
            $this->log("Cannot process order - CodesWholesale SDK not installed", 'warning', 'process_order', null, $order_id);
            return;
        }
        
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                throw new Exception("Order not found: $order_id");
            }
            
            // Check if already processed
            if ($order->get_meta('_cws_processed')) {
                $this->log("Order already processed: $order_id", 'info', 'process_order', null, $order_id);
                return;
            }
            
            // Check if order contains CodesWholesale products
            $cws_items = $this->get_cws_order_items($order);
            if (empty($cws_items)) {
                $this->log("No CodesWholesale products in order: $order_id", 'info', 'process_order', null, $order_id);
                return;
            }
            
            $this->log("Processing order: $order_id with " . count($cws_items) . " CWS items", 'info', 'process_order', null, $order_id);
            
            // Create orders for each CodesWholesale product
            $all_successful = true;
            $order_data = array();
            
            foreach ($cws_items as $item) {
                $result = $this->create_cws_order($order, $item);
                if ($result['success']) {
                    $order_data[] = $result;
                    $this->log("Successfully created CWS order for product: {$item['cws_product_id']}", 'info', 'process_order', $item['cws_product_id'], $order_id);
                } else {
                    $all_successful = false;
                    $this->log("Failed to create CWS order for product: {$item['cws_product_id']} - {$result['error']}", 'error', 'process_order', $item['cws_product_id'], $order_id);
                }
            }
            
            // Update order meta
            $order->update_meta_data('_cws_processed', true);
            $order->update_meta_data('_cws_orders', $order_data);
            $order->update_meta_data('_cws_process_date', current_time('mysql'));
            
            if ($all_successful) {
                $order->update_meta_data('_cws_status', 'completed');
                $order->add_order_note(__('CodesWholesale orders processed successfully. Product keys delivered.', 'codeswholesale-sync'));
                
                // Send keys to customer
                $this->deliver_keys_to_customer($order, $order_data);
                
            } else {
                $order->update_meta_data('_cws_status', 'partial');
                $order->add_order_note(__('Some CodesWholesale orders failed to process. Please check order details.', 'codeswholesale-sync'));
                
                // Schedule retry
                $this->schedule_order_retry($order_id);
            }
            
            $order->save();
            
            // Update statistics
            $this->update_order_statistics($order_id, $all_successful);
            
        } catch (Exception $e) {
            $this->log("Order processing failed: {$e->getMessage()}", 'error', 'process_order', null, $order_id);
            
            // Update order with error status
            if (isset($order)) {
                $order->update_meta_data('_cws_status', 'failed');
                $order->update_meta_data('_cws_error', $e->getMessage());
                $order->add_order_note(__('CodesWholesale order processing failed: ' . $e->getMessage(), 'codeswholesale-sync'));
                $order->save();
            }
        }
    }
    
    /**
     * Get CodesWholesale items from order
     */
    private function get_cws_order_items($order) {
        $cws_items = array();
        
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            $cws_product_id = $product->get_meta('_cws_product_id');
            if ($cws_product_id) {
                $cws_items[] = array(
                    'item_id' => $item_id,
                    'product_id' => $product->get_id(),
                    'cws_product_id' => $cws_product_id,
                    'quantity' => $item->get_quantity(),
                    'product_name' => $item->get_name(),
                    'line_total' => $item->get_total()
                );
            }
        }
        
        return $cws_items;
    }
    
    /**
     * Create CodesWholesale order
     */
    private function create_cws_order($wc_order, $item) {
        try {
            $client = $this->api_client->get_client();
            if (!$client) {
                throw new Exception('CodesWholesale API client not available');
            }
            
            // Create order request
            $order_request = array(
                'productId' => $item['cws_product_id'],
                'quantity' => $item['quantity'],
                'price' => floatval($item['line_total']),
                'currency' => $wc_order->get_currency(),
                'clientOrderId' => $wc_order->get_id() . '_' . $item['item_id']
            );
            
            $this->log("Creating CWS order request: " . json_encode($order_request), 'debug', 'create_order');
            
            // Make API request
            $cws_order = $client->createOrder($order_request);
            
            if (!$cws_order) {
                throw new Exception('Failed to create CodesWholesale order - no response');
            }
            
            // Process the order response
            $order_data = $this->process_order_response($cws_order, $item);
            $order_data['success'] = true;
            
            return $order_data;
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'item' => $item
            );
        }
    }
    
    /**
     * Process CodesWholesale order response
     */
    private function process_order_response($cws_order, $item) {
        $order_data = array(
            'cws_order_id' => $cws_order->getId(),
            'item_id' => $item['item_id'],
            'product_id' => $item['product_id'],
            'cws_product_id' => $item['cws_product_id'],
            'status' => $cws_order->getStatus(),
            'total_price' => $cws_order->getTotalPrice(),
            'keys' => array(),
            'created_at' => current_time('mysql')
        );
        
        // Get product keys if available
        if ($cws_order->getStatus() === 'COMPLETED') {
            $keys = $this->extract_product_keys($cws_order);
            $order_data['keys'] = $keys;
            $order_data['fulfilled_at'] = current_time('mysql');
        }
        
        return $order_data;
    }
    
    /**
     * Extract product keys from CodesWholesale order
     */
    private function extract_product_keys($cws_order) {
        $keys = array();
        
        try {
            // Get order details with keys
            $order_details = $cws_order->getOrderDetails();
            
            if ($order_details && method_exists($order_details, 'getCodes')) {
                $codes = $order_details->getCodes();
                foreach ($codes as $code) {
                    $keys[] = array(
                        'code' => $code->getCode(),
                        'description' => $code->getDescription() ?: '',
                        'platform' => $code->getPlatform() ?: '',
                        'region' => $code->getRegion() ?: ''
                    );
                }
            }
            
        } catch (Exception $e) {
            $this->log("Failed to extract keys: {$e->getMessage()}", 'error', 'extract_keys');
        }
        
        return $keys;
    }
    
    /**
     * Deliver keys to customer
     */
    public function deliver_keys_to_customer($order, $order_data) {
        $delivery_method = $this->settings->get('cws_key_delivery_method', 'email');
        
        switch ($delivery_method) {
            case 'email':
                $this->send_keys_email($order, $order_data);
                break;
                
            case 'order_page':
                // Keys will be shown on order page via hook
                break;
                
            case 'both':
                $this->send_keys_email($order, $order_data);
                // Keys also shown on order page
                break;
        }
    }
    
    /**
     * Send product keys via email
     */
    private function send_keys_email($order, $order_data) {
        try {
            $to = $order->get_billing_email();
            $subject = sprintf(__('Your Digital Product Keys - Order #%s', 'codeswholesale-sync'), $order->get_order_number());
            
            // Build email content
            $message = $this->build_keys_email_content($order, $order_data);
            
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            $sent = wp_mail($to, $subject, $message, $headers);
            
            if ($sent) {
                $order->add_order_note(__('Product keys sent to customer via email.', 'codeswholesale-sync'));
                $this->log("Keys email sent successfully to: $to", 'info', 'deliver_keys', null, $order->get_id());
            } else {
                throw new Exception('Failed to send email');
            }
            
        } catch (Exception $e) {
            $this->log("Failed to send keys email: {$e->getMessage()}", 'error', 'deliver_keys', null, $order->get_id());
            $order->add_order_note(__('Failed to send product keys email. Keys available in order details.', 'codeswholesale-sync'));
        }
    }
    
    /**
     * Build email content with product keys
     */
    private function build_keys_email_content($order, $order_data) {
        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <h2><?php printf(__('Your Digital Product Keys - Order #%s', 'codeswholesale-sync'), $order->get_order_number()); ?></h2>
            
            <p><?php _e('Thank you for your purchase! Your digital product keys are ready:', 'codeswholesale-sync'); ?></p>
            
            <?php foreach ($order_data as $item): ?>
                <?php if (!empty($item['keys'])): ?>
                    <div style="border: 1px solid #ddd; margin: 20px 0; padding: 15px; border-radius: 5px;">
                        <h3><?php echo esc_html($item['product_name'] ?? 'Digital Product'); ?></h3>
                        
                        <?php foreach ($item['keys'] as $key): ?>
                            <div style="background: #f9f9f9; padding: 10px; margin: 10px 0; border-radius: 3px;">
                                <strong><?php _e('Product Key:', 'codeswholesale-sync'); ?></strong>
                                <code style="font-size: 14px; background: #fff; padding: 5px; display: block; margin: 5px 0;"><?php echo esc_html($key['code']); ?></code>
                                
                                <?php if (!empty($key['description'])): ?>
                                    <p><strong><?php _e('Instructions:', 'codeswholesale-sync'); ?></strong> <?php echo esc_html($key['description']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($key['platform'])): ?>
                                    <p><strong><?php _e('Platform:', 'codeswholesale-sync'); ?></strong> <?php echo esc_html($key['platform']); ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($key['region'])): ?>
                                    <p><strong><?php _e('Region:', 'codeswholesale-sync'); ?></strong> <?php echo esc_html($key['region']); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <p><strong><?php _e('Important Notes:', 'codeswholesale-sync'); ?></strong></p>
                <ul>
                    <li><?php _e('Please save these keys in a safe place', 'codeswholesale-sync'); ?></li>
                    <li><?php _e('Keys are usually activated instantly', 'codeswholesale-sync'); ?></li>
                    <li><?php _e('Contact support if you have any issues', 'codeswholesale-sync'); ?></li>
                </ul>
            </div>
            
            <p><?php _e('You can also view your keys anytime in your order details.', 'codeswholesale-sync'); ?></p>
            <p><?php _e('Thank you for your business!', 'codeswholesale-sync'); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Add keys to WooCommerce emails
     */
    public function add_keys_to_email($order, $sent_to_admin, $plain_text, $email) {
        if (!in_array($email->id, array('customer_completed_order', 'customer_processing_order'))) {
            return;
        }
        
        $order_data = $order->get_meta('_cws_orders');
        if (empty($order_data)) {
            return;
        }
        
        $has_keys = false;
        foreach ($order_data as $item) {
            if (!empty($item['keys'])) {
                $has_keys = true;
                break;
            }
        }
        
        if (!$has_keys) {
            return;
        }
        
        if ($plain_text) {
            echo "\n" . __('YOUR DIGITAL PRODUCT KEYS:', 'codeswholesale-sync') . "\n";
            echo str_repeat('=', 40) . "\n\n";
            
            foreach ($order_data as $item) {
                if (!empty($item['keys'])) {
                    echo strtoupper($item['product_name'] ?? 'Digital Product') . ":\n";
                    foreach ($item['keys'] as $key) {
                        echo "Key: " . $key['code'] . "\n";
                        if (!empty($key['description'])) {
                            echo "Instructions: " . $key['description'] . "\n";
                        }
                        echo "\n";
                    }
                }
            }
        } else {
            echo '<h2>' . __('Your Digital Product Keys', 'codeswholesale-sync') . '</h2>';
            echo $this->build_keys_email_content($order, $order_data);
        }
    }
    
    /**
     * Display order meta in admin
     */
    public function display_order_meta($order) {
        $cws_status = $order->get_meta('_cws_status');
        $order_data = $order->get_meta('_cws_orders');
        
        if (!$cws_status) {
            return;
        }
        
        echo '<div class="cws-order-meta">';
        echo '<h3>' . __('CodesWholesale Order Details', 'codeswholesale-sync') . '</h3>';
        echo '<p><strong>' . __('Status:', 'codeswholesale-sync') . '</strong> ';
        
        switch ($cws_status) {
            case 'completed':
                echo '<span style="color: green;">✅ ' . __('Completed', 'codeswholesale-sync') . '</span>';
                break;
            case 'partial':
                echo '<span style="color: orange;">⚠️ ' . __('Partial', 'codeswholesale-sync') . '</span>';
                break;
            case 'failed':
                echo '<span style="color: red;">❌ ' . __('Failed', 'codeswholesale-sync') . '</span>';
                break;
            default:
                echo '<span style="color: blue;">🔄 ' . __('Processing', 'codeswholesale-sync') . '</span>';
        }
        
        echo '</p>';
        
        if (!empty($order_data)) {
            echo '<div class="cws-order-items">';
            foreach ($order_data as $item) {
                echo '<div style="border: 1px solid #ddd; padding: 10px; margin: 10px 0;">';
                echo '<h4>' . esc_html($item['product_name'] ?? 'Digital Product') . '</h4>';
                echo '<p><strong>' . __('CWS Order ID:', 'codeswholesale-sync') . '</strong> ' . esc_html($item['cws_order_id'] ?? 'N/A') . '</p>';
                echo '<p><strong>' . __('Status:', 'codeswholesale-sync') . '</strong> ' . esc_html($item['status'] ?? 'Unknown') . '</p>';
                
                if (!empty($item['keys'])) {
                    echo '<p><strong>' . __('Product Keys:', 'codeswholesale-sync') . '</strong></p>';
                    echo '<ul>';
                    foreach ($item['keys'] as $key) {
                        echo '<li><code>' . esc_html($key['code']) . '</code>';
                        if (!empty($key['platform'])) {
                            echo ' (' . esc_html($key['platform']) . ')';
                        }
                        echo '</li>';
                    }
                    echo '</ul>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Display keys to customer on order page
     */
    public function display_customer_keys($order) {
        $order_data = $order->get_meta('_cws_orders');
        if (empty($order_data)) {
            return;
        }
        
        $has_keys = false;
        foreach ($order_data as $item) {
            if (!empty($item['keys'])) {
                $has_keys = true;
                break;
            }
        }
        
        if (!$has_keys) {
            return;
        }
        
        echo '<section class="cws-order-keys" style="margin-top: 30px;">';
        echo '<h2>' . __('Your Digital Product Keys', 'codeswholesale-sync') . '</h2>';
        
        foreach ($order_data as $item) {
            if (!empty($item['keys'])) {
                echo '<div class="cws-product-keys" style="border: 1px solid #ddd; padding: 15px; margin: 15px 0; border-radius: 5px;">';
                echo '<h3>' . esc_html($item['product_name'] ?? 'Digital Product') . '</h3>';
                
                foreach ($item['keys'] as $key) {
                    echo '<div class="cws-key-item" style="background: #f9f9f9; padding: 10px; margin: 10px 0; border-radius: 3px;">';
                    echo '<p><strong>' . __('Product Key:', 'codeswholesale-sync') . '</strong></p>';
                    echo '<code style="font-size: 14px; background: #fff; padding: 8px; display: block; word-break: break-all;">' . esc_html($key['code']) . '</code>';
                    
                    if (!empty($key['description'])) {
                        echo '<p><strong>' . __('Instructions:', 'codeswholesale-sync') . '</strong> ' . esc_html($key['description']) . '</p>';
                    }
                    
                    if (!empty($key['platform'])) {
                        echo '<p><strong>' . __('Platform:', 'codeswholesale-sync') . '</strong> ' . esc_html($key['platform']) . '</p>';
                    }
                    
                    if (!empty($key['region'])) {
                        echo '<p><strong>' . __('Region:', 'codeswholesale-sync') . '</strong> ' . esc_html($key['region']) . '</p>';
                    }
                    echo '</div>';
                }
                echo '</div>';
            }
        }
        
        echo '<div class="cws-keys-notice" style="background: #e7f3ff; padding: 15px; border-radius: 5px; margin-top: 15px;">';
        echo '<p><strong>' . __('Important:', 'codeswholesale-sync') . '</strong></p>';
        echo '<ul>';
        echo '<li>' . __('Please save these keys in a safe place', 'codeswholesale-sync') . '</li>';
        echo '<li>' . __('Keys are usually activated instantly', 'codeswholesale-sync') . '</li>';
        echo '<li>' . __('Contact support if you have any issues with activation', 'codeswholesale-sync') . '</li>';
        echo '</ul>';
        echo '</div>';
        
        echo '</section>';
    }
    
    /**
     * Process pending orders (cron job)
     */
    public function process_pending_orders() {
        $pending_orders = $this->get_pending_orders();
        
        foreach ($pending_orders as $order_id) {
            $this->process_order($order_id);
            
            // Add small delay to avoid API rate limits
            sleep(1);
        }
        
        $this->log("Processed " . count($pending_orders) . " pending orders", 'info', 'cron_process');
    }
    
    /**
     * Get pending orders that need processing
     */
    private function get_pending_orders() {
        $args = array(
            'status' => array('processing', 'on-hold'),
            'limit' => 50,
            'meta_query' => array(
                array(
                    'key' => '_cws_processed',
                    'compare' => 'NOT EXISTS'
                )
            )
        );
        
        $orders = wc_get_orders($args);
        $order_ids = array();
        
        foreach ($orders as $order) {
            // Check if order has CWS products
            $cws_items = $this->get_cws_order_items($order);
            if (!empty($cws_items)) {
                $order_ids[] = $order->get_id();
            }
        }
        
        return $order_ids;
    }
    
    /**
     * Sync order status with CodesWholesale
     */
    public function sync_order_status($order_id, $old_status, $new_status) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $order_data = $order->get_meta('_cws_orders');
        if (empty($order_data)) return;
        
        // Handle order cancellation
        if ($new_status === 'cancelled' || $new_status === 'refunded') {
            $this->handle_order_cancellation($order, $order_data);
        }
    }
    
    /**
     * Handle order cancellation
     */
    private function handle_order_cancellation($order, $order_data) {
        try {
            $client = $this->api_client->get_client();
            if (!$client) return;
            
            foreach ($order_data as $item) {
                if (!empty($item['cws_order_id'])) {
                    // Try to cancel the CodesWholesale order
                    try {
                        $cws_order = $client->retrieveOrder($item['cws_order_id']);
                        if ($cws_order && $cws_order->getStatus() !== 'COMPLETED') {
                            $client->cancelOrder($item['cws_order_id']);
                            $this->log("Cancelled CWS order: {$item['cws_order_id']}", 'info', 'cancel_order', null, $order->get_id());
                        }
                    } catch (Exception $e) {
                        $this->log("Failed to cancel CWS order {$item['cws_order_id']}: {$e->getMessage()}", 'error', 'cancel_order', null, $order->get_id());
                    }
                }
            }
            
        } catch (Exception $e) {
            $this->log("Order cancellation failed: {$e->getMessage()}", 'error', 'cancel_order', null, $order->get_id());
        }
    }
    
    /**
     * Schedule order retry
     */
    private function schedule_order_retry($order_id, $retry_count = 0) {
        if ($retry_count >= 3) {
            $this->log("Maximum retry attempts reached for order: $order_id", 'warning', 'retry_order', null, $order_id);
            return;
        }
        
        $retry_time = time() + (300 * ($retry_count + 1)); // 5 minutes, 10 minutes, 15 minutes
        wp_schedule_single_event($retry_time, 'cws_retry_order', array($order_id, $retry_count + 1));
        
        $this->log("Scheduled retry #" . ($retry_count + 1) . " for order: $order_id", 'info', 'retry_order', null, $order_id);
    }
    
    /**
     * Update order statistics
     */
    private function update_order_statistics($order_id, $success) {
        $stats = get_option('cws_order_stats', array(
            'total_orders' => 0,
            'successful_orders' => 0,
            'failed_orders' => 0,
            'total_revenue' => 0
        ));
        
        $order = wc_get_order($order_id);
        if ($order) {
            $stats['total_orders']++;
            $stats['total_revenue'] += floatval($order->get_total());
            
            if ($success) {
                $stats['successful_orders']++;
            } else {
                $stats['failed_orders']++;
            }
            
            update_option('cws_order_stats', $stats);
        }
    }
    
    /**
     * Get order statistics
     */
    public function get_order_statistics() {
        return get_option('cws_order_stats', array(
            'total_orders' => 0,
            'successful_orders' => 0,
            'failed_orders' => 0,
            'total_revenue' => 0
        ));
    }
    
    /**
     * AJAX: Retry failed order
     */
    public function ajax_retry_order() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'codeswholesale-sync'));
        }
        
        $order_id = intval($_POST['order_id']);
        
        try {
            // Reset order processing status
            $order = wc_get_order($order_id);
            if ($order) {
                $order->delete_meta_data('_cws_processed');
                $order->delete_meta_data('_cws_status');
                $order->delete_meta_data('_cws_error');
                $order->save();
                
                // Process the order
                $this->process_order($order_id);
                
                wp_send_json_success(array(
                    'message' => __('Order retry initiated successfully', 'codeswholesale-sync')
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Cancel CodesWholesale order
     */
    public function ajax_cancel_order() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'codeswholesale-sync'));
        }
        
        // Check if dependencies are available
        if (!$this->dependencies_available) {
            wp_send_json_error(array(
                'message' => __('Cannot cancel order - CodesWholesale SDK not installed', 'codeswholesale-sync')
            ));
            return;
        }
        
        $order_id = intval($_POST['order_id']);
        
        try {
            $order = wc_get_order($order_id);
            if ($order) {
                $order_data = $order->get_meta('_cws_orders');
                $this->handle_order_cancellation($order, $order_data);
                
                wp_send_json_success(array(
                    'message' => __('CodesWholesale orders cancelled successfully', 'codeswholesale-sync')
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * AJAX: Get order details
     */
    public function ajax_get_order_details() {
        check_ajax_referer('cws_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied', 'codeswholesale-sync'));
        }
        
        $order_id = intval($_POST['order_id']);
        
        try {
            $order = wc_get_order($order_id);
            if ($order) {
                $order_data = $order->get_meta('_cws_orders');
                $cws_status = $order->get_meta('_cws_status');
                
                wp_send_json_success(array(
                    'order_data' => $order_data,
                    'cws_status' => $cws_status,
                    'order_total' => $order->get_total(),
                    'currency' => $order->get_currency()
                ));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'info', $operation_type = 'order', $product_id = null, $wc_order_id = null) {
        // Log to file if Monolog is available
        if ($this->logger) {
            try {
                $this->logger->log($level, $message, array(
                    'operation' => $operation_type,
                    'product_id' => $product_id,
                    'wc_order_id' => $wc_order_id
                ));
            } catch (Exception $e) {
                // Monolog failed, continue to database logging
            }
        }
        
        // Log to database as fallback
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'cws_sync_log';
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'operation_type' => $operation_type,
                        'message' => $message,
                        'level' => $level,
                        'product_id' => $product_id,
                        'wc_product_id' => $wc_order_id,
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s', '%d', '%s')
                );
            }
        } catch (Exception $e) {
            // Even database logging failed, fall back to WordPress error log
            error_log("CWS Order Manager: [$level] $message");
        }
    }
} 