<?php

declare(strict_types=1);

namespace SanctumTmp;

final class QrSvg
{
    /**
     * Encode text as inline SVG QR markup, or null if the library is unavailable.
     */
    public static function encode(string $text): ?string
    {
        if (!class_exists(\chillerlan\QRCode\QRCode::class)) {
            return null;
        }

        try {
            $options = new \chillerlan\QRCode\QROptions([
                'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
                'eccLevel' => \chillerlan\QRCode\QRCode::ECC_M,
                'scale' => 5,
                'addQuietzone' => true,
            ]);

            return (new \chillerlan\QRCode\QRCode($options))->render($text);
        } catch (\Throwable) {
            return null;
        }
    }
}
