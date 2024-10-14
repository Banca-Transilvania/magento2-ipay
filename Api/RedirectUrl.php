<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Api;

interface RedirectUrl
{
    /**
     * @param string $orderId
     * @return string
     */
    public function getUrl(string $orderId): string;
}
