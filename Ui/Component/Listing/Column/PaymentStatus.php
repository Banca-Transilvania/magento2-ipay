<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Ui\Component\Listing\Column;

use BTRL\Ipay\Model\Config\Source\PaymentStatus as PaymentStatusOptions;
use Magento\Framework\Data\OptionSourceInterface;

class PaymentStatus implements OptionSourceInterface
{
    private PaymentStatusOptions $paymentStatusOptions;

    public function __construct(PaymentStatusOptions $paymentStatusOptions)
    {
        $this->paymentStatusOptions = $paymentStatusOptions;
    }

    /**
     * @return mixed[]
     */
    public function toOptionArray()
    {
        return $this->paymentStatusOptions->toOptionArray();
    }
}
