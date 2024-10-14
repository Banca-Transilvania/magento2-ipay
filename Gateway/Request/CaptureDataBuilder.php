<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Request;

use BTRL\Ipay\Model\OrderProcessor;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CaptureDataBuilder implements BuilderInterface
{
    private OrderProcessor $orderProcessor;

    public function __construct(
        OrderProcessor $orderProcessor
    ) {
        $this->orderProcessor = $orderProcessor;
    }

    /**
     * @param mixed[] $buildSubject
     * @return mixed[]
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $paymentDataObject->getPayment();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        /** @var \Magento\Sales\Model\Order\Invoice $latestInvoice */
        $latestInvoice = $order->getInvoiceCollection()->getLastItem();

        $amount = $this->orderProcessor->getInvoicedAmount($latestInvoice);
        $orderId = $payment->getAdditionalInformation('orderId');
        $loyaltyData = [];

        $statusData = $payment->getAdditionalInformation('status') ?? [];
        if (
            !empty($statusData)
            && ($loyaltyPoints = $this->orderProcessor->getLoyaltyPoints($statusData))
        ) {
            $amount -= $loyaltyPoints;
            $loyaltyData = [
                'orderId' => $payment->getAdditionalInformation('loyaltyOrderId'),
                'amount' => $loyaltyPoints
            ];
        }

        $request['body'] = [
            'orderId' => $orderId,
            'amount' => $amount,
            'storeId' => $order->getStoreId(),
            'loyalty' => $loyaltyData
        ];

        return $request;
    }
}
