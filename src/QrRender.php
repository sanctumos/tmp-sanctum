<?php

declare(strict_types=1);

namespace SanctumTmp;

/**
 * Try-encode QR payloads via chillerlan. Returns null when data will not fit
 * (or the library is missing) so callers can fall back to file hosting.
 */
final class QrRender
{
    private const ECL_MAP = [
        'L' => \chillerlan\QRCode\QRCode::ECC_L,
        'M' => \chillerlan\QRCode\QRCode::ECC_M,
        'Q' => \chillerlan\QRCode\QRCode::ECC_Q,
        'H' => \chillerlan\QRCode\QRCode::ECC_H,
    ];

    private const ECL_RANK = ['L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3];

    /**
     * @return array{
     *   data_base64: string,
     *   mime_type: string,
     *   width: int,
     *   height: int,
     *   ecl_effective: string,
     *   format: string,
     *   data_uri: string
     * }|null
     */
    public static function tryEncode(
        string $payload,
        string $format = 'svg',
        string $ecl = 'M',
        int $scale = 5,
        ?string $logoBase64 = null,
        int $logoSizePct = 22,
    ): ?array {
        if ($payload === '' || !class_exists(\chillerlan\QRCode\QRCode::class)) {
            return null;
        }

        $format = strtolower($format);
        if (!in_array($format, ['svg', 'png'], true)) {
            $format = 'svg';
        }
        if ($format === 'png' && !extension_loaded('gd')) {
            $format = 'svg';
        }

        $eclKey = strtoupper($ecl);
        if (!isset(self::ECL_MAP[$eclKey])) {
            $eclKey = 'M';
        }
        $effective = $eclKey;
        if ($logoBase64 !== null && $logoBase64 !== '' && (self::ECL_RANK[$effective] ?? 0) < self::ECL_RANK['Q']) {
            $effective = 'Q';
        }

        try {
            if ($format === 'png') {
                return self::renderPng($payload, $effective, $scale, $logoBase64, $logoSizePct);
            }
            return self::renderSvg($payload, $effective, $scale, $logoBase64, $logoSizePct);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *   data_base64: string,
     *   mime_type: string,
     *   width: int,
     *   height: int,
     *   ecl_effective: string,
     *   format: string,
     *   data_uri: string
     * }
     */
    private static function renderSvg(
        string $payload,
        string $effective,
        int $scale,
        ?string $logoBase64,
        int $logoSizePct,
    ): array {
        $options = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => self::ECL_MAP[$effective],
            'scale' => max(2, min(20, $scale)),
            'addQuietzone' => true,
            'imageBase64' => false,
        ]);

        $svg = (new \chillerlan\QRCode\QRCode($options))->render($payload);
        if (!is_string($svg) || $svg === '') {
            throw new \RuntimeException('SVG render empty');
        }

        // chillerlan may still return a data URI depending on options/version
        if (str_starts_with($svg, 'data:image/svg+xml')) {
            $comma = strpos($svg, ',');
            $raw = $comma === false ? '' : substr($svg, $comma + 1);
            if (str_contains(substr($svg, 0, $comma ?: 0), ';base64')) {
                $decoded = base64_decode($raw, true);
                $svg = is_string($decoded) ? $decoded : $svg;
            } else {
                $svg = rawurldecode($raw);
            }
        }

        $width = 0;
        $height = 0;
        if (preg_match('/width="(\d+(?:\.\d+)?)"/', $svg, $m)) {
            $width = (int) round((float) $m[1]);
        }
        if (preg_match('/height="(\d+(?:\.\d+)?)"/', $svg, $m)) {
            $height = (int) round((float) $m[1]);
        }
        if ($width === 0 && preg_match('/viewBox="0 0 (\d+(?:\.\d+)?) (\d+(?:\.\d+)?)"/', $svg, $m)) {
            $width = (int) round((float) $m[1]);
            $height = (int) round((float) $m[2]);
        }
        if ($height === 0) {
            $height = $width;
        }

        if ($logoBase64 !== null && $logoBase64 !== '' && $width > 0) {
            $svg = self::injectSvgLogo($svg, $width, $logoBase64, $logoSizePct);
        }

        $b64 = base64_encode($svg);
        return [
            'data_base64' => $b64,
            'mime_type' => 'image/svg+xml',
            'width' => $width,
            'height' => $height,
            'ecl_effective' => $effective,
            'format' => 'svg',
            'data_uri' => 'data:image/svg+xml;base64,' . $b64,
        ];
    }

    /**
     * @return array{
     *   data_base64: string,
     *   mime_type: string,
     *   width: int,
     *   height: int,
     *   ecl_effective: string,
     *   format: string,
     *   data_uri: string
     * }
     */
    private static function renderPng(
        string $payload,
        string $effective,
        int $scale,
        ?string $logoBase64,
        int $logoSizePct,
    ): array {
        $options = new \chillerlan\QRCode\QROptions([
            'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => self::ECL_MAP[$effective],
            'scale' => max(2, min(20, $scale)),
            'addQuietzone' => true,
            'imageBase64' => false,
        ]);

        $png = (new \chillerlan\QRCode\QRCode($options))->render($payload);
        if (!is_string($png) || $png === '') {
            throw new \RuntimeException('PNG render empty');
        }

        if (str_starts_with($png, 'data:image/png;base64,')) {
            $decoded = base64_decode(substr($png, strlen('data:image/png;base64,')), true);
            if (!is_string($decoded)) {
                throw new \RuntimeException('PNG base64 decode failed');
            }
            $png = $decoded;
        }

        $img = @imagecreatefromstring($png);
        if ($img === false) {
            throw new \RuntimeException('PNG GD load failed');
        }
        $width = imagesx($img);
        $height = imagesy($img);

        if ($logoBase64 !== null && $logoBase64 !== '') {
            self::overlayLogo($img, $width, $logoBase64, $logoSizePct);
        }

        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        imagedestroy($img);
        if ($bytes === false) {
            throw new \RuntimeException('PNG encode failed');
        }

        $b64 = base64_encode($bytes);
        return [
            'data_base64' => $b64,
            'mime_type' => 'image/png',
            'width' => $width,
            'height' => $height,
            'ecl_effective' => $effective,
            'format' => 'png',
            'data_uri' => 'data:image/png;base64,' . $b64,
        ];
    }

    private static function injectSvgLogo(string $svg, int $size, string $logoBase64, int $logoSizePct): string
    {
        $raw = self::decodeLogo($logoBase64);
        if ($raw === null) {
            return $svg;
        }
        $pct = max(10, min(30, $logoSizePct)) / 100;
        $logoSide = $size * $pct;
        $padSide = $logoSide * 1.14;
        $cx = $size / 2;
        $cy = $size / 2;
        $padX = $cx - $padSide / 2;
        $padY = $cy - $padSide / 2;
        $radius = max(4, $padSide * 0.12);
        $imgX = $cx - $logoSide / 2;
        $imgY = $cy - $logoSide / 2;

        $mime = 'image/png';
        if (str_starts_with($raw, "\x89PNG")) {
            $mime = 'image/png';
        } elseif (str_starts_with($raw, "\xff\xd8\xff")) {
            $mime = 'image/jpeg';
        } elseif (str_starts_with($raw, 'GIF')) {
            $mime = 'image/gif';
        }

        $dataUri = 'data:' . $mime . ';base64,' . base64_encode($raw);
        $overlay = sprintf(
            '<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="#ffffff"/>'
            . '<image href="%s" x="%.2f" y="%.2f" width="%.2f" height="%.2f"/>',
            $padX,
            $padY,
            $padSide,
            $padSide,
            $radius,
            htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8'),
            $imgX,
            $imgY,
            $logoSide,
            $logoSide
        );

        if (str_contains($svg, '</svg>')) {
            return str_replace('</svg>', $overlay . '</svg>', $svg);
        }
        return $svg . $overlay;
    }

    private static function overlayLogo(\GdImage $img, int $canvasSize, string $logoBase64, int $logoSizePct): void
    {
        $raw = self::decodeLogo($logoBase64);
        if ($raw === null) {
            return;
        }
        $logo = @imagecreatefromstring($raw);
        if ($logo === false) {
            return;
        }

        $pct = max(10, min(30, $logoSizePct)) / 100;
        $logoSide = (int) round($canvasSize * $pct);
        $padSide = (int) round($logoSide * 1.14);
        $cx = (int) ($canvasSize / 2);
        $cy = (int) ($canvasSize / 2);
        $padX = (int) ($cx - $padSide / 2);
        $padY = (int) ($cy - $padSide / 2);
        $radius = max(4, (int) round($padSide * 0.12));

        $white = imagecolorallocate($img, 255, 255, 255);
        if ($white === false) {
            imagedestroy($logo);
            return;
        }
        self::filledRoundRect($img, $padX, $padY, $padSide, $padSide, $radius, $white);

        $dstX = (int) ($cx - $logoSide / 2);
        $dstY = (int) ($cy - $logoSide / 2);
        imagecopyresampled($img, $logo, $dstX, $dstY, 0, 0, $logoSide, $logoSide, imagesx($logo), imagesy($logo));
        imagedestroy($logo);
    }

    private static function decodeLogo(string $logoBase64): ?string
    {
        $b64 = $logoBase64;
        if (preg_match('/^data:image\/[a-zA-Z0-9.+-]+;base64,(.+)$/s', $b64, $m)) {
            $b64 = $m[1];
        }
        $raw = base64_decode(preg_replace('/\s+/', '', $b64) ?? '', true);
        if ($raw === false || $raw === '' || strlen($raw) > 2 * 1024 * 1024) {
            return null;
        }
        return $raw;
    }

    private static function filledRoundRect(\GdImage $img, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        $r = min($r, (int) ($w / 2), (int) ($h / 2));
        imagefilledrectangle($img, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($img, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        imagefilledellipse($img, $x + $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($img, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
    }
}
