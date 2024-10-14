<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Http\Client;

use BTRL\Ipay\Gateway\Config\Config as IpayConfig;
use BTRL\Ipay\Model\Client;
use Magento\Payment\Gateway\Http\{ClientInterface, TransferInterface};

class PaymentTransaction implements ClientInterface
{
    private Client $client;
    private IpayConfig $iPayConfig;

    public function __construct(
        Client $client,
        IpayConfig $iPayConfig
    ) {
        $this->client = $client;
        $this->iPayConfig = $iPayConfig;
    }

    /**
     * @return mixed[]
     */
    public function placeRequest(TransferInterface $transferObject): array
    {
        $request = $transferObject->getBody();
        $headers = $transferObject->getHeaders();
        if (!is_array($request)) {
            $request = [$request];
        }

        // If the payments call is already done (Initiate has already a response) return the request
        if (!empty($request['orderId'])) {
            return $request;
        }

        $response = [];
        try {
            $phase = $this->iPayConfig->getPaymentPhase();
            $response['response'] = $this->client->makeRequest($phase, $request, $headers);
        } catch (\Exception $e) {
            $response['error'] = $e->getMessage();
            $response['errorCode'] = $e->getCode();
        }

        return $response;
    }
}
