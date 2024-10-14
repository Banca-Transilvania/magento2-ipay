<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Block\Form;

use Magento\Payment\Block\Form;

class Ipay extends Form
{
    /** @var string */
    protected $_template = 'BTRL_Ipay::form/ipay.phtml';
}
