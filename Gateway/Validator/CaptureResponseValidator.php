<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Validator;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\{AbstractValidator, ResultInterface};

class CaptureResponseValidator extends AbstractValidator
{
    /**
     * @param mixed[] $validationSubject
     * @return ResultInterface
     */
    public function validate(array $validationSubject)
    {
        $response = SubjectReader::readResponse($validationSubject);

        $paymentDataObjectInterface = SubjectReader::readPayment($validationSubject);
        $payment = $paymentDataObjectInterface->getPayment();

        $isValid = true;
        $errorMessages = [];
        $errorCodes = [];

        // Validate result
        if (!empty($response['response'])) {
            $responseData = $response['response'];
            $payment->setAdditionalInformation('capture_response', $responseData);

            $actionCode = $responseData['actionCode'] ?? null;
            $errorCode = $responseData['errorCode'] ?? null;
            $errorMessage = $responseData['errorMessage'] ?? null;

            if (
                (!$actionCode || (int)$actionCode !== 0)
                && (int)$errorCode
                && $errorMessage
            ) {
                $errorCodes[] = $errorCode;
                $isValid = false;
            }
        } else {
            $errorMessages[] = __('Error with capture on transaction.');
            $isValid = false;
        }

        return $this->createResult($isValid, $errorMessages, $errorCodes);
    }
}
