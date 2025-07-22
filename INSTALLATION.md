# CodesWholesale WooCommerce Sync Plugin - Installation Guide

This guide will walk you through the complete installation and setup process for the CodesWholesale WooCommerce Sync Plugin.

## 📋 Prerequisites

Before installing the plugin, ensure your server meets these requirements:

### Server Requirements
- **WordPress**: 5.0 or higher
- **WooCommerce**: 4.0 or higher  
- **PHP**: 7.4 or higher (8.0+ recommended)
- **MySQL**: 5.6 or higher
- **SSL Certificate**: Required for API communication
- **cURL Extension**: Enabled for API requests
- **JSON Extension**: Enabled for API data processing

### CodesWholesale Account
- Active CodesWholesale account
- API access enabled
- Client ID and Client Secret generated

## 🚀 Installation Methods

### Method 1: WordPress Admin Upload (Recommended)

1. **Download the Plugin**
   - Download the plugin ZIP file
   - Ensure all dependencies are included

2. **Upload via WordPress Admin**
   ```
   WordPress Admin → Plugins → Add New → Upload Plugin
   ```
   - Choose the ZIP file
   - Click "Install Now"
   - Click "Activate Plugin"

### Method 2: Manual FTP Upload

1. **Extract Plugin Files**
   ```bash
   unzip codeswholesale-sync.zip
   ```

2. **Upload via FTP**
   - Upload the `codeswholesale-sync` folder to `/wp-content/plugins/`
   - Set proper file permissions (644 for files, 755 for directories)

3. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Find "CodesWholesale WooCommerce Sync"
   - Click "Activate"

### Method 3: Development Installation

1. **Clone Repository**
   ```bash
   cd /path/to/wordpress/wp-content/plugins/
   git clone https://github.com/your-repo/codeswholesale-sync.git
   cd codeswholesale-sync
   ```

2. **Install Dependencies**
   ```bash
   # Install Composer (if not already installed)
   curl -sS https://getcomposer.org/installer | php
   mv composer.phar /usr/local/bin/composer
   
   # Install plugin dependencies
   composer install --no-dev --optimize-autoloader
   ```

3. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Activate the plugin

## ⚙️ Initial Configuration

### Step 1: Generate API Credentials

1. **Login to CodesWholesale**
   - Visit [CodesWholesale Dashboard](https://app.codeswholesale.com/)
   - Login with your account credentials

2. **Generate API Keys**
   ```
   Account Menu → API → Generate Client Credentials
   ```
   - Copy your `Client ID`
   - Copy your `Client Secret` (shown only once!)
   - Save credentials securely

### Step 2: Configure Plugin Settings

1. **Access Plugin Settings**
   ```
   WordPress Admin → CodesWholesale → Settings
   ```

2. **API Configuration**
   ```
   Environment: Sandbox (for testing) or Live (for production)
   Client ID: [Your Client ID]
   Client Secret: [Your Client Secret]
   ```

3. **Test Connection**
   - Click "Test Connection" button
   - Verify successful connection
   - Check account balance and details

### Step 3: Configure Synchronization

1. **Sync Settings**
   ```
   ✅ Enable Automatic Sync
   Sync Interval: Daily (recommended to start)
   ```

2. **Import Filters** (Optional)
   ```
   Platforms: Steam, Epic Games, etc.
   Regions: Worldwide, EU, US, etc.
   Languages: Multilanguage, English, etc.
   ```

3. **Pricing Configuration**
   ```
   Markup Type: Percentage
   Markup Value: 20% (adjust as needed)
   ☐ Enable Charm Pricing (.99 endings)
   ```

4. **Inventory Settings**
   ```
   ✅ Enable Stock Sync
   Low Stock Threshold: 5 units
   Out of Stock Action: Mark as Out of Stock
   ```

5. **Notifications**
   ```
   Notification Email: your-email@domain.com
   ✅ Sync Error Notifications
   ✅ Low Balance Notifications
   ```

### Step 4: Initial Product Import

1. **Go to Import Page**
   ```
   WordPress Admin → CodesWholesale → Import Products
   ```

2. **Set Import Filters**
   - Choose desired platforms, regions, languages
   - Set import limit (start with 50-100 products)
   - Enable import options (images, descriptions, categories)

3. **Start Import**
   - Click "Start Import"
   - Monitor progress
   - Review results

## 🔧 Advanced Configuration

### Webhook Setup (Optional but Recommended)

1. **Configure Webhook URL**
   ```
   CodesWholesale Dashboard → API → Webhook Settings
   URL: https://yoursite.com/wp-json/codeswholesale/v1/webhook
   ```

2. **Test Webhook**
   - Send test notification from CodesWholesale
   - Check WordPress logs for successful receipt
   - Verify real-time updates are working

### Cron Job Configuration

The plugin uses WordPress cron system. For better reliability, set up server-level cron:

```bash
# Add to crontab (optional)
*/30 * * * * wget -q -O - https://yoursite.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1
```

### Performance Optimization

1. **Enable Object Caching**
   ```php
   // wp-config.php
   define('WP_CACHE', true);
   ```

2. **Database Optimization**
   ```sql
   -- Add indexes for better performance
   CREATE INDEX idx_cws_product_sync ON wp_cws_product_mapping(sync_status, last_sync);
   CREATE INDEX idx_cws_logs_date ON wp_cws_sync_log(operation_type, created_at);
   ```

3. **Increase PHP Limits** (if needed)
   ```php
   // wp-config.php or .htaccess
   ini_set('memory_limit', '256M');
   ini_set('max_execution_time', 300);
   ```

## 🔍 Verification & Testing

### Test Basic Functionality

1. **Dashboard Check**
   - Visit CodesWholesale → Dashboard
   - Verify connection status shows "Connected"
   - Check sync statistics

2. **Import Test**
   - Import a few test products
   - Verify products appear in WooCommerce
   - Check product details, images, pricing

3. **Sync Test**
   - Manually trigger sync from dashboard
   - Monitor sync logs
   - Verify prices and stock update correctly

### Production Checklist

- [ ] Switch from Sandbox to Live environment
- [ ] Update API credentials to live keys
- [ ] Test with real product import
- [ ] Configure webhook URL
- [ ] Set up monitoring and alerts
- [ ] Backup database before going live
- [ ] Test order processing flow

## 🚨 Troubleshooting

### Common Issues

**Plugin Won't Activate**
```
Error: Missing dependencies
Solution: Run `composer install` in plugin directory
```

**API Connection Failed**
```
Check: SSL certificate validity
Check: Server can reach CodesWholesale API
Check: Correct API credentials
Check: Firewall/security plugin blocking requests
```

**Products Not Importing**
```
Check: Import filters not too restrictive
Check: Account has sufficient balance
Check: API rate limits not exceeded
Review: Sync logs for detailed errors
```

**Images Not Downloading**
```
Check: WordPress upload directory permissions
Check: PHP memory/execution time limits
Check: Server can download from external URLs
Enable: WordPress media library cleanup
```

**Sync Performance Issues**
```
Reduce: Import batch sizes
Increase: PHP memory limits
Enable: Object caching (Redis/Memcached)
Optimize: Database with indexes
```

### Debug Mode

Enable debug logging:
```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('CWS_DEBUG', true);
```

Check logs at:
- `/wp-content/debug.log` (WordPress errors)
- `/wp-content/uploads/cws-logs/` (Plugin logs)

### Support Resources

- **Plugin Logs**: CodesWholesale → Sync Logs
- **WordPress Debug**: `/wp-content/debug.log`
- **Server Error Logs**: Check hosting control panel
- **API Documentation**: [CodesWholesale Docs](https://codeswholesale.com/documentation/)

## 📊 Monitoring & Maintenance

### Regular Maintenance Tasks

1. **Weekly**
   - Review sync logs for errors
   - Check product import statistics
   - Monitor account balance

2. **Monthly**
   - Clean up old log entries
   - Review pricing markup settings
   - Update plugin if newer version available

3. **Quarterly**
   - Database optimization
   - Performance review
   - Security audit

### Key Metrics to Monitor

- **Sync Success Rate**: Should be >95%
- **API Response Times**: Should be <2 seconds
- **Product Import Rate**: Consistent daily imports
- **Error Frequency**: Should be minimal
- **Account Balance**: Sufficient for operations

## 🔐 Security Best Practices

1. **API Security**
   - Keep API credentials secure and encrypted
   - Use environment variables for sensitive data
   - Regularly rotate API keys

2. **WordPress Security**
   - Keep WordPress and plugins updated
   - Use strong admin passwords
   - Limit admin access to trusted users
   - Enable two-factor authentication

3. **Server Security**
   - Use SSL/HTTPS for all connections
   - Keep server software updated
   - Configure proper file permissions
   - Use security plugins and monitoring

## 📞 Getting Help

If you encounter issues during installation or setup:

1. **Check Documentation**: Review this guide and README.md
2. **Review Logs**: Check plugin and WordPress logs
3. **Search Issues**: Look for similar problems in GitHub issues
4. **Contact Support**: Reach out with detailed error information

---

**Next Steps**: After successful installation, review the [User Guide](README.md#usage) for detailed operational instructions. 