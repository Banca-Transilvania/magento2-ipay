<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class DebugLogger
{
    const CONFIG_PATH_LOG_ENABLE = 'payment/btrl_ipay/debug_log/enable';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    public function logData(string $data): void
    {
        if ($this->canLog()) {
            $this->logger->debug(
                preg_replace('/[\\n\\r]+/', '', $data)
            );
        }
    }

    public function canLog(): bool
    {
        return (bool)$this->scopeConfig->getValue(
            self::CONFIG_PATH_LOG_ENABLE,
            ScopeInterface::SCOPE_WEBSITE
        );
    }
}
