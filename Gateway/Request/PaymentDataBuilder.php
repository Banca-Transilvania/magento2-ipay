<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Request;

use BTRL\Ipay\Gateway\Config\Config;
use BTRL\Ipay\Helper\{
    Country,
    Currency,
    Phone,
    Transliteration,
    UserData
};
use BTRL\Ipay\Model\OrderProcessor;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Data\PaymentDataObject;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

class PaymentDataBuilder implements BuilderInterface
{
    const DELIVERY_TYPE = 'courier';

    private UrlInterface $urlBuilder;
    private UserData $userData;
    private Json $json;
    private Config $config;
    private Country $country;
    private Currency $currency;
    private Phone $phone;
    private Transliteration $transliteration;
    private OrderProcessor $orderProcessor;

    public function __construct(
        UrlInterface $urlBuilder,
        UserData $userData,
        Json $json,
        Config $config,
        Country $country,
        Currency $currency,
        Phone $phone,
        Transliteration $transliteration,
        OrderProcessor $orderProcessor
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->userData = $userData;
        $this->json = $json;
        $this->config = $config;
        $this->country = $country;
        $this->currency = $currency;
        $this->phone = $phone;
        $this->transliteration = $transliteration;
        $this->orderProcessor = $orderProcessor;
    }

    /**
     * @param mixed[] $buildSubject
     * @return mixed[]
     * @throws LocalizedException
     */
    public function build(array $buildSubject): array
    {
        /** @var PaymentDataObject $paymentDataObject */
        $paymentDataObject = SubjectReader::readPayment($buildSubject);
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $payment = $paymentDataObject->getPayment();
        /** @var Order $order */
        $order = $payment->getOrder();

        $incrementId = $order->getIncrementId();
        $currencyNumber = $this->currency->getCurrencyNumber($this->orderProcessor->getChargedCurrencyCode($order));
        if (!$currencyNumber) {
            throw new LocalizedException(__('Currency is not supported!'));
        }

        $request['body'] = [
            'orderNumber' => $incrementId,
            'amount' => $this->orderProcessor->getChargedAmount($order),
            'currency' => $currencyNumber,
            'returnUrl' => $this->urlBuilder->getUrl('ipay/process/result', ['orderNumber' => $incrementId]),
            'description' => $this->config->getBankDescription((int)$order->getStoreId()) ?:
                $payment->getMethodInstance()->getTitle(),
            'language' => $this->userData->getLanguage(),
            'pageView' => $this->userData->getDeviceType(),
            'email' => $order->getCustomerEmail(),
            'orderBundle' => $this->getOrderBundleData($order)
        ];

        return $request;
    }

    private function getOrderBundleData(OrderInterface $order): string
    {
        /** @var Order $order */
        $billingAddress = $shippingAddress = $order->getBillingAddress();
        if ($order->hasShippingAddressId()) {
            $shippingAddress = $order->getShippingAddress();
        }

        $data = [
            'orderCreationDate' => date('Y-m-d'),
            'customerDetails' => [
                'email' => $order->getCustomerEmail(),
                'phone' => $this->phone->getFormattedPhoneNumber(
                    $billingAddress->getTelephone(),
                    $billingAddress->getCountryId()
                ),
                'contact' => $this->transliteration->transliterate($order->getCustomerName()),
                'deliveryInfo' => [
                    'deliveryType' => self::DELIVERY_TYPE,
                    'country' => $this->country->getCountryCode($shippingAddress->getCountryId()),
                    'city' => $this->transliteration->transliterate($shippingAddress->getCity()),
                    'postAddress' => $this->transliteration->transliterate(
                        implode(', ', $shippingAddress->getStreet())
                    )
                ],
                'billingInfo' => [
                    'country' => $this->country->getCountryCode($billingAddress->getCountryId()),
                    'city' => $this->transliteration->transliterate($billingAddress->getCity()),
                    'postAddress' => $this->transliteration->transliterate(
                        implode(', ', $billingAddress->getStreet())
                    )
                ]
            ]
        ];

        return (string)$this->json->serialize($data);
    }
}
