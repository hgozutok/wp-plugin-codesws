<?php
/**
 * CodesWholesale API Client
 * 
 * Handles API communication with CodesWholesale using the official PHP SDK.
 * Includes OAuth2 authentication, token management, and error handling.
 * 
 * @package CodesWholesaleSync
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if CodesWholesale SDK is available
if (!class_exists('CodesWholesale\CodesWholesale')) {
    // Provide a stub class if SDK is not available
    class CWS_API_Client {
        private static $instance = null;
        
        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        public function __construct() {
            // SDK not available
        }
        
        public function get_client() {
            return null;
        }
        
        public function is_connected() {
            return false;
        }
        
        public function test_connection() {
            return array('status' => 'error', 'message' => 'CodesWholesale SDK not installed. Please run "composer install".');
        }
        
        public function refresh_settings() {
            // No-op
        }
        
        public function get_platforms() {
            return array();
        }
        
        public function get_regions() {
            return array();
        }
        
        public function get_languages() {
            return array();
        }
        
        public function get_account_balance() {
            return null;
        }
    }
    return;
}

// Import CodesWholesale classes (only if SDK is available)
use CodesWholesale\CodesWholesale;
use CodesWholesale\Client\ClientBuilder;
use CodesWholesale\Storage\TokenStorageInterface;
use CodesWholesale\Resource\Account;
use CodesWholesale\Resource\Product;
use CodesWholesale\Resource\Platform;
use CodesWholesale\Resource\Region;
use CodesWholesale\Resource\Language;
use CodesWholesale\Resource\Invoice;
use CodesWholesale\Resource\Security;
use CodesWholesale\Util\Base64Writer;

/**
 * Custom database token storage for WordPress
 */
class CWS_Database_Token_Storage implements TokenStorageInterface {
    
    private $option_name = 'cws_api_token';
    
    public function storeToken($token) {
        update_option($this->option_name, $token, false);
    }
    
    public function getToken() {
        return get_option($this->option_name, null);
    }
    
    public function deleteToken() {
        delete_option($this->option_name);
    }
}

/**
 * CodesWholesale API Client Class
 */
class CWS_API_Client {
    
    /**
     * Instance
     * @var CWS_API_Client
     */
    private static $instance = null;
    
    /**
     * CodesWholesale Client
     * @var \CodesWholesale\Client
     */
    private $client = null;
    
    /**
     * Settings
     * @var array
     */
    private $settings = array();
    
    /**
     * Logger
     * @var \Monolog\Logger
     */
    private $logger;
    
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
        $this->init_logger();
        $this->load_settings();
        $this->init_client();
    }
    
    /**
     * Initialize logger
     */
    private function init_logger() {
        if (class_exists('\Monolog\Logger')) {
            $this->logger = new \Monolog\Logger('codeswholesale-sync');
            $handler = new \Monolog\Handler\StreamHandler(WP_CONTENT_DIR . '/uploads/cws-logs/api.log', \Monolog\Logger::INFO);
            $formatter = new \Monolog\Formatter\LineFormatter();
            $handler->setFormatter($formatter);
            $this->logger->pushHandler($handler);
        }
    }
    
    /**
     * Load settings from database
     */
    private function load_settings() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cws_settings';
        $results = $wpdb->get_results("SELECT setting_name, setting_value FROM $table_name WHERE autoload = 'yes'", ARRAY_A);
        
        foreach ($results as $setting) {
            $this->settings[$setting['setting_name']] = $setting['setting_value'];
        }
    }
    
    /**
     * Initialize CodesWholesale client
     */
    private function init_client() {
        $client_id = $this->get_setting('cws_client_id');
        $client_secret = $this->get_setting('cws_client_secret');
        $environment = $this->get_setting('cws_api_environment', 'sandbox');
        
        if (empty($client_id) || empty($client_secret)) {
            $this->log('API credentials not configured', 'warning');
            return false;
        }
        
        try {
            $params = array(
                'cw.client_id' => $client_id,
                'cw.client_secret' => $client_secret,
                'cw.endpoint_uri' => $environment === 'live' 
                    ? CodesWholesale::LIVE_ENDPOINT 
                    : CodesWholesale::SANDBOX_ENDPOINT,
                'cw.token_storage' => new CWS_Database_Token_Storage()
            );
            
            $clientBuilder = new ClientBuilder($params);
            $this->client = $clientBuilder->build();
            
            $this->log('API client initialized successfully', 'info');
            return true;
            
        } catch (Exception $e) {
            $this->log('Failed to initialize API client: ' . $e->getMessage(), 'error');
            return false;
        }
    }
    
    /**
     * Get setting value
     */
    private function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Check if client is initialized
     */
    public function is_connected() {
        return $this->client !== null;
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (!$this->is_connected()) {
            return false;
        }
        
        try {
            $account = $this->client->getAccount();
            $this->log('Connection test successful', 'info');
            return array(
                'success' => true,
                'data' => array(
                    'account_name' => $account->getFullName(),
                    'email' => $account->getEmail(),
                    'balance' => $account->getCurrentBalance(),
                    'credit' => $account->getCurrentCredit()
                )
            );
        } catch (Exception $e) {
            $this->log('Connection test failed: ' . $e->getMessage(), 'error');
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }
    
    /**
     * Get all products with optional filters
     */
    public function get_products($filters = array()) {
        if (!$this->is_connected()) {
            throw new Exception('API client not connected');
        }
        
        try {
            $products = $this->client->getProducts($filters);
            $this->log('Retrieved ' . count($products) . ' products', 'info');
            return $products;
        } catch (Exception $e) {
            $this->log('Failed to get products: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Get single product by ID or href
     */
    public function get_product($product_href) {
        if (!$this->is_connected()) {
            throw new Exception('API client not connected');
        }
        
        try {
            $product = Product::get($product_href);
            $this->log('Retrieved product: ' . $product->getName(), 'info');
            return $product;
        } catch (Exception $e) {
            $this->log('Failed to get product: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Get product description
     */
    public function get_product_description($description_href) {
        if (!$this->is_connected()) {
            throw new Exception('API client not connected');
        }
        
        try {
            $description = ProductDescription::get($description_href);
            $this->log('Retrieved product description', 'info');
            return $description;
        } catch (Exception $e) {
            $this->log('Failed to get product description: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Get platforms
     */
    public function get_platforms() {
        if (!$this->is_connected()) {
            throw new Exception('API client not connected');
        }
        
        try {
            $platforms = $this->client->getPlatforms();
            return $platforms;
        } catch (Exception $e) {
            $this->log('Failed to get platforms: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Get regions
     */
    public function get_regions() {
        if (!$this->is_connected()) {
            throw new Exception('API client not connected');
        }
        
        try {
            $regions = $this->client->getRegions();
            return $regions;
        } catch (Exception $e) {
            $this->log('Failed to get regions: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Get languages
     */
    public function get_languages() {
        if (!$this->is_connected()) {
            throw new Exception('API client not connected');
        }
        
        try {
            $languages = $this->client->getLanguages();
            return $languages;
        } catch (Exception $e) {
            $this->log('Failed to get languages: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Create order
     */
    public function create_order($products) {
        if (!$this->is_connected()) {
            throw new Exception('API client not connected');
        }
        
        try {
            $order = Order::createOrder($products, null);
            $this->log('Created order: ' . $order->getOrderId(), 'info');
            return $order;
        } catch (Exception $e) {
            $this->log('Failed to create order: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Get order history
     */
    public function get_order_history($date_from, $date_to) {
        if (!$this->is_connected()) {
            throw new Exception('API client not connected');
        }
        
        try {
            $orders = Order::getHistory($date_from, $date_to);
            return $orders;
        } catch (Exception $e) {
            $this->log('Failed to get order history: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Get account information
     */
    public function get_account() {
        if (!$this->is_connected()) {
            throw new Exception('API client not connected');
        }
        
        try {
            $account = $this->client->getAccount();
            return $account;
        } catch (Exception $e) {
            $this->log('Failed to get account info: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Security check for customer
     */
    public function security_check($email, $user_agent, $payment_email, $ip_address) {
        if (!$this->is_connected()) {
            throw new Exception('API client not connected');
        }
        
        try {
            $security = Security::check($email, $user_agent, $payment_email, $ip_address);
            $this->log('Security check completed for: ' . $email, 'info');
            return $security;
        } catch (Exception $e) {
            $this->log('Security check failed: ' . $e->getMessage(), 'error');
            throw $e;
        }
    }
    
    /**
     * Get webhook client for handling postback notifications
     */
    public function get_webhook_client() {
        return $this->client;
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'info') {
        if ($this->logger) {
            $this->logger->$level($message);
        }
        
        // Also log to WordPress debug.log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CodesWholesale Sync] ' . $message);
        }
    }
    
    /**
     * Update settings cache
     */
    public function refresh_settings() {
        $this->load_settings();
        
        // Reinitialize client if credentials changed
        $this->init_client();
    }
    
    /**
     * Get current environment
     */
    public function get_environment() {
        return $this->get_setting('cws_api_environment', 'sandbox');
    }
    
    /**
     * Check if using sandbox
     */
    public function is_sandbox() {
        return $this->get_environment() === 'sandbox';
    }
} 