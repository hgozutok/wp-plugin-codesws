# CodesWholesale Plugin Testing Guide

This guide will help you test and validate the CodesWholesale WordPress plugin functionality.

## 🏃‍♂️ Quick Start - Running the Plugin

### Step 1: Install Dependencies

Since the plugin uses Composer for dependency management, you'll need to install the CodesWholesale SDK:

```bash
# Navigate to your plugin directory
cd /path/to/wordpress/wp-content/plugins/codeswholesale-sync/

# Install dependencies using Composer
composer install --no-dev --optimize-autoloader

# If composer is not installed:
curl -sS https://getcomposer.org/installer | php
php composer.phar install --no-dev --optimize-autoloader
```

### Step 2: Activate Plugin in WordPress

1. **Copy Plugin to WordPress**
   ```bash
   # Copy the entire plugin folder to your WordPress plugins directory
   cp -r codeswholesale-sync/ /path/to/wordpress/wp-content/plugins/
   ```

2. **Activate via WordPress Admin**
   ```
   WordPress Admin → Plugins → CodesWholesale WooCommerce Sync → Activate
   ```

3. **Check for Activation Success**
   - You should see "CodesWholesale" in the admin menu
   - No PHP errors should appear

## 🧪 Comprehensive Testing Checklist

### ✅ Phase 1: Basic Plugin Validation

**1.1 Plugin Activation Test**
- [ ] Plugin activates without errors
- [ ] Database tables are created (`wp_cws_product_mapping`, `wp_cws_sync_log`, `wp_cws_settings`)
- [ ] Admin menu appears: "CodesWholesale"
- [ ] Cron jobs are scheduled

**1.2 Admin Interface Test**
- [ ] Dashboard loads correctly
- [ ] Settings page displays
- [ ] Import page shows filters
- [ ] All admin pages load without PHP errors

**1.3 Settings Test**
- [ ] Can save basic settings
- [ ] Form validation works
- [ ] AJAX requests complete successfully

### ✅ Phase 2: API Integration Testing

**2.1 API Connection Test**

First, set up test credentials:

```php
// Use these TEST credentials for initial testing
Client ID: ff72ce315d1259e822f47d87d02d261e
Client Secret: $2a$10$E2jVWDADFA5gh6zlRVcrlOOX01Q/HJoT6hXuDMJxek.YEo.lkO2T6
Environment: Sandbox
```

**Test Steps:**
1. Go to `CodesWholesale → Settings`
2. Enter the test credentials above
3. Select "Sandbox" environment
4. Click "Test Connection"
5. **Expected Result**: Connection success with account details

**2.2 API Endpoints Test**

You can test individual API calls:

```php
// Add this to a temporary test file or use WordPress debug
$api_client = CWS_API_Client::get_instance();

// Test getting platforms
$platforms = $api_client->get_platforms();
var_dump($platforms);

// Test getting products (limited)
$products = $api_client->get_products(['limit' => 5]);
var_dump($products);

// Test account info
$account = $api_client->get_account();
var_dump($account);
```

### ✅ Phase 3: Product Import Testing

**3.1 Small Import Test**
1. Go to `CodesWholesale → Import Products`
2. Set import limit to `5`
3. Select a single platform (e.g., "Steam")
4. Click "Start Import"
5. **Expected Results:**
   - Progress bar shows activity
   - Products are created in WooCommerce
   - Import results show success count
   - No fatal PHP errors

**3.2 Import Validation**
- [ ] Products appear in WooCommerce Products list
- [ ] Product names are correct
- [ ] Prices are applied with markup
- [ ] Images are downloaded (if available)
- [ ] Stock quantities are set
- [ ] Categories are created
- [ ] Product attributes are set

**3.3 Database Validation**
Check that mapping data was created:

```sql
-- Check product mappings
SELECT * FROM wp_cws_product_mapping LIMIT 10;

-- Check sync logs
SELECT * FROM wp_cws_sync_log ORDER BY created_at DESC LIMIT 10;

-- Check settings
SELECT * FROM wp_cws_settings;
```

### ✅ Phase 4: Synchronization Testing

**4.1 Price Update Test**
1. Go to `CodesWholesale → Dashboard`
2. Click "Manual Sync" or trigger `CWS_Price_Updater`
3. Check that existing product prices update

**4.2 Stock Update Test**
1. Check current stock levels in WooCommerce
2. Trigger stock sync
3. Verify stock levels update (if different in API)

**4.3 Cron Job Test**
```bash
# Test WordPress cron manually
wp cron event run cws_sync_products

# Or trigger via URL
wget -q -O - "https://yoursite.com/wp-cron.php?doing_wp_cron"
```

### ✅ Phase 5: Error Handling Testing

**5.1 Invalid API Credentials**
- [ ] Enter wrong credentials
- [ ] Test connection should fail gracefully
- [ ] Error message should be user-friendly

**5.2 Network Error Simulation**
- [ ] Temporarily block API access
- [ ] Operations should fail gracefully with logs
- [ ] No fatal errors should occur

**5.3 Large Import Test**
- [ ] Try importing 100+ products
- [ ] Monitor for memory/timeout issues
- [ ] Check error handling for failed imports

## 🔍 Debugging and Troubleshooting

### Enable Debug Mode

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('CWS_DEBUG', true);
```

### Key Log Locations

1. **WordPress Debug Log**: `/wp-content/debug.log`
2. **Plugin Logs**: `/wp-content/uploads/cws-logs/api.log`
3. **Database Logs**: Check `wp_cws_sync_log` table
4. **Admin Interface**: `CodesWholesale → Sync Logs`

### Common Issues and Solutions

**Issue: "Class not found" errors**
```bash
Solution: Run composer install in plugin directory
cd wp-content/plugins/codeswholesale-sync
composer install --no-dev
```

**Issue: Database tables not created**
```php
Solution: Manually run activation hook
$plugin = new CodesWholesaleSync();
$plugin->activate();
```

**Issue: API connection fails**
```
Check: SSL certificates
Check: Firewall/security plugins
Check: Server can reach external APIs
Check: Credentials are correct
```

**Issue: Images not importing**
```
Check: WordPress uploads directory permissions
Check: PHP memory limits (increase to 256M+)
Check: max_execution_time (increase to 300s+)
```

## 📊 Performance Testing

### Memory Usage Test
```php
// Add to test import
echo "Memory usage: " . memory_get_usage(true) / 1024 / 1024 . " MB\n";
echo "Peak memory: " . memory_get_peak_usage(true) / 1024 / 1024 . " MB\n";
```

### Database Performance
```sql
-- Check table sizes
SELECT 
    table_name AS "Table",
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.tables 
WHERE table_schema = DATABASE()
AND table_name LIKE 'wp_cws_%';
```

### API Response Times
```php
// Time API calls
$start = microtime(true);
$products = $api_client->get_products();
$time = microtime(true) - $start;
echo "API call took: " . $time . " seconds\n";
```

## 🚀 Production Testing Checklist

Before going live:

- [ ] **Switch to Live Environment**
  - Update API credentials to production
  - Change environment from "Sandbox" to "Live"
  - Test connection with live credentials

- [ ] **Full Import Test**
  - Import 1000+ products successfully
  - Monitor system resources during import
  - Verify all data integrity

- [ ] **Webhook Setup**
  - Configure webhook URL in CodesWholesale dashboard
  - Test webhook reception and processing
  - Verify real-time updates work

- [ ] **Backup Strategy**
  - Backup database before large operations
  - Test restoration procedures
  - Set up automated backups

- [ ] **Monitoring Setup**
  - Monitor error logs regularly
  - Set up email notifications for critical errors
  - Monitor API usage and limits

## 🎯 Expected Test Results

### Successful Import Results
```
✅ Products imported: 47
✅ Products updated: 3
✅ Products skipped: 0
✅ Errors: 0

Dashboard shows:
- Connection: ✅ Connected (Live/Sandbox)
- Last Sync: Just now
- Products Mapped: 50
- Auto Sync: Enabled
```

### Healthy System Indicators
```
✅ API Response Time: < 2 seconds
✅ Import Speed: 10-20 products/minute
✅ Memory Usage: < 64MB per import batch
✅ Database Size: Growing appropriately
✅ Error Rate: < 1%
```

## 🆘 Getting Help

If tests fail or you encounter issues:

1. **Check Debug Logs**: Look for specific error messages
2. **Review Prerequisites**: Ensure all requirements are met
3. **Test Components**: Isolate which component is failing
4. **Check Documentation**: Review installation and API docs
5. **Create Issue Report**: Include logs, configuration, and error details

---

**Next Step**: Once testing is complete, you can begin using the plugin for production imports and synchronization! 