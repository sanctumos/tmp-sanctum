<?php

declare(strict_types=1);

namespace SanctumTmp\Tests;

use PHPUnit\Framework\TestCase;

final class ApiUploadTest extends TestCase
{
    public function test_upload_endpoint_returns_tmpfiles_shape(): void
    {
        $root = sys_get_temp_dir() . '/tmp-sanctum-api-' . bin2hex(random_bytes(4));
        mkdir($root . '/db', 0755, true);
        mkdir($root . '/storage', 0755, true);
        mkdir($root . '/public/dl', 0755, true);
        mkdir($root . '/public/includes', 0755, true);
        mkdir($root . '/public/api/v1', 0755, true);

        file_put_contents(
            $root . '/public/includes/config.local.php',
            '<?php return ' . var_export([
                'base_url' => 'http://127.0.0.1',
                'db_path' => $root . '/db/test.db',
                'storage_dir' => $root . '/storage',
                'dl_dir' => $root . '/public/dl',
                'max_bytes' => 1048576,
                'ttl_seconds' => 3600,
            ], true) . ';'
        );

        // Copy project files needed for endpoint
        copy(dirname(__DIR__) . '/public/includes/bootstrap.php', $root . '/public/includes/bootstrap.php');
        copy(dirname(__DIR__) . '/public/api/v1/upload.php', $root . '/public/api/v1/upload.php');
        if (!is_dir($root . '/vendor')) {
            $this->markTestSkipped('Run composer install in repo root first');
        }
        // symlink vendor
        if (!is_link($root . '/vendor')) {
            symlink(dirname(__DIR__) . '/vendor', $root . '/vendor');
        }
        copy(dirname(__DIR__) . '/db/schema.sql', $root . '/db/schema.sql');
        if (!is_dir($root . '/src')) {
            symlink(dirname(__DIR__) . '/src', $root . '/src');
        }

        $cmd = sprintf(
            'php -S 127.0.0.1:0 -t %s/public 2>/dev/null',
            escapeshellarg($root)
        );
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open('php -S 127.0.0.1:18765 -t ' . escapeshellarg($root . '/public'), $descriptors, $pipes);
        if (!is_resource($proc)) {
            $this->fail('Could not start PHP built-in server');
        }
        usleep(200000);

        $png = tempnam(sys_get_temp_dir(), 'png');
        file_put_contents($png, "\x89PNG\r\n\x1a\n");
        $curl = curl_init('http://127.0.0.1:18765/api/v1/upload.php');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => ['file' => new CURLFile($png, 'image/png', 't.png')],
        ]);
        $body = curl_exec($curl);
        $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        proc_terminate($proc);

        unlink($png);
        $this->rmTree($root);

        $this->assertSame(200, $code, (string) $body);
        $json = json_decode((string) $body, true);
        $this->assertIsArray($json);
        $this->assertSame('success', $json['status'] ?? null);
        $this->assertStringContainsString('/dl/', $json['data']['url'] ?? $json['data']['direct_url'] ?? '');
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_link($path)) {
                @unlink($path);
                continue;
            }
            is_dir($path) ? $this->rmTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
