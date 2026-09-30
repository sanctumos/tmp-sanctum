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

    public function test_success_multi_all_success_and_all_fail(): void
    {
        $ok = UploadApi::successMultiPayload([
            ['status' => 'success', 'filename' => 'a.txt'],
            ['status' => 'success', 'filename' => 'b.txt'],
        ]);
        $this->assertSame('success', $ok['status']);

        $bad = UploadApi::successMultiPayload([
            ['status' => 'error', 'filename' => 'a.txt', 'message' => 'x'],
        ]);
        $this->assertSame('error', $bad['status']);
        $this->assertSame(0, $bad['data']['succeeded']);
    }

    public function test_is_multi_upload_requested_flags(): void
    {
        $_FILES = [];
        $_GET = ['multi' => '1'];
        $_POST = [];
        unset($_SERVER['HTTP_X_SANCTUM_TMP_MULTI']);
        $this->assertTrue(UploadApi::isMultiUploadRequested());

        $_GET = [];
        $_SERVER['HTTP_X_SANCTUM_TMP_MULTI'] = '1';
        $this->assertTrue(UploadApi::isMultiUploadRequested());

        unset($_SERVER['HTTP_X_SANCTUM_TMP_MULTI']);
        $this->assertFalse(UploadApi::isMultiUploadRequested());
    }

    public function test_resolve_array_file_without_multi_keeps_first(): void
    {
        $_FILES = [
            'file' => [
                'name' => ['a.txt', 'b.txt'],
                'type' => ['text/plain', 'text/plain'],
                'tmp_name' => ['/tmp/a', '/tmp/b'],
                'error' => [0, 0],
                'size' => [10, 20],
            ],
        ];
        unset($_GET['multi'], $_POST['multi'], $_SERVER['HTTP_X_SANCTUM_TMP_MULTI']);
        $resolved = UploadApi::resolveIncomingFiles();
        $this->assertNotNull($resolved);
        $this->assertSame('single', $resolved['mode']);
        $this->assertSame('a.txt', $resolved['files'][0]['name']);
    }

    public function test_resolve_array_file_with_multi_flag(): void
    {
        $_FILES = [
            'file' => [
                'name' => ['a.txt', 'b.txt'],
                'type' => ['text/plain', 'text/plain'],
                'tmp_name' => ['/tmp/a', '/tmp/b'],
                'error' => [0, 0],
                'size' => [10, 20],
            ],
        ];
        $_GET['multi'] = 'true';
        $resolved = UploadApi::resolveIncomingFiles();
        $this->assertNotNull($resolved);
        $this->assertSame('multi', $resolved['mode']);
        $this->assertCount(2, $resolved['files']);
    }

    public function test_resolve_missing_files_returns_null(): void
    {
        $_FILES = [];
        $this->assertNull(UploadApi::resolveIncomingFiles());
    }

    public function test_file_error_result_and_wants_json_accept(): void
    {
        $err = UploadApi::fileErrorResult(
            ['name' => 'z.bin', 'type' => '', 'tmp_name' => '', 'error' => 1, 'size' => 0],
            'boom'
        );
        $this->assertSame('error', $err['status']);
        $this->assertSame('z.bin', $err['filename']);
        $this->assertSame('boom', $err['message']);

        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        unset($_SERVER['HTTP_USER_AGENT'], $_SERVER['HTTP_X_REQUESTED_WITH']);
        $this->assertTrue(UploadApi::wantsJsonResponse(false));

        $_SERVER['HTTP_ACCEPT'] = 'text/html';
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue(UploadApi::wantsJsonResponse(false));
    }
}
