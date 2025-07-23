<?php
/**
 * Main Admin View - Dashboard
 * 
 * This is the main entry point for the CodesWholesale plugin admin interface.
 * It includes the dashboard view which provides an overview of sync status,
 * statistics, and quick actions.
 * 
 * @package CodesWholesaleSync
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include the dashboard view
include_once CWS_PLUGIN_PATH . 'includes/admin/views/dashboard.php'; 