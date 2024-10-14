<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Gateway\Config;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    // Gateway URLs
    const GATEWAY_TEST_URL = 'https://ecclients-sandbox.btrl.ro';
    const GATEWAY_PRODUCTION_URL = 'https://ecclients.btrl.ro';

    // Endpoints
    const ONE_PHASE_ENDPOINT = '/payment/rest/register.do';
    const TWO_PHASE_ENDPOINT = '/payment/rest/registerPreAuth.do';
    const CAPTURE_ENDPOINT = '/payment/rest/deposit.do';
    const ORDER_STATUS_ENDPOINT = '/payment/rest/getOrderStatusExtended.do';
    const ORDER_FINISHED_PAYMENT_INFO_ENDPOINT = '/payment/rest/getFinishedPaymentInfo.do';
    const REFUND_ENDPOINT = '/payment/rest/refund.do';
    const CANCEL_ENDPOINT = '/payment/rest/reverse.do';

    // Configuration fields
    const KEY_ACTIVE = 'active';
    const KEY_PAYMENT_ACTION = 'payment_action';
    const KEY_PAYMENT_PHASE = 'phase';
    const KEY_MERCHANT_USERNAME = 'merchant_username';
    const KEY_MERCHANT_PASSWORD = 'merchant_pass';
    const KEY_TEST_MODE = 'testmode';
    const KEY_ORDER_STATUS_NEW = 'order_status_new';
    const KEY_ORDER_STATUS_PAID = 'order_status_paid';
    const KEY_BANK_DESCRIPTION = 'bank_description';
    const KEY_SEND_INVOICE_EMAIL = 'send_auto_invoice_email';
    const SCA_EXEMPTION = 'sca_exemption';
    const CHARGED_CURRENCY = 'charged_currency';
    const KEY_CALLBACK_DECRYPTION_KEY = 'callback/decryption_key';

    // Gateway actions
    const AUTHORIZE_ACTION = 'authorize';
    const CANCEL_ACTION = 'cancel';
    const CAPTURE_ACTION = 'capture';
    const INFO_ACTION = 'info';
    const REFUND_ACTION = 'refund';
    const SALE_ACTION = 'sales';
    const STATUS_ACTION = 'status';

    public function isTestMode(?int $storeId = null): bool
    {
        return (bool)$this->getValue(Config::KEY_TEST_MODE, $storeId);
    }

    public function getMerchantUsername(?int $storeId = null): string
    {
        return (string)$this->getValue(Config::KEY_MERCHANT_USERNAME, $storeId);
    }

    public function getMerchantPassword(?int $storeId = null): string
    {
        return (string)$this->getValue(self::KEY_MERCHANT_PASSWORD, $storeId);
    }

    public function getNewOrderStatus(?int $storeId = null): string
    {
        return (string)$this->getValue(self::KEY_ORDER_STATUS_NEW, $storeId);
    }

    public function getPaidOrderStatus(?int $storeId = null): string
    {
        return (string)$this->getValue(self::KEY_ORDER_STATUS_PAID, $storeId);
    }

    public function getScaExemption(?int $storeId = null): string
    {
        return (string)$this->getValue(self::SCA_EXEMPTION, $storeId);
    }

    public function getGatewayUrl(string $paymentAction, ?int $storeId = null): string
    {
        $baseUrl = self::GATEWAY_PRODUCTION_URL;
        if ($this->isTestMode($storeId)) {
            $baseUrl = self::GATEWAY_TEST_URL;
        }

        $url = '';
        switch ($paymentAction) {
            case self::AUTHORIZE_ACTION:
                $url = $baseUrl . self::TWO_PHASE_ENDPOINT;
                break;
            case self::CANCEL_ACTION:
                $url = $baseUrl . self::CANCEL_ENDPOINT;
                break;
            case self::CAPTURE_ACTION:
                $url = $baseUrl . self::CAPTURE_ENDPOINT;
                break;
            case self::INFO_ACTION:
                $url = $baseUrl . self::ORDER_FINISHED_PAYMENT_INFO_ENDPOINT;
                break;
            case self::REFUND_ACTION:
                $url = $baseUrl . self::REFUND_ENDPOINT;
                break;
            case self::SALE_ACTION:
                $url = $baseUrl . self::ONE_PHASE_ENDPOINT;
                break;
            case self::STATUS_ACTION:
                $url = $baseUrl . self::ORDER_STATUS_ENDPOINT;
                break;
        }

        return $url;
    }

    public function getPaymentAction(?int $storeId = null): string
    {
        return (string)$this->getValue(self::KEY_PAYMENT_ACTION, $storeId);
    }

    public function getPaymentPhase(?int $storeId = null): string
    {
        return (string)$this->getValue(self::KEY_PAYMENT_PHASE, $storeId);
    }

    public function isActive(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::KEY_ACTIVE, $storeId);
    }

    public function getBankDescription(?int $storeId = null): string
    {
        return (string)$this->getValue(self::KEY_BANK_DESCRIPTION, $storeId);
    }

    /**
     * Check if auto-invoice email can be sent
     */
    public function canSendInvoiceEmail(?int $storeId = null): bool
    {
        return (bool)$this->getValue(self::KEY_SEND_INVOICE_EMAIL, $storeId);
    }

    public function getChargedCurrencyType(?int $storeId = null): string
    {
        return (string)$this->getValue(self::CHARGED_CURRENCY, $storeId);
    }

    public function getCallbackDecryptionKey(?int $storeId = null): string
    {
        return (string)$this->getValue(self::KEY_CALLBACK_DECRYPTION_KEY, $storeId);
    }
}
