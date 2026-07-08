<?php

declare(strict_types=1);

namespace SanctumTmp\Tests;

use PHPUnit\Framework\TestCase;
use SanctumTmp\Config;
use SanctumTmp\Database;
use SanctumTmp\UploadService;

final class UploadServiceTest extends TestCase
{
    private string $root;
    private Config $config;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/tmp-sanctum-test-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/db', 0755, true);
        mkdir($this->root . '/storage', 0755, true);
        mkdir($this->root . '/public/dl', 0755, true);
        mkdir($this->root . '/public/includes', 0755, true);
        $this->config = new Config($this->root);
        Database::reset();
        // Override paths via config.local for test
        file_put_contents(
            $this->root . '/public/includes/config.local.php',
            '<?php return ' . var_export([
                'base_url' => 'https://tmp.test',
                'db_path' => $this->root . '/db/test.db',
                'storage_dir' => $this->root . '/storage',
                'dl_dir' => $this->root . '/public/dl',
                'max_bytes' => 1048576,
                'ttl_seconds' => 3600,
            ], true) . ';'
        );
        $this->config = new Config($this->root);
        Database::reset();
    }

    protected function tearDown(): void
    {
        Database::reset();
        $this->rmTree($this->root);
    }

    public function test_store_and_direct_path_exists(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, "\x89PNG\r\n\x1a\nfake");
        $service = new UploadService($this->config);
        $result = $service->storeUploadedFile('photo.png', $tmp, 'image/png');
        unlink($tmp);

        $this->assertStringStartsWith('https://tmp.test/', $result['view_url']);
        $this->assertStringContainsString('/uploads/', $result['direct_url']);
        $dlPath = $this->config->dlDir . '/' . $result['id'] . '/photo.png';
        $this->assertFileExists($dlPath);
    }

    public function test_get_for_download_rejects_expired(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'data');
        $service = new UploadService($this->config);
        $result = $service->storeUploadedFile('x.txt', $tmp, 'text/plain');
        unlink($tmp);

        $pdo = Database::connection($this->config);
        $pdo->exec('UPDATE uploads SET expires_at = ' . (time() - 10));
        $row = $service->getForDownload($result['id'], 'x.txt');
        $this->assertNull($row);
    }

    public function test_purge_expired_removes_files(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'data');
        $service = new UploadService($this->config);
        $result = $service->storeUploadedFile('gone.txt', $tmp, 'text/plain');
        unlink($tmp);
        $dlPath = $this->config->dlDir . '/' . $result['id'] . '/gone.txt';
        $this->assertFileExists($dlPath);

        $pdo = Database::connection($this->config);
        $pdo->exec('UPDATE uploads SET expires_at = ' . (time() - 1));
        $n = $service->purgeExpired();
        $this->assertSame(1, $n);
        $this->assertFileDoesNotExist($dlPath);
    }

    public function test_sanitize_filename_blocks_traversal(): void
    {
        $service = new UploadService($this->config);
        $name = $service->sanitizeFilename('../../etc/passwd');
        $this->assertSame('passwd', $name);
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
            is_dir($path) ? $this->rmTree($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
