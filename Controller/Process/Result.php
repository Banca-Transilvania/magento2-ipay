<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Controller\Process;

use BTRL\Ipay\Model\OrderProcessor;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\{
    Action\HttpGetActionInterface,
    RequestInterface,
    ResponseInterface,
    Response\RedirectInterface
};
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Payment\Gateway\ErrorMapper\ErrorMessageMapper;

class Result implements HttpGetActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly RedirectInterface $redirect,
        private readonly CheckoutSession $checkoutSession,
        private readonly OrderProcessor $orderProcessor,
        private readonly ErrorMessageMapper $errorMessageMapper,
        private readonly MessageManager $messageManager
    ) {}

    public function execute()
    {
        // Receive all params from GET request
        $response = $this->request->getParams();

        $arguments = [];
        if ($this->checkAndValidateResponse($response)) {
            // Valid => go to success page
            $path = 'checkout/onepage/success';
        } else {
            // Something went wrong => reopen quote and redirect back to cart
            $this->checkoutSession->restoreQuote();
            $path = 'checkout/cart/index';
        }

        $this->redirect->redirect($this->response, $path, $arguments);

        return $this->response;
    }

    /**
     * @param mixed[] $response
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function checkAndValidateResponse(array $response): bool
    {
        $orderNumber = $response['orderNumber'] ?? '';

        $updateResponse = $this->orderProcessor->checkAndUpdateOrderStatus($orderNumber);
        $responseStatus = $updateResponse['success'] ?? false;
        $errorCode = $updateResponse['error_code'] ?? false;

        if ($errorCode) {
            $this->processErrorCode((string)$errorCode);
        }

        return $responseStatus;
    }

    protected function processErrorCode(string $errorCode): void
    {
        if ($message = $this->errorMessageMapper->getMessage($errorCode)) {
            $this->messageManager->addErrorMessage(__('Payment was declined. Error: %1', $message));
        } else {
            $this->messageManager->addErrorMessage(__('Payment was declined. Please retry.'));
        }
    }
}
