<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Model;

use BTRL\Ipay\Gateway\Config\Config;
use Firebase\JWT\{JWT, Key};
use Magento\Framework\Exception\LocalizedException;

class Callback
{
    private OrderProcessor $orderProcessor;
    private Config $config;
    private string $algorithm;

    public function __construct(
        OrderProcessor $orderProcessor,
        Config $config,
        string $algorithm = 'HS256'
    ) {
        $this->orderProcessor = $orderProcessor;
        $this->config = $config;
        $this->algorithm = $algorithm;
    }

    /**
     * @throws LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function processRequest(string $rawContent): bool
    {
        $key = $this->config->getCallbackDecryptionKey();
        if (!$key) {
            throw new LocalizedException(__('Decryption key is not set!'));
        }

        // Prevent "Cannot handle token with nbf prior" error when callback is called too soon
        JWT::$leeway = 60;

        $decryptedContent = JWT::decode($rawContent, new Key(base64_decode($key), $this->algorithm));
        $payload = $decryptedContent->payload;
        if (!$payload) {
            throw new LocalizedException(__('Payload is not set!'));
        }

        $orderNumber = $payload->orderNumber;
        if (!$orderNumber) {
            throw new LocalizedException(__('Order number is missing or wrong.'));
        }

        $response = $this->orderProcessor->checkAndUpdateOrderStatus($orderNumber);

        return isset($response['success']) && $response['success'];
    }
}
