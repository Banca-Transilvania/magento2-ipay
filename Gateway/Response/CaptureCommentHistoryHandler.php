<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class CaptureCommentHistoryHandler implements HandlerInterface
{
    /**
     * @param mixed[] $handlingSubject
     * @param mixed[] $response
     */
    public function handle(array $handlingSubject, array $response): void
    {
        $readPayment = SubjectReader::readPayment($handlingSubject);
        /** @var \Magento\Payment\Model\Info $payment */
        $payment = $readPayment->getPayment();
        $comment = __('BT capture response:');

        if (!empty($response['response']['errorCode'])) {
            $comment .= '<br/>' . __('Response Code: %1', $response['response']['errorCode']);
        }

        if (!empty($response['response']['errorMessage'])) {
            $comment .= '<br/>' . __('Response Message: %1', $response['response']['errorMessage']);
        }

        $payment->getOrder()->addStatusHistoryComment($comment, $payment->getOrder()->getStatus());
    }
}
