<?php
/**
 * Settings Admin View
 *
 * @package CodesWholesaleSync
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap cws-admin-page">
    <h1 class="wp-heading-inline"><?php _e('CodesWholesale Sync Settings', 'codeswholesale-sync'); ?></h1>
    
    <form id="cws-settings-form" method="post">
        <?php wp_nonce_field('cws_admin_nonce', 'cws_nonce'); ?>
        
        <div class="cws-settings-form">
            <?php foreach ($formatted_settings as $section_key => $section): ?>
                <div class="cws-settings-section" id="section-<?php echo esc_attr($section_key); ?>">
                    <div class="cws-settings-section-header">
                        <h2><?php echo esc_html($section['title']); ?></h2>
                        <p><?php echo esc_html($section['description']); ?></p>
                    </div>
                    
                    <div class="cws-settings-section-body">
                        <?php foreach ($section['fields'] as $field_key => $field): ?>
                            <div class="cws-setting-field">
                                <?php $this->render_setting_field($field_key, $field); ?>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if ($section_key === 'api'): ?>
                            <div class="cws-setting-field">
                                <button type="button" class="button button-secondary" id="cws-test-connection">
                                    <?php _e('Test Connection', 'codeswholesale-sync'); ?>
                                </button>
                                <div class="cws-connection-result" style="display: none;"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <p class="submit">
            <button type="submit" class="button button-primary" id="cws-save-settings">
                <?php _e('Save Settings', 'codeswholesale-sync'); ?>
            </button>
        </p>
    </form>
</div>

<?php
/**
 * Render individual setting field
 */
function render_setting_field($field_key, $field) {
    $field_id = 'field-' . $field_key;
    $field_name = $field_key;
    $field_value = isset($field['value']) ? $field['value'] : '';
    
    echo '<label for="' . esc_attr($field_id) . '">' . esc_html($field['title']) . '</label>';
    
    switch ($field['type']) {
        case 'text':
            echo '<input type="text" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" class="regular-text" />';
            break;
            
        case 'password':
            echo '<input type="password" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" class="regular-text" />';
            break;
            
        case 'email':
            echo '<input type="email" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" class="regular-text" />';
            break;
            
        case 'number':
            $min = isset($field['min']) ? $field['min'] : '';
            $max = isset($field['max']) ? $field['max'] : '';
            $step = isset($field['step']) ? $field['step'] : '1';
            echo '<input type="number" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="' . esc_attr($field_value) . '" class="small-text" min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" step="' . esc_attr($step) . '" />';
            break;
            
        case 'select':
            echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '">';
            foreach ($field['options'] as $option_value => $option_label) {
                $selected = selected($field_value, $option_value, false);
                echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
            }
            echo '</select>';
            break;
            
        case 'multiselect':
            $selected_values = is_array($field_value) ? $field_value : array();
            echo '<select id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '[]" multiple="multiple" class="cws-select2">';
            if (isset($field['options'])) {
                foreach ($field['options'] as $option_value => $option_label) {
                    $selected = in_array($option_value, $selected_values) ? 'selected="selected"' : '';
                    echo '<option value="' . esc_attr($option_value) . '" ' . $selected . '>' . esc_html($option_label) . '</option>';
                }
            }
            echo '</select>';
            break;
            
        case 'checkbox':
            $checked = checked($field_value, 'yes', false);
            echo '<input type="checkbox" id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" value="yes" ' . $checked . ' />';
            echo '<span class="description">' . esc_html($field['description']) . '</span>';
            return; // Skip description below
            
        case 'textarea':
            $rows = isset($field['rows']) ? $field['rows'] : 5;
            echo '<textarea id="' . esc_attr($field_id) . '" name="' . esc_attr($field_name) . '" rows="' . esc_attr($rows) . '" class="large-text">' . esc_textarea($field_value) . '</textarea>';
            break;
    }
    
    if (isset($field['description']) && $field['type'] !== 'checkbox') {
        echo '<p class="cws-setting-description">' . esc_html($field['description']) . '</p>';
    }
}
?>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Settings form submission
    $('#cws-settings-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $('#cws-save-settings');
        
        $button.prop('disabled', true).text('<?php _e('Saving...', 'codeswholesale-sync'); ?>');
        
        // Collect form data
        var formData = {};
        $form.find('[name^="cws_"]').each(function() {
            var $field = $(this);
            var name = $field.attr('name');
            
            if ($field.attr('type') === 'checkbox') {
                formData[name] = $field.is(':checked') ? 'yes' : 'no';
            } else if ($field.is('select[multiple]')) {
                formData[name] = $field.val() || [];
            } else {
                formData[name] = $field.val();
            }
        });
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cws_save_settings',
                nonce: $('#cws_nonce').val(),
                settings: formData
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
                $button.prop('disabled', false).text('<?php _e('Save Settings', 'codeswholesale-sync'); ?>');
            }
        });
    });
    
    // Test connection
    $('#cws-test-connection').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $result = $('.cws-connection-result');
        
        $button.prop('disabled', true).text('<?php _e('Testing...', 'codeswholesale-sync'); ?>');
        $result.hide();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cws_test_connection',
                nonce: $('#cws_nonce').val()
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data.data || response.data;
                    $result.removeClass('cws-connection-error')
                           .addClass('cws-connection-success')
                           .html('<strong><?php _e('Connection successful!', 'codeswholesale-sync'); ?></strong><br>' +
                                 '<?php _e('Account:', 'codeswholesale-sync'); ?> ' + data.email + '<br>' +
                                 '<?php _e('Balance:', 'codeswholesale-sync'); ?> ' + data.current_balance + '<br>' +
                                 '<?php _e('Credit:', 'codeswholesale-sync'); ?> ' + data.current_credit)
                           .show();
                } else {
                    var errorMessage = response.data && response.data.message ? response.data.message : 'Unknown error occurred';
                    $result.removeClass('cws-connection-success')
                           .addClass('cws-connection-error')
                           .html('<strong><?php _e('Connection failed:', 'codeswholesale-sync'); ?></strong><br>' + errorMessage)
                           .show();
                }
            },
            error: function() {
                $result.removeClass('cws-connection-success')
                       .addClass('cws-connection-error')
                       .html('<strong><?php _e('Network error occurred', 'codeswholesale-sync'); ?></strong>')
                       .show();
            },
            complete: function() {
                $button.prop('disabled', false).text('<?php _e('Test Connection', 'codeswholesale-sync'); ?>');
            }
        });
    });
    
    // Initialize select2 if available
    if ($.fn.select2) {
        $('.cws-select2').select2({
            width: '100%',
            placeholder: '<?php _e('Select options...', 'codeswholesale-sync'); ?>'
        });
    }
});
</script> 