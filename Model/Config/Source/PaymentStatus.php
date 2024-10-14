<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Model\Config\Source;

use BTRL\Ipay\Model\OrderProcessor;
use Magento\Framework\Data\OptionSourceInterface;

class PaymentStatus implements OptionSourceInterface
{
    /**
     * @return mixed[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => OrderProcessor::PAYMENT_STATUS_UNPAID_VALUE,
                'label' => __('Unpaid')
            ],
            [
                'value' => OrderProcessor::PAYMENT_STATUS_AUTHORIZED_VALUE,
                'label' => __('Authorized')
            ],
            [
                'value' => OrderProcessor::PAYMENT_STATUS_CAPTURED_VALUE,
                'label' => __('Deposited')
            ],
            [
                'value' => OrderProcessor::PAYMENT_STATUS_AUTHORIZATION_REVERSED_VALUE,
                'label' => __('Authorization reversed')
            ],
            [
                'value' => OrderProcessor::PAYMENT_STATUS_REFUNDED_VALUE,
                'label' => __('Refunded')
            ],
            [
                'value' => OrderProcessor::PAYMENT_STATUS_AUTHORIZATION_ACS_INITIATED_VALUE,
                'label' => __('Authorization ACS initiated')
            ],
            [
                'value' => OrderProcessor::PAYMENT_STATUS_AUTHORIZATION_DECLINED_VALUE,
                'label' => __('Authorization declined')
            ],
            [
                'value' => OrderProcessor::PAYMENT_STATUS_PARTIALLY_REFUNDED_VALUE,
                'label' => __('Refunded partially')
            ],
        ];
    }

    /**
     * @return mixed[]
     */
    public function toArray(): array
    {
        $complexOptions = $this->toOptionArray();
        $options = [];

        foreach ($complexOptions as $complexOption) {
            $options[$complexOption['value']] = $complexOption['label'];
        }

        return $options;
    }
}
