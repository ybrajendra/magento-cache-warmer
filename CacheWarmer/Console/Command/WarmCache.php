<?php
/**
 * CloudCommerce Cache Warmer CLI Command
 */
namespace CloudCommerce\CacheWarmer\Console\Command;

use CloudCommerce\CacheWarmer\Model\Warmer;
use CloudCommerce\CacheWarmer\Model\UrlCollector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

/**
 * CLI Command for cache warming
 */
class WarmCache extends Command
{
    private $warmer;
    private $urlCollector;

    /**
     * Constructor
     * @param Warmer $warmer
     * @param UrlCollector $urlCollector
     */
    public function __construct(
        Warmer $warmer,
        UrlCollector $urlCollector
    ) {
        $this->warmer = $warmer;
        $this->urlCollector = $urlCollector;
        parent::__construct();
    }

    /**
     * Configure command options and description
     */
    protected function configure()
    {
        $this->setName('cloudcommerce:cache:warm')
            ->setDescription('Warm cache by requesting URLs')
            ->addOption(
                'store-id',
                's',
                InputOption::VALUE_OPTIONAL,
                'Store ID to warm cache for'
            )
            ->addOption(
                'url',
                'u',
                InputOption::VALUE_OPTIONAL,
                'Single URL to warm'
            )
            ->addOption(
                'check-cache',
                'c',
                InputOption::VALUE_NONE,
                'Check cache status without warming'
            )
            ->addOption(
                'smart-warm',
                null,
                InputOption::VALUE_NONE,
                'Only warm URLs that are not cached'
            );
    }

    /**
     * Execute the command
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int Command exit code (0 for success, non-zero for failure)
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->warmer->isEnabled()) {
            $output->writeln('<error>Cache warmer is disabled in configuration</error>');
            return Command::FAILURE;
        }

        $storeId = $input->getOption('store-id');
        $singleUrl = $input->getOption('url');
        $checkCache = $input->getOption('check-cache');

        if ($singleUrl) {
            if ($checkCache) {
                $output->writeln('<info>Checking cache status for: ' . $singleUrl . '</info>');
                $status = $this->warmer->checkCacheStatus($singleUrl);
                
                if ($status['cached']) {
                    $output->writeln('<info>✓ Cached</info>');
                } else {
                    $output->writeln('<comment>✗ Not cached or expired</comment>');
                }
                
                return Command::SUCCESS;
            }
            $output->writeln('<info>Warming single URL: ' . $singleUrl . '</info>');
            $result = $this->warmer->warmUrl($singleUrl, 'manual');
            
            if ($result['success']) {
                $message = isset($result['response_time']) ? '(' . $result['response_time'] . 'ms)' : '(cached)';
                $output->writeln('<info>✓ Success ' . $message . '</info>');
            } else {
                $output->writeln('<error>✗ Failed: ' . $result['message'] . '</error>');
            }
            
            return $result['success'] ? Command::SUCCESS : Command::FAILURE;
        }

        $output->writeln('<info>Collecting URLs...</info>');
        $urls = $this->urlCollector->collectUrls($storeId);
        
        if (empty($urls)) {
            $output->writeln('<comment>No URLs found to warm</comment>');
            return Command::SUCCESS;
        }

        $output->writeln('<info>Found ' . count($urls) . ' URLs to warm</info>');
        
        if ($checkCache) {
            $output->writeln('<info>Checking cache status for all URLs...</info>');
            $cachedCount = 0;
            $uncachedCount = 0;
            
            foreach ($urls as $urlData) {
                $status = $this->warmer->checkCacheStatus($urlData['url']);
                if ($status['cached']) {
                    $cachedCount++;
                } else {
                    $uncachedCount++;
                    $output->writeln('<comment>Not cached: ' . $urlData['url'] . '</comment>');
                }
            }
            
            $output->writeln('<info>Cache Status Summary:</info>');
            $output->writeln('<info>✓ Cached: ' . $cachedCount . '</info>');
            $output->writeln('<comment>✗ Not cached: ' . $uncachedCount . '</comment>');
            
            return Command::SUCCESS;
        }
        
        $progressBar = new ProgressBar($output, count($urls));
        $progressBar->start();

        $successCount = 0;
        $failureCount = 0;

        $results = $this->warmer->warmUrls($urls);
        
        foreach ($results as $result) {
            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln('');
        $output->writeln('<info>Cache warming completed:</info>');
        $output->writeln('<info>✓ Success: ' . $successCount . '</info>');
        
        if ($failureCount > 0) {
            $output->writeln('<error>✗ Failed: ' . $failureCount . '</error>');
        }

        return Command::SUCCESS;
    }
}