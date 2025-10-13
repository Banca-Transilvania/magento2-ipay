<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Http;

use Magento\Payment\Gateway\Http\{TransferBuilder, TransferFactoryInterface, TransferInterface};

class TransferFactory implements TransferFactoryInterface
{
    public function __construct(
        private readonly TransferBuilder $transferBuilder
    ) {}

    /**
     * @param mixed[] $request
     * @return TransferInterface
     */
    public function create(array $request)
    {
        if (!empty($request['headers'])) {
            $this->transferBuilder->setHeaders($request['headers']);
        }

        return $this->transferBuilder
            ->setBody($request['body'])
            ->build();
    }
}
