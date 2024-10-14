<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Response;

use BTRL\Ipay\Model\{OrderProcessor, OrderStatus};
use Magento\Framework\Exception\{LocalizedException, NoSuchEntityException};
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;

class RefundDetailsHandler implements HandlerInterface
{
    private OrderStatus $orderStatus;
    private OrderProcessor $orderProcessor;

    public function __construct(
        OrderStatus $orderStatus,
        OrderProcessor $orderProcessor
    ) {
        $this->orderStatus = $orderStatus;
        $this->orderProcessor = $orderProcessor;
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
        /** @var \Magento\Sales\Model\Order\Creditmemo|null $creditMemo */
        $creditMemo = $payment->getCreditMemo();

        if (!$creditMemo) {
            throw new LocalizedException(
                __('Error with refund on transaction: The credit memo is missing.')
            );
        }

        // Get all BT order IDs used for refund - it can contain a LOY transaction -
        $orderIds = [];
        if (!empty($response['request']['loyalty']['orderId'])) {
            $orderIds[] = $response['request']['loyalty']['orderId'];
        }
        if (!empty($response['request']['orderId'])) {
            $orderIds[] = $response['request']['orderId'];
        }

        $statusResponse = [];
        $totalRefunded = 0;
        $loyaltyOrderId = $payment->getAdditionalInformation('loyaltyOrderId');
        foreach ($orderIds as $orderId) {
            $request = [
                'orderId' => $orderId
            ];

            $statusResponse = $this->orderStatus->checkStatus($request, (int)$order->getStoreId());
            $prefix = '';
            if ($loyaltyOrderId === $orderId) {
                $prefix = 'loyalty_';
            }
            $payment->setAdditionalInformation($prefix . 'refund_status_response', $statusResponse);
            $payment->setAdditionalInformation($prefix . 'status', $statusResponse);
            $paymentStatus = (int)($statusResponse[OrderProcessor::PAYMENT_STATUS_KEY] ?? 0);

            $hasValidRefundedCurrency = $this->orderProcessor
                ->hasCreditmemoValidCurrency($creditMemo, $statusResponse);
            if (
                !$hasValidRefundedCurrency
                || !in_array(
                    $paymentStatus,
                    [
                        OrderProcessor::PAYMENT_STATUS_PARTIALLY_REFUNDED_VALUE,
                        OrderProcessor::PAYMENT_STATUS_REFUNDED_VALUE
                    ]
                )
            ) {
                throw new LocalizedException(
                    __('Error with refund on transaction: Wrong status, currency or refunded amount. Please contact bank.')
                );
            }

            $totalRefunded += $this->orderProcessor->getLastRefundAmount($statusResponse);
        }

        if (!$this->orderProcessor->hasCreditmemoValidAmount($creditMemo, $totalRefunded)) {
            throw new LocalizedException(
                __('Error with refund on transaction: Wrong status, currency or refunded amount. Please contact bank.')
            );
        }

        $this->orderProcessor->addCreditmemoComment($creditMemo, $statusResponse);
    }
}
