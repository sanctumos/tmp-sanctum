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

    public function test_normalize_single_file_entry(): void
    {
        $files = UploadApi::normalizeFilesArray([
            'name' => 'a.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/x',
            'error' => 0,
            'size' => 12,
        ]);
        $this->assertCount(1, $files);
        $this->assertSame('a.txt', $files[0]['name']);
    }

    public function test_normalize_multi_file_entry(): void
    {
        $files = UploadApi::normalizeFilesArray([
            'name' => ['a.txt', 'b.txt'],
            'type' => ['text/plain', 'text/plain'],
            'tmp_name' => ['/tmp/a', '/tmp/b'],
            'error' => [0, 0],
            'size' => [10, 20],
        ]);
        $this->assertCount(2, $files);
        $this->assertSame('b.txt', $files[1]['name']);
    }

    public function test_resolve_incoming_files_single(): void
    {
        $_FILES = [
            'file' => [
                'name' => 'one.png',
                'type' => 'image/png',
                'tmp_name' => '/tmp/x',
                'error' => 0,
                'size' => 100,
            ],
        ];
        unset($_GET['multi'], $_POST['multi']);
        $resolved = UploadApi::resolveIncomingFiles();
        $this->assertNotNull($resolved);
        $this->assertSame('single', $resolved['mode']);
        $this->assertCount(1, $resolved['files']);
    }

    public function test_resolve_incoming_files_multi_via_files_field(): void
    {
        $_FILES = [
            'files' => [
                'name' => ['a.txt', 'b.txt'],
                'type' => ['text/plain', 'text/plain'],
                'tmp_name' => ['/tmp/a', '/tmp/b'],
                'error' => [0, 0],
                'size' => [10, 20],
            ],
        ];
        $resolved = UploadApi::resolveIncomingFiles();
        $this->assertNotNull($resolved);
        $this->assertSame('multi', $resolved['mode']);
        $this->assertCount(2, $resolved['files']);
    }

    public function test_success_multi_payload_counts(): void
    {
        $payload = UploadApi::successMultiPayload([
            ['status' => 'success', 'filename' => 'a.txt', 'url' => 'https://x/a'],
            ['status' => 'error', 'filename' => 'b.txt', 'message' => 'fail'],
        ]);
        $this->assertSame('partial', $payload['status']);
        $this->assertSame(2, $payload['data']['count']);
        $this->assertSame(1, $payload['data']['succeeded']);
        $this->assertSame(1, $payload['data']['failed']);
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
