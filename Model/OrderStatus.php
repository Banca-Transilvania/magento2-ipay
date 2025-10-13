<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Model;

use BTRL\Ipay\Gateway\Config\Config;

class OrderStatus
{
    public function __construct(
        private readonly Client $client
    ) {}

    /**
     * @param mixed[] $request
     * @return mixed[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function checkStatus(array $request, int $storeId): array
    {
        return $this->client->makeRequest(Config::STATUS_ACTION, $request, [], $storeId);
    }
}
