<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Response;

use BTRL\Ipay\Gateway\Config\Config as IpayConfig;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\{
    Transaction,
    Transaction\BuilderInterface as TransactionBuilder
};

class PaymentDetailsHandler implements HandlerInterface
{
    private TransactionBuilder $transactionBuilder;
    private IpayConfig $iPayConfig;

    public function __construct(
        TransactionBuilder $transactionBuilder,
        IpayConfig $iPayConfig
    ) {
        $this->transactionBuilder = $transactionBuilder;
        $this->iPayConfig = $iPayConfig;
    }

    /**
     * @param mixed[] $handlingSubject
     * @param mixed[] $response
     * @throws LocalizedException
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDataObject = SubjectReader::readPayment($handlingSubject);
        /** @var Payment $payment */
        $payment = $paymentDataObject->getPayment();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        // Set transaction not to processing by default wait for notification
        $payment->setIsTransactionPending(true);

        // Do not close transaction, so you can do a cancel() and void
        $payment->setIsTransactionClosed(false);
        $payment->setShouldCloseParentTransaction(false);

        if (!empty($response['response']['orderId'])) {
            $transactionId = $response['response']['orderId'];
            $formattedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());
            $message = __('The authorized amount is %1.', $formattedPrice);

            // Create new payment transaction
            /** @var Transaction $transaction */
            $transaction = $this->transactionBuilder
                ->setPayment($payment)
                ->setOrder($order)
                ->setTransactionId((string)$transactionId)
                ->setFailSafe(true)
                ->build(Transaction::TYPE_AUTH);
            $payment->addTransactionCommentsToOrder($transaction, $message);

            // Set BT order ID
            $payment->setAdditionalInformation('orderId', $transactionId);
        }

        if (!empty($response['response']['formUrl'])) {
            // Set redirect URL
            $payment->setAdditionalInformation('redirectUrl', $response['response']['formUrl']);
        }

        // Save charged currency type to use it later for capturing or refunding the correct amount
        $payment->setAdditionalInformation(
            'charged_currency_type',
            $this->iPayConfig->getChargedCurrencyType((int)$order->getStoreId())
        );
    }
}
