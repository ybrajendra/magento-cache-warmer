<?php
/**
 * CloudCommerce Cache Warmer
 */
namespace CloudCommerce\CacheWarmer\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\StoreManagerInterface;
use CloudCommerce\CacheWarmer\Logger\Logger;
use Magento\Framework\App\CacheInterface;
use Magento\PageCache\Model\Cache\Type as PageCache;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\Http as HttpRequest;

/**
 * Cache Warmer Model
 * 
 * Handles cache warming operations by making HTTP requests to URLs
 */
class Warmer
{
    const XML_PATH_ENABLED = 'cloudcommerce_cachewarmer/general/enabled';
    const XML_PATH_CRON_TIME = 'cloudcommerce_cachewarmer/general/cron_time';

    private $scopeConfig;
    private $curl;
    private $storeManager;
    private $logger;
    private $cache;
    private $pageCache;
    private $httpContext;
    private $serializer;
    private $directoryList;
    private $httpRequest;
    private $identifierForSave;

    /**
     * Constructor
     * @param ScopeConfigInterface $scopeConfig
     * @param Curl $curl
     * @param StoreManagerInterface $storeManager
     * @param Logger $logger
     * @param CacheInterface $cache
     * @param PageCache $pageCache
     * @param HttpContext $httpContext
     * @param Json $serializer
     * @param DirectoryList $directoryList
     * @param HttpRequest $httpRequest
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Curl $curl,
        StoreManagerInterface $storeManager,
        Logger $logger,
        CacheInterface $cache,
        PageCache $pageCache,
        HttpContext $httpContext,
        Json $serializer,
        DirectoryList $directoryList,
        HttpRequest $httpRequest
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->curl = $curl;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
        $this->cache = $cache;
        $this->pageCache = $pageCache;
        $this->httpContext = $httpContext;
        $this->serializer = $serializer;
        $this->directoryList = $directoryList;
        $this->httpRequest = $httpRequest;
        // Initialize IdentifierForSave only if available (Magento 2.4.8+)
        if (class_exists('\Magento\PageCache\Model\App\Request\Http\IdentifierForSave')) {
            $this->identifierForSave = \Magento\Framework\App\ObjectManager::getInstance()
                ->get('\Magento\PageCache\Model\App\Request\Http\IdentifierForSave');
        } else {
            // Fallback to standard Identifier for older versions
            $this->identifierForSave = \Magento\Framework\App\ObjectManager::getInstance()
                ->get('\Magento\Framework\App\PageCache\Identifier');
        }
    }

    /**
     * Check if cache warmer is enabled
     */
    public function isEnabled(): bool
    {
        return (bool)$this->scopeConfig->getValue(self::XML_PATH_ENABLED);
    }

    /**
     * Warm a single URL
     */
    public function warmUrl($urlData, string $type = 'unknown'): array
    {
        $url = is_array($urlData) ? $urlData['url'] : $urlData;
        $type = is_array($urlData) ? $urlData['type'] : $type;
        
        if (!$this->isEnabled()) {
            return ['success' => false, 'message' => 'Cache warmer disabled'];
        }

        // Check if already cached
        $cacheStatus = $this->checkCacheStatus($url);
        if ($cacheStatus['cached']) {
            $this->logger->info("CACHED [{$type}] {$url} - skipping");
            return [
                'success' => true,
                'url' => $url,
                'type' => $type,
                'cached' => true,
                'message' => 'Already cached'
            ];
        }

        try {
            $startTime = microtime(true);
            $this->curl->setTimeout(30);
            $this->curl->setOption(CURLOPT_FOLLOWLOCATION, true);
            $this->curl->setOption(CURLOPT_SSL_VERIFYPEER, false);
            $this->curl->setOption(CURLOPT_SSL_VERIFYHOST, false);
            $this->curl->setOption(CURLOPT_USERAGENT, 'Magento Cache Warmer');
            $this->curl->get($url);
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $httpCode = $this->curl->getStatus();
            
            if ($httpCode >= 200 && $httpCode < 300) {
                $this->logger->info("SUCCESS [{$type}] {$url} ({$responseTime}ms)");
                return [
                    'success' => true,
                    'url' => $url,
                    'type' => $type,
                    'response_time' => $responseTime,
                    'http_code' => $httpCode
                ];
            } else {
                return [
                    'success' => false,
                    'url' => $url,
                    'type' => $type,
                    'http_code' => $httpCode,
                    'message' => "HTTP {$httpCode}"
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'url' => $url,
                'type' => $type,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check cache status of a URL using Magento cache interface
     */
    public function checkCacheStatus(string $url): array
    {
        try {
            // Generate cache key with version compatibility
            $cacheKey = $this->generateCacheKey($url);
            $this->logger->info("Checking cache for URL: {$url} with key: {$cacheKey}");
            
            // Check page cache first
            $pageData = $this->pageCache->load($cacheKey);
            if ($pageData !== false) {
                $this->logger->info("Found in page cache: {$cacheKey}");
                return [
                    'cached' => true,
                    'cache_type' => 'page_cache',
                    'cache_key' => $cacheKey
                ];
            }
            
            // Check file-based cache directly
            $fileExists = $this->checkFileCacheExists($cacheKey);
            if ($fileExists) {
                $this->logger->info("Found in file cache: {$url}");
                return [
                    'cached' => true,
                    'cache_type' => 'file_cache',
                    'cache_key' => $cacheKey
                ];
            }
            
            $this->logger->info("Not found in any cache: {$cacheKey}");
            return [
                'cached' => false,
                'cache_key' => $cacheKey
            ];
            
        } catch (\Exception $e) {
            $this->logger->error("Cache check error: " . $e->getMessage());
            return [
                'cached' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate cache key compatible with both Magento versions
     */
    private function generateCacheKey(string $url): string
    {
        $originalUri = $this->httpRequest->getUriString();
        $this->httpRequest->setUri($url);
        $cacheKey = $this->identifierForSave->getValue();
        $this->httpRequest->setUri($originalUri);
        return $cacheKey;
    }
    
    /**
     * Check if cache exists in file system (var/page_cache)
     */
    private function checkFileCacheExists(string $cacheKey): bool
    {
        try {
            $varDir = $this->directoryList->getPath('var');
            $pageCacheDir = $varDir . '/page_cache';
            
            if (!is_dir($pageCacheDir)) {
                return false;
            }
            
            $subdirs = glob($pageCacheDir . '/mage--*', GLOB_ONLYDIR);
            
            foreach ($subdirs as $subdir) {
                $cacheFile = $subdir . '/mage---' . $cacheKey;
                if (file_exists($cacheFile)) {
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            $this->logger->error("File cache check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Warm multiple URLs
     */
    public function warmUrls(array $urls): array
    {
        $results = [];
        $failed = [];
        
        foreach ($urls as $urlData) {
            $result = $this->warmUrl($urlData);
            if ($result['success']) {
                $results[] = $result;
            } else {
                $failed[] = $result;
            }
        }
        
        // Log failed URLs at the end
        foreach ($failed as $failure) {
            $this->logger->error("FAILED [{$failure['type']}] {$failure['url']} - {$failure['message']}");
        }
        
        return array_merge($results, $failed);
    }
}