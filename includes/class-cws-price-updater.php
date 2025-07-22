<?php
/**
 * CodesWholesale Price Updater
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Price updater class
 */
class CWS_Price_Updater {
    
    /**
     * Instance
     * @var CWS_Price_Updater
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
        add_action('cws_update_prices', array($this, 'update_all_prices'));
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Hook into WooCommerce price display if needed
        if (class_exists('WooCommerce')) {
            add_filter('woocommerce_product_get_price', array($this, 'maybe_apply_dynamic_pricing'), 10, 2);
        }
    }
    
    /**
     * Calculate prices for a CodesWholesale product
     */
    public function calculate_prices($cws_product) {
        $prices = $cws_product->getPrices();
        
        if (empty($prices) || !is_array($prices)) {
            return array(
                'regular' => 0,
                'wholesale' => 0
            );
        }
        
        // Get the first/base price
        $wholesale_price = 0;
        foreach ($prices as $price) {
            $wholesale_price = $price->getValue();
            break;
        }
        
        if ($wholesale_price <= 0) {
            return array(
                'regular' => 0,
                'wholesale' => $wholesale_price
            );
        }
        
        // Apply markup
        $regular_price = $this->apply_markup($wholesale_price);
        
        // Apply charm pricing if enabled
        if ($this->settings->get('cws_enable_charm_pricing', 'no') === 'yes') {
            $regular_price = $this->apply_charm_pricing($regular_price);
        }
        
        // Convert currency if needed
        $regular_price = $this->convert_currency($regular_price);
        
        // Round to appropriate decimal places
        $regular_price = round($regular_price, wc_get_price_decimals());
        
        return array(
            'regular' => $regular_price,
            'wholesale' => $wholesale_price,
            'markup_amount' => $regular_price - $wholesale_price,
            'markup_percentage' => $wholesale_price > 0 ? (($regular_price - $wholesale_price) / $wholesale_price) * 100 : 0
        );
    }
    
    /**
     * Apply markup to wholesale price
     */
    public function apply_markup($wholesale_price) {
        $markup_type = $this->settings->get('cws_price_markup_type', 'percentage');
        $markup_value = floatval($this->settings->get('cws_price_markup_value', '20'));
        
        if ($markup_value <= 0) {
            return $wholesale_price;
        }
        
        switch ($markup_type) {
            case 'percentage':
                return $wholesale_price * (1 + ($markup_value / 100));
                
            case 'fixed':
                return $wholesale_price + $markup_value;
                
            default:
                return $wholesale_price;
        }
    }
    
    /**
     * Apply charm pricing (prices ending in .99)
     */
    public function apply_charm_pricing($price) {
        if ($price <= 0) {
            return $price;
        }
        
        // Get the integer part
        $integer_part = floor($price);
        
        // If already ends in .99, return as is
        $decimal_part = $price - $integer_part;
        if (abs($decimal_part - 0.99) < 0.01) {
            return $price;
        }
        
        // Apply charm pricing
        if ($decimal_part <= 0.99) {
            return $integer_part + 0.99;
        } else {
            return $integer_part + 1.99;
        }
    }
    
    /**
     * Convert currency if needed
     */
    public function convert_currency($price) {
        // Get WooCommerce currency
        $wc_currency = get_woocommerce_currency();
        
        // For now, assume prices are already in the correct currency
        // In the future, you could implement currency conversion using exchange rates
        
        return $price;
    }
    
    /**
     * Update all product prices
     */
    public function update_all_prices() {
        global $wpdb;
        
        $this->log('Starting price update for all products', 'info', 'price_update');
        
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $mappings = $wpdb->get_results("SELECT * FROM $mapping_table WHERE sync_status = 'synced'");
        
        $updated = 0;
        $errors = 0;
        
        foreach ($mappings as $mapping) {
            try {
                $result = $this->update_single_product_price($mapping->wc_product_id);
                if ($result['updated']) {
                    $updated++;
                }
            } catch (Exception $e) {
                $errors++;
                $this->log(
                    'Price update failed for product ' . $mapping->wc_product_id . ': ' . $e->getMessage(),
                    'error',
                    'price_update',
                    $mapping->cws_product_id,
                    $mapping->wc_product_id
                );
            }
            
            // Small delay to prevent overwhelming the API
            usleep(100000); // 0.1 seconds
        }
        
        $this->log(
            "Price update completed: $updated updated, $errors errors",
            'info',
            'price_update'
        );
        
        return array(
            'updated' => $updated,
            'errors' => $errors
        );
    }
    
    /**
     * Update price for single product
     */
    public function update_single_product_price($wc_product_id) {
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
        
        // Calculate new prices
        $prices = $this->calculate_prices($cws_product);
        
        // Get current prices
        $current_regular = floatval($wc_product->get_regular_price());
        $current_sale = floatval($wc_product->get_sale_price());
        
        $price_changed = false;
        
        // Update regular price if changed
        if (abs($current_regular - $prices['regular']) > 0.01) {
            $wc_product->set_regular_price($prices['regular']);
            $price_changed = true;
        }
        
        // Handle sale price
        if (isset($prices['sale']) && $prices['sale'] > 0 && $prices['sale'] < $prices['regular']) {
            if (abs($current_sale - $prices['sale']) > 0.01) {
                $wc_product->set_sale_price($prices['sale']);
                $price_changed = true;
            }
        } else {
            // Remove sale price if not applicable
            if ($current_sale > 0) {
                $wc_product->set_sale_price('');
                $price_changed = true;
            }
        }
        
        // Save if changed
        if ($price_changed) {
            $wc_product->save();
            
            // Update metadata
            update_post_meta($wc_product_id, '_cws_wholesale_price', $prices['wholesale']);
            update_post_meta($wc_product_id, '_cws_markup_amount', $prices['markup_amount']);
            update_post_meta($wc_product_id, '_cws_markup_percentage', $prices['markup_percentage']);
            update_post_meta($wc_product_id, '_cws_price_updated', current_time('mysql'));
            
            $this->log(
                sprintf(
                    'Updated price for product %s: %s → %s (markup: %s%%)',
                    $wc_product->get_name(),
                    wc_price($current_regular),
                    wc_price($prices['regular']),
                    number_format($prices['markup_percentage'], 2)
                ),
                'info',
                'price_update',
                $mapping->cws_product_id,
                $wc_product_id
            );
        }
        
        return array(
            'updated' => $price_changed,
            'old_price' => $current_regular,
            'new_price' => $prices['regular'],
            'wholesale_price' => $prices['wholesale'],
            'markup_percentage' => $prices['markup_percentage']
        );
    }
    
    /**
     * Update prices based on webhook data
     */
    public function update_prices_from_webhook($price_changes) {
        if (empty($price_changes) || !is_array($price_changes)) {
            return;
        }
        
        $updated = 0;
        
        foreach ($price_changes as $price_change) {
            try {
                $cws_product_id = $price_change->getProductId();
                $wc_product_id = $this->get_wc_product_by_cws_id($cws_product_id);
                
                if ($wc_product_id) {
                    $result = $this->update_single_product_price($wc_product_id);
                    if ($result['updated']) {
                        $updated++;
                    }
                }
                
            } catch (Exception $e) {
                $this->log(
                    'Webhook price update failed: ' . $e->getMessage(),
                    'error',
                    'webhook'
                );
            }
        }
        
        $this->log(
            "Webhook price update completed: $updated products updated",
            'info',
            'webhook'
        );
        
        return $updated;
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
     * Maybe apply dynamic pricing (if needed for real-time adjustments)
     */
    public function maybe_apply_dynamic_pricing($price, $product) {
        // This could be used for real-time price adjustments based on stock levels,
        // time-based pricing, or other dynamic factors
        
        // For now, return the stored price
        return $price;
    }
    
    /**
     * Get price comparison data for admin display
     */
    public function get_price_comparison($wc_product_id) {
        $wholesale_price = get_post_meta($wc_product_id, '_cws_wholesale_price', true);
        $markup_amount = get_post_meta($wc_product_id, '_cws_markup_amount', true);
        $markup_percentage = get_post_meta($wc_product_id, '_cws_markup_percentage', true);
        $last_updated = get_post_meta($wc_product_id, '_cws_price_updated', true);
        
        $wc_product = wc_get_product($wc_product_id);
        
        if (!$wc_product) {
            return null;
        }
        
        return array(
            'wholesale_price' => floatval($wholesale_price),
            'retail_price' => floatval($wc_product->get_regular_price()),
            'sale_price' => floatval($wc_product->get_sale_price()),
            'markup_amount' => floatval($markup_amount),
            'markup_percentage' => floatval($markup_percentage),
            'last_updated' => $last_updated,
            'price_formatted' => array(
                'wholesale' => wc_price($wholesale_price),
                'retail' => wc_price($wc_product->get_regular_price()),
                'sale' => $wc_product->get_sale_price() ? wc_price($wc_product->get_sale_price()) : null
            )
        );
    }
    
    /**
     * Bulk update prices with custom markup
     */
    public function bulk_update_prices($product_ids, $markup_type = null, $markup_value = null) {
        if (empty($product_ids)) {
            return array('updated' => 0, 'errors' => 0);
        }
        
        // Temporarily override settings if custom markup provided
        $original_markup_type = null;
        $original_markup_value = null;
        
        if ($markup_type !== null && $markup_value !== null) {
            $original_markup_type = $this->settings->get('cws_price_markup_type');
            $original_markup_value = $this->settings->get('cws_price_markup_value');
            
            $this->settings->set('cws_price_markup_type', $markup_type);
            $this->settings->set('cws_price_markup_value', $markup_value);
        }
        
        $updated = 0;
        $errors = 0;
        
        foreach ($product_ids as $wc_product_id) {
            try {
                $result = $this->update_single_product_price($wc_product_id);
                if ($result['updated']) {
                    $updated++;
                }
            } catch (Exception $e) {
                $errors++;
            }
        }
        
        // Restore original settings if they were overridden
        if ($original_markup_type !== null && $original_markup_value !== null) {
            $this->settings->set('cws_price_markup_type', $original_markup_type);
            $this->settings->set('cws_price_markup_value', $original_markup_value);
        }
        
        return array(
            'updated' => $updated,
            'errors' => $errors
        );
    }
    
    /**
     * Calculate profit margin for a product
     */
    public function calculate_profit_margin($wc_product_id) {
        $wholesale_price = get_post_meta($wc_product_id, '_cws_wholesale_price', true);
        $wc_product = wc_get_product($wc_product_id);
        
        if (!$wc_product || !$wholesale_price) {
            return null;
        }
        
        $retail_price = floatval($wc_product->get_price());
        $wholesale_price = floatval($wholesale_price);
        
        if ($retail_price <= 0) {
            return null;
        }
        
        $profit = $retail_price - $wholesale_price;
        $margin_percentage = ($profit / $retail_price) * 100;
        $markup_percentage = $wholesale_price > 0 ? ($profit / $wholesale_price) * 100 : 0;
        
        return array(
            'wholesale_price' => $wholesale_price,
            'retail_price' => $retail_price,
            'profit_amount' => $profit,
            'margin_percentage' => $margin_percentage,
            'markup_percentage' => $markup_percentage,
            'formatted' => array(
                'wholesale' => wc_price($wholesale_price),
                'retail' => wc_price($retail_price),
                'profit' => wc_price($profit),
                'margin' => number_format($margin_percentage, 2) . '%',
                'markup' => number_format($markup_percentage, 2) . '%'
            )
        );
    }
    
    /**
     * Get pricing statistics for dashboard
     */
    public function get_pricing_statistics() {
        global $wpdb;
        
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $posts_table = $wpdb->posts;
        $meta_table = $wpdb->postmeta;
        
        // Get average markup percentage
        $avg_markup = $wpdb->get_var("
            SELECT AVG(CAST(meta_value AS DECIMAL(10,2))) 
            FROM $meta_table 
            WHERE meta_key = '_cws_markup_percentage'
        ");
        
        // Get products with markup data
        $products_with_pricing = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id) 
            FROM $meta_table 
            WHERE meta_key = '_cws_wholesale_price' 
            AND meta_value > 0
        ");
        
        // Get price update statistics
        $updated_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->prefix}cws_sync_log 
            WHERE operation_type = 'price_update' 
            AND status = 'success' 
            AND DATE(created_at) = %s
        ", current_time('Y-m-d')));
        
        return array(
            'products_with_pricing' => intval($products_with_pricing),
            'average_markup' => floatval($avg_markup),
            'updated_today' => intval($updated_today),
            'markup_type' => $this->settings->get('cws_price_markup_type', 'percentage'),
            'markup_value' => $this->settings->get('cws_price_markup_value', '20'),
            'charm_pricing_enabled' => $this->settings->get('cws_enable_charm_pricing', 'no') === 'yes'
        );
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'info', $operation_type = 'price_update', $product_id = null, $wc_product_id = null) {
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