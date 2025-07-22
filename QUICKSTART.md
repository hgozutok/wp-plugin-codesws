# 🚀 QuickStart - Deploy & Run CodesWholesale Plugin

## Immediate Deployment (5 minutes)

### Step 1: Package the Plugin

```bash
# Create a deployable ZIP file (run this on your server/local environment with PHP)
zip -r codeswholesale-sync.zip . -x "*.git*" "node_modules/*" "*.DS_Store"
```

### Step 2: Deploy to WordPress

1. **Upload Plugin**
   - WordPress Admin → Plugins → Add New → Upload Plugin
   - Upload the ZIP file
   - Click "Activate Plugin"

2. **Install Dependencies** (SSH required)
   ```bash
   cd /path/to/wp-content/plugins/codeswholesale-sync/
   composer install --no-dev --optimize-autoloader
   ```

### Step 3: Configure & Test (2 minutes)

1. **Initial Setup**
   - Go to: `CodesWholesale → Settings`
   - Enter these **TEST credentials**:
     ```
     Environment: Sandbox
     Client ID: ff72ce315d1259e822f47d87d02d261e
     Client Secret: $2a$10$E2jVWDADFA5gh6zlRVcrlOOX01Q/HJoT6hXuDMJxek.YEo.lkO2T6
     ```
   - Click "Test Connection" → Should show "✅ Connected"

2. **Test Import**
   - Go to: `CodesWholesale → Import Products`
   - Set limit to `5` products
   - Select platform: `Steam`
   - Click "Start Import"
   - **Expected:** 5 products imported successfully

## Alternative: Manual Setup (if Composer isn't available)

If you can't run Composer, you can manually download the CodesWholesale SDK:

1. **Download SDK**
   ```bash
   # Create vendor directory
   mkdir -p vendor/codeswholesale
   
   # Download from GitHub
   wget -O codeswholesale-sdk.zip https://github.com/youailu1/codeswholesale-sdk-php/archive/master.zip
   unzip codeswholesale-sdk.zip -d vendor/codeswholesale/
   ```

2. **Update Autoloader**
   Edit `codeswholesale-sync.php` line 45:
   ```php
   // Replace this:
   if (file_exists(CWS_PLUGIN_PATH . 'vendor/autoload.php')) {
       require_once CWS_PLUGIN_PATH . 'vendor/autoload.php';
   }
   
   // With manual include:
   require_once CWS_PLUGIN_PATH . 'vendor/codeswholesale/codeswholesale-sdk-php-master/src/CodesWholesale/CodesWholesale.php';
   ```

## 🎯 What You Should See

### After Activation:
- ✅ New menu item "CodesWholesale" in WordPress admin
- ✅ No PHP errors in WordPress debug log
- ✅ Database tables created (`wp_cws_*`)

### After Configuration:
- ✅ "Connected to CodesWholesale API" message
- ✅ Account balance and details displayed
- ✅ Dashboard shows system status

### After Test Import:
- ✅ Products appear in WooCommerce → Products
- ✅ Products have prices (with markup applied)
- ✅ Import logs show success entries
- ✅ Categories created automatically

## 🔧 Troubleshooting Quick Fixes

**Plugin won't activate:**
```php
// Add to wp-config.php
ini_set('memory_limit', '256M');
define('WP_DEBUG', true);
```

**"Class not found" errors:**
```bash
# Install dependencies
composer install --no-dev
# OR manually download SDK (see above)
```

**Connection fails:**
```
1. Check internet connectivity
2. Verify test credentials are correct
3. Try switching to different environment
4. Check WordPress/server firewall settings
```

**Import fails:**
```php
// Add to wp-config.php
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
```

## 📱 Mobile Testing

Test the admin interface on mobile:
- ✅ Dashboard responsive design
- ✅ Settings form works on mobile
- ✅ Import progress shows correctly

## ⚡ Performance Verification

Run these checks:
```php
// Check memory usage during import
echo "Memory: " . memory_get_usage(true) / 1024 / 1024 . " MB";

// Time API calls
$start = microtime(true);
$result = $api_client->get_products();
echo "API time: " . (microtime(true) - $start) . "s";
```

## 🚀 Ready for Production?

Once testing is successful:

1. **Get Live Credentials**
   - Login to CodesWholesale
   - Generate production API keys
   - Switch environment to "Live"

2. **Configure Webhooks** (Optional)
   - URL: `https://yoursite.com/wp-json/codeswholesale/v1/webhook`
   - Configure in CodesWholesale dashboard

3. **Enable Auto-Sync**
   - Set sync interval (hourly/daily)
   - Enable stock synchronization
   - Configure notifications

4. **Import Your Products**
   - Set appropriate filters
   - Start with small batches (50-100)
   - Monitor logs and performance

---

**🎉 You're Ready to Go!** The plugin should now be fully functional and ready to sync thousands of products from CodesWholesale to your WooCommerce store. 