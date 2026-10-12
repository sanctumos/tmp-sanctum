<?php

declare(strict_types=1);

namespace SanctumTmp;

final class QrSvg
{
    /**
     * Encode text as inline SVG markup or a data-URI image, or null if encoding fails.
     */
    public static function encode(string $text): ?string
    {
        $rendered = QrRender::tryEncode($text, 'svg', 'M', 5);
        if ($rendered === null) {
            return null;
        }

        return $rendered['data_uri'];
    }
}
