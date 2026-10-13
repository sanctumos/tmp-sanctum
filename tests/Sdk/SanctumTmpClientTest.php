<?php

declare(strict_types=1);

namespace SanctumTmp\Tests\Sdk;

use PHPUnit\Framework\TestCase;
use SanctumTmp\Sdk\QrCapacityException;
use SanctumTmp\Sdk\SanctumTmpClient;
use SanctumTmp\Sdk\ValidationException;

/**
 * Behavioral tests for encodeOrUpload using a stub transport subclass.
 */
final class SanctumTmpClientTest extends TestCase
{
    public function test_default_base(): void
    {
        $c = new SanctumTmpClient();
        $this->assertSame('https://tmp.sanctumos.org', $c->getBaseUrl());
        $this->assertStringEndsWith('/api/v1/upload.php', $c->uploadUrl());
        $this->assertStringEndsWith('/api/v1/encode.php', $c->encodeUrl());
    }

    public function test_encode_or_upload_prefers_qr(): void
    {
        $c = new class extends SanctumTmpClient {
            public int $encodeCalls = 0;
            public int $uploadCalls = 0;

            public function encode(string $content, array $options = []): array
            {
                $this->encodeCalls++;
                return [
                    'status' => 'success',
                    'data' => ['mode' => 'qr', 'encoded' => $content, 'type' => 'text'],
                ];
            }

            public function uploadText(string $text, string $filename = 'share.txt', string $mime = 'text/plain'): array
            {
                $this->uploadCalls++;
                return [
                    'status' => 'success',
                    'data' => ['direct_url' => 'https://tmp.test/uploads/x/' . $filename],
                ];
            }
        };

        $out = $c->encodeOrUpload('hi');
        $this->assertSame('qr', $out['sanctum_tmp_mode']);
        $this->assertSame(1, $c->encodeCalls);
        $this->assertSame(0, $c->uploadCalls);
    }

    public function test_encode_or_upload_falls_back_on_capacity(): void
    {
        $c = new class extends SanctumTmpClient {
            public function encode(string $content, array $options = []): array
            {
                throw new QrCapacityException('too big', 422, ['code' => 'qr_capacity'], $content, 'text');
            }

            public function uploadText(string $text, string $filename = 'share.txt', string $mime = 'text/plain'): array
            {
                return [
                    'status' => 'success',
                    'data' => [
                        'direct_url' => 'https://tmp.test/uploads/id/' . $filename,
                        'filename' => $filename,
                    ],
                ];
            }
        };

        $out = $c->encodeOrUpload('huge');
        $this->assertSame('file', $out['sanctum_tmp_mode']);
        $this->assertSame('qr_capacity', $out['sanctum_tmp_fallback']);
        $this->assertStringContainsString('share.txt', $out['data']['direct_url']);
    }

    public function test_encode_or_upload_prefer_file_skips_qr(): void
    {
        $c = new class extends SanctumTmpClient {
            public int $encodeCalls = 0;

            public function encode(string $content, array $options = []): array
            {
                $this->encodeCalls++;
                throw new ValidationException('should not run');
            }

            public function uploadText(string $text, string $filename = 'share.txt', string $mime = 'text/plain'): array
            {
                return ['status' => 'success', 'data' => ['filename' => $filename]];
            }
        };

        $out = $c->encodeOrUpload('x', preferQr: false);
        $this->assertSame('file', $out['sanctum_tmp_mode']);
        $this->assertSame(0, $c->encodeCalls);
    }
}
