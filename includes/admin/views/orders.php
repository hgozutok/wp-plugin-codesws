<?php
/**
 * Admin Orders View
 * 
 * Interface for managing CodesWholesale orders, viewing fulfillment status,
 * and handling order operations.
 * 
 * @package CodesWholesaleSync
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get order manager and statistics
$order_manager = CWS_Order_Manager::get_instance();
$order_stats = $order_manager->get_order_statistics();

// Get recent orders with CWS data
$recent_orders = $this->get_recent_cws_orders();

// Get filters from request
$status_filter = $_GET['cws_status'] ?? '';
$date_filter = $_GET['date_range'] ?? 'all';
$search_filter = $_GET['search'] ?? '';

?>

<div class="wrap cws-admin-page">
    <h1><?php _e('CodesWholesale Orders', 'codeswholesale-sync'); ?></h1>
    
    <!-- Order Statistics Cards -->
    <div class="cws-dashboard-grid">
        <div class="cws-card">
            <div class="cws-card-header">
                <h3><?php _e('Order Statistics', 'codeswholesale-sync'); ?></h3>
            </div>
            <div class="cws-card-body">
                <div class="cws-stats-grid">
                    <div class="cws-stat">
                        <span class="cws-stat-number"><?php echo esc_html($order_stats['total_orders']); ?></span>
                        <span class="cws-stat-label"><?php _e('Total Orders', 'codeswholesale-sync'); ?></span>
                    </div>
                    <div class="cws-stat">
                        <span class="cws-stat-number cws-success"><?php echo esc_html($order_stats['successful_orders']); ?></span>
                        <span class="cws-stat-label"><?php _e('Successful', 'codeswholesale-sync'); ?></span>
                    </div>
                    <div class="cws-stat">
                        <span class="cws-stat-number cws-error"><?php echo esc_html($order_stats['failed_orders']); ?></span>
                        <span class="cws-stat-label"><?php _e('Failed', 'codeswholesale-sync'); ?></span>
                    </div>
                    <div class="cws-stat">
                        <span class="cws-stat-number"><?php echo wc_price($order_stats['total_revenue']); ?></span>
                        <span class="cws-stat-label"><?php _e('Total Revenue', 'codeswholesale-sync'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="cws-card">
            <div class="cws-card-header">
                <h3><?php _e('Quick Actions', 'codeswholesale-sync'); ?></h3>
            </div>
            <div class="cws-card-body">
                <div class="cws-actions">
                    <button type="button" class="button button-primary" id="cws-process-pending">
                        <?php _e('Process Pending Orders', 'codeswholesale-sync'); ?>
                    </button>
                    <button type="button" class="button" id="cws-sync-order-status">
                        <?php _e('Sync Order Status', 'codeswholesale-sync'); ?>
                    </button>
                    <button type="button" class="button" id="cws-export-orders">
                        <?php _e('Export Orders', 'codeswholesale-sync'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Filters -->
    <div class="cws-orders-filters">
        <form method="get" id="cws-orders-filter-form">
            <input type="hidden" name="page" value="cws-orders">
            
            <div class="cws-filter-row">
                <label for="cws_status"><?php _e('Status:', 'codeswholesale-sync'); ?></label>
                <select name="cws_status" id="cws_status">
                    <option value=""><?php _e('All Statuses', 'codeswholesale-sync'); ?></option>
                    <option value="completed" <?php selected($status_filter, 'completed'); ?>><?php _e('Completed', 'codeswholesale-sync'); ?></option>
                    <option value="partial" <?php selected($status_filter, 'partial'); ?>><?php _e('Partial', 'codeswholesale-sync'); ?></option>
                    <option value="failed" <?php selected($status_filter, 'failed'); ?>><?php _e('Failed', 'codeswholesale-sync'); ?></option>
                    <option value="processing" <?php selected($status_filter, 'processing'); ?>><?php _e('Processing', 'codeswholesale-sync'); ?></option>
                </select>
                
                <label for="date_range"><?php _e('Date Range:', 'codeswholesale-sync'); ?></label>
                <select name="date_range" id="date_range">
                    <option value="all" <?php selected($date_filter, 'all'); ?>><?php _e('All Time', 'codeswholesale-sync'); ?></option>
                    <option value="today" <?php selected($date_filter, 'today'); ?>><?php _e('Today', 'codeswholesale-sync'); ?></option>
                    <option value="week" <?php selected($date_filter, 'week'); ?>><?php _e('This Week', 'codeswholesale-sync'); ?></option>
                    <option value="month" <?php selected($date_filter, 'month'); ?>><?php _e('This Month', 'codeswholesale-sync'); ?></option>
                </select>
                
                <label for="search"><?php _e('Search:', 'codeswholesale-sync'); ?></label>
                <input type="text" name="search" id="search" value="<?php echo esc_attr($search_filter); ?>" placeholder="<?php _e('Order ID, Customer...', 'codeswholesale-sync'); ?>">
                
                <input type="submit" class="button" value="<?php _e('Filter', 'codeswholesale-sync'); ?>">
                <a href="<?php echo admin_url('admin.php?page=cws-orders'); ?>" class="button"><?php _e('Reset', 'codeswholesale-sync'); ?></a>
            </div>
        </form>
    </div>
    
    <!-- Orders Table -->
    <div class="cws-orders-table-container">
        <form method="post" id="cws-orders-bulk-form">
            <?php wp_nonce_field('cws_admin_nonce', 'cws_nonce'); ?>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Bulk Actions', 'codeswholesale-sync'); ?></option>
                        <option value="retry"><?php _e('Retry Failed Orders', 'codeswholesale-sync'); ?></option>
                        <option value="export"><?php _e('Export Selected', 'codeswholesale-sync'); ?></option>
                        <option value="resend_keys"><?php _e('Resend Keys', 'codeswholesale-sync'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'codeswholesale-sync'); ?>">
                </div>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column">
                            <input type="checkbox" id="cb-select-all-1">
                        </td>
                        <th class="manage-column"><?php _e('Order', 'codeswholesale-sync'); ?></th>
                        <th class="manage-column"><?php _e('Customer', 'codeswholesale-sync'); ?></th>
                        <th class="manage-column"><?php _e('Products', 'codeswholesale-sync'); ?></th>
                        <th class="manage-column"><?php _e('Status', 'codeswholesale-sync'); ?></th>
                        <th class="manage-column"><?php _e('Total', 'codeswholesale-sync'); ?></th>
                        <th class="manage-column"><?php _e('Date', 'codeswholesale-sync'); ?></th>
                        <th class="manage-column"><?php _e('Actions', 'codeswholesale-sync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($recent_orders)): ?>
                        <?php foreach ($recent_orders as $order_data): ?>
                            <?php 
                            $order = wc_get_order($order_data['order_id']);
                            if (!$order) continue;
                            
                            $cws_status = $order->get_meta('_cws_status');
                            $cws_orders = $order->get_meta('_cws_orders');
                            ?>
                            <tr>
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="orders[]" value="<?php echo esc_attr($order->get_id()); ?>">
                                </th>
                                
                                <td class="column-order">
                                    <strong>
                                        <a href="<?php echo esc_url($order->get_edit_order_url()); ?>">
                                            #<?php echo esc_html($order->get_order_number()); ?>
                                        </a>
                                    </strong>
                                    <?php if ($cws_orders): ?>
                                        <div class="cws-order-details">
                                            <?php foreach ($cws_orders as $cws_order): ?>
                                                <?php if (!empty($cws_order['cws_order_id'])): ?>
                                                    <small>CWS: <?php echo esc_html($cws_order['cws_order_id']); ?></small><br>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-customer">
                                    <?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?>
                                    <br>
                                    <small><?php echo esc_html($order->get_billing_email()); ?></small>
                                </td>
                                
                                <td class="column-products">
                                    <?php 
                                    $cws_product_count = 0;
                                    $total_keys = 0;
                                    
                                    if ($cws_orders) {
                                        foreach ($cws_orders as $cws_order) {
                                            $cws_product_count++;
                                            if (!empty($cws_order['keys'])) {
                                                $total_keys += count($cws_order['keys']);
                                            }
                                        }
                                    }
                                    ?>
                                    <strong><?php echo esc_html($cws_product_count); ?></strong> <?php _e('CWS Products', 'codeswholesale-sync'); ?>
                                    <?php if ($total_keys > 0): ?>
                                        <br><small><?php echo esc_html($total_keys); ?> <?php _e('Keys Delivered', 'codeswholesale-sync'); ?></small>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-status">
                                    <?php
                                    $status_class = '';
                                    $status_text = '';
                                    
                                    switch ($cws_status) {
                                        case 'completed':
                                            $status_class = 'cws-badge cws-success';
                                            $status_text = __('Completed', 'codeswholesale-sync');
                                            break;
                                        case 'partial':
                                            $status_class = 'cws-badge cws-warning';
                                            $status_text = __('Partial', 'codeswholesale-sync');
                                            break;
                                        case 'failed':
                                            $status_class = 'cws-badge cws-error';
                                            $status_text = __('Failed', 'codeswholesale-sync');
                                            break;
                                        default:
                                            $status_class = 'cws-badge';
                                            $status_text = __('Processing', 'codeswholesale-sync');
                                    }
                                    ?>
                                    <span class="<?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
                                    
                                    <?php if ($order->get_status() !== 'completed' && $order->get_status() !== 'cancelled'): ?>
                                        <br><small>WC: <?php echo esc_html($order->get_status()); ?></small>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="column-total">
                                    <?php echo wp_kses_post($order->get_formatted_order_total()); ?>
                                </td>
                                
                                <td class="column-date">
                                    <?php echo esc_html($order->get_date_created()->date('M j, Y')); ?>
                                    <br>
                                    <small><?php echo esc_html($order->get_date_created()->date('g:i a')); ?></small>
                                </td>
                                
                                <td class="column-actions">
                                    <div class="cws-order-actions">
                                        <button type="button" class="button button-small cws-view-order" 
                                                data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                                                title="<?php _e('View Details', 'codeswholesale-sync'); ?>">
                                            <?php _e('View', 'codeswholesale-sync'); ?>
                                        </button>
                                        
                                        <?php if ($cws_status === 'failed' || $cws_status === 'partial'): ?>
                                            <button type="button" class="button button-small cws-retry-order" 
                                                    data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                                                    title="<?php _e('Retry Order', 'codeswholesale-sync'); ?>">
                                                <?php _e('Retry', 'codeswholesale-sync'); ?>
                                            </button>
                                        <?php endif; ?>
                                        
                                        <?php if ($total_keys > 0): ?>
                                            <button type="button" class="button button-small cws-resend-keys" 
                                                    data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                                                    title="<?php _e('Resend Keys', 'codeswholesale-sync'); ?>">
                                                <?php _e('Resend', 'codeswholesale-sync'); ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="cws-no-orders">
                                <p><?php _e('No CodesWholesale orders found.', 'codeswholesale-sync'); ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <select name="action2" id="bulk-action-selector-bottom">
                        <option value="-1"><?php _e('Bulk Actions', 'codeswholesale-sync'); ?></option>
                        <option value="retry"><?php _e('Retry Failed Orders', 'codeswholesale-sync'); ?></option>
                        <option value="export"><?php _e('Export Selected', 'codeswholesale-sync'); ?></option>
                        <option value="resend_keys"><?php _e('Resend Keys', 'codeswholesale-sync'); ?></option>
                    </select>
                    <input type="submit" class="button action" value="<?php _e('Apply', 'codeswholesale-sync'); ?>">
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Order Details Modal -->
<div id="cws-order-modal" class="cws-modal" style="display: none;">
    <div class="cws-modal-content">
        <div class="cws-modal-header">
            <h2><?php _e('Order Details', 'codeswholesale-sync'); ?></h2>
            <span class="cws-modal-close">&times;</span>
        </div>
        <div class="cws-modal-body">
            <div id="cws-order-details-content">
                <!-- Content loaded via AJAX -->
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Process pending orders
        $('#cws-process-pending').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.prop('disabled', true).text('<?php _e('Processing...', 'codeswholesale-sync'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cws_process_pending_orders',
                    nonce: '<?php echo wp_create_nonce('cws_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        CWSAdmin.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        CWSAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    CWSAdmin.showNotice('<?php _e('Request failed', 'codeswholesale-sync'); ?>', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('<?php _e('Process Pending Orders', 'codeswholesale-sync'); ?>');
                }
            });
        });
        
        // View order details
        $('.cws-view-order').on('click', function(e) {
            e.preventDefault();
            
            var orderId = $(this).data('order-id');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cws_get_order_details',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('cws_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        displayOrderDetails(response.data);
                        $('#cws-order-modal').show();
                    } else {
                        CWSAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    CWSAdmin.showNotice('<?php _e('Failed to load order details', 'codeswholesale-sync'); ?>', 'error');
                }
            });
        });
        
        // Retry order
        $('.cws-retry-order').on('click', function(e) {
            e.preventDefault();
            
            var orderId = $(this).data('order-id');
            var $button = $(this);
            
            if (!confirm('<?php _e('Are you sure you want to retry this order?', 'codeswholesale-sync'); ?>')) {
                return;
            }
            
            $button.prop('disabled', true).text('<?php _e('Retrying...', 'codeswholesale-sync'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cws_retry_order',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('cws_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        CWSAdmin.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        CWSAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    CWSAdmin.showNotice('<?php _e('Retry request failed', 'codeswholesale-sync'); ?>', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('<?php _e('Retry', 'codeswholesale-sync'); ?>');
                }
            });
        });
        
        // Resend keys
        $('.cws-resend-keys').on('click', function(e) {
            e.preventDefault();
            
            var orderId = $(this).data('order-id');
            var $button = $(this);
            
            if (!confirm('<?php _e('Are you sure you want to resend the product keys?', 'codeswholesale-sync'); ?>')) {
                return;
            }
            
            $button.prop('disabled', true).text('<?php _e('Sending...', 'codeswholesale-sync'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'cws_resend_keys',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('cws_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        CWSAdmin.showNotice(response.data.message, 'success');
                    } else {
                        CWSAdmin.showNotice(response.data.message, 'error');
                    }
                },
                error: function() {
                    CWSAdmin.showNotice('<?php _e('Failed to resend keys', 'codeswholesale-sync'); ?>', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('<?php _e('Resend', 'codeswholesale-sync'); ?>');
                }
            });
        });
        
        // Modal close
        $('.cws-modal-close').on('click', function() {
            $('#cws-order-modal').hide();
        });
        
        // Click outside modal to close
        $(window).on('click', function(event) {
            if (event.target.id === 'cws-order-modal') {
                $('#cws-order-modal').hide();
            }
        });
        
        // Select all checkbox
        $('#cb-select-all-1').on('change', function() {
            $('input[name="orders[]"]').prop('checked', this.checked);
        });
    });
    
    function displayOrderDetails(data) {
        var html = '<div class="cws-order-details">';
        html += '<h3>' + '<?php _e('Order Information', 'codeswholesale-sync'); ?>' + '</h3>';
        html += '<p><strong>' + '<?php _e('Status:', 'codeswholesale-sync'); ?>' + '</strong> ' + data.cws_status + '</p>';
        html += '<p><strong>' + '<?php _e('Total:', 'codeswholesale-sync'); ?>' + '</strong> ' + data.order_total + ' ' + data.currency + '</p>';
        
        if (data.order_data && data.order_data.length > 0) {
            html += '<h4>' + '<?php _e('CodesWholesale Orders', 'codeswholesale-sync'); ?>' + '</h4>';
            
            $.each(data.order_data, function(index, item) {
                html += '<div class="cws-order-item" style="border: 1px solid #ddd; padding: 10px; margin: 10px 0;">';
                html += '<h5>' + (item.product_name || 'Digital Product') + '</h5>';
                html += '<p><strong>' + '<?php _e('CWS Order ID:', 'codeswholesale-sync'); ?>' + '</strong> ' + (item.cws_order_id || 'N/A') + '</p>';
                html += '<p><strong>' + '<?php _e('Status:', 'codeswholesale-sync'); ?>' + '</strong> ' + (item.status || 'Unknown') + '</p>';
                
                if (item.keys && item.keys.length > 0) {
                    html += '<p><strong>' + '<?php _e('Product Keys:', 'codeswholesale-sync'); ?>' + '</strong></p>';
                    html += '<ul>';
                    $.each(item.keys, function(keyIndex, key) {
                        html += '<li><code>' + key.code + '</code>';
                        if (key.platform) {
                            html += ' (' + key.platform + ')';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                }
                html += '</div>';
            });
        }
        
        html += '</div>';
        
        $('#cws-order-details-content').html(html);
    }
    
})(jQuery);
</script>

<?php
// Add CSS for modal
?>
<style type="text/css">
.cws-modal {
    position: fixed;
    z-index: 100000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.cws-modal-content {
    background-color: #fff;
    margin: 5% auto;
    padding: 0;
    border-radius: 5px;
    width: 80%;
    max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
}

.cws-modal-header {
    padding: 20px;
    background-color: #f1f1f1;
    border-bottom: 1px solid #ddd;
    border-radius: 5px 5px 0 0;
    position: relative;
}

.cws-modal-header h2 {
    margin: 0;
}

.cws-modal-close {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.cws-modal-close:hover {
    color: #999;
}

.cws-modal-body {
    padding: 20px;
}

.cws-orders-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 15px;
    margin: 20px 0;
    border-radius: 3px;
}

.cws-filter-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cws-filter-row label {
    font-weight: 600;
    min-width: 80px;
}

.cws-orders-table-container {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
}

.cws-order-actions {
    display: flex;
    gap: 5px;
}

.cws-order-details {
    font-size: 12px;
    color: #666;
}

.cws-no-orders {
    text-align: center;
    padding: 40px 20px;
    color: #666;
}

.cws-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.cws-stat {
    text-align: center;
}

.cws-stat-number {
    display: block;
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 5px;
}

.cws-stat-number.cws-success {
    color: #46b450;
}

.cws-stat-number.cws-error {
    color: #dc3232;
}

.cws-stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}
</style> 