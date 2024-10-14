<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Request;

use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class CancelDataBuilder implements BuilderInterface
{
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

        $loyaltyData = [];
        if ($loyaltyOrderId = $payment->getAdditionalInformation('loyaltyOrderId')) {
            $loyaltyData = [
                'orderId' => $loyaltyOrderId
            ];
        }

        $request['body'] = [
            'orderId' => $payment->getAdditionalInformation('orderId'),
            'storeId' => $order->getStoreId(),
            'loyalty' => $loyaltyData
        ];

        return $request;
    }
}
