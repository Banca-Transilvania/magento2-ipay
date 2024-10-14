<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Model\Config\Source;

use BTRL\Ipay\Gateway\Config\Config;
use Magento\Framework\Data\OptionSourceInterface;

class ChargedCurrency implements OptionSourceInterface
{
    const BASE_CURRENCY = 'base';
    const STORE_CURRENCY = 'store';

    /**
     * @return mixed[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::BASE_CURRENCY, 'label' => __('Base Currency')],
            ['value' => self::STORE_CURRENCY, 'label' => __('Store Currency')]
        ];
    }
}
