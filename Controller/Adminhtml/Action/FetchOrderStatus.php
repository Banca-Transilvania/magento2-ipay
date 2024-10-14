<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Controller\Adminhtml\Action;

use BTRL\Ipay\Model\OrderProcessor;
use Magento\Backend\App\Action;

class FetchOrderStatus extends Action
{
    private OrderProcessor $orderProcessor;

    public function __construct(
        Action\Context $context,
        OrderProcessor $orderProcessor
    ) {
        parent::__construct($context);
        $this->orderProcessor = $orderProcessor;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $orderId = $this->getRequest()->getParam('order_id');
        $incrementId = $this->getRequest()->getParam('increment_id');

        try {
            $this->orderProcessor->checkAndUpdateOrderStatus($incrementId, false);
            $this->messageManager->addSuccessMessage(__('Order status was fetched.'));
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(__('Order status cannot be fetched: %1', $e->getMessage()));
        }

        return $resultRedirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
