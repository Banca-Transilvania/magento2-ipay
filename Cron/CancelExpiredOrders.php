<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Cron;

use BTRL\Ipay\Model\OrderProcessor;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\{
    Collection as OrderCollection,
    CollectionFactory as OrderCollectionFactory
};

class CancelExpiredOrders
{
    private OrderCollectionFactory $orderCollectionFactory;
    private ScopeConfigInterface $scopeConfig;
    private OrderProcessor $orderProcessor;
    private TimezoneInterface $timezone;

    /** @var string[] */
    private array $methodCodes;

    /** @var string[] */
    private array $stateCodes;

    /**
     * @param string[]|null $methodCodes
     * @param string[]|null $stateCodes
     */
    public function __construct(
        OrderCollectionFactory $orderCollectionFactory,
        OrderProcessor $orderProcessor,
        ScopeConfigInterface $scopeConfig,
        TimezoneInterface $timezone,
        ?array $methodCodes = [],
        ?array $stateCodes = []
    ) {
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->orderProcessor = $orderProcessor;
        $this->scopeConfig = $scopeConfig;
        $this->timezone = $timezone;
        $this->methodCodes = $methodCodes;
        $this->stateCodes = $stateCodes;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function execute(): void
    {
        $pendingLifetime = $this->getPendingLifetime();

        if (!$pendingLifetime) {
            return;
        }

        /** @var Order $pendingOrder */
        foreach ($this->getPendingOrders($pendingLifetime) as $pendingOrder) {
            $this->orderProcessor->checkAndUpdateOrderStatus($pendingOrder->getIncrementId(), true);
        }
    }

    public function getPendingOrders(int $pendingLifetime): OrderCollection
    {
        $now = strtotime($this->timezone->date()->format('Y-m-d H:i:s'));
        $toDate = date('Y-m-d H:i:s', (int)($now - ($pendingLifetime * 60 * 60)));

        $collection = $this->orderCollectionFactory->create()
            ->addFieldToFilter('state', ['in' => $this->stateCodes])
            ->addFieldToFilter('created_at', ['lteq' => $toDate]);
        $collection->getSelect()
            ->join(
                ['sop' => $collection->getTable('sales_order_payment')],
                'main_table.entity_id = sop.parent_id',
                ['method']
            )
            ->where('sop.method in (?)', $this->methodCodes);
        $collection->setOrder('created_at', 'asc');

        return $collection;
    }

    private function getPendingLifetime(): int
    {
        return (int)$this->scopeConfig->getValue('payment/btrl_ipay/pending_lifetime');
    }
}
