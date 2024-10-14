<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Model\Config\Source;

use BTRL\Ipay\Gateway\Config\Config;
use Magento\Framework\Data\OptionSourceInterface;

class Phase implements OptionSourceInterface
{
    /**
     * @return mixed[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::SALE_ACTION, 'label' => __('One Phase')],
            ['value' => Config::AUTHORIZE_ACTION, 'label' => __('Two Phase')]
        ];
    }
}
