<?php
/**
 * Copyright © Banca Transilvania. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace BTRL\Ipay\Helper;

use Psr\Log\LoggerInterface;

class Transliteration
{
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function transliterate(string $content): string
    {
        try {
            $transliterations = $this->getTransliterations();
            $content = preg_replace(array_keys($transliterations), array_values($transliterations), $content);

            setlocale(LC_ALL, 'en_US');
            $content = iconv('UTF-8', 'ASCII//TRANSLIT', $content);
        } catch (\Exception $exception) {
            $this->logger->critical('BT iPay Transliteration: ' . $exception->getMessage());
        }

        return (string)$content;
    }

    /**
     * @return string[]
     */
    public function getTransliterations(): array
    {
        return [
            '/[áàâãªäă]/u' => 'a',
            '/[ÁÀÂÃÄĂ]/u' => 'A',
            '/[ÍÌÎÏ]/u' => 'I',
            '/[íìîï]/u' => 'i',
            '/[éèêë]/u' => 'e',
            '/[ÉÈÊË]/u' => 'E',
            '/[óòôõºö]/u' => 'o',
            '/[ÓÒÔÕÖ]/u' => 'O',
            '/[šşș]/u' => 's',
            '/[ŠŞȘ]/u' => 'S',
            '/[úùûü]/u' => 'u',
            '/[ÚÙÛÜ]/u' => 'U',
            '/ç/' => 'c',
            '/Ç/' => 'C',
            '/ñ/' => 'n',
            '/Ñ/' => 'N',
            '/ț/' => 't',
            '/Ț/' => 'T',
            '/–/' => '-', // UTF-8 hyphen to "normal" hyphen
            '/[’‘‹›‚]/u' => ' ', // Literally a single quote
            '/[“”«»„]/u' => ' ', // Double quote
            '/ /' => ' ', // Non-breaking space (equiv. to 0x160)
        ];
    }
}
