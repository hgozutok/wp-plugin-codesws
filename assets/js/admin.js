/**
 * CodesWholesale Sync Admin JavaScript
 */

(function($) {
    'use strict';
    
    var CWSAdmin = {
        
        init: function() {
            this.bindEvents();
            this.initComponents();
        },
        
        bindEvents: function() {
            // Settings form
            $(document).on('click', '#cws-save-settings', this.saveSettings);
            $(document).on('click', '#cws-test-connection', this.testConnection);
            
            // Import functionality
            $(document).on('click', '#cws-start-import', this.startImport);
            $(document).on('change', '#cws-import-limit', this.updateImportLimit);
            
            // Product sync
            $(document).on('click', '.cws-sync-product', this.syncSingleProduct);
            
            // Logs functionality
            $(document).on('click', '#cws-clear-logs', this.clearLogs);
            $(document).on('click', '.cws-toggle-details', this.toggleLogDetails);
            
            // Dashboard refresh
            $(document).on('click', '#refresh-stats', this.refreshStats);
            $(document).on('click', '#manual-sync', this.manualSync);
        },
        
        initComponents: function() {
            // Initialize select2 if available
            if ($.fn.select2) {
                $('.cws-select2').select2({
                    width: '100%'
                });
            }
            
            // Initialize tooltips
            if ($.fn.tooltip) {
                $('.cws-tooltip').tooltip();
            }
            
            // Auto-refresh dashboard stats every 30 seconds
            if ($('.cws-admin-page').length && $('.cws-admin-page').attr('data-page') === 'dashboard') {
                setInterval(this.refreshStats, 30000);
            }
        },
        
        saveSettings: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $form = $button.closest('form');
            var formData = new FormData($form[0]);
            
            // Show loading state
            $button.prop('disabled', true).text(cwsAdmin.strings.saving || 'Saving...');
            
            // Collect all settings
            var settings = {};
            $form.find('[name^="cws_"]').each(function() {
                var $field = $(this);
                var name = $field.attr('name');
                var value;
                
                if ($field.attr('type') === 'checkbox') {
                    value = $field.is(':checked') ? 1 : 0;
                } else if ($field.is('select[multiple]')) {
                    value = $field.val() || [];
                } else {
                    value = $field.val();
                }
                
                settings[name] = value;
            });
            
            $.ajax({
                url: cwsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cws_save_settings',
                    nonce: cwsAdmin.nonce,
                    settings: settings
                },
                success: function(response) {
                    if (response.success) {
                        CWSAdmin.showNotice(response.data.message, 'success');
                    } else {
                        CWSAdmin.showNotice(response.data || 'Save failed', 'error');
                    }
                },
                error: function() {
                    CWSAdmin.showNotice('Network error occurred', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Save Settings');
                }
            });
        },
        
        testConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('.cws-connection-result');
            
            $button.prop('disabled', true).text(cwsAdmin.strings.testing || 'Testing...');
            $result.hide();
            
            $.ajax({
                url: cwsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cws_test_connection',
                    nonce: cwsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('cws-connection-error')
                               .addClass('cws-connection-success')
                               .html('<strong>Connection successful!</strong><br>' +
                                     'Account: ' + response.data.account_name + '<br>' +
                                     'Balance: ' + response.data.balance + '<br>' +
                                     'Credit: ' + response.data.credit)
                               .show();
                    } else {
                        $result.removeClass('cws-connection-success')
                               .addClass('cws-connection-error')
                               .html('<strong>Connection failed:</strong><br>' + response.data)
                               .show();
                    }
                },
                error: function() {
                    $result.removeClass('cws-connection-success')
                           .addClass('cws-connection-error')
                           .html('<strong>Network error occurred</strong>')
                           .show();
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },
        
        startImport: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $form = $button.closest('form');
            var $progress = $('.cws-import-progress');
            var $results = $('.cws-import-results');
            
            // Get form data
            var filters = {};
            $form.find('[name^="filter_"]').each(function() {
                var $field = $(this);
                var name = $field.attr('name').replace('filter_', '');
                var value = $field.val();
                
                if (value && value.length > 0) {
                    filters[name] = Array.isArray(value) ? value : [value];
                }
            });
            
            var limit = parseInt($('#cws-import-limit').val()) || 50;
            
            // Show progress
            $button.prop('disabled', true).text(cwsAdmin.strings.importing || 'Importing...');
            $progress.show();
            $results.hide();
            
            // Reset progress bar
            $('.cws-progress-fill').css('width', '0%');
            $('.cws-progress-text').text('Starting import...');
            
            $.ajax({
                url: cwsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cws_import_products',
                    nonce: cwsAdmin.nonce,
                    filters: filters,
                    limit: limit
                },
                success: function(response) {
                    if (response.success) {
                        $('.cws-progress-fill').css('width', '100%');
                        $('.cws-progress-text').text('Import completed!');
                        
                        var html = '<div class="notice notice-success"><p><strong>Import completed successfully!</strong></p></div>';
                        html += '<ul>';
                        html += '<li>Products imported: ' + (response.data.imported || 0) + '</li>';
                        html += '<li>Products updated: ' + (response.data.updated || 0) + '</li>';
                        html += '<li>Products skipped: ' + (response.data.skipped || 0) + '</li>';
                        if (response.data.errors && response.data.errors.length > 0) {
                            html += '<li>Errors: ' + response.data.errors.length + '</li>';
                        }
                        html += '</ul>';
                        
                        $results.html(html).show();
                    } else {
                        $('.cws-progress-text').text('Import failed');
                        $results.html('<div class="notice notice-error"><p>' + (response.data || 'Import failed') + '</p></div>').show();
                    }
                },
                error: function() {
                    $('.cws-progress-text').text('Import failed');
                    $results.html('<div class="notice notice-error"><p>Network error occurred</p></div>').show();
                },
                complete: function() {
                    $button.prop('disabled', false).text('Start Import');
                    setTimeout(function() {
                        $progress.hide();
                    }, 3000);
                }
            });
        },
        
        syncSingleProduct: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var productId = $button.data('product-id');
            
            $button.prop('disabled', true).text('Syncing...');
            
            $.ajax({
                url: cwsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cws_sync_single_product',
                    nonce: cwsAdmin.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        CWSAdmin.showNotice('Product synced successfully', 'success');
                        // Reload the page to show updated data
                        location.reload();
                    } else {
                        CWSAdmin.showNotice(response.data || 'Sync failed', 'error');
                    }
                },
                error: function() {
                    CWSAdmin.showNotice('Network error occurred', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Sync');
                }
            });
        },
        
        clearLogs: function(e) {
            e.preventDefault();
            
            if (!confirm(cwsAdmin.strings.confirmClearLogs || 'Are you sure you want to clear old logs?')) {
                return;
            }
            
            var $button = $(this);
            
            $button.prop('disabled', true).text('Clearing...');
            
            $.ajax({
                url: cwsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cws_clear_logs',
                    nonce: cwsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        CWSAdmin.showNotice(response.data.message, 'success');
                        location.reload();
                    } else {
                        CWSAdmin.showNotice(response.data || 'Clear failed', 'error');
                    }
                },
                error: function() {
                    CWSAdmin.showNotice('Network error occurred', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clear Old Logs');
                }
            });
        },
        
        toggleLogDetails: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $details = $button.closest('tr').next('.cws-log-details');
            
            if ($details.is(':visible')) {
                $details.hide();
                $button.text('Show Details');
            } else {
                $details.show();
                $button.text('Hide Details');
            }
        },
        
        refreshStats: function() {
            var $statsCard = $('.cws-stats-grid').closest('.cws-card');
            
            if ($statsCard.length === 0) return;
            
            $statsCard.addClass('cws-loading');
            
            $.ajax({
                url: cwsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cws_get_sync_status',
                    nonce: cwsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update stats if the structure is the same
                        if (response.data.stats) {
                            var stats = response.data.stats;
                            $('.cws-stats-grid .cws-stat-number').each(function(i) {
                                var $stat = $(this);
                                var value;
                                
                                switch(i) {
                                    case 0: value = stats.total_mapped; break;
                                    case 1: value = stats.synced_today; break;
                                    case 2: value = stats.errors_today; break;
                                }
                                
                                if (value !== undefined) {
                                    $stat.text(CWSAdmin.numberFormat(value));
                                }
                            });
                            
                            // Update last sync time
                            if (stats.last_sync) {
                                // You would need to calculate human time diff here
                                // For now, just indicate it was updated
                                $('.cws-stat-value').last().text('Just updated');
                            }
                        }
                    }
                },
                complete: function() {
                    $statsCard.removeClass('cws-loading');
                }
            });
        },
        
        manualSync: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            $button.prop('disabled', true).html('<span class="spinner is-active"></span> Syncing...');
            
            $.ajax({
                url: cwsAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cws_manual_sync',
                    nonce: cwsAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        CWSAdmin.showNotice('Manual sync completed successfully', 'success');
                        CWSAdmin.refreshStats();
                    } else {
                        CWSAdmin.showNotice(response.data || 'Sync failed', 'error');
                    }
                },
                error: function() {
                    CWSAdmin.showNotice('Network error occurred', 'error');
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Manual Sync');
                }
            });
        },
        
        updateImportLimit: function() {
            var value = parseInt($(this).val()) || 50;
            var $preview = $('.cws-import-preview');
            
            if ($preview.length) {
                $preview.text('Will import up to ' + value + ' products');
            }
        },
        
        showNotice: function(message, type) {
            type = type || 'info';
            
            // Remove existing notices
            $('.cws-admin-notice').remove();
            
            var noticeClass = 'notice notice-' + type + ' cws-admin-notice';
            var $notice = $('<div class="' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
            
            // Add dismiss button
            $notice.append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
            
            // Insert notice
            $('.cws-admin-page h1').after($notice);
            
            // Handle dismiss
            $notice.on('click', '.notice-dismiss', function() {
                $notice.fadeOut(300, function() {
                    $notice.remove();
                });
            });
            
            // Auto dismiss success messages
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(300, function() {
                        $notice.remove();
                    });
                }, 5000);
            }
        },
        
        numberFormat: function(num) {
            return new Intl.NumberFormat().format(num);
        },
        
        debounce: function(func, wait, immediate) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                var later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                var callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                if (callNow) func.apply(context, args);
            };
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        CWSAdmin.init();
    });
    
    // Make CWSAdmin globally available
    window.CWSAdmin = CWSAdmin;
    
})(jQuery); 