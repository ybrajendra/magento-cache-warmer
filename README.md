# CloudCommerce Cache Warmer - Magento 2 Page Cache Warming Extension

A powerful Magento 2 module that automatically warms page cache by pre-loading URLs to improve site performance, reduce page load times, and enhance user experience. Supports both Magento's built-in Full Page Cache and Varnish cache. Boost your eCommerce store's speed with intelligent cache warming.

## Features

- **Smart Cache Detection** - Checks if pages are already cached before warming
- **Varnish Cache Support** - Compatible with Varnish cache servers
- **Multi-Store Support** - Supports multiple store views with separate URL collections
- **Flexible Scheduling** - Configurable cron times from admin panel
- **URL Collection Caching** - Caches collected URLs for improved performance
- **Comprehensive Logging** - Detailed logs for monitoring and debugging
- **CLI Commands** - Manual cache warming via command line
- **Admin Configuration** - Easy setup through Magento admin panel

## Installation

### Via Composer (Recommended)
```bash
composer require cloudcommerce/cachewarmer
php bin/magento module:enable CloudCommerce_CacheWarmer
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

### Manual Installation
1. Copy module files to `app/code/CloudCommerce/CacheWarmer/`
2. Run setup commands:
```bash
php bin/magento module:enable CloudCommerce_CacheWarmer
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento cache:flush
```

## Configuration

Navigate to **Stores > Configuration > CloudCommerce > Cache Warmer**

### General Settings
- **Enable Cache Warmer** - Enable/disable the module
- **Cache Warmer Cron Time** - Schedule automatic cache warming:
  - 12:00 AM - 6:00 AM (hourly options)
  - Every Hour
  - Every 30 Minutes
  - Every 15 Minutes
  - Every 5 Minutes

### URL Configuration
- **Warm Category Pages** - Include category URLs
- **Warm Product Pages** - Include product URLs (limited to 5000 for performance)
- **Warm CMS Pages** - Include CMS page URLs
- **Custom URLs** - Add custom URLs (one per line)

## CLI Commands

### Warm All URLs
```bash
php bin/magento cloudcommerce:cache:warm
```

### Warm Specific Store
```bash
php bin/magento cloudcommerce:cache:warm --store-id=1
```

### Warm Single URL
```bash
php bin/magento cloudcommerce:cache:warm --url="https://example.com/page.html"
```

### Check Cache Status
```bash
php bin/magento cloudcommerce:cache:warm --check-cache
php bin/magento cloudcommerce:cache:warm --url="https://example.com/page.html" --check-cache
```

## Cache Management

The module creates a custom cache type **"CloudCommerce URL Collection"** that appears in:
**System > Cache Management**

This cache stores collected URLs and can be flushed independently from other cache types.

## How It Works

1. **URL Collection** - Gathers URLs from categories, products, CMS pages, and custom URLs
2. **Cache Check** - Uses Magento's cache identifier to check if pages are already cached
3. **Smart Warming** - Only makes HTTP requests to uncached pages
4. **Multi-Store** - Processes each store view separately with store-specific URLs
5. **Cron Automation** - Runs automatically based on configured schedule

## Performance Features

- **Cache-First Approach** - Checks cache before making HTTP requests
- **URL Collection Caching** - Avoids repeated database queries
- **Store-Specific Processing** - Separate cache keys per store
- **Efficient File Checking** - Direct cache file existence checks
- **Smart Skipping** - Skips already cached pages

## Logging

Logs are written to `var/log/cloudcommerce_cachewarmer.log` with detailed information:
- Cache warming results
- Cache status checks
- Error messages
- Performance metrics

## Technical Details

- **Cache Key Generation** - Uses Magento's `IdentifierForSave` for accurate cache keys
- **File Cache Detection** - Checks `var/page_cache/mage--*/mage---{cacheKey}` files
- **Multi-Store Support** - Store-specific URL collection and base URLs
- **Error Handling** - Comprehensive exception handling and logging

## Requirements

- Magento 2.4+
- PHP 8.0+
- Full Page Cache or Varnish cache enabled

## SEO Keywords

**Magento 2 Cache Warmer** | **Page Cache Warming** | **Magento Performance Optimization** | **Full Page Cache** | **Varnish Cache** | **Site Speed Optimization** | **Magento 2 Extension** | **Cache Management** | **Performance Module** | **Page Load Speed** | **Magento 2 Performance** | **Cache Preloading** | **Website Speed Boost** | **Magento Cache Extension** | **Performance Enhancement** | **Fast Loading Pages** | **Cache Optimization** | **Magento Speed Module** | **Page Cache Management** | **Performance Improvement** | **Cache Warming Tool**

## Support

For issues and feature requests, please check the module logs at `var/log/cloudcommerce_cachewarmer.log`
