<?php
/**
 * CloudCommerce Cache Warmer Cron Time Source Model
 */
namespace CloudCommerce\CacheWarmer\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Cron Time Options Source Model
 */
class CronTime implements OptionSourceInterface
{
    /**
     * Return array of options as value-label pairs
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => '0 0 * * *', 'label' => __('12:00 AM')],
            ['value' => '0 1 * * *', 'label' => __('1:00 AM')],
            ['value' => '0 2 * * *', 'label' => __('2:00 AM')],
            ['value' => '0 3 * * *', 'label' => __('3:00 AM')],
            ['value' => '0 4 * * *', 'label' => __('4:00 AM')],
            ['value' => '0 5 * * *', 'label' => __('5:00 AM')],
            ['value' => '0 6 * * *', 'label' => __('6:00 AM')],
            ['value' => '0 * * * *', 'label' => __('Every Hour')],
            ['value' => '*/30 * * * *', 'label' => __('Every 30 Minutes')],
            ['value' => '*/15 * * * *', 'label' => __('Every 15 Minutes')],
            ['value' => '*/5 * * * *', 'label' => __('Every 5 Minutes')]
        ];
    }
}