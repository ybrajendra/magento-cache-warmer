<?php
/**
 * CloudCommerce Cache Warmer Cron Job
 */
namespace CloudCommerce\CacheWarmer\Cron;

use CloudCommerce\CacheWarmer\Model\Warmer;
use CloudCommerce\CacheWarmer\Model\UrlCollector;
use CloudCommerce\CacheWarmer\Logger\Logger;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Cache Warmer Cron Job
 */
class WarmCache
{
    private $warmer;
    private $urlCollector;
    private $logger;
    private $storeManager;

    /**
     * Constructor
     * @param Warmer $warmer
     * @param UrlCollector $urlCollector
     * @param Logger $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Warmer $warmer,
        UrlCollector $urlCollector,
        Logger $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->warmer = $warmer;
        $this->urlCollector = $urlCollector;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * Execute cache warming cron job
     */
    public function execute()
    {
        if (!$this->warmer->isEnabled()) {
            $this->logger->info('Cache warmer is disabled, skipping cron job');
            return;
        }

        $this->logger->info('Starting cache warmer cron job for all stores');
        
        $stores = $this->storeManager->getStores();
        $totalSuccess = 0;
        $totalFailure = 0;
        
        foreach ($stores as $store) {
            $this->logger->info('Warming cache for store: ' . $store->getName() . ' (ID: ' . $store->getId() . ')');
            
            $urls = $this->urlCollector->collectUrls($store->getId());
            
            if (empty($urls)) {
                $this->logger->info('No URLs found for store ' . $store->getId());
                continue;
            }

            $this->logger->info('Found ' . count($urls) . ' URLs for store ' . $store->getId());
            
            $results = $this->warmer->warmUrls($urls);
            
            $successCount = 0;
            $failureCount = 0;
            
            foreach ($results as $result) {
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }
            
            $totalSuccess += $successCount;
            $totalFailure += $failureCount;
            
            $this->logger->info("Store {$store->getId()} completed - Success: {$successCount}, Failed: {$failureCount}");
        }
        
        $this->logger->info("All stores cache warming completed - Total Success: {$totalSuccess}, Total Failed: {$totalFailure}");
    }
}