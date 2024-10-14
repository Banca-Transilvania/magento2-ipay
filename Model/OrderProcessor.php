<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Model;

use BTRL\Ipay\Helper\Currency;
use BTRL\Ipay\Gateway\Config\Config as IpayConfig;
use BTRL\Ipay\Model\Config\Source\ChargedCurrency;
use Magento\Directory\Model\Currency as CurrencyModel;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Sales\Api\Data\{CreditmemoInterface, InvoiceInterface, OrderInterface};
use Magento\Sales\Api\{InvoiceRepositoryInterface, OrderRepositoryInterface, TransactionRepositoryInterface};
use Magento\Sales\Model\{
    Order,
    Order\Email\Sender\InvoiceSender,
    Order\Payment,
    Order\Status\HistoryFactory,
    OrderFactory
};
use Magento\Sales\Model\Service\OrderService;
use Psr\Log\LoggerInterface;

class OrderProcessor
{
    const PAYMENT_STATUS_KEY = 'orderStatus';
    const PAYMENT_CURRENCY_KEY = 'currency';
    const PAYMENT_AMOUNT_KEY = 'amount';
    const PAYMENT_PAYMENT_INFO_KEY = 'paymentAmountInfo';
    const PAYMENT_CAPTURED_AMOUNT_KEY = 'depositedAmount';
    const PAYMENT_ACTION_CODE_KEY = 'actionCode';
    const PAYMENT_ACTION_CODE_DESCRIPTION_KEY = 'actionCodeDescription';
    const PAYMENT_ERROR_CODE_KEY = 'errorCode';
    const PAYMENT_ERROR_MESSAGE_KEY = 'errorMessage';
    const PAYMENT_REFUNDS_KEY = 'refunds';
    const PAYMENT_MERCHANT_PARAMS_KEY = 'merchantOrderParams';
    const PAYMENT_MERCHANT_LOYALTY_KEY = 'loyaltyAmount';
    const PAYMENT_ATTRIBUTES_KEY = 'attributes';
    const PAYMENT_ATTRIBUTES_LOYALTY_KEY = 'loyalties';

    const PAYMENT_STATUS_UNPAID_VALUE = 0;
    const PAYMENT_STATUS_AUTHORIZED_VALUE = 1;
    const PAYMENT_STATUS_CAPTURED_VALUE = 2;
    const PAYMENT_STATUS_AUTHORIZATION_REVERSED_VALUE = 3;
    const PAYMENT_STATUS_REFUNDED_VALUE = 4;
    const PAYMENT_STATUS_AUTHORIZATION_ACS_INITIATED_VALUE = 5;
    const PAYMENT_STATUS_AUTHORIZATION_DECLINED_VALUE = 6;
    const PAYMENT_STATUS_PARTIALLY_REFUNDED_VALUE = 7;

    const ADDITIONAL_INFO_RESPONSE_KEY = 'status_check_response';

    private IpayConfig $iPayConfig;
    private OrderRepositoryInterface $orderRepository;
    private InvoiceRepositoryInterface $invoiceRepository;
    private TransactionRepositoryInterface $transactionRepository;
    private InvoiceSender $invoiceSender;
    private OrderFactory $orderFactory;
    private OrderStatus $orderStatus;
    private OrderService $orderService;
    private HistoryFactory $orderHistoryFactory;
    private Currency $currency;
    private EventManager $eventManager;
    private LoggerInterface $logger;

    public function __construct(
        OrderRepositoryInterface $orderRepository,
        InvoiceRepositoryInterface $invoiceRepository,
        TransactionRepositoryInterface $transactionRepository,
        InvoiceSender $invoiceSender,
        OrderFactory $orderFactory,
        OrderStatus $orderStatus,
        OrderService $orderService,
        HistoryFactory $orderHistoryFactory,
        IpayConfig $iPayConfig,
        Currency $currency,
        EventManager $eventManager,
        LoggerInterface $logger
    ) {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
        $this->transactionRepository = $transactionRepository;
        $this->invoiceSender = $invoiceSender;
        $this->orderFactory = $orderFactory;
        $this->orderStatus = $orderStatus;
        $this->orderService = $orderService;
        $this->orderHistoryFactory = $orderHistoryFactory;
        $this->iPayConfig = $iPayConfig;
        $this->currency = $currency;
        $this->eventManager = $eventManager;
        $this->logger = $logger;
    }

    /**
     * @return mixed[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException|\Magento\Framework\Exception\LocalizedException
     */
    public function checkAndUpdateOrderStatus(string $orderNumber, bool $forceCancel = false): array
    {
        $response = [
            'success' => false
        ];

        if ($order = $this->getOrder($orderNumber)) {
            /** @var Order|null $order */
            /** @var \Magento\Sales\Model\Order\Payment\Info $payment */
            $payment = $order->getPayment();
            $request = [
                'orderId' => $payment->getAdditionalInformation('orderId')
            ];
            $statusResponse = $this->orderStatus->checkStatus($request, (int)$order->getStoreId());
            $paymentStatus = (int)($statusResponse[self::PAYMENT_STATUS_KEY] ?? 0);
            /** @var Payment $payment */
            $payment->setAdditionalInformation('status', $statusResponse);

            // Check if there is a LOY transaction
            $loyStatusResponse = [];
            if ($loyaltyOrderNumber = $this->getLoyaltyOrderNumber($statusResponse)) {
                $request = [
                    'orderId' => $loyaltyOrderNumber
                ];
                $loyStatusResponse = $this->orderStatus->checkStatus($request, (int)$order->getStoreId());
                $payment->setAdditionalInformation('loyalty_status', $loyStatusResponse);
            }

            // Handle loyalty points
            if ($loyaltyOrderId = $this->getLoyaltyOrderNumber($statusResponse)) {
                $payment->setAdditionalInformation('loyaltyOrderId', $loyaltyOrderId);

                $loyaltyRequest = [
                    'orderId' => $loyaltyOrderId
                ];
                $loyaltyStatusResponse = $this->orderStatus->checkStatus($loyaltyRequest, (int)$order->getStoreId());

                $payment->setAdditionalInformation('loyalty_status', $loyaltyStatusResponse);
            }

            $this->eventManager->dispatch(
                'btrl_ipay_payment_response',
                [
                    'payment' => $payment,
                    'response' => $statusResponse
                ]
            );

            // Handle errors
            $responseErrorCode = (int)($statusResponse[self::PAYMENT_ERROR_CODE_KEY] ?? 0);
            if ($responseErrorCode) {
                $response = [
                    'error_code' => $responseErrorCode
                ];
            } elseif (!empty($loyStatusResponse[self::PAYMENT_ERROR_CODE_KEY])) {
                $response = [
                    'error_code' => $loyStatusResponse[self::PAYMENT_ERROR_CODE_KEY]
                ];
            }

            // Check if response currency and amount matches order, if not set status to null
            $paymentStatus = $this->hasOrderValidCurrencyAndAmount($order, $statusResponse) ? $paymentStatus : null;

            // In case there is no payment status and force cancellation is on => cancel order
            if ($forceCancel && !$paymentStatus) {
                $paymentStatus = self::PAYMENT_STATUS_AUTHORIZATION_DECLINED_VALUE;
            }

            switch ($paymentStatus) {
                case self::PAYMENT_STATUS_UNPAID_VALUE:
                    $this->updatePendingStatus($order, $statusResponse);
                    $response['success'] = true;
                    break;
                case self::PAYMENT_STATUS_AUTHORIZED_VALUE:
                case self::PAYMENT_STATUS_AUTHORIZATION_ACS_INITIATED_VALUE:
                    $this->setOrderToProcessing($order, $statusResponse);
                    $response['success'] = true;
                    break;
                case self::PAYMENT_STATUS_CAPTURED_VALUE:
                    $this->setOrderToProcessingAndCreateInvoice($order, $statusResponse);
                    $response['success'] = true;
                    break;
                case self::PAYMENT_STATUS_AUTHORIZATION_DECLINED_VALUE:
                case self::PAYMENT_STATUS_AUTHORIZATION_REVERSED_VALUE:
                    $response = $this->cancelOrder($order, $statusResponse, $loyStatusResponse);
                    $response['success'] = false;
                    break;
                case self::PAYMENT_STATUS_REFUNDED_VALUE:
                case self::PAYMENT_STATUS_PARTIALLY_REFUNDED_VALUE:
                    // Save order with new status data
                    $this->orderRepository->save($order);
                    $response['success'] = true;
                    break;
            }
        }

        return $response;
    }

    /**
     * @param mixed[] $statusResponse
     */
    public function updatePendingStatus(OrderInterface $order, array $statusResponse): void
    {
        $orderStatus = $this->iPayConfig->getNewOrderStatus((int)$order->getStoreId());
        $this->updatePaymentInfo($order, $statusResponse);
        /** @var Order $order */
        $order->addStatusToHistory($orderStatus, __('Order registered, but not paid off'));
        $order->setState(Order::STATE_PENDING_PAYMENT);
        $order->setStatus($orderStatus);
        $this->orderRepository->save($order);
    }

    /**
     * @param mixed[] $statusResponse
     */
    public function setOrderToProcessing(OrderInterface $order, array $statusResponse): void
    {
        $orderStatus = $this->iPayConfig->getPaidOrderStatus((int)$order->getStoreId());

        if ($order->getState() !== Order::STATE_PROCESSING || $order->getStatus() !== $orderStatus) {
            $this->updatePaymentInfo($order, $statusResponse);
            /** @var Order $order */
            $order->addStatusToHistory($orderStatus, __('Pre-authorization amount was held'));
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus($orderStatus);
            $this->orderRepository->save($order);
        }
    }

    /**
     * @param mixed[] $statusResponse
     */
    public function setOrderToProcessingAndCreateInvoice(OrderInterface $order, array $statusResponse): void
    {
        /** @var Order $order */
        $orderStatus = $this->iPayConfig->getPaidOrderStatus((int)$order->getStoreId());
        $canSaveOrder = false;

        if ($order->getState() !== Order::STATE_PROCESSING || $order->getStatus() !== $orderStatus) {
            $this->updatePaymentInfo($order, $statusResponse);
            $order->addStatusToHistory($orderStatus, __('Amount was captured.'));
            $order->setState(Order::STATE_PROCESSING);
            $order->setStatus($orderStatus);
            $canSaveOrder = true;
        }

        if (
            $order->canInvoice()
            && ($invoice = $this->createInvoice($order))
        ) {
            // Update order paid total
            $amountPaid = $order->getTotalPaid() + $invoice->getGrandTotal();
            $baseAmountPaid = $order->getBaseTotalPaid() + $invoice->getBaseGrandTotal();
            $order->setTotalPaid($amountPaid);
            $order->setBaseTotalPaid($baseAmountPaid);
            /** @var \Magento\Sales\Model\Order\Payment $payment */
            $payment = $order->getPayment();
            $payment->setAmountPaid($amountPaid);
            $payment->setBaseAmountPaidOnline($amountPaid);
            $payment->setBaseAmountPaid($baseAmountPaid);
            $payment->setBaseAmountPaidOnline($baseAmountPaid);

            $order->addStatusToHistory($orderStatus, __('Invoice was created.'));
            $this->closeAuthorizationTransaction($order);
            $canSaveOrder = true;
        }

        if ($canSaveOrder) {
            $this->orderRepository->save($order);
        }
    }

    public function createInvoice(OrderInterface $order): ?InvoiceInterface
    {
        try {
            /** @var Order $order */
            /** @var Order\Invoice $invoice */
            $invoice = $order->prepareInvoice();
            $invoice->getOrder()->setIsInProcess(true);
            $payment = $order->getPayment();

            // Set transaction ID so you can do an online refund from credit memo
            $invoice->setTransactionId($payment->getLastTransId());
            $invoice->setState(Order\Invoice::STATE_PAID);
            $invoice->register();

            $this->invoiceRepository->save($invoice);

            if ($this->iPayConfig->canSendInvoiceEmail((int)$invoice->getStoreId())) {
                $this->invoiceSender->send($invoice);
            }

            return $invoice;

        } catch (\Exception $e) {
            $this->logger->critical('BT iPay: Error saving invoice - ' . $e->getMessage());
        }

        return null;
    }

    /**
     * @param mixed[] $statusResponse
     * @param mixed[]|null $loyStatusResponse
     * @return mixed[]
     * @throws \Exception
     */
    public function cancelOrder(OrderInterface $order, array $statusResponse, ?array $loyStatusResponse = []): array
    {
        $orderStatus = $order->getStatus();
        $responseActionCode = (int)($statusResponse[self::PAYMENT_ACTION_CODE_KEY] ?? 0);
        $responseActionCodeDescription = (string)($statusResponse[self::PAYMENT_ACTION_CODE_DESCRIPTION_KEY] ?? '');

        if (!$responseActionCode) {
            $responseActionCode = (int)($loyStatusResponse[self::PAYMENT_ACTION_CODE_KEY] ?? 0);
            $responseActionCodeDescription = (string)($loyStatusResponse[self::PAYMENT_ACTION_CODE_DESCRIPTION_KEY] ?? '');
        }

        $message = $responseActionCode . ' - ' . $responseActionCodeDescription;
        $response = [
            'error_code' => $responseActionCode
        ];

        $this->updatePaymentInfo($order, $statusResponse);
        $canceled = false;

        // First close the opened authorization transaction, if available
        $this->closeAuthorizationTransaction($order);
        // Set state to pending payment so the order can be canceled
        $order->setState(Order::STATE_PENDING_PAYMENT);

        /** @var Order $order */
        if ($order->canCancel()) {
            try {
                $canceled = $this->orderService->cancel($order->getEntityId());
            } catch (\Exception $e) {
                $this->logger->critical('BT iPay: Order cancel error - ' . $e->getMessage());
            }

            if (!$canceled) {
                try {
                    $canceled = $order->cancel();
                    $this->orderRepository->save($order);
                } catch (\Exception $e) {
                    $this->logger->critical('BT iPay: Order cancel error - ' . $e->getMessage());
                }
            }

            if ($canceled) {
                $orderStatusHistory = $this->orderHistoryFactory->create()
                    ->setParentId($order->getEntityId())
                    ->setEntityName('order')
                    ->setStatus(Order::STATE_CANCELED)
                    ->setComment(
                        __('Order was cancelled by "%1" payment response.', $order->getPayment()->getMethod())
                    );
                $this->orderService->addComment($order->getEntityId(), $orderStatusHistory);

                $orderStatusHistory = $this->orderHistoryFactory->create()
                    ->setParentId($order->getEntityId())
                    ->setEntityName('order')
                    ->setStatus(Order::STATE_CANCELED)
                    ->setComment($message);
                $this->orderService->addComment($order->getEntityId(), $orderStatusHistory);
            }
        }

        if (!$canceled) {
            $order->addStatusToHistory(
                $orderStatus,
                __('Authorization was declined. Order cannot be canceled.')
            );
            $order->addStatusToHistory($orderStatus, $message);
            $this->orderRepository->save($order);
        }

        return $response;
    }

    /**
     * @param mixed[] $statusResponse
     */
    protected function updatePaymentInfo(OrderInterface $order, array $statusResponse): void
    {
        /** @var \Magento\Sales\Model\Order\Payment\Info $payment */
        $payment = $order->getPayment();
        // Save status response to payment additional data
        $payment->setAdditionalInformation(self::ADDITIONAL_INFO_RESPONSE_KEY, $statusResponse);
    }

    /**
     * @param mixed[] $response
     */
    public function hasOrderValidCurrencyAndAmount(OrderInterface $order, array $response): bool
    {
        $orderCurrency = $this->currency->getCurrencyNumber($this->getChargedCurrencyCode($order));
        $responseCurrency = (int)($response[self::PAYMENT_CURRENCY_KEY] ?? 0);
        $orderAmount = $this->getChargedAmount($order);
        $authorizedAmount = (int)($response[self::PAYMENT_AMOUNT_KEY] ?? 0);
        $authorizedAmount += $this->getLoyaltyPoints($response);

        return (
            (
                $orderCurrency === $responseCurrency
                || (
                    $responseCurrency === $this->currency->getCurrencyNumber('LOY')
                    && $orderCurrency === $this->currency->getCurrencyNumber('RON')
                )
            )
            && $orderAmount === $authorizedAmount
        );
    }

    /**
     * @param mixed[] $response
     */
    public function getLoyaltyPoints(array $response): int
    {
        if (
            isset($response[self::PAYMENT_MERCHANT_PARAMS_KEY])
            && is_array($response[self::PAYMENT_MERCHANT_PARAMS_KEY])
        ) {
            foreach ($response[self::PAYMENT_MERCHANT_PARAMS_KEY] as $param) {
                if (
                    isset($param['name'])
                    && isset($param['value'])
                    && ($param['name'] === self::PAYMENT_MERCHANT_LOYALTY_KEY)
                ) {
                    return (int)$param['value'];
                }
            }
        }

        return 0;
    }

    /**
     * @param mixed[] $response
     */
    public function getLoyaltyOrderNumber(array $response): string
    {
        if (
            isset($response[self::PAYMENT_ATTRIBUTES_KEY])
            && is_array($response[self::PAYMENT_ATTRIBUTES_KEY])
        ) {
            foreach ($response[self::PAYMENT_ATTRIBUTES_KEY] as $attribute) {
                if (
                    isset($attribute['name'])
                    && isset($attribute['value'])
                    && ($attribute['name'] === self::PAYMENT_ATTRIBUTES_LOYALTY_KEY)
                ) {
                    try {
                        $values = explode(',', trim($attribute['value'], '{[]}'));

                        foreach ($values as $value) {
                            $data = explode(':', $value);

                            if (isset($data[0]) && $data[0] === 'mdOrder' && isset($data[1])) {
                                return trim($data[1]);
                            }
                        }
                    } catch (\Exception $e) {
                        return $attribute['value'];
                    }
                }
            }
        }

        return '';
    }

    /**
     * @param mixed[] $response
     */
    public function hasInvoiceValidCurrencyAndAmount(InvoiceInterface $invoice, array $response): bool
    {
        $invoiceCurrency = $this->currency->getCurrencyNumber($this->getInvoicedCurrencyCode($invoice));
        $responseCurrency = (int)($response[self::PAYMENT_CURRENCY_KEY] ?? 0);
        $invoiceAmount = $this->getInvoicedAmount($invoice);
        $depositedAmount = (int)($response[self::PAYMENT_PAYMENT_INFO_KEY][self::PAYMENT_CAPTURED_AMOUNT_KEY] ?? 0);
        $depositedAmount += $this->getLoyaltyPoints($response);

        return (
            (
                $invoiceCurrency === $responseCurrency
                || (
                    $responseCurrency === $this->currency->getCurrencyNumber('LOY')
                    && $invoiceCurrency === $this->currency->getCurrencyNumber('RON')
                )
            )
            && $invoiceAmount === $depositedAmount
        );
    }

    public function hasCreditmemoValidAmount(CreditmemoInterface $creditmemo, int $totalRefunded): bool
    {
        $creditMemoAmount = $this->getCreditedAmount($creditmemo);;

        return $creditMemoAmount === $totalRefunded;
    }

    /**
     * @param mixed[] $response
     */
    public function hasCreditmemoValidCurrency(CreditmemoInterface $creditmemo, array $response): bool
    {
        $creditMemoCurrency = $this->currency->getCurrencyNumber($this->getCreditedCurrencyCode($creditmemo));
        $responseCurrency = (int)($response[self::PAYMENT_CURRENCY_KEY] ?? 0);

        return (
            (
                $creditMemoCurrency === $responseCurrency
                || (
                    $responseCurrency === $this->currency->getCurrencyNumber('LOY')
                    && $creditMemoCurrency === $this->currency->getCurrencyNumber('RON')
                )
            )
        );
    }

    /**
     * @param mixed[] $response
     */
    public function getLastRefundAmount(array $response): int
    {
        $refunds = $response[self::PAYMENT_REFUNDS_KEY] ?? [];
        $refund = end($refunds);

        return (int)($refund[self::PAYMENT_AMOUNT_KEY] ?? 0);
    }

    /**
     * @param mixed[] $response
     */
    public function addCreditmemoComment(CreditmemoInterface $creditmemo, array $response): void
    {
        $refunds = $response[self::PAYMENT_REFUNDS_KEY] ?? [];
        $refund = end($refunds);

        if ($refundId = $refund['referenceNumber'] ?? '') {
            /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
            $creditmemo->addComment(__('Credit Memo Reference Number: %1', $refundId));
        }
    }

    protected function getOrder(string $orderNumber): ?OrderInterface
    {
        /** @var OrderInterface $order */
        $order = $this->orderFactory->create()->loadByIncrementId($orderNumber);

        return $order->getEntityId() ? $order : null;
    }

    public function closeAuthorizationTransaction(OrderInterface $order): void
    {
        // First check if auth transaction is opened and, if so, close it
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $order->getPayment();
        if (
            ($authTransaction = $payment->getAuthorizationTransaction())
            && !$authTransaction->getIsClosed()
        ) {
            $authTransaction->setIsClosed(1);
            $this->transactionRepository->save($authTransaction);
        }
    }

    public function getChargedAmount(OrderInterface $order): int
    {
        $chargeCurrencyType = $this->getChargedCurrencyType($order);

        if ($chargeCurrencyType === ChargedCurrency::STORE_CURRENCY) {
            return (int)round($order->getGrandTotal() * 100, 0);
        }

        return (int)round($order->getBaseGrandTotal() * 100, 0);
    }

    public function getChargedCurrencyCode(OrderInterface $order): string
    {
        $chargeCurrencyType = $this->getChargedCurrencyType($order);

        if ($chargeCurrencyType === ChargedCurrency::STORE_CURRENCY) {
            return $order->getOrderCurrencyCode();
        }

        return $order->getBaseCurrencyCode();
    }

    public function getInvoicedAmount(InvoiceInterface $invoice): int
    {
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $chargeCurrencyType = $this->getChargedCurrencyType($invoice->getOrder());

        if ($chargeCurrencyType === ChargedCurrency::STORE_CURRENCY) {
            return (int)round($invoice->getGrandTotal() * 100, 0);
        }

        return (int)round($invoice->getBaseGrandTotal() * 100, 0);
    }

    public function getInvoicedCurrencyCode(InvoiceInterface $invoice): string
    {
        /** @var \Magento\Sales\Model\Order\Invoice $invoice */
        $chargeCurrencyType = $this->getChargedCurrencyType($invoice->getOrder());

        if ($chargeCurrencyType === ChargedCurrency::STORE_CURRENCY) {
            return $invoice->getOrderCurrencyCode();
        }

        return $invoice->getBaseCurrencyCode();
    }

    public function getCreditedAmount(CreditmemoInterface $creditmemo): int
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $chargeCurrencyType = $this->getChargedCurrencyType($creditmemo->getOrder());

        if ($chargeCurrencyType === ChargedCurrency::STORE_CURRENCY) {
            return (int)round($creditmemo->getGrandTotal() * 100, 0);
        }

        return (int)round($creditmemo->getBaseGrandTotal() * 100, 0);
    }

    public function getTotalOnlineCreditedAmount(OrderInterface $order): int
    {
        $chargeCurrencyType = $this->getChargedCurrencyType($order);

        if ($chargeCurrencyType === ChargedCurrency::STORE_CURRENCY) {
            return (int)round($order->getTotalOnlineRefunded() * 100, 0);
        }

        return (int)round($order->getBaseTotalOnlineRefunded() * 100, 0);
    }

    public function getCreditedCurrencyCode(CreditmemoInterface $creditmemo): string
    {
        /** @var \Magento\Sales\Model\Order\Creditmemo $creditmemo */
        $chargeCurrencyType = $this->getChargedCurrencyType($creditmemo->getOrder());

        if ($chargeCurrencyType === ChargedCurrency::STORE_CURRENCY) {
            return $creditmemo->getOrderCurrencyCode();
        }

        return $creditmemo->getBaseCurrencyCode();
    }

    public function getOrderCurrency(OrderInterface $order): CurrencyModel
    {
        /** @var \Magento\Sales\Model\Order $order */
        $chargeCurrencyType = $this->getChargedCurrencyType($order);

        if ($chargeCurrencyType === ChargedCurrency::STORE_CURRENCY) {
            return $order->getOrderCurrency();
        }

        return $order->getBaseCurrency();
    }

    public function getChargedCurrencyType(OrderInterface $order): string
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $order->getPayment();
        $additionalInformation = $payment->getAdditionalInformation();

        if (!empty($additionalInformation['charged_currency_type'])) {
            return $additionalInformation['charged_currency_type'];
        }

        return $this->iPayConfig->getChargedCurrencyType((int)$order->getStoreId());
    }
}
