<?php
/**
 * CodesWholesale Sync Uninstall Script
 *
 * @package CodesWholesaleSync
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove plugin data on uninstall
 */
function cws_uninstall_plugin() {
    global $wpdb;
    
    // Remove custom database tables
    $table_names = array(
        $wpdb->prefix . 'cws_product_mapping',
        $wpdb->prefix . 'cws_sync_log',
        $wpdb->prefix . 'cws_settings'
    );
    
    foreach ($table_names as $table_name) {
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
    
    // Remove WordPress options
    $options = array(
        'cws_api_token',
        'cws_last_balance_check',
        'cws_plugin_version',
        'cws_installation_time'
    );
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Remove post meta for CodesWholesale products
    $meta_keys = array(
        '_cws_product_id',
        '_cws_product_href',
        '_cws_last_sync',
        '_cws_stock_quantity',
        '_cws_wholesale_price',
        '_cws_markup_amount',
        '_cws_markup_percentage',
        '_cws_price_updated',
        '_cws_stock_updated'
    );
    
    foreach ($meta_keys as $meta_key) {
        delete_post_meta_by_key($meta_key);
    }
    
    // Remove scheduled cron jobs
    $cron_hooks = array(
        'cws_sync_products',
        'cws_update_prices', 
        'cws_update_stock',
        'cws_import_products',
        'cws_check_balance',
        'cws_cleanup_logs'
    );
    
    foreach ($cron_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
    }
    
    // Clear any cached data
    wp_cache_flush();
    
    // Remove uploaded files (logs, images, etc.)
    $upload_dir = wp_upload_dir();
    $cws_dir = $upload_dir['basedir'] . '/cws-logs';
    
    if (is_dir($cws_dir)) {
        cws_delete_directory($cws_dir);
    }
}

/**
 * Recursively delete directory and its contents
 */
function cws_delete_directory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        
        if (is_dir($path)) {
            cws_delete_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

// Execute uninstall
cws_uninstall_plugin(); 