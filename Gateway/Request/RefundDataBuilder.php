<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Request;

use BTRL\Ipay\Model\OrderProcessor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RefundDataBuilder implements BuilderInterface
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
        /** @var \Magento\Sales\Model\Order\Creditmemo|null $creditMemo */
        $creditMemo = $payment->getCreditMemo();

        if (!$creditMemo) {
            return [];
        }

        $amount = $this->orderProcessor->getCreditedAmount($creditMemo);

        if ($amount <= 0) {
            throw new LocalizedException(__('Amount cannot be 0 or negative for online refund!'));
        }

        $orderId = $payment->getAdditionalInformation('orderId');
        $loyaltyData = [];
        $totalOnlineRefundedAmount = $this->orderProcessor->getTotalOnlineCreditedAmount($order);
        $previousOnlineRefundedAmount = $totalOnlineRefundedAmount - $amount;

        /**
         * Check how many LOY points can be refunded first, before refunding the currency amount
         */
        $statusData = $payment->getAdditionalInformation('status') ?? [];
        if (
            !empty($statusData)
            && ($loyaltyOrderId = $payment->getAdditionalInformation('loyaltyOrderId'))
            && ($loyaltyPoints = $this->orderProcessor->getLoyaltyPoints($statusData))
            && ($loyaltyPoints > $previousOnlineRefundedAmount)
        ) {
            $loyaltyPointsBalance = $loyaltyPoints - $previousOnlineRefundedAmount;
            $refundedLoyaltyPoints = min($loyaltyPointsBalance, $amount);
            $amount -= $refundedLoyaltyPoints;

            /**
             * Check if there is a currency amount to refund beside LOY,
             * otherwise replace the refund data for LOY transaction
             */
            if ($amount > 0) {
                $loyaltyData = [
                    'orderId' => $loyaltyOrderId,
                    'amount' => $refundedLoyaltyPoints
                ];
            } else {
                $orderId = $loyaltyOrderId;
                $amount = $refundedLoyaltyPoints;
            }
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
