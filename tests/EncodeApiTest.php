<?php

declare(strict_types=1);

namespace SanctumTmp\Tests;

use PHPUnit\Framework\TestCase;
use SanctumTmp\Config;
use SanctumTmp\Database;
use SanctumTmp\QrNormalize;
use SanctumTmp\QrRender;
use SanctumTmp\UploadService;

final class EncodeApiTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/tmp-encode-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/db', 0777, true);
        mkdir($this->root . '/public/uploads', 0777, true);
        mkdir($this->root . '/public/includes', 0777, true);
        mkdir($this->root . '/storage', 0777, true);
        file_put_contents(
            $this->root . '/public/includes/config.local.php',
            '<?php return ' . var_export([
                'base_url' => 'https://tmp.test',
                'db_path' => $this->root . '/db/test.db',
                'storage_dir' => $this->root . '/storage',
                'dl_dir' => $this->root . '/public/uploads',
                'max_bytes' => 1048576,
                'ttl_seconds' => 3600,
            ], true) . ';'
        );
        Database::reset();
    }

    protected function tearDown(): void
    {
        Database::reset();
        $this->rmTree($this->root);
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }

    /** @param array<string,mixed> $payload @return array{code:int,body:array<string,mixed>} */
    private function postEncode(array $payload): array
    {
        $normalized = QrNormalize::process((string) ($payload['content'] ?? ''), (string) ($payload['type'] ?? 'auto'));
        if (!empty($normalized['error'])) {
            return ['code' => 422, 'body' => ['status' => 'error', 'message' => $normalized['error'], 'code' => 'validation']];
        }
        $encoded = (string) ($normalized['encoded'] ?? '');
        $rendered = QrRender::tryEncode(
            $encoded,
            (string) ($payload['format'] ?? 'svg'),
            (string) ($payload['ecl'] ?? 'M'),
        );
        if ($rendered === null) {
            return ['code' => 422, 'body' => ['status' => 'error', 'code' => 'qr_capacity']];
        }
        $body = [
            'status' => 'success',
            'data' => [
                'mode' => 'qr',
                'encoded' => $encoded,
                'type' => $normalized['type'] ?? 'text',
                'format' => $rendered['format'],
                'data_uri' => $rendered['data_uri'],
                'persisted' => false,
            ],
        ];
        if (!empty($payload['persist'])) {
            $config = new Config($this->root);
            $tmpFile = tempnam(sys_get_temp_dir(), 'encp_');
            file_put_contents($tmpFile, $encoded);
            $stored = (new UploadService($config))->storeUploadedFile('payload.txt', $tmpFile, 'text/plain');
            @unlink($tmpFile);
            $body['data']['persisted'] = true;
            $body['data']['direct_url'] = $stored['direct_url'];
            $body['data']['id'] = $stored['id'];
        }
        return ['code' => 200, 'body' => $body];
    }

    public function test_encode_url_ephemeral(): void
    {
        $r = $this->postEncode(['content' => 'example.com', 'type' => 'auto']);
        $this->assertSame(200, $r['code']);
        $this->assertSame('success', $r['body']['status']);
        $this->assertSame('https://example.com', $r['body']['data']['encoded']);
        $this->assertFalse($r['body']['data']['persisted']);
    }

    public function test_encode_capacity_failure(): void
    {
        $r = $this->postEncode(['content' => str_repeat('Z', 4000), 'type' => 'text']);
        $this->assertSame(422, $r['code']);
        $this->assertSame('qr_capacity', $r['body']['code']);
    }

    public function test_persist_writes_upload(): void
    {
        $r = $this->postEncode(['content' => 'hello from tmp', 'type' => 'text', 'persist' => true]);
        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['data']['persisted']);
        $this->assertArrayHasKey('direct_url', $r['body']['data']);
        $id = $r['body']['data']['id'];
        $this->assertDirectoryExists($this->root . '/public/uploads/' . $id);
    }
}
