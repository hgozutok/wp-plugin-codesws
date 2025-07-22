<?php
/**
 * CodesWholesale Scheduler
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Scheduler class for managing cron jobs
 */
class CWS_Scheduler {
    
    /**
     * Instance
     * @var CWS_Scheduler
     */
    private static $instance = null;
    
    /**
     * Settings
     * @var CWS_Settings
     */
    private $settings;
    
    /**
     * Cron hooks
     * @var array
     */
    private $cron_hooks = array(
        'cws_sync_products',
        'cws_update_prices', 
        'cws_update_stock',
        'cws_import_products',
        'cws_check_balance',
        'cws_cleanup_logs',
        'cws_process_pending_orders'
    );
    
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
        
        // Hook into WordPress
        add_action('init', array($this, 'init'));
        add_filter('cron_schedules', array($this, 'add_custom_cron_schedules'));
        
        // Register cron job handlers
        foreach ($this->cron_hooks as $hook) {
            add_action($hook, array($this, 'handle_cron_job'));
        }
        
        // Hook into settings changes to reschedule jobs
        add_action('cws_settings_updated', array($this, 'reschedule_cron_jobs'));
    }
    
    /**
     * Initialize scheduler
     */
    public function init() {
        // Schedule initial cron jobs if they don't exist
        $this->schedule_initial_jobs();
        
        // Clean up old cron jobs that might be orphaned
        $this->cleanup_old_jobs();
    }
    
    /**
     * Add custom cron schedules
     */
    public function add_custom_cron_schedules($schedules) {
        // Every 30 minutes
        $schedules['every_30_minutes'] = array(
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display'  => __('Every 30 Minutes', 'codeswholesale-sync')
        );
        
        // Every 2 hours
        $schedules['every_2_hours'] = array(
            'interval' => 2 * HOUR_IN_SECONDS,
            'display'  => __('Every 2 Hours', 'codeswholesale-sync')
        );
        
        // Every 6 hours
        $schedules['every_6_hours'] = array(
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => __('Every 6 Hours', 'codeswholesale-sync')
        );
        
        // Weekly
        $schedules['weekly'] = array(
            'interval' => WEEK_IN_SECONDS,
            'display'  => __('Weekly', 'codeswholesale-sync')
        );
        
        return $schedules;
    }
    
    /**
     * Schedule initial cron jobs
     */
    public function schedule_initial_jobs() {
        // Product sync (full sync of all mapped products)
        if (!wp_next_scheduled('cws_sync_products')) {
            $interval = $this->get_sync_interval();
            wp_schedule_event(time(), $interval, 'cws_sync_products');
        }
        
        // Price updates (more frequent than full sync)
        if (!wp_next_scheduled('cws_update_prices')) {
            wp_schedule_event(time(), 'every_6_hours', 'cws_update_prices');
        }
        
        // Stock updates (most frequent)
        if (!wp_next_scheduled('cws_update_stock')) {
            wp_schedule_event(time(), 'every_2_hours', 'cws_update_stock');
        }
        
        // New product import (if enabled)
        if ($this->settings->get('cws_auto_import_enabled', 'no') === 'yes') {
            if (!wp_next_scheduled('cws_import_products')) {
                wp_schedule_event(time(), 'daily', 'cws_import_products');
            }
        }
        
        // Balance check (before orders are processed)
        if (!wp_next_scheduled('cws_check_balance')) {
            wp_schedule_event(time(), 'every_6_hours', 'cws_check_balance');
        }
        
        // Process pending orders (frequently to handle failed orders)
        if (!wp_next_scheduled('cws_process_pending_orders')) {
            wp_schedule_event(time(), 'every_30_minutes', 'cws_process_pending_orders');
        }
        
        // Log cleanup (weekly)
        if (!wp_next_scheduled('cws_cleanup_logs')) {
            wp_schedule_event(time(), 'weekly', 'cws_cleanup_logs');
        }
    }
    
    /**
     * Handle cron job execution
     */
    public function handle_cron_job() {
        $current_action = current_action();
        
        $this->log("Cron job started: $current_action", 'info', 'cron');
        
        // Check if auto sync is enabled (except for cleanup jobs and order processing)
        if (!in_array($current_action, array('cws_cleanup_logs', 'cws_check_balance', 'cws_process_pending_orders'))) {
            if ($this->settings->get('cws_auto_sync_enabled', 'no') !== 'yes') {
                $this->log("Auto sync disabled, skipping: $current_action", 'info', 'cron');
                return;
            }
        }
        
        try {
            switch ($current_action) {
                case 'cws_sync_products':
                    $this->run_product_sync();
                    break;
                    
                case 'cws_update_prices':
                    $this->run_price_update();
                    break;
                    
                case 'cws_update_stock':
                    $this->run_stock_update();
                    break;
                    
                case 'cws_import_products':
                    $this->run_product_import();
                    break;
                    
                case 'cws_check_balance':
                    $this->run_balance_check();
                    break;
                    
                case 'cws_process_pending_orders':
                    $this->run_process_pending_orders();
                    break;
                    
                case 'cws_cleanup_logs':
                    $this->run_log_cleanup();
                    break;
            }
            
        } catch (Exception $e) {
            $this->log("Cron job failed ($current_action): " . $e->getMessage(), 'error', 'cron');
        }
        
        $this->log("Cron job completed: $current_action", 'info', 'cron');
    }
    
    /**
     * Run full product sync
     */
    private function run_product_sync() {
        $product_sync = CWS_Product_Sync::get_instance();
        $result = $product_sync->sync_all_products();
        
        $this->log(
            sprintf(
                'Product sync completed: %d synced, %d errors',
                $result['synced'],
                $result['errors']
            ),
            $result['errors'] > 0 ? 'warning' : 'info',
            'cron'
        );
        
        return $result;
    }
    
    /**
     * Run price update
     */
    private function run_price_update() {
        $price_updater = CWS_Price_Updater::get_instance();
        $result = $price_updater->update_all_prices();
        
        $this->log(
            sprintf(
                'Price update completed: %d updated, %d errors',
                $result['updated'],
                $result['errors']
            ),
            $result['errors'] > 0 ? 'warning' : 'info',
            'cron'
        );
        
        return $result;
    }
    
    /**
     * Run stock update
     */
    private function run_stock_update() {
        $stock_manager = CWS_Stock_Manager::get_instance();
        $result = $stock_manager->update_all_stock();
        
        $this->log(
            sprintf(
                'Stock update completed: %d updated, %d errors, %d low stock alerts',
                $result['updated'],
                $result['errors'],
                $result['low_stock']
            ),
            $result['errors'] > 0 ? 'warning' : 'info',
            'cron'
        );
        
        return $result;
    }
    
    /**
     * Run product import for new products
     */
    private function run_product_import() {
        $product_sync = CWS_Product_Sync::get_instance();
        
        // Use filters from settings
        $filters = $this->get_import_filters();
        
        // Import new products (last 7 days)
        $filters['days_ago'] = 7;
        
        $result = $product_sync->import_products($filters, 100);
        
        $this->log(
            sprintf(
                'New product import completed: %d imported, %d updated, %d skipped, %d errors',
                $result['imported'],
                $result['updated'],
                $result['skipped'],
                count($result['errors'])
            ),
            count($result['errors']) > 0 ? 'warning' : 'info',
            'cron'
        );
        
        return $result;
    }
    
    /**
     * Run balance check
     */
    private function run_balance_check() {
        $api_client = CWS_API_Client::get_instance();
        
        if (!$api_client->is_connected()) {
            $this->log('API not connected, skipping balance check', 'warning', 'cron');
            return;
        }
        
        try {
            $account = $api_client->get_account();
            $current_balance = $account->getCurrentBalance();
            $current_credit = $account->getCurrentCredit();
            $total_available = $account->getTotalToUse();
            
            // Check if balance is low
            $low_balance_threshold = floatval($this->settings->get('cws_low_balance_threshold', '100'));
            
            if ($total_available <= $low_balance_threshold) {
                $this->send_low_balance_notification($current_balance, $current_credit, $total_available);
            }
            
            // Store balance for dashboard display
            update_option('cws_last_balance_check', array(
                'balance' => $current_balance,
                'credit' => $current_credit,
                'total' => $total_available,
                'timestamp' => time()
            ), false);
            
            $this->log(
                sprintf(
                    'Balance check completed: Balance: %s, Credit: %s, Total: %s',
                    $current_balance,
                    $current_credit,
                    $total_available
                ),
                'info',
                'cron'
            );
            
        } catch (Exception $e) {
            $this->log('Balance check failed: ' . $e->getMessage(), 'error', 'cron');
        }
    }
    
    /**
     * Run log cleanup
     */
    private function run_log_cleanup() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'cws_sync_log';
        
        // Delete logs older than 30 days
        $deleted = $wpdb->query("
            DELETE FROM $logs_table 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        
        // Optimize table
        $wpdb->query("OPTIMIZE TABLE $logs_table");
        
        $this->log("Log cleanup completed: $deleted old entries removed", 'info', 'cron');
        
        return array('deleted' => $deleted);
    }
    
    /**
     * Process pending orders
     */
    private function run_process_pending_orders() {
        try {
            $order_manager = CWS_Order_Manager::get_instance();
            $order_manager->process_pending_orders();
            
            $this->log("Pending orders processing completed", 'info', 'cron');
            
        } catch (Exception $e) {
            $this->log("Failed to process pending orders: " . $e->getMessage(), 'error', 'cron');
            
            // Send notification to admin
            $this->send_error_notification(
                __('Order Processing Failed', 'codeswholesale-sync'),
                $e->getMessage()
            );
        }
    }
    
    /**
     * Send low balance notification
     */
    private function send_low_balance_notification($balance, $credit, $total) {
        if ($this->settings->get('cws_notify_low_balance', 'yes') !== 'yes') {
            return;
        }
        
        $notification_email = $this->settings->get('cws_notification_email', get_option('admin_email'));
        
        if (empty($notification_email)) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] Low Balance Alert', 'codeswholesale-sync'),
            get_bloginfo('name')
        );
        
        $message = __('Your CodesWholesale account balance is running low:', 'codeswholesale-sync') . "\n\n";
        $message .= sprintf(__('Current Balance: %s', 'codeswholesale-sync'), $balance) . "\n";
        $message .= sprintf(__('Available Credit: %s', 'codeswholesale-sync'), $credit) . "\n";
        $message .= sprintf(__('Total Available: %s', 'codeswholesale-sync'), $total) . "\n\n";
        $message .= __('Please add funds to your CodesWholesale account to continue processing orders.', 'codeswholesale-sync') . "\n";
        $message .= __('Login to CodesWholesale: https://app.codeswholesale.com/', 'codeswholesale-sync');
        
        wp_mail($notification_email, $subject, $message);
        
        $this->log('Low balance notification sent', 'info', 'notification');
    }
    
    /**
     * Get sync interval from settings
     */
    private function get_sync_interval() {
        $interval = $this->settings->get('cws_sync_interval', 'daily');
        
        $valid_intervals = array('hourly', 'twicedaily', 'daily');
        
        if (!in_array($interval, $valid_intervals)) {
            $interval = 'daily';
        }
        
        return $interval;
    }
    
    /**
     * Get import filters from settings
     */
    private function get_import_filters() {
        $filters = array();
        
        $platforms = $this->settings->get('cws_import_platforms', '');
        if (!empty($platforms)) {
            $filters['platforms'] = is_array($platforms) ? $platforms : explode(',', $platforms);
        }
        
        $regions = $this->settings->get('cws_import_regions', '');
        if (!empty($regions)) {
            $filters['regions'] = is_array($regions) ? $regions : explode(',', $regions);
        }
        
        $languages = $this->settings->get('cws_import_languages', '');
        if (!empty($languages)) {
            $filters['languages'] = is_array($languages) ? $languages : explode(',', $languages);
        }
        
        return $filters;
    }
    
    /**
     * Reschedule cron jobs when settings change
     */
    public function reschedule_cron_jobs() {
        // Clear existing schedules
        $this->clear_all_scheduled_jobs();
        
        // Reschedule with new settings
        $this->schedule_initial_jobs();
        
        $this->log('Cron jobs rescheduled due to settings change', 'info', 'cron');
    }
    
    /**
     * Clear all scheduled jobs
     */
    public function clear_all_scheduled_jobs() {
        foreach ($this->cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }
    
    /**
     * Manually trigger a specific job
     */
    public function trigger_job($job_name) {
        if (!in_array($job_name, $this->cron_hooks)) {
            throw new Exception('Invalid job name');
        }
        
        // Temporarily override the current action
        global $wp_filter;
        
        $original_action = isset($GLOBALS['wp_current_filter']) ? end($GLOBALS['wp_current_filter']) : '';
        $GLOBALS['wp_current_filter'][] = $job_name;
        
        try {
            $this->handle_cron_job();
        } finally {
            // Restore original action
            if ($original_action) {
                $GLOBALS['wp_current_filter'] = array($original_action);
            } else {
                $GLOBALS['wp_current_filter'] = array();
            }
        }
    }
    
    /**
     * Get next scheduled times for all jobs
     */
    public function get_scheduled_jobs() {
        $jobs = array();
        
        foreach ($this->cron_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            
            $jobs[$hook] = array(
                'hook' => $hook,
                'next_run' => $timestamp,
                'next_run_formatted' => $timestamp ? date('Y-m-d H:i:s', $timestamp) : 'Not scheduled',
                'next_run_human' => $timestamp ? human_time_diff($timestamp, time()) : 'Not scheduled',
                'interval' => $this->get_job_interval($hook),
                'enabled' => $timestamp !== false
            );
        }
        
        return $jobs;
    }
    
    /**
     * Get job interval
     */
    private function get_job_interval($hook) {
        $cron = _get_cron_array();
        
        foreach ($cron as $timestamp => $hooks) {
            if (isset($hooks[$hook])) {
                foreach ($hooks[$hook] as $key => $job) {
                    return $job['schedule'] ?? 'single';
                }
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Get cron job statistics
     */
    public function get_cron_statistics() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'cws_sync_log';
        
        $cron_jobs_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $logs_table 
            WHERE operation_type = 'cron' 
            AND DATE(created_at) = %s
        ", current_time('Y-m-d')));
        
        $cron_errors_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM $logs_table 
            WHERE operation_type = 'cron' 
            AND status = 'error' 
            AND DATE(created_at) = %s
        ", current_time('Y-m-d')));
        
        $last_cron = $wpdb->get_var("
            SELECT MAX(created_at) 
            FROM $logs_table 
            WHERE operation_type = 'cron'
        ");
        
        return array(
            'jobs_today' => intval($cron_jobs_today),
            'errors_today' => intval($cron_errors_today),
            'last_run' => $last_cron,
            'auto_sync_enabled' => $this->settings->get('cws_auto_sync_enabled', 'no') === 'yes',
            'sync_interval' => $this->get_sync_interval()
        );
    }
    
    /**
     * Cleanup old jobs (for plugin maintenance)
     */
    private function cleanup_old_jobs() {
        // Remove any old/orphaned cron jobs that might exist from previous versions
        $old_hooks = array(
            'cws_legacy_sync',
            'cws_old_import',
            'codeswholesale_sync' // Example old hook names
        );
        
        foreach ($old_hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }
    
    /**
     * Enable/disable auto sync
     */
    public function toggle_auto_sync($enabled) {
        if ($enabled) {
            $this->schedule_initial_jobs();
        } else {
            $this->clear_all_scheduled_jobs();
        }
        
        $this->log(
            'Auto sync ' . ($enabled ? 'enabled' : 'disabled'),
            'info',
            'cron'
        );
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'info', $operation_type = 'cron', $product_id = null, $wc_product_id = null) {
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
        
        // Also log to WordPress debug log for cron jobs
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[CodesWholesale Sync - Cron] ' . $message);
        }
    }
} 