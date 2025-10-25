<?php
/**
 * CloudCommerce Cache Warmer URL Collector
 */
namespace CloudCommerce\CacheWarmer\Model;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use CloudCommerce\CacheWarmer\Model\Cache\Type\UrlCollection as UrlCollectionCache;
use Magento\Framework\Serialize\Serializer\Json;

/**
 * URL Collector
 * 
 * Collects URLs from various Magento entities for cache warming
 */
class UrlCollector
{
    const XML_PATH_WARM_CATEGORIES = 'cloudcommerce_cachewarmer/urls/warm_categories';
    const XML_PATH_WARM_PRODUCTS = 'cloudcommerce_cachewarmer/urls/warm_products';
    const XML_PATH_WARM_CMS = 'cloudcommerce_cachewarmer/urls/warm_cms';
    const XML_PATH_CUSTOM_URLS = 'cloudcommerce_cachewarmer/urls/custom_urls';

    private $categoryCollectionFactory;
    private $productCollectionFactory;
    private $pageCollectionFactory;
    private $scopeConfig;
    private $storeManager;
    private $urlCollectionCache;
    private $serializer;

    /**
     * Constructor
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param PageCollectionFactory $pageCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param UrlCollectionCache $urlCollectionCache
     * @param Json $serializer
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        PageCollectionFactory $pageCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        UrlCollectionCache $urlCollectionCache,
        Json $serializer
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->pageCollectionFactory = $pageCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->urlCollectionCache = $urlCollectionCache;
        $this->serializer = $serializer;
    }

    /**
     * Collect all URLs for warming
     */
    public function collectUrls($storeId = null): array
    {
        $store = $storeId ? $this->storeManager->getStore($storeId) : $this->storeManager->getStore();
        $cacheKey = 'url_collection_' . $store->getId();
        
        // Try to get from cache first
        $cachedUrls = $this->urlCollectionCache->load($cacheKey);
        if ($cachedUrls) {
            return $this->serializer->unserialize($cachedUrls);
        }
        
        // Generate URLs if not cached
        $urls = $this->generateUrls($store);
        
        // Save to cache
        $this->urlCollectionCache->save(
            $this->serializer->serialize($urls),
            $cacheKey,
            [UrlCollectionCache::CACHE_TAG]
        );
        
        return $urls;
    }
    
    /**
     * Generate URLs from database
     */
    private function generateUrls($store): array
    {
        $urls = [];
        $baseUrl = $this->getStoreBaseUrl($store);
        // $baseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, $store->isCurrentlySecure());

        // Category URLs
        if ($this->scopeConfig->getValue(self::XML_PATH_WARM_CATEGORIES)) {
            $urls = array_merge($urls, $this->getCategoryUrls($store->getId(), $baseUrl));
        }

        // Product URLs
        if ($this->scopeConfig->getValue(self::XML_PATH_WARM_PRODUCTS)) {
            $urls = array_merge($urls, $this->getProductUrls($store->getId(), $baseUrl));
        }

        // CMS Page URLs
        if ($this->scopeConfig->getValue(self::XML_PATH_WARM_CMS)) {
            $urls = array_merge($urls, $this->getCmsUrls($store->getId(), $baseUrl));
        }

        // Custom URLs
        $customUrls = $this->getCustomUrls($baseUrl);
        if (!empty($customUrls)) {
            $urls = array_merge($urls, $customUrls);
        }

        // Add home page URL
        $urls[] = [
            'url' => $baseUrl,
            'type' => 'home'
        ];

        return $urls;
    }

    /**
     * Get category URLs
     */
    private function getCategoryUrls(int $storeId, string $baseUrl): array
    {
        $urls = [];
        $store = $this->storeManager->getStore($storeId);
        $rootCategoryId = $store->getRootCategoryId();
        
        $collection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect(['url_key', 'url_path'])
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('level', ['gt' => 1])
            ->addAttributeToFilter('path', ['like' => "1/{$rootCategoryId}/%"])
            ->setStoreId($storeId);

        foreach ($collection as $category) {
            // Use url_path for hierarchical URLs, fallback to url_key
            $urlPath = $category->getUrlPath() ?: $category->getUrlKey();
            if ($urlPath) {
                $urls[] = [
                    'url' => $baseUrl . $urlPath . '.html',
                    'type' => 'category'
                ];
            }
        }

        return $urls;
    }

    /**
     * Get product URLs
     */
    private function getProductUrls(int $storeId, string $baseUrl): array
    {
        $urls = [];
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect('url_key')
            ->addAttributeToFilter('status', 1)
            ->addAttributeToFilter('visibility', ['in' => [2, 4]])
            ->setStoreId($storeId)
            ->setPageSize(5000); // Limit for performance

        foreach ($collection as $product) {
            if ($product->getUrlKey()) {
                $urls[] = [
                    'url' => $baseUrl . $product->getUrlKey() . '.html',
                    'type' => 'product'
                ];
            }
        }

        return $urls;
    }

    /**
     * Get CMS page URLs
     */
    private function getCmsUrls(int $storeId, string $baseUrl): array
    {
        $urls = [];
        $collection = $this->pageCollectionFactory->create()
            ->addFieldToFilter('is_active', 1)
            ->addStoreFilter($storeId);

        foreach ($collection as $page) {
            if ($page->getIdentifier() && $page->getIdentifier() !== 'home') {
                $urls[] = [
                    'url' => $baseUrl . $page->getIdentifier(),
                    'type' => 'cms'
                ];
            }
        }

        return $urls;
    }

    /**
     * Get custom URLs from configuration
     */
    private function getCustomUrls(string $baseUrl): array
    {
        $urls = [];
        $customUrls = $this->scopeConfig->getValue(self::XML_PATH_CUSTOM_URLS);
        
        if ($customUrls) {
            $lines = explode("\n", $customUrls);
            foreach ($lines as $line) {
                $url = trim($line);
                if (!empty($url)) {
                    $urls[] = [
                        'url' => $baseUrl . ltrim($url, '/'),
                        'type' => 'custom'
                    ];
                }
            }
        }

        return $urls;
    }

    /**
     * Get base URL with store code if enabled
     */
    private function getStoreBaseUrl($store): string
    {
        $baseUrl = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB, $store->isCurrentlySecure());
        
        // Check if "Add Store Code to URLs" is enabled
        $addStoreCodeToUrls = $this->scopeConfig->getValue(
            \Magento\Store\Model\Store::XML_PATH_STORE_IN_URL,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
        
        if ($addStoreCodeToUrls && $store->getCode() !== 'default') {
            $baseUrl .= $store->getCode() . '/';
        }
        
        return $baseUrl;
    }
}