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

class CaptureDetailsHandler implements HandlerInterface
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
        /** @var \Magento\Sales\Model\Order\Invoice $latestInvoice */
        $latestInvoice = $order->getInvoiceCollection()->getLastItem();

        // Fetch and check payment status
        $request = [
            'orderId' => $payment->getAdditionalInformation('orderId')
        ];
        $statusResponse = $this->orderStatus->checkStatus($request, (int)$order->getStoreId());
        $payment->setAdditionalInformation('capture_status_response', $statusResponse);
        $payment->setAdditionalInformation('status', $statusResponse);

        // Handle loyalty points
        if ($loyaltyOrderId = $this->orderProcessor->getLoyaltyOrderNumber($statusResponse)) {
            $payment->setAdditionalInformation('loyaltyOrderId', $loyaltyOrderId);

            $loyaltyRequest = [
                'orderId' => $loyaltyOrderId
            ];
            $loyaltyStatusResponse = $this->orderStatus->checkStatus($loyaltyRequest, (int)$order->getStoreId());

            $payment->setAdditionalInformation('loyalty_status', $loyaltyStatusResponse);
        }

        $paymentStatus = (int)($statusResponse[OrderProcessor::PAYMENT_STATUS_KEY] ?? 0);
        $hasValidCapturedAmount = $this->orderProcessor
            ->hasInvoiceValidCurrencyAndAmount($latestInvoice, $statusResponse);

        if (!$hasValidCapturedAmount || $paymentStatus !== OrderProcessor::PAYMENT_STATUS_CAPTURED_VALUE) {
            throw new LocalizedException(
                __('Error with capture on transaction: Wrong status, currency or deposited amount. Please contact bank.')
            );
        }
    }
}
