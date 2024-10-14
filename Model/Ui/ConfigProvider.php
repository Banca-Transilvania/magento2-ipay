<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Model\Ui;

use BTRL\Ipay\Gateway\Config\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    const CODE = 'btrl_ipay';

    private Config $config;
    private RequestInterface $request;
    private StoreManagerInterface $storeManager;
    private UrlInterface $urlBuilder;

    public function __construct(
        Config $config,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        UrlInterface $urlBuilder
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @return mixed[]
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getConfig(): array
    {
        $storeId = (int)$this->getStoreManager()->getStore()->getId();
        $isActive = $this->getGatewayConfig()->isActive($storeId);

        return [
            'payment' => [
                self::CODE => [
                    'isActive' => $isActive,
                    'successPage' => $this->getUrlBuilder()->getUrl(
                        'checkout/onepage/success',
                        ['_secure' => $this->getRequest()->isSecure()]
                    )
                ]
            ]
        ];
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    public function getUrlBuilder(): UrlInterface
    {
        return $this->urlBuilder;
    }

    public function getStoreManager(): StoreManagerInterface
    {
        return $this->storeManager;
    }

    public function getGatewayConfig(): Config
    {
        return $this->config;
    }
}
