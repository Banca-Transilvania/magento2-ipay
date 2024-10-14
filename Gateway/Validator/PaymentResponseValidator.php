<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Validator;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Validator\{AbstractValidator, ResultInterface};

class PaymentResponseValidator extends AbstractValidator
{
    /**
     * @param mixed[] $validationSubject
     * @return ResultInterface
     * @throws LocalizedException
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
        $responseData = $response['response'] ?? '';
        if ($responseData) {
            $payment->setAdditionalInformation('response', $responseData);

            if (
                empty($responseData['orderId'])
                || empty($responseData['formUrl'])
            ) {
                if (!empty($responseData['errorCode'])) {
                    $errorCodes[] = $responseData['errorCode'];
                    $isValid = false;
                } else {
                    throw new LocalizedException(__('Error with payment method. Please select different payment method.'));
                }
            }
        } else {
            throw new LocalizedException(__('Error with payment method. Please select different payment method.'));
        }

        return $this->createResult($isValid, $errorMessages, $errorCodes);
    }
}
