<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Block\Info;

use BTRL\Ipay\Model\Config\Source\PaymentStatus;
use BTRL\Ipay\Model\OrderProcessor;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Block\Info;
use Magento\Sales\Api\Data\OrderInterface;

class Ipay extends Info
{
    /** @var string */
    protected $_template = 'BTRL_Ipay::info/ipay.phtml';

    private OrderProcessor $orderProcessor;
    private PaymentStatus $paymentStatusOptions;

    /**
     * @param mixed[] $data
     */
    public function __construct(
        Context $context,
        OrderProcessor $orderProcessor,
        PaymentStatus $paymentStatusOptions,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->orderProcessor = $orderProcessor;
        $this->paymentStatusOptions = $paymentStatusOptions;
    }

    /**
     * @return mixed[]
     */
    public function getPaymentStatusData(bool $loyalty = false): array
    {
        try {
            if ($loyalty) {
                $statusData = $this->getInfo()->getAdditionalInformation('loyalty_status') ?? [];
            } else {
                $statusData = $this->getInfo()->getAdditionalInformation('status') ?? [];
            }
        } catch (\Exception $e) {
            $statusData = [];
        }

        return $statusData;
    }

    /**
     * @return mixed[]
     */
    public function getRefunds(): array
    {
        static $refunds = null;

        if ($refunds === null) {
            try {
                $statusData = $this->getPaymentStatusData();
                if ($statusData) {
                    $refunds = $statusData[OrderProcessor::PAYMENT_REFUNDS_KEY] ?? [];
                } else {
                    $refunds = [];
                }

                if ($this->getLoyaltyOrderNumber()) {
                    $statusData = $this->getPaymentStatusData(true);
                    if ($statusData) {
                        $loyaltyRefunds = $statusData[OrderProcessor::PAYMENT_REFUNDS_KEY] ?? [];
                        $refunds = array_merge($refunds, $loyaltyRefunds);
                    }
                }
            } catch (\Exception $e) {
                $refunds = [];
            }
        }

        return $refunds;
    }

    public function getPaymentStatus(bool $loyalty = false): ?string
    {
        $statusData = $this->getPaymentStatusData($loyalty);
        if (!$statusData) {
            return null;
        }

        $statusCode = (int)($statusData[OrderProcessor::PAYMENT_STATUS_KEY] ?? 0);
        $statusOptions = $this->paymentStatusOptions->toArray();
        $statusMessage = $statusOptions[$statusCode] ?? '';

        return (string)$statusMessage;
    }

    public function getMagentoCreditedAmount(OrderInterface $order): int
    {
        $creditTotal = 0;
        /** @var \Magento\Sales\Model\Order $order */
        $creditMemos = $order->getCreditmemosCollection();
        if ($creditMemos) {
            /** @var \Magento\Sales\Model\Order\Creditmemo $creditMemo */
            foreach ($creditMemos as $creditMemo) {
                $creditTotal += $this->orderProcessor->getCreditedAmount($creditMemo);
            }
        }

        return $creditTotal;
    }

    public function getOnlineCreditedAmount(): int
    {
        $creditTotalOnline = 0;
        foreach ($this->getRefunds() as $refund) {
            $creditTotalOnline += $refund['amount'] ?? 0;
        }

        return $creditTotalOnline;
    }

    public function formatCurrency(OrderInterface $order, float $amount): string
    {
        return $this->orderProcessor->getOrderCurrency($order)->formatTxt($amount);
    }

    public function getLoyaltyAmount(): float
    {
        $statusData = $this->getPaymentStatusData();
        if (!$statusData) {
            return 0.0;
        }

        return $this->orderProcessor->getLoyaltyPoints($statusData) / 100;
    }

    public function getLoyaltyOrderNumber(): ?string
    {
        if ($loyaltyOrderId = $this->getInfo()->getAdditionalInformation('loyaltyOrderId')) {
            return $loyaltyOrderId;
        }

        // Try to extract from status data
        $statusData = $this->getPaymentStatusData();
        if (!$statusData) {
            return '';
        }

        return $this->orderProcessor->getLoyaltyOrderNumber($statusData);
    }
}
