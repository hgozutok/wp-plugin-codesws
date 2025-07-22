<?php
/**
 * Dashboard Admin View
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap cws-admin-page">
    <h1 class="wp-heading-inline"><?php _e('CodesWholesale Sync Dashboard', 'codeswholesale-sync'); ?></h1>
    
    <?php if (isset($connection_status['success']) && $connection_status['success']): ?>
        <div class="notice notice-success inline">
            <p>
                <strong><?php _e('Connected to CodesWholesale API', 'codeswholesale-sync'); ?></strong>
                <?php if ($api_client->is_sandbox()): ?>
                    <span class="cws-badge cws-badge-warning"><?php _e('Sandbox Mode', 'codeswholesale-sync'); ?></span>
                <?php else: ?>
                    <span class="cws-badge cws-badge-success"><?php _e('Live Mode', 'codeswholesale-sync'); ?></span>
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div class="notice notice-error inline">
            <p>
                <strong><?php _e('API Connection Failed', 'codeswholesale-sync'); ?></strong>
                <?php if (isset($connection_status['error'])): ?>
                    <br><?php echo esc_html($connection_status['error']); ?>
                <?php endif; ?>
                <br><a href="<?php echo admin_url('admin.php?page=codeswholesale-sync-settings'); ?>" class="button button-primary">
                    <?php _e('Configure API Settings', 'codeswholesale-sync'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <div class="cws-dashboard-grid">
        <!-- Account Information Card -->
        <?php if (isset($connection_status['success']) && $connection_status['success'] && isset($connection_status['data'])): ?>
        <div class="cws-card">
            <div class="cws-card-header">
                <h2><?php _e('Account Information', 'codeswholesale-sync'); ?></h2>
            </div>
            <div class="cws-card-body">
                <div class="cws-account-info">
                    <div class="cws-account-field">
                        <label><?php _e('Account Name:', 'codeswholesale-sync'); ?></label>
                        <span><?php echo esc_html($connection_status['data']['account_name']); ?></span>
                    </div>
                    <div class="cws-account-field">
                        <label><?php _e('Email:', 'codeswholesale-sync'); ?></label>
                        <span><?php echo esc_html($connection_status['data']['email']); ?></span>
                    </div>
                    <div class="cws-account-field">
                        <label><?php _e('Current Balance:', 'codeswholesale-sync'); ?></label>
                        <span class="cws-balance"><?php echo esc_html($connection_status['data']['balance']); ?></span>
                    </div>
                    <div class="cws-account-field">
                        <label><?php _e('Available Credit:', 'codeswholesale-sync'); ?></label>
                        <span class="cws-credit"><?php echo esc_html($connection_status['data']['credit']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Sync Statistics Card -->
        <div class="cws-card">
            <div class="cws-card-header">
                <h2><?php _e('Sync Statistics', 'codeswholesale-sync'); ?></h2>
                <button type="button" class="button button-secondary" id="refresh-stats">
                    <?php _e('Refresh', 'codeswholesale-sync'); ?>
                </button>
            </div>
            <div class="cws-card-body">
                <div class="cws-stats-grid">
                    <div class="cws-stat-item">
                        <div class="cws-stat-number"><?php echo number_format($sync_stats['total_mapped']); ?></div>
                        <div class="cws-stat-label"><?php _e('Products Mapped', 'codeswholesale-sync'); ?></div>
                    </div>
                    <div class="cws-stat-item">
                        <div class="cws-stat-number"><?php echo number_format($sync_stats['synced_today']); ?></div>
                        <div class="cws-stat-label"><?php _e('Synced Today', 'codeswholesale-sync'); ?></div>
                    </div>
                    <div class="cws-stat-item">
                        <div class="cws-stat-number cws-stat-error"><?php echo number_format($sync_stats['errors_today']); ?></div>
                        <div class="cws-stat-label"><?php _e('Errors Today', 'codeswholesale-sync'); ?></div>
                    </div>
                    <div class="cws-stat-item">
                        <div class="cws-stat-label"><?php _e('Last Sync:', 'codeswholesale-sync'); ?></div>
                        <div class="cws-stat-value">
                            <?php 
                            if ($sync_stats['last_sync']) {
                                echo human_time_diff(strtotime($sync_stats['last_sync']), current_time('timestamp')) . ' ' . __('ago', 'codeswholesale-sync');
                            } else {
                                _e('Never', 'codeswholesale-sync');
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Card -->
        <div class="cws-card">
            <div class="cws-card-header">
                <h2><?php _e('Quick Actions', 'codeswholesale-sync'); ?></h2>
            </div>
            <div class="cws-card-body">
                <div class="cws-quick-actions">
                    <a href="<?php echo admin_url('admin.php?page=codeswholesale-sync-import'); ?>" class="button button-primary button-large">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Import Products', 'codeswholesale-sync'); ?>
                    </a>
                    
                    <button type="button" class="button button-secondary button-large" id="manual-sync">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Manual Sync', 'codeswholesale-sync'); ?>
                    </button>
                    
                    <a href="<?php echo admin_url('admin.php?page=codeswholesale-sync-settings'); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Settings', 'codeswholesale-sync'); ?>
                    </a>
                    
                    <a href="<?php echo admin_url('admin.php?page=codeswholesale-sync-logs'); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php _e('View Logs', 'codeswholesale-sync'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity Card -->
        <div class="cws-card cws-card-full-width">
            <div class="cws-card-header">
                <h2><?php _e('Recent Activity', 'codeswholesale-sync'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=codeswholesale-sync-logs'); ?>" class="button button-secondary">
                    <?php _e('View All Logs', 'codeswholesale-sync'); ?>
                </a>
            </div>
            <div class="cws-card-body">
                <?php if (!empty($recent_logs)): ?>
                    <div class="cws-recent-logs">
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Time', 'codeswholesale-sync'); ?></th>
                                    <th><?php _e('Operation', 'codeswholesale-sync'); ?></th>
                                    <th><?php _e('Status', 'codeswholesale-sync'); ?></th>
                                    <th><?php _e('Message', 'codeswholesale-sync'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_logs as $log): ?>
                                    <tr>
                                        <td>
                                            <abbr title="<?php echo esc_attr($log->created_at); ?>">
                                                <?php echo human_time_diff(strtotime($log->created_at), current_time('timestamp')) . ' ' . __('ago', 'codeswholesale-sync'); ?>
                                            </abbr>
                                        </td>
                                        <td>
                                            <span class="cws-operation-type cws-operation-<?php echo esc_attr($log->operation_type); ?>">
                                                <?php echo ucwords(str_replace('_', ' ', $log->operation_type)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="cws-status cws-status-<?php echo esc_attr($log->status); ?>">
                                                <?php echo ucfirst($log->status); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo esc_html(wp_trim_words($log->message, 20)); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="cws-no-logs">
                        <p><?php _e('No recent activity found.', 'codeswholesale-sync'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- System Status Card -->
        <div class="cws-card">
            <div class="cws-card-header">
                <h2><?php _e('System Status', 'codeswholesale-sync'); ?></h2>
            </div>
            <div class="cws-card-body">
                <div class="cws-system-status">
                    <div class="cws-status-item">
                        <span class="cws-status-label"><?php _e('WordPress Version:', 'codeswholesale-sync'); ?></span>
                        <span class="cws-status-value"><?php echo get_bloginfo('version'); ?></span>
                    </div>
                    <div class="cws-status-item">
                        <span class="cws-status-label"><?php _e('WooCommerce Version:', 'codeswholesale-sync'); ?></span>
                        <span class="cws-status-value">
                            <?php echo class_exists('WooCommerce') ? WC()->version : __('Not Installed', 'codeswholesale-sync'); ?>
                        </span>
                    </div>
                    <div class="cws-status-item">
                        <span class="cws-status-label"><?php _e('PHP Version:', 'codeswholesale-sync'); ?></span>
                        <span class="cws-status-value"><?php echo PHP_VERSION; ?></span>
                    </div>
                    <div class="cws-status-item">
                        <span class="cws-status-label"><?php _e('Plugin Version:', 'codeswholesale-sync'); ?></span>
                        <span class="cws-status-value"><?php echo CWS_PLUGIN_VERSION; ?></span>
                    </div>
                    <div class="cws-status-item">
                        <span class="cws-status-label"><?php _e('Auto Sync:', 'codeswholesale-sync'); ?></span>
                        <span class="cws-status-value">
                            <?php 
                            $auto_sync = $settings->get('cws_auto_sync_enabled', 'no');
                            if ($auto_sync === 'yes') {
                                echo '<span class="cws-status-enabled">' . __('Enabled', 'codeswholesale-sync') . '</span>';
                            } else {
                                echo '<span class="cws-status-disabled">' . __('Disabled', 'codeswholesale-sync') . '</span>';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="cws-status-item">
                        <span class="cws-status-label"><?php _e('Next Scheduled Sync:', 'codeswholesale-sync'); ?></span>
                        <span class="cws-status-value">
                            <?php 
                            $next_sync = wp_next_scheduled('cws_sync_products');
                            if ($next_sync) {
                                echo human_time_diff($next_sync, current_time('timestamp')) . ' ' . __('from now', 'codeswholesale-sync');
                            } else {
                                _e('Not scheduled', 'codeswholesale-sync');
                            }
                            ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="cws-dashboard-actions">
        <h2><?php _e('Getting Started', 'codeswholesale-sync'); ?></h2>
        <p><?php _e('Follow these steps to set up your CodesWholesale synchronization:', 'codeswholesale-sync'); ?></p>
        
        <div class="cws-setup-steps">
            <div class="cws-step <?php echo (!empty($settings->get('cws_client_id')) && !empty($settings->get('cws_client_secret'))) ? 'cws-step-completed' : 'cws-step-pending'; ?>">
                <div class="cws-step-number">1</div>
                <div class="cws-step-content">
                    <h3><?php _e('Configure API Credentials', 'codeswholesale-sync'); ?></h3>
                    <p><?php _e('Enter your CodesWholesale API credentials in the settings page.', 'codeswholesale-sync'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=codeswholesale-sync-settings'); ?>" class="button">
                        <?php _e('Go to Settings', 'codeswholesale-sync'); ?>
                    </a>
                </div>
            </div>
            
            <div class="cws-step <?php echo ($sync_stats['total_mapped'] > 0) ? 'cws-step-completed' : 'cws-step-pending'; ?>">
                <div class="cws-step-number">2</div>
                <div class="cws-step-content">
                    <h3><?php _e('Import Products', 'codeswholesale-sync'); ?></h3>
                    <p><?php _e('Import products from CodesWholesale into your WooCommerce store.', 'codeswholesale-sync'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=codeswholesale-sync-import'); ?>" class="button">
                        <?php _e('Import Products', 'codeswholesale-sync'); ?>
                    </a>
                </div>
            </div>
            
            <div class="cws-step <?php echo ($settings->get('cws_auto_sync_enabled') === 'yes') ? 'cws-step-completed' : 'cws-step-pending'; ?>">
                <div class="cws-step-number">3</div>
                <div class="cws-step-content">
                    <h3><?php _e('Enable Auto Sync', 'codeswholesale-sync'); ?></h3>
                    <p><?php _e('Enable automatic synchronization to keep your products, prices, and stock levels updated.', 'codeswholesale-sync'); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=codeswholesale-sync-settings#sync'); ?>" class="button">
                        <?php _e('Enable Auto Sync', 'codeswholesale-sync'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Refresh stats
    $('#refresh-stats').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).text('<?php _e('Refreshing...', 'codeswholesale-sync'); ?>');
        
        $.post(ajaxurl, {
            action: 'cws_get_sync_status',
            nonce: cwsAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            }
        }).always(function() {
            button.prop('disabled', false).text('<?php _e('Refresh', 'codeswholesale-sync'); ?>');
        });
    });
    
    // Manual sync
    $('#manual-sync').on('click', function() {
        var button = $(this);
        button.prop('disabled', true).html('<span class="spinner is-active"></span> <?php _e('Syncing...', 'codeswholesale-sync'); ?>');
        
        $.post(ajaxurl, {
            action: 'cws_sync_products',
            nonce: cwsAdmin.nonce
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Sync failed: ' + response.data);
            }
        }).always(function() {
            button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> <?php _e('Manual Sync', 'codeswholesale-sync'); ?>');
        });
    });
});
</script> 