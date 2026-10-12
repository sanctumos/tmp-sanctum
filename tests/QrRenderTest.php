<?php

declare(strict_types=1);

namespace SanctumTmp\Tests;

use PHPUnit\Framework\TestCase;
use SanctumTmp\QrRender;
use SanctumTmp\QrSvg;

final class QrRenderTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(\chillerlan\QRCode\QRCode::class)) {
            $this->markTestSkipped('chillerlan/php-qrcode not installed');
        }
    }

    public function test_try_encode_svg_success(): void
    {
        $r = QrRender::tryEncode('https://tmp.sanctumos.org', 'svg', 'M');
        $this->assertNotNull($r);
        $this->assertSame('svg', $r['format']);
        $this->assertSame('image/svg+xml', $r['mime_type']);
        $this->assertGreaterThan(0, $r['width']);
        $this->assertStringStartsWith('data:image/svg+xml', $r['data_uri']);
    }

    public function test_try_encode_falls_back_when_too_large(): void
    {
        $r = QrRender::tryEncode(str_repeat('A', 4000), 'svg', 'M');
        $this->assertNull($r);
    }

    public function test_qr_svg_wrapper(): void
    {
        $svg = QrSvg::encode('hello');
        $this->assertNotNull($svg);
        $this->assertTrue(
            str_contains($svg, '<svg') || str_starts_with($svg, 'data:image/svg+xml')
        );
    }

    public function test_empty_payload_returns_null(): void
    {
        $this->assertNull(QrRender::tryEncode('', 'svg', 'M'));
    }

    public function test_svg_with_logo_injects_image(): void
    {
        // 1x1 PNG
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $this->assertNotFalse($png);
        $r = QrRender::tryEncode(
            'https://example.com/logo-test',
            'svg',
            'M',
            5,
            'data:image/png;base64,' . base64_encode($png),
            22,
        );
        $this->assertNotNull($r);
        $svg = base64_decode($r['data_base64']);
        $this->assertIsString($svg);
        $this->assertStringContainsString('<image href=', $svg);
        $this->assertSame('Q', $r['ecl_effective']); // logo bumps ECL to Q
    }

    public function test_unknown_format_defaults_to_svg(): void
    {
        $r = QrRender::tryEncode('hi', 'gif', 'M');
        $this->assertNotNull($r);
        $this->assertSame('svg', $r['format']);
    }

    public function test_png_encode_with_optional_logo(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD required for PNG path');
        }
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $r = QrRender::tryEncode(
            'https://example.com/png',
            'png',
            'M',
            4,
            base64_encode($png),
            18,
        );
        $this->assertNotNull($r);
        $this->assertSame('png', $r['format']);
        $this->assertSame('image/png', $r['mime_type']);
        $this->assertGreaterThan(0, $r['width']);
        $bytes = base64_decode($r['data_base64']);
        $this->assertNotFalse($bytes);
        $this->assertSame("\x89PNG", substr($bytes, 0, 4));
    }
}
