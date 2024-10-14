<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Model;

use BTRL\Ipay\Gateway\Config\Config as IpayConfig;
use Magento\Framework\HTTP\Client\{Curl, CurlFactory};
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Client
{
    private IpayConfig $iPayConfig;
    private CurlFactory $curlFactory;
    private StoreManagerInterface $storeManager;
    private Json $json;
    private LoggerInterface $logger;
    private DebugLogger $debugLogger;

    public function __construct(
        CurlFactory $curlFactory,
        IpayConfig $iPayConfig,
        StoreManagerInterface $storeManager,
        Json $json,
        LoggerInterface $logger,
        DebugLogger $debugLogger
    ) {
        $this->curlFactory = $curlFactory;
        $this->iPayConfig = $iPayConfig;
        $this->storeManager = $storeManager;
        $this->json = $json;
        $this->logger = $logger;
        $this->debugLogger = $debugLogger;
    }

    /**
     * @param mixed[] $request
     * @param mixed[] $headers
     * @return mixed[]
     * @throws NoSuchEntityException
     */
    public function makeRequest(string $paymentAction, array $request, array $headers = [], int $storeId = null): array
    {
        if ($storeId === null) {
            $storeId = (int)$this->storeManager->getStore()->getId();
        }

        $client = $this->initializeClient();
        $gatewayUrl = $this->iPayConfig->getGatewayUrl($paymentAction, $storeId);

        // Log request for debug
        $this->debugLogger->logData('Start ' . $paymentAction . ':');
        $this->debugLogger->logData('Request: ' . (string)$this->json->serialize($request));

        // Add API credentials to request
        $request['userName'] = $this->iPayConfig->getMerchantUsername($storeId);
        $request['password'] = $this->iPayConfig->getMerchantPassword($storeId);

        $client->setHeaders($headers);
        $client->post($gatewayUrl, $request);
        $response = $client->getBody();
        $result = null;

        try {
            $result = $this->json->unserialize($response);
        } catch (\Exception $e) {
            $this->logger->critical('BT iPay: ' . $e->getMessage());
        }

        if (!is_array($result)) {
            $result = [$response];
        }

        // Log response for debug
        $this->debugLogger->logData('Response: ' . (string)$this->json->serialize($response));
        $this->debugLogger->logData('End ' . $paymentAction . ':');

        return $result;
    }

    private function initializeClient(): Curl
    {
        return $this->curlFactory->create();
    }
}
