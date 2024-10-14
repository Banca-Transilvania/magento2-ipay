<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Controller\Callback;

use BTRL\Ipay\Model\Callback;
use BTRL\Ipay\Model\DebugLogger;
use Magento\Framework\App\{
    Action\HttpPostActionInterface,
    CsrfAwareActionInterface,
    Request\InvalidRequestException,
    RequestInterface
};
use Magento\Framework\Controller\ResultFactory;
use Psr\Log\LoggerInterface;

class Index implements CsrfAwareActionInterface, HttpPostActionInterface
{
    const HTTP_INTERNAL_SUCCESS = 200;
    const HTTP_INTERNAL_ERROR = 500;

    private RequestInterface $request;
    private ResultFactory $resultFactory;
    private Callback $callback;
    private LoggerInterface $logger;
    private DebugLogger $debugLogger;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        Callback $callback,
        LoggerInterface $logger,
        DebugLogger $debugLogger
    ) {
        $this->request = $request;
        $this->resultFactory = $resultFactory;
        $this->callback = $callback;
        $this->logger = $logger;
        $this->debugLogger = $debugLogger;
    }

    public function execute()
    {
        $rawContent = $this->request->getContent(); /** @phpstan-ignore-line */
        $responseCode = self::HTTP_INTERNAL_ERROR;
        $response = [
            'success' => false,
        ];

        if ($rawContent) {
            try {
                if ($this->callback->processRequest($rawContent)) {
                    $responseCode = self::HTTP_INTERNAL_SUCCESS;
                    $response = [
                        'success' => true,
                    ];
                }
            } catch (\Throwable $exception) {
                $this->logger->critical('BT iPay Callback: ' . $exception->getMessage());

                // Log response for debug
                $this->debugLogger->logData('Callback Error: ' . $exception->getMessage());
                $this->debugLogger->logData('Callback Request: ' . $rawContent);
            }
        }

        /** @var \Magento\Framework\Controller\Result\Json $resultJson */
        $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $resultJson->setHttpResponseCode($responseCode);
        $resultJson->setData($response);

        return $resultJson;
    }

    public function createCsrfValidationException(
        RequestInterface $request
    ): ?InvalidRequestException {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
