<?php
/**
 * CloudCommerce Cache Warmer Log Handler
 */
namespace CloudCommerce\CacheWarmer\Logger;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

/**
 * Cache Warmer Log Handler
 */
class Handler extends Base
{
    protected $loggerType = Logger::INFO;
    protected $fileName = '/var/log/cache_warmer.log';
}