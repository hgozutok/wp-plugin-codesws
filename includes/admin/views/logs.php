<?php
/**
 * Sync Logs Admin View
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get current page for pagination
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
?>

<div class="wrap cws-admin-page">
    <h1 class="wp-heading-inline"><?php _e('Sync Logs', 'codeswholesale-sync'); ?></h1>
    
    <hr class="wp-header-end">
    
    <div class="cws-logs-container">
        <?php if (empty($logs)): ?>
            <div class="cws-no-data">
                <p><?php _e('No sync logs found.', 'codeswholesale-sync'); ?></p>
                <p><?php _e('Logs will appear here as the plugin performs sync operations.', 'codeswholesale-sync'); ?></p>
            </div>
        <?php else: ?>
            <div class="cws-logs-stats">
                <p><?php echo sprintf(__('Showing %d log entries (%d total)', 'codeswholesale-sync'), count($logs), $total_items); ?></p>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-primary">
                            <?php _e('Date/Time', 'codeswholesale-sync'); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php _e('Operation', 'codeswholesale-sync'); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php _e('Status', 'codeswholesale-sync'); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php _e('Message', 'codeswholesale-sync'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="column-primary">
                                <strong>
                                    <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $log->created_at)); ?>
                                </strong>
                                <button type="button" class="toggle-row"><span class="screen-reader-text"><?php _e('Show more details', 'codeswholesale-sync'); ?></span></button>
                            </td>
                            <td data-colname="<?php _e('Operation', 'codeswholesale-sync'); ?>">
                                <?php echo esc_html(ucfirst($log->operation_type)); ?>
                            </td>
                            <td data-colname="<?php _e('Status', 'codeswholesale-sync'); ?>">
                                <?php 
                                $status_class = '';
                                switch ($log->status) {
                                    case 'success':
                                        $status_class = 'cws-success';
                                        break;
                                    case 'error':
                                        $status_class = 'cws-error';
                                        break;
                                    case 'warning':
                                        $status_class = 'cws-warning';
                                        break;
                                    default:
                                        $status_class = 'cws-info';
                                }
                                ?>
                                <span class="cws-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html(ucfirst($log->status)); ?>
                                </span>
                            </td>
                            <td data-colname="<?php _e('Message', 'codeswholesale-sync'); ?>">
                                <?php echo esc_html($log->message); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($total_pages > 1): ?>
                <div class="tablenav">
                    <div class="tablenav-pages">
                        <?php
                        $pagination_args = array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page,
                            'type' => 'plain'
                        );
                        
                        echo paginate_links($pagination_args);
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div> 