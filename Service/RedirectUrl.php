<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Service;

use Magento\Sales\Api\OrderRepositoryInterface;

class RedirectUrl implements \BTRL\Ipay\Api\RedirectUrl
{
    private OrderRepositoryInterface $orderRepository;

    public function __construct(
        OrderRepositoryInterface $orderRepository
    ) {
        $this->orderRepository = $orderRepository;
    }

    public function getUrl(string $orderId): string
    {
        try {
            $order = $this->orderRepository->get((int)$orderId);
            $payment = $order->getPayment();
            $additionalInformation = $payment->getAdditionalInformation();

            if (isset($additionalInformation['redirectUrl'])) {
                return $additionalInformation['redirectUrl'];
            }
        } catch (\Exception $exception) {
            return '';
        }

        return '';
    }
}
