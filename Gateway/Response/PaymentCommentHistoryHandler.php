<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class PaymentCommentHistoryHandler implements HandlerInterface
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
        $comment = __('BT result response:');

        if (!empty($response['response']['formUrl'])) {
            $comment .= '<br/>' . __('Redirect URL: ') . $response['response']['formUrl'];
        }

        if (!empty($response['response']['orderId'])) {
            $comment .= '<br/>' . __('BT Order ID: ') . $response['response']['orderId'];
        }

        $payment->getOrder()->addStatusHistoryComment($comment, $payment->getOrder()->getStatus());
    }
}
