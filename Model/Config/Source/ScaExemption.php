<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Model\Config\Source;

use BTRL\Ipay\Gateway\Config\Config;
use Magento\Framework\Data\OptionSourceInterface;

class ScaExemption implements OptionSourceInterface
{
    const TRANSACTION_RISK_ANALYSIS = 'TRA';
    const LOW_VALUE_PAYMENT = 'LVP';
    const SECURE_CORPORATE_PAYMENT = 'SCP';

    /**
     * @return mixed[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('None')],
            ['value' => self::TRANSACTION_RISK_ANALYSIS, 'label' => __('Transaction Risk Analysis')],
            ['value' => self::LOW_VALUE_PAYMENT, 'label' => __('Low Value Payment')],
            ['value' => self::SECURE_CORPORATE_PAYMENT, 'label' => __('Secure Corporate Payment')]
        ];
    }
}
