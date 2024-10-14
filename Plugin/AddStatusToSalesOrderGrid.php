<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Plugin;

use Magento\Sales\Model\ResourceModel\Order\Grid\Collection;
use Psr\Log\LoggerInterface;

class AddStatusToSalesOrderGrid
{
    private LoggerInterface $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    /**
     * @see Collection::load()
     *
     * @return mixed[]
     */
    public function beforeLoad(Collection $subject, bool $printQuery = false, bool $logQuery = false): array
    {
        try {
            if (!$subject->isLoaded()) {
                /** @var \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resourceConnection */
                $resourceConnection = $subject->getResource();
                $primaryKey = $resourceConnection->getIdFieldName();
                $paymentTableName = $resourceConnection->getTable('sales_order_payment');

                $subject->getSelect()->joinLeft(
                    $paymentTableName,
                    $paymentTableName . '.`parent_id` = main_table.' . $primaryKey,
                    ['btrl_ipay_status' => 'JSON_EXTRACT(' . $paymentTableName .
                        '.`additional_information`, "$.status.orderStatus")']
                );
            }
        } catch (\Exception $exception) {
            $this->logger->critical('BT iPay Grid Error: ' . $exception->getMessage());
        }

        return [$printQuery, $logQuery];
    }

    /**
     * @see Collection::getSelect()
     */
    public function beforeGetSelect(Collection $subject): void
    {
        static $filterMapAdded = null;

        if (!$filterMapAdded) {
            /** @var \Magento\Framework\Model\ResourceModel\Db\AbstractDb $resourceConnection */
            $resourceConnection = $subject->getResource();
            $paymentTableName = $resourceConnection->getTable('sales_order_payment');

            $subject->addFilterToMap(
                'btrl_ipay_status',
                /** @phpstan-ignore-next-line */
                new \Zend_Db_Expr('CONVERT(JSON_EXTRACT(' . $paymentTableName
                    . '.`additional_information`, "$.status.orderStatus"), CHAR)')
            );

            $filterMapAdded = true;
        }
    }
}
