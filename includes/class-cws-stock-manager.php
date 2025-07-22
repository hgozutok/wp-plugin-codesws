<?php
/**
 * CodesWholesale Stock Manager
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Stock management class
 */
class CWS_Stock_Manager {
    
    /**
     * Instance
     * @var CWS_Stock_Manager
     */
    private static $instance = null;
    
    /**
     * Settings
     * @var CWS_Settings
     */
    private $settings;
    
    /**
     * API Client
     * @var CWS_API_Client
     */
    private $api_client;
    
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
        $this->settings = CWS_Settings::get_instance();
        $this->api_client = CWS_API_Client::get_instance();
        
        // Hook into WordPress
        add_action('cws_update_stock', array($this, 'update_all_stock'));
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Hook into WooCommerce stock management
        if (class_exists('WooCommerce')) {
            add_action('woocommerce_product_set_stock', array($this, 'on_stock_changed'), 10, 3);
            add_action('woocommerce_variation_set_stock', array($this, 'on_stock_changed'), 10, 3);
        }
    }
    
    /**
     * Update all product stock levels
     */
    public function update_all_stock() {
        global $wpdb;
        
        if ($this->settings->get('cws_enable_stock_sync', 'yes') !== 'yes') {
            return;
        }
        
        $this->log('Starting stock update for all products', 'info', 'stock_update');
        
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $mappings = $wpdb->get_results("SELECT * FROM $mapping_table WHERE sync_status = 'synced'");
        
        $updated = 0;
        $errors = 0;
        $low_stock_alerts = array();
        
        foreach ($mappings as $mapping) {
            try {
                $result = $this->update_single_product_stock($mapping->wc_product_id);
                
                if ($result['updated']) {
                    $updated++;
                }
                
                // Check for low stock
                if ($result['low_stock']) {
                    $low_stock_alerts[] = $result;
                }
                
            } catch (Exception $e) {
                $errors++;
                $this->log(
                    'Stock update failed for product ' . $mapping->wc_product_id . ': ' . $e->getMessage(),
                    'error',
                    'stock_update',
                    $mapping->cws_product_id,
                    $mapping->wc_product_id
                );
            }
            
            // Small delay to prevent API overload
            usleep(100000); // 0.1 seconds
        }
        
        // Send low stock notifications
        if (!empty($low_stock_alerts)) {
            $this->send_low_stock_notifications($low_stock_alerts);
        }
        
        $this->log(
            "Stock update completed: $updated updated, $errors errors, " . count($low_stock_alerts) . " low stock alerts",
            'info',
            'stock_update'
        );
        
        return array(
            'updated' => $updated,
            'errors' => $errors,
            'low_stock' => count($low_stock_alerts)
        );
    }
    
    /**
     * Update stock for single product
     */
    public function update_single_product_stock($wc_product_id) {
        global $wpdb;
        
        // Get product mapping
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $mapping_table WHERE wc_product_id = %d",
            $wc_product_id
        ));
        
        if (!$mapping) {
            throw new Exception(__('Product mapping not found', 'codeswholesale-sync'));
        }
        
        // Get current CodesWholesale product data
        $cws_product = $this->api_client->get_product($mapping->cws_product_href);
        
        if (!$cws_product) {
            throw new Exception(__('CodesWholesale product not found', 'codeswholesale-sync'));
        }
        
        // Get WooCommerce product
        $wc_product = wc_get_product($wc_product_id);
        
        if (!$wc_product) {
            throw new Exception(__('WooCommerce product not found', 'codeswholesale-sync'));
        }
        
        // Get new stock quantity
        $new_stock = $cws_product->getStockQuantity();
        $current_stock = $wc_product->get_stock_quantity();
        
        $stock_changed = false;
        $low_stock = false;
        $out_of_stock = false;
        
        // Update stock if changed
        if ($current_stock != $new_stock) {
            $wc_product->set_manage_stock(true);
            $wc_product->set_stock_quantity($new_stock);
            
            // Update stock status
            if ($new_stock > 0) {
                $wc_product->set_stock_status('instock');
            } else {
                $wc_product->set_stock_status('outofstock');
                $out_of_stock = true;
                
                // Handle out of stock action
                $this->handle_out_of_stock($wc_product);
            }
            
            $stock_changed = true;
        }
        
        // Check for low stock
        $low_stock_threshold = intval($this->settings->get('cws_low_stock_threshold', '5'));
        if ($new_stock > 0 && $new_stock <= $low_stock_threshold) {
            $low_stock = true;
        }
        
        // Save if changed
        if ($stock_changed) {
            $wc_product->save();
            
            // Update metadata
            update_post_meta($wc_product_id, '_cws_stock_quantity', $new_stock);
            update_post_meta($wc_product_id, '_cws_stock_updated', current_time('mysql'));
            
            $this->log(
                sprintf(
                    'Updated stock for product %s: %d → %d',
                    $wc_product->get_name(),
                    $current_stock,
                    $new_stock
                ),
                'info',
                'stock_update',
                $mapping->cws_product_id,
                $wc_product_id
            );
        }
        
        return array(
            'updated' => $stock_changed,
            'old_stock' => intval($current_stock),
            'new_stock' => intval($new_stock),
            'low_stock' => $low_stock,
            'out_of_stock' => $out_of_stock,
            'product_name' => $wc_product->get_name(),
            'product_id' => $wc_product_id
        );
    }
    
    /**
     * Update stock based on webhook data
     */
    public function update_stock_from_webhook($stock_changes) {
        if (empty($stock_changes) || !is_array($stock_changes)) {
            return;
        }
        
        $updated = 0;
        $low_stock_alerts = array();
        
        foreach ($stock_changes as $stock_change) {
            try {
                $cws_product_id = $stock_change->getProductId();
                $wc_product_id = $this->get_wc_product_by_cws_id($cws_product_id);
                
                if ($wc_product_id) {
                    $result = $this->update_single_product_stock($wc_product_id);
                    
                    if ($result['updated']) {
                        $updated++;
                    }
                    
                    if ($result['low_stock']) {
                        $low_stock_alerts[] = $result;
                    }
                }
                
            } catch (Exception $e) {
                $this->log(
                    'Webhook stock update failed: ' . $e->getMessage(),
                    'error',
                    'webhook'
                );
            }
        }
        
        // Send low stock notifications
        if (!empty($low_stock_alerts)) {
            $this->send_low_stock_notifications($low_stock_alerts);
        }
        
        $this->log(
            "Webhook stock update completed: $updated products updated",
            'info',
            'webhook'
        );
        
        return $updated;
    }
    
    /**
     * Handle out of stock products
     */
    private function handle_out_of_stock($wc_product) {
        $action = $this->settings->get('cws_out_of_stock_action', 'disable');
        
        switch ($action) {
            case 'hide':
                // Set product to private
                wp_update_post(array(
                    'ID' => $wc_product->get_id(),
                    'post_status' => 'private'
                ));
                break;
                
            case 'disable':
                // Already handled by setting stock status to outofstock
                break;
                
            case 'notify':
                // Send notification only (no action on product)
                $this->send_out_of_stock_notification($wc_product);
                break;
        }
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
     * Send low stock notifications
     */
    private function send_low_stock_notifications($low_stock_products) {
        if ($this->settings->get('cws_notify_low_stock', 'yes') !== 'yes') {
            return;
        }
        
        $notification_email = $this->settings->get('cws_notification_email', get_option('admin_email'));
        
        if (empty($notification_email)) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] Low Stock Alert - %d Products', 'codeswholesale-sync'),
            get_bloginfo('name'),
            count($low_stock_products)
        );
        
        $message = __('The following products are running low on stock:', 'codeswholesale-sync') . "\n\n";
        
        foreach ($low_stock_products as $product) {
            $message .= sprintf(
                "- %s (ID: %d): %d units remaining\n",
                $product['product_name'],
                $product['product_id'],
                $product['new_stock']
            );
        }
        
        $message .= "\n" . __('You may want to check your CodesWholesale account for stock updates.', 'codeswholesale-sync');
        $message .= "\n\n" . __('View products:', 'codeswholesale-sync') . " " . admin_url('admin.php?page=codeswholesale-sync-mapping');
        
        wp_mail($notification_email, $subject, $message);
        
        $this->log(
            'Low stock notification sent for ' . count($low_stock_products) . ' products',
            'info',
            'notification'
        );
    }
    
    /**
     * Send out of stock notification
     */
    private function send_out_of_stock_notification($wc_product) {
        if ($this->settings->get('cws_notify_out_of_stock', 'yes') !== 'yes') {
            return;
        }
        
        $notification_email = $this->settings->get('cws_notification_email', get_option('admin_email'));
        
        if (empty($notification_email)) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] Out of Stock Alert - %s', 'codeswholesale-sync'),
            get_bloginfo('name'),
            $wc_product->get_name()
        );
        
        $message = sprintf(
            __('Product "%s" (ID: %d) is now out of stock.', 'codeswholesale-sync'),
            $wc_product->get_name(),
            $wc_product->get_id()
        );
        
        $message .= "\n\n" . __('Edit product:', 'codeswholesale-sync') . " " . admin_url('post.php?post=' . $wc_product->get_id() . '&action=edit');
        
        wp_mail($notification_email, $subject, $message);
    }
    
    /**
     * Handle stock changes (for logging and monitoring)
     */
    public function on_stock_changed($product, $stock_quantity, $operation) {
        // Only log for CodesWholesale managed products
        $cws_product_id = get_post_meta($product->get_id(), '_cws_product_id', true);
        
        if (!$cws_product_id) {
            return;
        }
        
        $this->log(
            sprintf(
                'Stock changed for product %s (ID: %d): %s',
                $product->get_name(),
                $product->get_id(),
                $operation
            ),
            'info',
            'stock_change',
            $cws_product_id,
            $product->get_id()
        );
    }
    
    /**
     * Get stock statistics for dashboard
     */
    public function get_stock_statistics() {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $meta_table = $wpdb->postmeta;
        
        // Get total managed products
        $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $mapping_table");
        
        // Get in stock products
        $in_stock = $wpdb->get_var("
            SELECT COUNT(DISTINCT m.wc_product_id) 
            FROM $mapping_table m 
            INNER JOIN $meta_table pm ON m.wc_product_id = pm.post_id 
            WHERE pm.meta_key = '_stock_status' 
            AND pm.meta_value = 'instock'
        ");
        
        // Get out of stock products
        $out_of_stock = $wpdb->get_var("
            SELECT COUNT(DISTINCT m.wc_product_id) 
            FROM $mapping_table m 
            INNER JOIN $meta_table pm ON m.wc_product_id = pm.post_id 
            WHERE pm.meta_key = '_stock_status' 
            AND pm.meta_value = 'outofstock'
        ");
        
        // Get low stock products
        $low_stock_threshold = intval($this->settings->get('cws_low_stock_threshold', '5'));
        $low_stock = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT m.wc_product_id) 
            FROM $mapping_table m 
            INNER JOIN $meta_table pm1 ON m.wc_product_id = pm1.post_id 
            INNER JOIN $meta_table pm2 ON m.wc_product_id = pm2.post_id 
            WHERE pm1.meta_key = '_stock_status' AND pm1.meta_value = 'instock'
            AND pm2.meta_key = '_stock' AND CAST(pm2.meta_value AS UNSIGNED) <= %d
        ", $low_stock_threshold));
        
        // Get stock updates today
        $updated_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}cws_sync_log 
            WHERE operation_type = 'stock_update' 
            AND status = 'success' 
            AND DATE(created_at) = %s
        ", current_time('Y-m-d')));
        
        return array(
            'total_products' => intval($total_products),
            'in_stock' => intval($in_stock),
            'out_of_stock' => intval($out_of_stock),
            'low_stock' => intval($low_stock),
            'low_stock_threshold' => $low_stock_threshold,
            'updated_today' => intval($updated_today),
            'stock_sync_enabled' => $this->settings->get('cws_enable_stock_sync', 'yes') === 'yes'
        );
    }
    
    /**
     * Get low stock products for admin display
     */
    public function get_low_stock_products() {
        global $wpdb;
        
        $low_stock_threshold = intval($this->settings->get('cws_low_stock_threshold', '5'));
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $posts_table = $wpdb->posts;
        $meta_table = $wpdb->postmeta;
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT 
                m.wc_product_id,
                m.cws_product_id,
                p.post_title as product_name,
                pm.meta_value as stock_quantity
            FROM $mapping_table m
            INNER JOIN $posts_table p ON m.wc_product_id = p.ID
            INNER JOIN $meta_table pm ON m.wc_product_id = pm.post_id
            INNER JOIN $meta_table pm2 ON m.wc_product_id = pm2.post_id
            WHERE pm.meta_key = '_stock'
            AND pm2.meta_key = '_stock_status'
            AND pm2.meta_value = 'instock'
            AND CAST(pm.meta_value AS UNSIGNED) <= %d
            AND CAST(pm.meta_value AS UNSIGNED) > 0
            ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC
        ", $low_stock_threshold));
    }
    
    /**
     * Get out of stock products for admin display
     */
    public function get_out_of_stock_products() {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $posts_table = $wpdb->posts;
        $meta_table = $wpdb->postmeta;
        
        return $wpdb->get_results("
            SELECT 
                m.wc_product_id,
                m.cws_product_id,
                p.post_title as product_name,
                m.last_sync
            FROM $mapping_table m
            INNER JOIN $posts_table p ON m.wc_product_id = p.ID
            INNER JOIN $meta_table pm ON m.wc_product_id = pm.post_id
            WHERE pm.meta_key = '_stock_status'
            AND pm.meta_value = 'outofstock'
            ORDER BY m.last_sync DESC
        ");
    }
    
    /**
     * Bulk update stock for specific products
     */
    public function bulk_update_stock($product_ids) {
        if (empty($product_ids)) {
            return array('updated' => 0, 'errors' => 0);
        }
        
        $updated = 0;
        $errors = 0;
        $low_stock_alerts = array();
        
        foreach ($product_ids as $wc_product_id) {
            try {
                $result = $this->update_single_product_stock($wc_product_id);
                
                if ($result['updated']) {
                    $updated++;
                }
                
                if ($result['low_stock']) {
                    $low_stock_alerts[] = $result;
                }
                
            } catch (Exception $e) {
                $errors++;
            }
        }
        
        // Send low stock notifications
        if (!empty($low_stock_alerts)) {
            $this->send_low_stock_notifications($low_stock_alerts);
        }
        
        return array(
            'updated' => $updated,
            'errors' => $errors,
            'low_stock' => count($low_stock_alerts)
        );
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'info', $operation_type = 'stock_update', $product_id = null, $wc_product_id = null) {
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