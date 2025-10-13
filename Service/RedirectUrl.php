<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Service;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Api\Data\OrderInterface;

class RedirectUrl implements \BTRL\Ipay\Api\RedirectUrl
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession
    ) {}

    public function getUrl(): string
    {
        try {
            $order = $this->getOrderFromSession();
            if ($payment = $order->getPayment()) {
                $additionalInformation = $payment->getAdditionalInformation();

                if (isset($additionalInformation['redirectUrl'])) {
                    return $additionalInformation['redirectUrl'];
                }
            }
        } catch (\Exception $exception) {}

        return '';
    }

    private function getOrderFromSession(): OrderInterface
    {
        return $this->checkoutSession->getLastRealOrder();
    }
}
