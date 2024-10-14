<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Request;

use BTRL\Ipay\Gateway\Config\Config as IpayConfig;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class ScaExceptionsDataBuilder implements BuilderInterface
{
    private Json $json;
    private IpayConfig $iPayConfig;

    public function __construct(
        Json $json,
        IpayConfig $iPayConfig
    ) {
        $this->json = $json;
        $this->iPayConfig = $iPayConfig;
    }

    /**
     * @param mixed[] $buildSubject
     * @return mixed[]
     */
    public function build(array $buildSubject): array
    {
        $request['body'] = [];

        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $paymentDataObject->getPayment();
        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();

        $exemption = $this->iPayConfig->getScaExemption((int)$order->getStoreId());

        if ($exemption) {
            $request['body'] = [
                'jsonParams' => $this->json->serialize([
                    'requestedScaExemptionInd' => $exemption
                ])
            ];
        }

        return $request;
    }
}
