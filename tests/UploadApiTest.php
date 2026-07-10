<?php

declare(strict_types=1);

namespace SanctumTmp\Tests;

use PHPUnit\Framework\TestCase;
use SanctumTmp\QrSvg;
use SanctumTmp\UploadApi;

final class UploadApiTest extends TestCase
{
    public function test_success_payload_shape(): void
    {
        $payload = UploadApi::successPayload([
            'view_url' => 'https://tmp.test/abc/file.txt',
            'direct_url' => 'https://tmp.test/uploads/abc/file.txt',
            'id' => 'abc',
            'filename' => 'file.txt',
        ]);

        $this->assertSame('success', $payload['status']);
        $this->assertSame('https://tmp.test/abc/file.txt', $payload['data']['url']);
        $this->assertSame('https://tmp.test/uploads/abc/file.txt', $payload['data']['direct_url']);
    }

    public function test_wants_json_for_api_path(): void
    {
        $_SERVER['REQUEST_URI'] = '/api/v1/upload.php';
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
        $this->assertTrue(UploadApi::wantsJsonResponse(false));
    }

    public function test_browser_form_wants_html(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
        $this->assertFalse(UploadApi::wantsJsonResponse(true));
    }

    public function test_broca_user_agent_wants_json(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_ACCEPT'] = '*/*';
        $_SERVER['HTTP_USER_AGENT'] = 'Sanctum-Broca/1.0 (ephemeral upload)';
        $this->assertTrue(UploadApi::wantsJsonResponse(false));
    }

    public function test_qr_svg_encodes_json(): void
    {
        if (!class_exists(\chillerlan\QRCode\QRCode::class)) {
            $this->markTestSkipped('chillerlan/php-qrcode not installed');
        }

        $json = UploadApi::encodePayload([
            'status' => 'success',
            'data' => ['url' => 'https://example.test/x/y'],
        ]);
        $svg = QrSvg::encode($json);
        $this->assertNotNull($svg);
        $this->assertTrue(
            str_contains((string) $svg, '<svg')
            || str_starts_with((string) $svg, 'data:image/svg+xml')
        );
    }
}
