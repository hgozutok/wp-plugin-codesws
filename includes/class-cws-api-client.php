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

/**
 * Custom database token storage for WordPress
 */
class CWS_Database_Token_Storage {
    
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
 * CodesWholesale API Client
 * 
 * Handles communication with CodesWholesale API using the official PHP SDK.
 * Provides graceful fallback when SDK is not available.
 */
class CWS_API_Client {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * CodesWholesale client instance
     */
    private $client = null;
    
    /**
     * Whether the SDK is available
     */
    private $sdk_available = false;
    
    /**
     * Settings instance
     */
    private $settings = null;
    
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
        $this->sdk_available = class_exists('CodesWholesale\CodesWholesale');
        
        if ($this->sdk_available) {
            $this->settings = CWS_Settings::get_instance();
            $this->init_client();
        }
    }
    
    /**
     * Initialize CodesWholesale client
     */
    private function init_client() {
        if (!$this->sdk_available) {
            return false;
        }
        
        try {
            $client_id = $this->settings->get('client_id');
            $client_secret = $this->settings->get('client_secret');
            $environment = $this->settings->get('environment', 'live');
            
            if (empty($client_id) || empty($client_secret)) {
                return false;
            }
            
            // Use fully qualified class names
            $builder = new \CodesWholesale\Client\ClientBuilder();
            $builder->setClientId($client_id)
                   ->setClientSecret($client_secret)
                   ->setTokenStorage(new CWS_Database_Token_Storage());
                   
            if ($environment === 'sandbox') {
                $builder->setEnvironment(\CodesWholesale\Client\ClientBuilder::ENVIRONMENT_SANDBOX);
            }
            
            $this->client = $builder->build();
            
            return true;
            
        } catch (Exception $e) {
            error_log('CWS API Client initialization failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get CodesWholesale client
     */
    public function get_client() {
        if (!$this->sdk_available) {
            return null;
        }
        
        if (null === $this->client) {
            $this->init_client();
        }
        
        return $this->client;
    }
    
    /**
     * Check if API is connected
     */
    public function is_connected() {
        if (!$this->sdk_available) {
            return false;
        }
        
        $client = $this->get_client();
        if (!$client) {
            return false;
        }
        
        try {
            $account = $client->getAccount();
            return $account !== null;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (!$this->sdk_available) {
            return array(
                'status' => 'error', 
                'message' => 'CodesWholesale SDK not installed. Please run "composer install".'
            );
        }
        
        $client = $this->get_client();
        if (!$client) {
            return array(
                'status' => 'error',
                'message' => 'API client not configured. Please check your credentials.'
            );
        }
        
        try {
            $account = $client->getAccount();
            
            if ($account) {
                return array(
                    'status' => 'success',
                    'message' => 'Connection successful',
                    'data' => array(
                        'email' => $account->getEmail(),
                        'current_balance' => $account->getCurrentBalance(),
                        'current_credit' => $account->getCurrentCredit()
                    )
                );
            } else {
                return array(
                    'status' => 'error',
                    'message' => 'Failed to retrieve account information'
                );
            }
            
        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Refresh settings and reinitialize client
     */
    public function refresh_settings() {
        if (!$this->sdk_available) {
            return;
        }
        
        $this->client = null;
        $this->init_client();
    }
    
    /**
     * Get platforms
     */
    public function get_platforms() {
        if (!$this->sdk_available) {
            return array();
        }
        
        $client = $this->get_client();
        if (!$client) {
            return array();
        }
        
        try {
            $platforms = $client->getPlatforms();
            $result = array();
            
            foreach ($platforms as $platform) {
                $result[] = array(
                    'id' => $platform->getId(),
                    'name' => $platform->getName()
                );
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Failed to get platforms: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get regions
     */
    public function get_regions() {
        if (!$this->sdk_available) {
            return array();
        }
        
        $client = $this->get_client();
        if (!$client) {
            return array();
        }
        
        try {
            $regions = $client->getRegions();
            $result = array();
            
            foreach ($regions as $region) {
                $result[] = array(
                    'id' => $region->getId(),
                    'name' => $region->getName()
                );
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Failed to get regions: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get languages
     */
    public function get_languages() {
        if (!$this->sdk_available) {
            return array();
        }
        
        $client = $this->get_client();
        if (!$client) {
            return array();
        }
        
        try {
            $languages = $client->getLanguages();
            $result = array();
            
            foreach ($languages as $language) {
                $result[] = array(
                    'id' => $language->getId(),
                    'name' => $language->getName()
                );
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log('Failed to get languages: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get account balance
     */
    public function get_account_balance() {
        if (!$this->sdk_available) {
            return null;
        }
        
        $client = $this->get_client();
        if (!$client) {
            return null;
        }
        
        try {
            $account = $client->getAccount();
            
            if ($account) {
                return array(
                    'current_balance' => $account->getCurrentBalance(),
                    'current_credit' => $account->getCurrentCredit(),
                    'email' => $account->getEmail()
                );
            }
            
            return null;
            
        } catch (Exception $e) {
            error_log('Failed to get account balance: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if SDK is available
     */
    public function is_sdk_available() {
        return $this->sdk_available;
    }
} 