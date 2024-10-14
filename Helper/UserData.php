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

    private ResolverInterface $localResolver;
    private Header $httpHeader;

    public function __construct(
        ResolverInterface $localResolver,
        Header $httpHeader
    ) {
        $this->localResolver = $localResolver;
        $this->httpHeader = $httpHeader;
    }

    public function getLanguage(): string
    {
        $languageCode = explode('_', $this->localResolver->getLocale())[0];

        // Only Romanian and English are supported so far
        return ($languageCode === 'ro') ? $languageCode : 'en';
    }

    public function getDeviceType(): string
    {
        $userAgent = $this->httpHeader->getHttpUserAgent();
        $mobileRegex = '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i';
        $match = preg_match($mobileRegex, $userAgent);

        if ($match) {
            return self::MOBILE_DEVICE;
        }

        return self::DESKTOP_DEVICE;
    }
}
