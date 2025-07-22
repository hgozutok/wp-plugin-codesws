<?php
/**
 * CodesWholesale Product Sync
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Product synchronization class
 */
class CWS_Product_Sync {
    
    /**
     * Instance
     * @var CWS_Product_Sync
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
     * Price Updater
     * @var CWS_Price_Updater
     */
    private $price_updater;
    
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
        
        // Hook into WooCommerce
        add_action('init', array($this, 'init'));
        add_action('cws_sync_products', array($this, 'sync_all_products'));
        add_action('cws_import_products', array($this, 'scheduled_import'));
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Initialize price updater after WooCommerce is loaded
        if (class_exists('WooCommerce')) {
            $this->price_updater = CWS_Price_Updater::get_instance();
        }
    }
    
    /**
     * Import products with filters
     */
    public function import_products($filters = array(), $limit = 50) {
        if (!$this->api_client->is_connected()) {
            throw new Exception(__('API client not connected', 'codeswholesale-sync'));
        }
        
        $this->log('Starting product import', 'info', 'import');
        
        try {
            // Get products from API
            $api_filters = $this->prepare_api_filters($filters);
            $products = $this->api_client->get_products($api_filters);
            
            if (empty($products)) {
                return array(
                    'imported' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => array(),
                    'message' => __('No products found with current filters', 'codeswholesale-sync')
                );
            }
            
            // Limit products if specified
            if ($limit > 0) {
                $products = array_slice($products, 0, $limit);
            }
            
            $results = array(
                'imported' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => array()
            );
            
            foreach ($products as $index => $cws_product) {
                try {
                    $result = $this->import_single_product($cws_product);
                    $results[$result['action']]++;
                    
                    // Add small delay to prevent API rate limiting
                    if ($index > 0 && $index % 10 === 0) {
                        usleep(500000); // 0.5 second delay every 10 products
                    }
                    
                } catch (Exception $e) {
                    $results['errors'][] = sprintf(
                        __('Product %s: %s', 'codeswholesale-sync'),
                        $cws_product->getName(),
                        $e->getMessage()
                    );
                    $this->log(
                        'Failed to import product: ' . $cws_product->getName() . ' - ' . $e->getMessage(),
                        'error',
                        'import'
                    );
                }
            }
            
            $this->log(
                sprintf(
                    'Import completed: %d imported, %d updated, %d skipped, %d errors',
                    $results['imported'],
                    $results['updated'],
                    $results['skipped'],
                    count($results['errors'])
                ),
                'info',
                'import'
            );
            
            return $results;
            
        } catch (Exception $e) {
            $this->log('Import failed: ' . $e->getMessage(), 'error', 'import');
            throw $e;
        }
    }
    
    /**
     * Import single product from CodesWholesale
     */
    public function import_single_product($cws_product) {
        global $wpdb;
        
        // Check if product already exists
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $existing_mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $mapping_table WHERE cws_product_id = %s",
            $cws_product->getProductId()
        ));
        
        $action = 'imported';
        
        if ($existing_mapping) {
            // Update existing product
            $wc_product_id = $existing_mapping->wc_product_id;
            $wc_product = wc_get_product($wc_product_id);
            
            if (!$wc_product) {
                // WooCommerce product was deleted, create new one
                $wc_product_id = $this->create_woocommerce_product($cws_product);
                $this->update_product_mapping($existing_mapping->id, $wc_product_id);
            } else {
                // Update existing WooCommerce product
                $this->update_woocommerce_product($wc_product, $cws_product);
                $action = 'updated';
            }
        } else {
            // Create new product
            $wc_product_id = $this->create_woocommerce_product($cws_product);
            $this->create_product_mapping($wc_product_id, $cws_product);
        }
        
        // Update mapping timestamp
        $wpdb->update(
            $mapping_table,
            array(
                'last_sync' => current_time('mysql'),
                'sync_status' => 'synced'
            ),
            array('wc_product_id' => $wc_product_id),
            array('%s', '%s'),
            array('%d')
        );
        
        return array(
            'action' => $action,
            'wc_product_id' => $wc_product_id,
            'cws_product_id' => $cws_product->getProductId()
        );
    }
    
    /**
     * Create WooCommerce product from CodesWholesale product
     */
    private function create_woocommerce_product($cws_product) {
        // Create WooCommerce product
        $wc_product = new WC_Product_Simple();
        
        // Basic product information
        $wc_product->set_name($cws_product->getName());
        $wc_product->set_status('publish');
        $wc_product->set_catalog_visibility('visible');
        $wc_product->set_virtual(true); // Digital products are virtual
        $wc_product->set_downloadable(true);
        
        // Set initial stock
        $stock_quantity = $cws_product->getStockQuantity();
        if ($stock_quantity > 0) {
            $wc_product->set_manage_stock(true);
            $wc_product->set_stock_quantity($stock_quantity);
            $wc_product->set_stock_status('instock');
        } else {
            $wc_product->set_stock_status('outofstock');
        }
        
        // Set price with markup
        $this->set_product_price($wc_product, $cws_product);
        
        // Get and set product description
        try {
            $description = $this->get_product_description($cws_product);
            if ($description) {
                $wc_product->set_description($description['long']);
                $wc_product->set_short_description($description['short']);
            }
        } catch (Exception $e) {
            $this->log('Failed to get product description: ' . $e->getMessage(), 'warning', 'import');
        }
        
        // Save the product
        $wc_product_id = $wc_product->save();
        
        // Set categories and attributes
        $this->set_product_taxonomy($wc_product_id, $cws_product);
        
        // Download and set product images
        $this->set_product_images($wc_product_id, $cws_product);
        
        // Add CodesWholesale metadata
        $this->set_product_metadata($wc_product_id, $cws_product);
        
        $this->log(
            'Created WooCommerce product: ' . $cws_product->getName() . ' (ID: ' . $wc_product_id . ')',
            'info',
            'import'
        );
        
        return $wc_product_id;
    }
    
    /**
     * Update existing WooCommerce product
     */
    private function update_woocommerce_product($wc_product, $cws_product) {
        // Update basic information
        $wc_product->set_name($cws_product->getName());
        
        // Update stock
        $stock_quantity = $cws_product->getStockQuantity();
        if ($stock_quantity > 0) {
            $wc_product->set_manage_stock(true);
            $wc_product->set_stock_quantity($stock_quantity);
            $wc_product->set_stock_status('instock');
        } else {
            $wc_product->set_stock_status('outofstock');
        }
        
        // Update price
        $this->set_product_price($wc_product, $cws_product);
        
        // Save changes
        $wc_product->save();
        
        // Update metadata
        $this->set_product_metadata($wc_product->get_id(), $cws_product);
        
        $this->log(
            'Updated WooCommerce product: ' . $cws_product->getName() . ' (ID: ' . $wc_product->get_id() . ')',
            'info',
            'import'
        );
    }
    
    /**
     * Set product price with markup
     */
    private function set_product_price($wc_product, $cws_product) {
        if ($this->price_updater) {
            $prices = $this->price_updater->calculate_prices($cws_product);
            $wc_product->set_regular_price($prices['regular']);
            
            if (isset($prices['sale']) && $prices['sale'] < $prices['regular']) {
                $wc_product->set_sale_price($prices['sale']);
            }
        }
    }
    
    /**
     * Get product description from CodesWholesale
     */
    private function get_product_description($cws_product) {
        try {
            $description_href = $cws_product->getDescriptionHref();
            if (!$description_href) {
                return null;
            }
            
            $description = $this->api_client->get_product_description($description_href);
            
            if (!$description) {
                return null;
            }
            
            $long_description = '';
            $short_description = '';
            
            // Get localized titles
            $titles = $description->getLocalizedTitles();
            if ($titles && isset($titles['EN'])) {
                $short_description .= '<h3>' . esc_html($titles['EN']) . '</h3>';
            }
            
            // Get fact sheets (descriptions)
            $fact_sheets = $description->getFactSheets();
            if ($fact_sheets) {
                foreach ($fact_sheets as $fact_sheet) {
                    if (isset($fact_sheet['language']) && $fact_sheet['language'] === 'EN') {
                        $long_description .= wp_kses_post($fact_sheet['description']);
                        break;
                    }
                }
                
                // Fallback to first available description
                if (empty($long_description) && !empty($fact_sheets)) {
                    $long_description = wp_kses_post($fact_sheets[0]['description']);
                }
            }
            
            // Add additional info
            $additional_info = array();
            
            $platform = $description->getPlatform();
            if ($platform) {
                $additional_info[] = '<strong>Platform:</strong> ' . esc_html($platform);
            }
            
            $developer = $description->getDeveloperName();
            if ($developer) {
                $additional_info[] = '<strong>Developer:</strong> ' . esc_html($developer);
            }
            
            $pegi_rating = $description->getPegiRating();
            if ($pegi_rating) {
                $additional_info[] = '<strong>PEGI Rating:</strong> ' . esc_html($pegi_rating);
            }
            
            $game_languages = $description->getGameLanguages();
            if ($game_languages && is_array($game_languages)) {
                $additional_info[] = '<strong>Languages:</strong> ' . esc_html(implode(', ', $game_languages));
            }
            
            if (!empty($additional_info)) {
                $long_description .= '<br><br><div class="cws-product-info">' . implode('<br>', $additional_info) . '</div>';
            }
            
            return array(
                'long' => $long_description,
                'short' => $short_description
            );
            
        } catch (Exception $e) {
            $this->log('Failed to get product description: ' . $e->getMessage(), 'warning', 'import');
            return null;
        }
    }
    
    /**
     * Set product categories and attributes
     */
    private function set_product_taxonomy($wc_product_id, $cws_product) {
        // Get or create categories based on platform
        try {
            $description_href = $cws_product->getDescriptionHref();
            if ($description_href) {
                $description = $this->api_client->get_product_description($description_href);
                
                $categories = array();
                
                // Platform category
                $platform = $description->getPlatform();
                if ($platform) {
                    $cat_id = $this->get_or_create_category($platform, 'Platform');
                    if ($cat_id) {
                        $categories[] = $cat_id;
                    }
                }
                
                // Game category
                $category = $description->getCategory();
                if ($category) {
                    $cat_id = $this->get_or_create_category($category, 'Genre');
                    if ($cat_id) {
                        $categories[] = $cat_id;
                    }
                }
                
                // Assign categories
                if (!empty($categories)) {
                    wp_set_object_terms($wc_product_id, $categories, 'product_cat');
                }
                
                // Set attributes
                $this->set_product_attributes($wc_product_id, $description);
            }
        } catch (Exception $e) {
            $this->log('Failed to set product taxonomy: ' . $e->getMessage(), 'warning', 'import');
        }
    }
    
    /**
     * Get or create product category
     */
    private function get_or_create_category($name, $parent_name = '') {
        $parent_id = 0;
        
        // Create parent category if specified
        if (!empty($parent_name)) {
            $parent_term = get_term_by('name', $parent_name, 'product_cat');
            if (!$parent_term) {
                $parent_result = wp_insert_term($parent_name, 'product_cat');
                if (!is_wp_error($parent_result)) {
                    $parent_id = $parent_result['term_id'];
                }
            } else {
                $parent_id = $parent_term->term_id;
            }
        }
        
        // Check if category exists
        $term = get_term_by('name', $name, 'product_cat');
        if ($term && $term->parent == $parent_id) {
            return $term->term_id;
        }
        
        // Create new category
        $result = wp_insert_term($name, 'product_cat', array(
            'parent' => $parent_id
        ));
        
        if (is_wp_error($result)) {
            $this->log('Failed to create category: ' . $name, 'warning', 'import');
            return null;
        }
        
        return $result['term_id'];
    }
    
    /**
     * Set product attributes
     */
    private function set_product_attributes($wc_product_id, $description) {
        $attributes = array();
        
        // Platform attribute
        $platform = $description->getPlatform();
        if ($platform) {
            $attributes['platform'] = array(
                'name' => 'Platform',
                'value' => $platform,
                'position' => 0,
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 0
            );
        }
        
        // Developer attribute
        $developer = $description->getDeveloperName();
        if ($developer) {
            $attributes['developer'] = array(
                'name' => 'Developer',
                'value' => $developer,
                'position' => 1,
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 0
            );
        }
        
        // PEGI Rating attribute
        $pegi_rating = $description->getPegiRating();
        if ($pegi_rating) {
            $attributes['pegi_rating'] = array(
                'name' => 'PEGI Rating',
                'value' => $pegi_rating,
                'position' => 2,
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 0
            );
        }
        
        // Languages attribute
        $languages = $description->getGameLanguages();
        if ($languages && is_array($languages)) {
            $attributes['languages'] = array(
                'name' => 'Languages',
                'value' => implode(', ', $languages),
                'position' => 3,
                'is_visible' => 1,
                'is_variation' => 0,
                'is_taxonomy' => 0
            );
        }
        
        // Save attributes
        if (!empty($attributes)) {
            update_post_meta($wc_product_id, '_product_attributes', $attributes);
        }
    }
    
    /**
     * Set product images
     */
    private function set_product_images($wc_product_id, $cws_product) {
        try {
            // Get product images from different sizes
            $image_urls = array();
            
            // Try different image sizes
            $sizes = array('LARGE', 'MEDIUM', 'SMALL');
            foreach ($sizes as $size) {
                try {
                    $image_url = $cws_product->getImageUrl($size);
                    if ($image_url) {
                        $image_urls[] = $image_url;
                        break; // Use the first available size
                    }
                } catch (Exception $e) {
                    continue;
                }
            }
            
            if (empty($image_urls)) {
                return;
            }
            
            // Download and attach images
            $attachment_ids = array();
            foreach ($image_urls as $image_url) {
                $attachment_id = $this->download_and_attach_image($image_url, $wc_product_id, $cws_product->getName());
                if ($attachment_id) {
                    $attachment_ids[] = $attachment_id;
                }
            }
            
            // Set featured image (first image)
            if (!empty($attachment_ids)) {
                set_post_thumbnail($wc_product_id, $attachment_ids[0]);
                
                // Set gallery images (remaining images)
                if (count($attachment_ids) > 1) {
                    update_post_meta($wc_product_id, '_product_image_gallery', implode(',', array_slice($attachment_ids, 1)));
                }
            }
            
        } catch (Exception $e) {
            $this->log('Failed to set product images: ' . $e->getMessage(), 'warning', 'import');
        }
    }
    
    /**
     * Download and attach image to product
     */
    private function download_and_attach_image($image_url, $post_id, $product_name) {
        if (!$image_url) {
            return false;
        }
        
        // Download image
        $response = wp_remote_get($image_url, array(
            'timeout' => 30,
            'user-agent' => 'CodesWholesale-Sync-Plugin/' . CWS_PLUGIN_VERSION
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            return false;
        }
        
        // Get file info
        $filename = sanitize_file_name($product_name . '-' . time() . '.jpg');
        $upload_dir = wp_upload_dir();
        
        if ($upload_dir['error']) {
            return false;
        }
        
        // Save file
        $file_path = $upload_dir['path'] . '/' . $filename;
        $file_saved = file_put_contents($file_path, $image_data);
        
        if (!$file_saved) {
            return false;
        }
        
        // Create attachment
        $attachment = array(
            'post_mime_type' => 'image/jpeg',
            'post_title' => $product_name,
            'post_content' => '',
            'post_status' => 'inherit',
            'post_parent' => $post_id
        );
        
        $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        // Generate metadata
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attachment_data);
        
        return $attachment_id;
    }
    
    /**
     * Set product metadata
     */
    private function set_product_metadata($wc_product_id, $cws_product) {
        // Store CodesWholesale metadata
        update_post_meta($wc_product_id, '_cws_product_id', $cws_product->getProductId());
        update_post_meta($wc_product_id, '_cws_product_href', $cws_product->getHref());
        update_post_meta($wc_product_id, '_cws_last_sync', current_time('mysql'));
        update_post_meta($wc_product_id, '_cws_stock_quantity', $cws_product->getStockQuantity());
        
        // Store original prices for reference
        $prices = $cws_product->getPrices();
        if ($prices && is_array($prices)) {
            foreach ($prices as $price) {
                update_post_meta($wc_product_id, '_cws_wholesale_price', $price->getValue());
                break; // Use first price
            }
        }
    }
    
    /**
     * Create product mapping
     */
    private function create_product_mapping($wc_product_id, $cws_product) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cws_product_mapping';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'wc_product_id' => $wc_product_id,
                'cws_product_id' => $cws_product->getProductId(),
                'cws_product_href' => $cws_product->getHref(),
                'sync_status' => 'synced',
                'last_sync' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            $this->log('Failed to create product mapping for product ID: ' . $wc_product_id, 'error', 'import');
        }
    }
    
    /**
     * Update product mapping
     */
    private function update_product_mapping($mapping_id, $wc_product_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cws_product_mapping';
        
        $wpdb->update(
            $table_name,
            array(
                'wc_product_id' => $wc_product_id,
                'last_sync' => current_time('mysql'),
                'sync_status' => 'synced'
            ),
            array('id' => $mapping_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
    }
    
    /**
     * Sync all mapped products
     */
    public function sync_all_products() {
        global $wpdb;
        
        $this->log('Starting full product sync', 'info', 'sync');
        
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $mappings = $wpdb->get_results("SELECT * FROM $mapping_table ORDER BY last_sync ASC");
        
        $synced = 0;
        $errors = 0;
        
        foreach ($mappings as $mapping) {
            try {
                $this->sync_single_product($mapping->wc_product_id);
                $synced++;
            } catch (Exception $e) {
                $errors++;
                $this->log('Sync failed for product ' . $mapping->wc_product_id . ': ' . $e->getMessage(), 'error', 'sync');
            }
            
            // Add delay to prevent rate limiting
            usleep(200000); // 0.2 seconds
        }
        
        $this->log("Full sync completed: $synced synced, $errors errors", 'info', 'sync');
        
        return array(
            'synced' => $synced,
            'errors' => $errors
        );
    }
    
    /**
     * Sync single product by WooCommerce product ID
     */
    public function sync_single_product($wc_product_id) {
        global $wpdb;
        
        // Get mapping
        $mapping_table = $wpdb->prefix . 'cws_product_mapping';
        $mapping = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $mapping_table WHERE wc_product_id = %d",
            $wc_product_id
        ));
        
        if (!$mapping) {
            throw new Exception(__('Product mapping not found', 'codeswholesale-sync'));
        }
        
        // Get CodesWholesale product
        $cws_product = $this->api_client->get_product($mapping->cws_product_href);
        
        if (!$cws_product) {
            throw new Exception(__('CodesWholesale product not found', 'codeswholesale-sync'));
        }
        
        // Get WooCommerce product
        $wc_product = wc_get_product($wc_product_id);
        
        if (!$wc_product) {
            throw new Exception(__('WooCommerce product not found', 'codeswholesale-sync'));
        }
        
        // Update product
        $this->update_woocommerce_product($wc_product, $cws_product);
        
        // Update mapping
        $wpdb->update(
            $mapping_table,
            array(
                'last_sync' => current_time('mysql'),
                'sync_status' => 'synced'
            ),
            array('id' => $mapping->id),
            array('%s', '%s'),
            array('%d')
        );
        
        return array(
            'success' => true,
            'wc_product_id' => $wc_product_id,
            'cws_product_id' => $mapping->cws_product_id
        );
    }
    
    /**
     * Prepare API filters
     */
    private function prepare_api_filters($filters) {
        $api_filters = array();
        
        // Platform filter
        if (!empty($filters['platforms'])) {
            $api_filters['platform'] = is_array($filters['platforms']) ? $filters['platforms'] : array($filters['platforms']);
        }
        
        // Region filter
        if (!empty($filters['regions'])) {
            $api_filters['region'] = is_array($filters['regions']) ? $filters['regions'] : array($filters['regions']);
        }
        
        // Language filter
        if (!empty($filters['languages'])) {
            $api_filters['language'] = is_array($filters['languages']) ? $filters['languages'] : array($filters['languages']);
        }
        
        // Date filter
        if (!empty($filters['days_ago'])) {
            $api_filters['inStockDaysAgo'] = intval($filters['days_ago']);
        }
        
        return $api_filters;
    }
    
    /**
     * Scheduled import (for cron)
     */
    public function scheduled_import() {
        if ($this->settings->get('cws_auto_sync_enabled', 'no') !== 'yes') {
            return;
        }
        
        try {
            $filters = array();
            
            // Get saved import filters from settings
            $saved_platforms = $this->settings->get('cws_import_platforms', '');
            if (!empty($saved_platforms)) {
                $filters['platforms'] = is_array($saved_platforms) ? $saved_platforms : explode(',', $saved_platforms);
            }
            
            $saved_regions = $this->settings->get('cws_import_regions', '');
            if (!empty($saved_regions)) {
                $filters['regions'] = is_array($saved_regions) ? $saved_regions : explode(',', $saved_regions);
            }
            
            $saved_languages = $this->settings->get('cws_import_languages', '');
            if (!empty($saved_languages)) {
                $filters['languages'] = is_array($saved_languages) ? $saved_languages : explode(',', $saved_languages);
            }
            
            // Import with default limit
            $result = $this->import_products($filters, 100);
            
            $this->log(
                sprintf(
                    'Scheduled import completed: %d imported, %d updated, %d errors',
                    $result['imported'],
                    $result['updated'],
                    count($result['errors'])
                ),
                'info',
                'import'
            );
            
        } catch (Exception $e) {
            $this->log('Scheduled import failed: ' . $e->getMessage(), 'error', 'import');
        }
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'info', $operation_type = 'sync', $product_id = null, $wc_product_id = null) {
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
        
        // Also log to WordPress debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CodesWholesale Sync] ' . $message);
        }
    }
} 