# CodesWholesale WooCommerce Sync Plugin

A powerful WordPress plugin that synchronizes WooCommerce products with the CodesWholesale API for automated digital game inventory management, pricing, and stock control.

## 🚀 Features

### Core Functionality
- **Automated Product Import** - Import thousands of digital games from CodesWholesale catalog
- **Real-time Price Synchronization** - Keep prices updated automatically with markup rules
- **Stock Level Management** - Auto-sync stock quantities and handle out-of-stock scenarios
- **Webhook Integration** - Real-time notifications for price changes, new products, and stock updates
- **Order Processing** - Automatic order fulfillment with digital key delivery

### Advanced Features
- **Smart Filtering** - Import products by platform, region, language, and date ranges
- **Markup Configuration** - Set percentage or fixed markup with optional charm pricing (.99)
- **Pre-order Support** - Handle pre-order products with automatic release date management
- **Multi-currency Support** - Automatic currency conversion when needed
- **Risk Assessment** - Built-in security checks for customer orders
- **Detailed Logging** - Comprehensive sync logs with error tracking and notifications

### Admin Interface
- **Intuitive Dashboard** - Overview of sync status, account balance, and recent activity
- **Easy Configuration** - Simple setup wizard and settings management
- **Product Mapping** - Visual mapping between CodesWholesale and WooCommerce products
- **Import Tools** - Bulk import with filtering and progress tracking
- **Log Viewer** - Detailed sync logs with filtering and search capabilities

## 📋 Requirements

- **WordPress**: 5.0 or higher
- **WooCommerce**: 4.0 or higher
- **PHP**: 7.4 or higher
- **CodesWholesale Account** with API access

## 🔧 Installation

### Method 1: WordPress Admin (Recommended)
1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and activate the plugin
4. Follow the setup wizard to configure your API credentials

### Method 2: Manual Installation
1. Extract the plugin files to `/wp-content/plugins/codeswholesale-sync/`
2. Run `composer install` in the plugin directory
3. Activate the plugin through WordPress Admin → Plugins
4. Configure your API settings

### Method 3: Development Setup
```bash
git clone https://github.com/your-repo/codeswholesale-sync.git
cd codeswholesale-sync
composer install
# Upload to your WordPress plugins directory
```

## ⚙️ Configuration

### 1. API Setup
1. Create a CodesWholesale account at [codeswholesale.com](https://codeswholesale.com)
2. Generate API credentials in your account dashboard
3. Go to WordPress Admin → CodesWholesale → Settings
4. Enter your Client ID and Client Secret
5. Test the connection

### 2. Import Configuration
```php
// Example import filters
$import_settings = [
    'platforms' => ['Steam', 'Epic Games'],
    'regions' => ['WORLDWIDE', 'EU'],
    'languages' => ['Multilanguage', 'English'],
    'markup_type' => 'percentage',
    'markup_value' => 20.00
];
```

### 3. Webhook Setup (Optional)
- Configure your webhook URL in CodesWholesale dashboard
- URL format: `https://yoursite.com/wp-json/codeswholesale/v1/webhook`
- Enable real-time synchronization for instant updates

## 📊 API Documentation

### CodesWholesale API Endpoints Used

| Endpoint | Purpose | Frequency |
|----------|---------|-----------|
| `/products` | Product listing with filters | Hourly/Daily |
| `/products/{id}` | Individual product details | On-demand |
| `/product-descriptions/{id}` | Rich product metadata | During import |
| `/platforms` | Available platforms | Daily |
| `/regions` | Available regions | Daily |
| `/languages` | Available languages | Daily |
| `/account` | Account balance/info | Before orders |
| `/orders` | Create/retrieve orders | Per purchase |
| **Webhooks** | Real-time notifications | Instant |

### Webhook Events Supported
- `stock_and_price_change` - Price and inventory updates
- `product_update` - Product details changed
- `new_product` - New products available
- `hidden_product` - Products removed from catalog
- `pre_order_assigned` - Pre-order codes available

## 🛠️ Usage

### Basic Product Import
```php
// Import up to 100 Steam games for worldwide region
$sync = CWS_Product_Sync::get_instance();
$result = $sync->import_products([
    'platform' => ['Steam'],
    'region' => ['WORLDWIDE'],
    'limit' => 100
]);
```

### Custom Pricing Rules
```php
// Set 25% markup with charm pricing
$settings = CWS_Settings::get_instance();
$settings->set('cws_price_markup_type', 'percentage');
$settings->set('cws_price_markup_value', '25');
$settings->set('cws_enable_charm_pricing', 'yes');
```

### Manual Synchronization
```php
// Sync specific product
$sync = CWS_Product_Sync::get_instance();
$result = $sync->sync_single_product($woocommerce_product_id);

// Full sync all mapped products
$result = $sync->sync_all_products();
```

## 🎯 Key Classes Overview

### Core Classes
- **`CWS_API_Client`** - CodesWholesale API integration
- **`CWS_Product_Sync`** - Product import and synchronization
- **`CWS_Price_Updater`** - Price management and markup calculation
- **`CWS_Stock_Manager`** - Inventory tracking and updates
- **`CWS_Webhook_Handler`** - Real-time webhook processing
- **`CWS_Scheduler`** - Cron job management

### Admin Classes
- **`CWS_Admin`** - Admin interface management
- **`CWS_Settings`** - Configuration management

## 📈 Performance Optimization

### Recommended Settings
- **Sync Interval**: Daily for most stores, hourly for high-volume
- **Import Batch Size**: 50-100 products per batch
- **Enable Webhooks**: For real-time updates
- **Cache Duration**: 1 hour for API responses

### Database Optimization
```sql
-- Recommended indexes for performance
CREATE INDEX idx_product_mapping_status ON wp_cws_product_mapping(sync_status);
CREATE INDEX idx_sync_log_operation ON wp_cws_sync_log(operation_type, created_at);
```

## 🔧 Troubleshooting

### Common Issues

**API Connection Failed**
- Verify Client ID and Client Secret are correct
- Check if your server can reach CodesWholesale API endpoints
- Ensure SSL/TLS is properly configured

**Products Not Importing**
- Check import filters - they might be too restrictive
- Verify account has sufficient balance
- Review sync logs for detailed error messages

**Sync Performance Issues**
- Reduce batch size in import settings
- Enable object caching (Redis/Memcached)
- Check server PHP memory limits

### Debug Mode
```php
// Enable debug logging
define('CWS_DEBUG', true);

// Check logs at /wp-content/uploads/cws-logs/
```

## 🔒 Security Features

- **API Token Security** - Encrypted storage of authentication tokens
- **Webhook Verification** - Signature validation for incoming webhooks
- **Permission Checks** - WordPress capability-based access control
- **Input Validation** - All user inputs sanitized and validated
- **SQL Injection Prevention** - Prepared statements for all database queries

## 📝 Changelog

### Version 1.0.0 (Current)
- Initial release
- Full CodesWholesale API integration
- Product import and synchronization
- Real-time webhook support
- Admin dashboard and settings
- Comprehensive logging system

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📄 License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

### Documentation
- [Plugin Documentation](https://github.com/your-repo/codeswholesale-sync/wiki)
- [CodesWholesale API Docs](https://codeswholesale.com/documentation/)
- [WooCommerce Developer Resources](https://woocommerce.com/developers/)

### Community Support
- [WordPress Plugin Support Forum](https://wordpress.org/support/plugin/codeswholesale-sync)
- [GitHub Issues](https://github.com/your-repo/codeswholesale-sync/issues)

### Professional Support
For premium support, custom development, or enterprise solutions, contact:
- Email: support@yourcompany.com
- Website: [yourcompany.com](https://yourcompany.com)

## 🎮 About CodesWholesale

CodesWholesale.com is a leading B2B platform for digital game distribution, offering:
- 2000+ digital products
- Global distribution network
- Automated supply chain
- Real-time API integration
- Competitive wholesale pricing

## ⭐ Show Your Support

If this plugin helps your business, please consider:
- ⭐ Starring this repository
- 💬 Leaving a review on WordPress.org
- 🐛 Reporting issues to help improve the plugin
- 💡 Suggesting new features

---

**Built with ❤️ for WordPress & WooCommerce communities** 