<?php
/**
 * Product Mapping Admin View
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
    <h1 class="wp-heading-inline"><?php _e('Product Mapping', 'codeswholesale-sync'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=cws-import'); ?>" class="page-title-action">
        <?php _e('Import Products', 'codeswholesale-sync'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <div class="cws-mapping-container">
        <?php if (empty($mappings)): ?>
            <div class="cws-no-data">
                <p><?php _e('No product mappings found.', 'codeswholesale-sync'); ?></p>
                <p><?php _e('Product mappings are created automatically when you import products from CodesWholesale.', 'codeswholesale-sync'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=cws-import'); ?>" class="button button-primary">
                    <?php _e('Import Products Now', 'codeswholesale-sync'); ?>
                </a>
            </div>
        <?php else: ?>
            <div class="cws-mapping-stats">
                <p><?php echo sprintf(__('Showing %d product mappings (%d total)', 'codeswholesale-sync'), count($mappings), $total_items); ?></p>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column column-primary">
                            <?php _e('CodesWholesale Product', 'codeswholesale-sync'); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php _e('WooCommerce Product', 'codeswholesale-sync'); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php _e('Status', 'codeswholesale-sync'); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php _e('Last Updated', 'codeswholesale-sync'); ?>
                        </th>
                        <th scope="col" class="manage-column">
                            <?php _e('Actions', 'codeswholesale-sync'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mappings as $mapping): ?>
                        <tr>
                            <td class="column-primary">
                                <strong><?php echo esc_html($mapping->cws_product_name); ?></strong>
                                <div class="row-actions">
                                    <span class="id">ID: <?php echo esc_html($mapping->cws_product_id); ?></span>
                                    <?php if (!empty($mapping->cws_product_sku)): ?>
                                        | <span class="sku">SKU: <?php echo esc_html($mapping->cws_product_sku); ?></span>
                                    <?php endif; ?>
                                </div>
                                <button type="button" class="toggle-row"><span class="screen-reader-text"><?php _e('Show more details', 'codeswholesale-sync'); ?></span></button>
                            </td>
                            <td data-colname="<?php _e('WooCommerce Product', 'codeswholesale-sync'); ?>">
                                <?php if ($mapping->wc_product_id && $mapping->wc_product_name): ?>
                                    <a href="<?php echo get_edit_post_link($mapping->wc_product_id); ?>" target="_blank">
                                        <?php echo esc_html($mapping->wc_product_name); ?>
                                    </a>
                                    <div class="row-actions">
                                        <span class="id">ID: <?php echo esc_html($mapping->wc_product_id); ?></span>
                                    </div>
                                <?php else: ?>
                                    <span class="cws-status cws-error"><?php _e('Not mapped', 'codeswholesale-sync'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-colname="<?php _e('Status', 'codeswholesale-sync'); ?>">
                                <?php 
                                $status_class = '';
                                $status_text = '';
                                
                                switch ($mapping->sync_status) {
                                    case 'active':
                                        $status_class = 'cws-success';
                                        $status_text = __('Active', 'codeswholesale-sync');
                                        break;
                                    case 'inactive':
                                        $status_class = 'cws-warning';
                                        $status_text = __('Inactive', 'codeswholesale-sync');
                                        break;
                                    case 'error':
                                        $status_class = 'cws-error';
                                        $status_text = __('Error', 'codeswholesale-sync');
                                        break;
                                    default:
                                        $status_class = 'cws-neutral';
                                        $status_text = __('Unknown', 'codeswholesale-sync');
                                }
                                ?>
                                <span class="cws-status <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                            </td>
                            <td data-colname="<?php _e('Last Updated', 'codeswholesale-sync'); ?>">
                                <?php 
                                if ($mapping->updated_at) {
                                    echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $mapping->updated_at));
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td data-colname="<?php _e('Actions', 'codeswholesale-sync'); ?>">
                                <div class="cws-mapping-actions">
                                    <?php if ($mapping->wc_product_id): ?>
                                        <a href="<?php echo get_edit_post_link($mapping->wc_product_id); ?>" 
                                           class="button button-small" target="_blank">
                                            <?php _e('Edit Product', 'codeswholesale-sync'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <button type="button" 
                                            class="button button-small cws-sync-mapping" 
                                            data-mapping-id="<?php echo esc_attr($mapping->id); ?>">
                                        <?php _e('Sync Now', 'codeswholesale-sync'); ?>
                                    </button>
                                    
                                    <button type="button" 
                                            class="button button-small button-link-delete cws-delete-mapping" 
                                            data-mapping-id="<?php echo esc_attr($mapping->id); ?>">
                                        <?php _e('Delete', 'codeswholesale-sync'); ?>
                                    </button>
                                </div>
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

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Handle sync mapping action
    $('.cws-sync-mapping').on('click', function() {
        var $button = $(this);
        var mappingId = $button.data('mapping-id');
        var originalText = $button.text();
        
        $button.prop('disabled', true).text('<?php esc_js(_e('Syncing...', 'codeswholesale-sync')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cws_sync_single_mapping',
                mapping_id: mappingId,
                nonce: '<?php echo wp_create_nonce('cws_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.text('<?php esc_js(_e('Synced!', 'codeswholesale-sync')); ?>');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    alert('<?php esc_js(_e('Sync failed: ', 'codeswholesale-sync')); ?>' + response.data);
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert('<?php esc_js(_e('Sync failed due to connection error.', 'codeswholesale-sync')); ?>');
                $button.prop('disabled', false).text(originalText);
            }
        });
    });
    
    // Handle delete mapping action
    $('.cws-delete-mapping').on('click', function() {
        var $button = $(this);
        var mappingId = $button.data('mapping-id');
        
        if (!confirm('<?php esc_js(_e('Are you sure you want to delete this mapping? This will not delete the WooCommerce product.', 'codeswholesale-sync')); ?>')) {
            return;
        }
        
        $button.prop('disabled', true).text('<?php esc_js(_e('Deleting...', 'codeswholesale-sync')); ?>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cws_delete_mapping',
                mapping_id: mappingId,
                nonce: '<?php echo wp_create_nonce('cws_admin_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.closest('tr').fadeOut(function() {
                        $(this).remove();
                    });
                } else {
                    alert('<?php esc_js(_e('Delete failed: ', 'codeswholesale-sync')); ?>' + response.data);
                    $button.prop('disabled', false).text('<?php esc_js(_e('Delete', 'codeswholesale-sync')); ?>');
                }
            },
            error: function() {
                alert('<?php esc_js(_e('Delete failed due to connection error.', 'codeswholesale-sync')); ?>');
                $button.prop('disabled', false).text('<?php esc_js(_e('Delete', 'codeswholesale-sync')); ?>');
            }
        });
    });
});
</script> 