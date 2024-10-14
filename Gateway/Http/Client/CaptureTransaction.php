<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Http\Client;

use BTRL\Ipay\Gateway\Config\Config;
use BTRL\Ipay\Model\Client;
use Magento\Payment\Gateway\Http\{ClientInterface, TransferInterface};

class CaptureTransaction implements ClientInterface
{
    private Client $client;

    public function __construct(
        Client $client
    ) {
        $this->client = $client;
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

        // Prepare store ID for request
        $storeId = null;
        if (isset($request['storeId'])) {
            $storeId = (int)$request['storeId'];
            unset($request['storeId']);
        }

        $response = [];
        try {
            if (!empty($request['loyalty'])) {
                $response['response_loyalty'] = $this->client->makeRequest(
                    Config::CAPTURE_ACTION,
                    $request['loyalty'],
                    $headers,
                    $storeId
                );

                unset($request['loyalty']);
            }

            $response['response'] = $this->client->makeRequest(
                Config::CAPTURE_ACTION,
                $request,
                $headers,
                $storeId
            );
        } catch (\Exception $e) {
            $response['error'] = $e->getMessage();
            $response['errorCode'] = $e->getCode();
        }

        return $response;
    }
}
