<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Helper;

use Magento\Framework\HTTP\Header;
use Magento\Framework\Locale\ResolverInterface;

class UserData
{
    const DESKTOP_DEVICE = 'DESKTOP';
    const MOBILE_DEVICE = 'MOBILE';
    const MOBILE_REGEX = '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i';

    public function __construct(
        private readonly ResolverInterface $localResolver,
        private readonly Header $httpHeader
    ) {}

    public function getLanguage(): string
    {
        $languageCode = explode('_', $this->localResolver->getLocale())[0];

        // Only Romanian and English are supported so far
        return ($languageCode === 'ro') ? $languageCode : 'en';
    }

    public function getDeviceType(): string
    {
        $userAgent = $this->httpHeader->getHttpUserAgent();
        $match = preg_match(self::MOBILE_REGEX, $userAgent);

        if ($match) {
            return self::MOBILE_DEVICE;
        }

        return self::DESKTOP_DEVICE;
    }
}
