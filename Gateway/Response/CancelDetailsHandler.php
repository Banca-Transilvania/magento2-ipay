<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Response;

use BTRL\Ipay\Model\OrderStatus;
use Magento\Framework\Exception\{LocalizedException, NoSuchEntityException};
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class CancelDetailsHandler implements HandlerInterface
{
    private OrderStatus $orderStatus;

    public function __construct(
        OrderStatus $orderStatus
    ) {
        $this->orderStatus = $orderStatus;
    }

    /**
     * @param mixed[] $handlingSubject
     * @param mixed[] $response
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDataObject = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDataObject->getPayment();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        // Fetch and check payment status
        $request = [
            'orderId' => $payment->getAdditionalInformation('orderId')
        ];
        // Update status
        $statusResponse = $this->orderStatus->checkStatus($request, (int)$order->getStoreId());
        $payment->setAdditionalInformation('status', $statusResponse);
    }
}
