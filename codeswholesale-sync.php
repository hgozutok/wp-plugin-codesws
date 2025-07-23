<?php
/**
 * Plugin Name: CodesWholesale WooCommerce Sync
 * Plugin URI: https://github.com/your-repo/codeswholesale-sync
 * Description: Synchronize WooCommerce products with CodesWholesale API for automated digital game inventory management, pricing, and stock control.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 * Requires Plugins: woocommerce
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: codeswholesale-sync
 * Domain Path: /languages
 * Network: false
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CWS_PLUGIN_VERSION', '1.0.0');
define('CWS_PLUGIN_FILE', __FILE__);
define('CWS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('CWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CWS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// WooCommerce dependency check will be done in admin_init hook

// Include the Composer autoloader
if (file_exists(CWS_PLUGIN_PATH . 'vendor/autoload.php')) {
    require_once CWS_PLUGIN_PATH . 'vendor/autoload.php';
}

/**
 * Main plugin class
 */
final class CodesWholesaleSync {
    
    /**
     * Plugin instance
     * @var CodesWholesaleSync
     */
    private static $instance = null;
    
    /**
     * Get plugin instance
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
    private function __construct() {
        $this->includes();
        $this->init_classes();
        $this->declare_woocommerce_compatibility();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Plugin activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Add settings link in plugins page
        add_filter('plugin_action_links_' . CWS_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
    }
    
    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once CWS_PLUGIN_PATH . 'includes/class-cws-api-client.php';
        require_once CWS_PLUGIN_PATH . 'includes/class-cws-product-sync.php';
        require_once CWS_PLUGIN_PATH . 'includes/class-cws-price-updater.php';
        require_once CWS_PLUGIN_PATH . 'includes/class-cws-stock-manager.php';
        require_once CWS_PLUGIN_PATH . 'includes/class-cws-webhook-handler.php';
        require_once CWS_PLUGIN_PATH . 'includes/class-cws-scheduler.php';
        require_once CWS_PLUGIN_PATH . 'includes/class-cws-order-manager.php';
        
        // Admin classes
        if (is_admin()) {
            require_once CWS_PLUGIN_PATH . 'includes/admin/class-cws-admin.php';
            require_once CWS_PLUGIN_PATH . 'includes/admin/class-cws-settings.php';
        }
    }
    
    /**
     * Initialize classes
     */
    private function init_classes() {
        if (is_admin()) {
            CWS_Admin::get_instance();
            CWS_Settings::get_instance();
        }
        
        // Initialize core classes
        CWS_API_Client::get_instance();
        CWS_Product_Sync::get_instance();
        CWS_Price_Updater::get_instance();
        CWS_Stock_Manager::get_instance();
        CWS_Webhook_Handler::get_instance();
        CWS_Scheduler::get_instance();
        CWS_Order_Manager::get_instance();
    }
    
    /**
     * Declare WooCommerce compatibility
     */
    private function declare_woocommerce_compatibility() {
        // Declare HPOS compatibility
        add_action('before_woocommerce_init', function() {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('orders_cache', __FILE__, true);
            }
        });
        
        // Declare general WooCommerce compatibility
        add_action('init', function() {
            if (function_exists('wc_get_container')) {
                // Plugin is compatible with WooCommerce
                add_filter('woocommerce_admin_features', function($features) {
                    return $features;
                });
            }
        });
    }
    
    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain('codeswholesale-sync', false, dirname(CWS_PLUGIN_BASENAME) . '/languages');
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_menu_page(
            __('CodesWholesale Sync', 'codeswholesale-sync'),
            __('CodesWholesale', 'codeswholesale-sync'),
            'manage_options',
            'codeswholesale-sync',
            array($this, 'admin_page'),
            'dashicons-update',
            30
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        include CWS_PLUGIN_PATH . 'includes/admin/views/main.php';
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Schedule cron events
        if (!wp_next_scheduled('cws_sync_products')) {
            wp_schedule_event(time(), 'daily', 'cws_sync_products');
        }
        
        if (!wp_next_scheduled('cws_update_prices')) {
            wp_schedule_event(time(), 'hourly', 'cws_update_prices');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('cws_sync_products');
        wp_clear_scheduled_hook('cws_update_prices');
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Product mapping table
        $table_name = $wpdb->prefix . 'cws_product_mapping';
        $sql = "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            wc_product_id bigint(20) NOT NULL,
            cws_product_id varchar(255) NOT NULL,
            cws_product_href varchar(500),
            last_sync datetime DEFAULT NULL,
            sync_status enum('synced','pending','error') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_mapping (wc_product_id, cws_product_id),
            KEY idx_cws_product_id (cws_product_id),
            KEY idx_sync_status (sync_status)
        ) $charset_collate;";
        
        // Sync log table
        $log_table = $wpdb->prefix . 'cws_sync_log';
        $sql .= "CREATE TABLE $log_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            operation_type enum('import','price_update','stock_update','webhook','order') NOT NULL,
            product_id varchar(255),
            wc_product_id bigint(20),
            status enum('success','error','warning') NOT NULL,
            message text,
            details longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_operation_type (operation_type),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        // Settings table
        $settings_table = $wpdb->prefix . 'cws_settings';
        $sql .= "CREATE TABLE $settings_table (
            setting_name varchar(255) NOT NULL,
            setting_value longtext,
            autoload enum('yes','no') DEFAULT 'yes',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (setting_name),
            KEY idx_autoload (autoload)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Insert default settings
        $this->insert_default_settings();
    }
    
    /**
     * Insert default settings
     */
    private function insert_default_settings() {
        $default_settings = array(
            'cws_api_environment' => 'sandbox',
            'cws_client_id' => '',
            'cws_client_secret' => '',
            'cws_auto_sync_enabled' => 'yes',
            'cws_sync_interval' => 'daily',
            'cws_price_markup_type' => 'percentage',
            'cws_price_markup_value' => '20',
            'cws_enable_charm_pricing' => 'no',
            'cws_enable_pre_orders' => 'yes',
            'cws_low_stock_threshold' => '5',
            'cws_notification_email' => get_option('admin_email')
        );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cws_settings';
        
        foreach ($default_settings as $name => $value) {
            $wpdb->replace(
                $table_name,
                array(
                    'setting_name' => $name,
                    'setting_value' => $value
                ),
                array('%s', '%s')
            );
        }
    }
    
    /**
     * Add plugin action links
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=codeswholesale-sync') . '">' . __('Settings', 'codeswholesale-sync') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

/**
 * Check if WooCommerce is active
 */
function cws_check_woocommerce_dependency() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'cws_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Display WooCommerce missing notice
 */
function cws_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('CodesWholesale Sync requires WooCommerce to be installed and active.', 'codeswholesale-sync'); ?></p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function cws_init() {
    // Check WooCommerce dependency
    if (!cws_check_woocommerce_dependency()) {
        return;
    }
    
    // Initialize the plugin
    return CodesWholesaleSync::get_instance();
}

// Initialize the plugin after all plugins are loaded
add_action('plugins_loaded', 'cws_init'); 