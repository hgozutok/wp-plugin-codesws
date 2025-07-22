<?php
/**
 * Import Products Admin View
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap cws-admin-page">
    <h1 class="wp-heading-inline"><?php _e('Import Products', 'codeswholesale-sync'); ?></h1>
    
    <?php if (!$api_client->is_connected()): ?>
        <div class="notice notice-error inline">
            <p>
                <strong><?php _e('API Connection Required', 'codeswholesale-sync'); ?></strong><br>
                <?php _e('Please configure your API settings before importing products.', 'codeswholesale-sync'); ?>
                <a href="<?php echo admin_url('admin.php?page=codeswholesale-sync-settings'); ?>" class="button button-primary">
                    <?php _e('Configure API', 'codeswholesale-sync'); ?>
                </a>
            </p>
        </div>
    <?php else: ?>
        
        <div class="cws-import-form">
            <h2><?php _e('Import Filters', 'codeswholesale-sync'); ?></h2>
            <p><?php _e('Use the filters below to specify which products to import from CodesWholesale.', 'codeswholesale-sync'); ?></p>
            
            <form id="cws-import-form">
                <?php wp_nonce_field('cws_admin_nonce', 'cws_nonce'); ?>
                
                <div class="cws-import-filters">
                    <!-- Platform Filter -->
                    <div class="cws-filter-group">
                        <label for="filter-platforms"><?php _e('Platforms', 'codeswholesale-sync'); ?></label>
                        <select name="filter_platforms[]" id="filter-platforms" multiple="multiple" class="cws-select2">
                            <?php if (!empty($platforms)): ?>
                                <?php foreach ($platforms as $platform): ?>
                                    <option value="<?php echo esc_attr($platform->getName()); ?>">
                                        <?php echo esc_html($platform->getName()); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="Steam">Steam</option>
                                <option value="Epic Games">Epic Games</option>
                                <option value="Origin">Origin</option>
                                <option value="Uplay">Uplay</option>
                            <?php endif; ?>
                        </select>
                        <p class="description"><?php _e('Leave empty to import from all platforms', 'codeswholesale-sync'); ?></p>
                    </div>
                    
                    <!-- Region Filter -->
                    <div class="cws-filter-group">
                        <label for="filter-regions"><?php _e('Regions', 'codeswholesale-sync'); ?></label>
                        <select name="filter_regions[]" id="filter-regions" multiple="multiple" class="cws-select2">
                            <?php if (!empty($regions)): ?>
                                <?php foreach ($regions as $region): ?>
                                    <option value="<?php echo esc_attr($region->getName()); ?>">
                                        <?php echo esc_html($region->getName()); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="WORLDWIDE">Worldwide</option>
                                <option value="EU">Europe</option>
                                <option value="US">United States</option>
                            <?php endif; ?>
                        </select>
                        <p class="description"><?php _e('Leave empty to import from all regions', 'codeswholesale-sync'); ?></p>
                    </div>
                    
                    <!-- Language Filter -->
                    <div class="cws-filter-group">
                        <label for="filter-languages"><?php _e('Languages', 'codeswholesale-sync'); ?></label>
                        <select name="filter_languages[]" id="filter-languages" multiple="multiple" class="cws-select2">
                            <?php if (!empty($languages)): ?>
                                <?php foreach ($languages as $language): ?>
                                    <option value="<?php echo esc_attr($language->getName()); ?>">
                                        <?php echo esc_html($language->getName()); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="Multilanguage">Multilanguage</option>
                                <option value="English">English</option>
                                <option value="Spanish">Spanish</option>
                                <option value="French">French</option>
                                <option value="German">German</option>
                            <?php endif; ?>
                        </select>
                        <p class="description"><?php _e('Leave empty to import all languages', 'codeswholesale-sync'); ?></p>
                    </div>
                    
                    <!-- Date Range Filter -->
                    <div class="cws-filter-group">
                        <label for="filter-days-ago"><?php _e('Products in Stock Since', 'codeswholesale-sync'); ?></label>
                        <select name="filter_days_ago" id="filter-days-ago">
                            <option value=""><?php _e('All Products', 'codeswholesale-sync'); ?></option>
                            <option value="7"><?php _e('Last 7 Days', 'codeswholesale-sync'); ?></option>
                            <option value="30"><?php _e('Last 30 Days', 'codeswholesale-sync'); ?></option>
                            <option value="60"><?php _e('Last 60 Days', 'codeswholesale-sync'); ?></option>
                            <option value="90"><?php _e('Last 90 Days', 'codeswholesale-sync'); ?></option>
                        </select>
                        <p class="description"><?php _e('Filter by when products were last in stock', 'codeswholesale-sync'); ?></p>
                    </div>
                    
                    <!-- Import Limit -->
                    <div class="cws-filter-group">
                        <label for="cws-import-limit"><?php _e('Import Limit', 'codeswholesale-sync'); ?></label>
                        <input type="number" name="import_limit" id="cws-import-limit" value="50" min="1" max="500" class="small-text" />
                        <p class="description"><?php _e('Maximum number of products to import (1-500)', 'codeswholesale-sync'); ?></p>
                    </div>
                    
                    <!-- Import Options -->
                    <div class="cws-filter-group">
                        <label><?php _e('Import Options', 'codeswholesale-sync'); ?></label>
                        <fieldset>
                            <label for="import-images">
                                <input type="checkbox" name="import_images" id="import-images" value="yes" checked />
                                <?php _e('Import product images', 'codeswholesale-sync'); ?>
                            </label><br>
                            
                            <label for="import-descriptions">
                                <input type="checkbox" name="import_descriptions" id="import-descriptions" value="yes" checked />
                                <?php _e('Import detailed descriptions', 'codeswholesale-sync'); ?>
                            </label><br>
                            
                            <label for="create-categories">
                                <input type="checkbox" name="create_categories" id="create-categories" value="yes" checked />
                                <?php _e('Create categories automatically', 'codeswholesale-sync'); ?>
                            </label><br>
                            
                            <label for="update-existing">
                                <input type="checkbox" name="update_existing" id="update-existing" value="yes" checked />
                                <?php _e('Update existing products', 'codeswholesale-sync'); ?>
                            </label>
                        </fieldset>
                    </div>
                </div>
                
                <div class="cws-import-actions">
                    <button type="submit" class="button button-primary button-large" id="cws-start-import">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Start Import', 'codeswholesale-sync'); ?>
                    </button>
                    
                    <button type="button" class="button button-secondary" id="cws-preview-import">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Preview Import', 'codeswholesale-sync'); ?>
                    </button>
                    
                    <div class="cws-import-preview">
                        <span class="cws-import-preview-text"></span>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Import Progress -->
        <div class="cws-import-progress" style="display: none;">
            <h3><?php _e('Import Progress', 'codeswholesale-sync'); ?></h3>
            <div class="cws-progress-bar">
                <div class="cws-progress-fill"></div>
            </div>
            <div class="cws-progress-text"><?php _e('Preparing import...', 'codeswholesale-sync'); ?></div>
            <div class="cws-progress-stats">
                <span class="cws-imported">0</span> <?php _e('imported', 'codeswholesale-sync'); ?> | 
                <span class="cws-updated">0</span> <?php _e('updated', 'codeswholesale-sync'); ?> | 
                <span class="cws-skipped">0</span> <?php _e('skipped', 'codeswholesale-sync'); ?> | 
                <span class="cws-errors">0</span> <?php _e('errors', 'codeswholesale-sync'); ?>
            </div>
        </div>
        
        <!-- Import Results -->
        <div class="cws-import-results" style="display: none;">
            <h3><?php _e('Import Results', 'codeswholesale-sync'); ?></h3>
            <div class="cws-results-content"></div>
        </div>
        
        <!-- Recent Imports -->
        <div class="cws-recent-imports">
            <h3><?php _e('Recent Import Activity', 'codeswholesale-sync'); ?></h3>
            <div class="cws-import-history">
                <?php $this->display_recent_imports(); ?>
            </div>
        </div>
        
    <?php endif; ?>
</div>

<?php
/**
 * Display recent imports from logs
 */
function display_recent_imports() {
    global $wpdb;
    
    $logs_table = $wpdb->prefix . 'cws_sync_log';
    $recent_imports = $wpdb->get_results($wpdb->prepare("
        SELECT * FROM $logs_table 
        WHERE operation_type = 'import' 
        AND status = 'success'
        ORDER BY created_at DESC 
        LIMIT %d
    ", 10));
    
    if (empty($recent_imports)) {
        echo '<p>' . __('No recent imports found.', 'codeswholesale-sync') . '</p>';
        return;
    }
    
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . __('Date', 'codeswholesale-sync') . '</th>';
    echo '<th>' . __('Products', 'codeswholesale-sync') . '</th>';
    echo '<th>' . __('Status', 'codeswholesale-sync') . '</th>';
    echo '<th>' . __('Details', 'codeswholesale-sync') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($recent_imports as $import) {
        echo '<tr>';
        echo '<td>' . esc_html(human_time_diff(strtotime($import->created_at), time()) . ' ago') . '</td>';
        echo '<td>' . esc_html($import->wc_product_id ? '1 product' : 'Bulk import') . '</td>';
        echo '<td><span class="cws-status cws-status-' . esc_attr($import->status) . '">' . ucfirst($import->status) . '</span></td>';
        echo '<td>' . esc_html(wp_trim_words($import->message, 10)) . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
}
?>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize select2
    if ($.fn.select2) {
        $('.cws-select2').select2({
            width: '100%',
            placeholder: '<?php _e('Select options...', 'codeswholesale-sync'); ?>'
        });
    }
    
    // Import form submission
    $('#cws-import-form').on('submit', function(e) {
        e.preventDefault();
        startImport();
    });
    
    // Preview import
    $('#cws-preview-import').on('click', function(e) {
        e.preventDefault();
        previewImport();
    });
    
    // Update import limit preview
    $('#cws-import-limit').on('change', function() {
        updateImportPreview();
    });
    
    function startImport() {
        var $form = $('#cws-import-form');
        var $button = $('#cws-start-import');
        var $progress = $('.cws-import-progress');
        var $results = $('.cws-import-results');
        
        // Collect form data
        var formData = {};
        $form.find('input, select').each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            
            if (!name || name === 'cws_nonce') return;
            
            if ($field.attr('type') === 'checkbox') {
                if ($field.is(':checked')) {
                    formData[name] = $field.val();
                }
            } else if ($field.is('select[multiple]')) {
                var values = $field.val();
                if (values && values.length > 0) {
                    formData[name] = values;
                }
            } else {
                var value = $field.val();
                if (value) {
                    formData[name] = value;
                }
            }
        });
        
        // Show progress
        $button.prop('disabled', true).html('<span class="spinner is-active"></span> <?php _e('Importing...', 'codeswholesale-sync'); ?>');
        $progress.show();
        $results.hide();
        
        // Reset progress
        $('.cws-progress-fill').css('width', '0%');
        $('.cws-progress-text').text('<?php _e('Starting import...', 'codeswholesale-sync'); ?>');
        resetProgressStats();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cws_import_products',
                nonce: $('#cws_nonce').val(),
                filters: formData
            },
            success: function(response) {
                if (response.success) {
                    $('.cws-progress-fill').css('width', '100%');
                    $('.cws-progress-text').text('<?php _e('Import completed!', 'codeswholesale-sync'); ?>');
                    
                    // Update stats
                    updateProgressStats(response.data);
                    
                    // Show results
                    showImportResults(response.data);
                    
                } else {
                    $('.cws-progress-text').text('<?php _e('Import failed', 'codeswholesale-sync'); ?>');
                    showImportError(response.data || '<?php _e('Import failed', 'codeswholesale-sync'); ?>');
                }
            },
            error: function() {
                $('.cws-progress-text').text('<?php _e('Import failed', 'codeswholesale-sync'); ?>');
                showImportError('<?php _e('Network error occurred', 'codeswholesale-sync'); ?>');
            },
            complete: function() {
                $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> <?php _e('Start Import', 'codeswholesale-sync'); ?>');
            }
        });
    }
    
    function previewImport() {
        // This would make a preview call to see how many products match the filters
        CWSAdmin.showNotice('<?php _e('Preview functionality coming soon', 'codeswholesale-sync'); ?>', 'info');
    }
    
    function updateImportPreview() {
        var limit = $('#cws-import-limit').val() || 50;
        $('.cws-import-preview-text').text('<?php _e('Will import up to', 'codeswholesale-sync'); ?> ' + limit + ' <?php _e('products', 'codeswholesale-sync'); ?>');
    }
    
    function resetProgressStats() {
        $('.cws-imported').text('0');
        $('.cws-updated').text('0');
        $('.cws-skipped').text('0');
        $('.cws-errors').text('0');
    }
    
    function updateProgressStats(data) {
        $('.cws-imported').text(data.imported || 0);
        $('.cws-updated').text(data.updated || 0);
        $('.cws-skipped').text(data.skipped || 0);
        $('.cws-errors').text(data.errors ? data.errors.length : 0);
    }
    
    function showImportResults(data) {
        var html = '<div class="notice notice-success"><p><strong><?php _e('Import completed successfully!', 'codeswholesale-sync'); ?></strong></p></div>';
        
        html += '<div class="cws-import-summary">';
        html += '<h4><?php _e('Import Summary', 'codeswholesale-sync'); ?></h4>';
        html += '<ul>';
        html += '<li><?php _e('Products imported:', 'codeswholesale-sync'); ?> <strong>' + (data.imported || 0) + '</strong></li>';
        html += '<li><?php _e('Products updated:', 'codeswholesale-sync'); ?> <strong>' + (data.updated || 0) + '</strong></li>';
        html += '<li><?php _e('Products skipped:', 'codeswholesale-sync'); ?> <strong>' + (data.skipped || 0) + '</strong></li>';
        
        if (data.errors && data.errors.length > 0) {
            html += '<li><?php _e('Errors:', 'codeswholesale-sync'); ?> <strong>' + data.errors.length + '</strong></li>';
            html += '</ul>';
            
            html += '<h4><?php _e('Errors', 'codeswholesale-sync'); ?></h4>';
            html += '<ul class="cws-error-list">';
            for (var i = 0; i < Math.min(data.errors.length, 10); i++) {
                html += '<li>' + data.errors[i] + '</li>';
            }
            if (data.errors.length > 10) {
                html += '<li><em>' + (data.errors.length - 10) + ' <?php _e('more errors...', 'codeswholesale-sync'); ?></em></li>';
            }
            html += '</ul>';
        } else {
            html += '</ul>';
        }
        
        html += '</div>';
        
        $('.cws-results-content').html(html);
        $('.cws-import-results').show();
        
        // Scroll to results
        $('html, body').animate({
            scrollTop: $('.cws-import-results').offset().top
        }, 500);
    }
    
    function showImportError(message) {
        var html = '<div class="notice notice-error"><p><strong><?php _e('Import failed:', 'codeswholesale-sync'); ?></strong> ' + message + '</p></div>';
        $('.cws-results-content').html(html);
        $('.cws-import-results').show();
    }
    
    // Initialize preview text
    updateImportPreview();
});
</script> 