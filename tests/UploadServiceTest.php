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

    public function test_store_writes_only_to_uploads_not_storage(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, "single-copy-body");
        $service = new UploadService($this->config);
        $result = $service->storeUploadedFile('only-once.txt', $tmp, 'text/plain');
        unlink($tmp);

        $dlPath = $this->config->dlDir . '/' . $result['id'] . '/only-once.txt';
        $storagePath = $this->config->storageDir . '/' . $result['id'] . '/only-once.txt';
        $this->assertFileExists($dlPath);
        $this->assertFileDoesNotExist($storagePath);
        $this->assertDirectoryDoesNotExist($this->config->storageDir . '/' . $result['id']);

        $pdo = Database::connection($this->config);
        $row = $pdo->query('SELECT storage_rel, dl_rel FROM uploads LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame($row['dl_rel'], $row['storage_rel']);
        $this->assertSame($result['id'] . '/only-once.txt', $row['dl_rel']);
    }

    public function test_purge_expired_removes_single_path_and_legacy_storage(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'data');
        $service = new UploadService($this->config);
        $result = $service->storeUploadedFile('gone.txt', $tmp, 'text/plain');
        unlink($tmp);
        $dlPath = $this->config->dlDir . '/' . $result['id'] . '/gone.txt';
        $this->assertFileExists($dlPath);

        // Simulate a pre-single-copy leftover under storage/
        $legacyDir = $this->config->storageDir . '/' . $result['id'];
        mkdir($legacyDir, 0755, true);
        $legacyPath = $legacyDir . '/gone.txt';
        file_put_contents($legacyPath, 'legacy-twin');

        $pdo = Database::connection($this->config);
        $pdo->exec('UPDATE uploads SET expires_at = ' . (time() - 1));
        $n = $service->purgeExpired();
        $this->assertSame(1, $n);
        $this->assertFileDoesNotExist($dlPath);
        $this->assertFileDoesNotExist($legacyPath);
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
        $this->assertFileDoesNotExist($this->config->storageDir . '/' . $result['id'] . '/photo.png');
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
        $this->assertDirectoryDoesNotExist($this->config->dlDir . '/' . $result['id']);
    }

    public function test_sanitize_filename_blocks_traversal(): void
    {
        $service = new UploadService($this->config);
        $name = $service->sanitizeFilename('../../etc/passwd');
        $this->assertSame('passwd', $name);
    }

    public function test_sanitize_filename_neutralizes_executables(): void
    {
        $service = new UploadService($this->config);
        $this->assertSame('shell.php.txt', $service->sanitizeFilename('shell.php'));
        $this->assertSame('x.PHtml.txt', $service->sanitizeFilename('x.PHtml'));
        // double-extension trick is caught by scanning every segment
        $this->assertSame('evil.php.jpg.txt', $service->sanitizeFilename('evil.php.jpg'));
        // benign images are untouched
        $this->assertSame('photo.png', $service->sanitizeFilename('photo.png'));
    }

    public function test_store_rejects_empty_and_oversized(): void
    {
        $service = new UploadService($this->config);
        $empty = tempnam(sys_get_temp_dir(), 'empty');
        file_put_contents($empty, '');
        $this->expectException(\RuntimeException::class);
        try {
            $service->storeUploadedFile('e.txt', $empty, 'text/plain');
        } finally {
            @unlink($empty);
        }
    }

    public function test_store_rejects_oversized_file(): void
    {
        $service = new UploadService($this->config);
        $big = tempnam(sys_get_temp_dir(), 'big');
        file_put_contents($big, str_repeat('x', 1048577));
        try {
            $this->expectException(\RuntimeException::class);
            $service->storeUploadedFile('big.bin', $big, 'application/octet-stream');
        } finally {
            @unlink($big);
        }
    }

    public function test_get_for_download_rejects_bad_id_and_missing_file(): void
    {
        $service = new UploadService($this->config);
        $this->assertNull($service->getForDownload('bad!', 'x.txt'));
        $this->assertNull($service->getForDownload('goodid1', '../x.txt'));

        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'gone-file');
        $result = $service->storeUploadedFile('y.txt', $tmp, 'text/plain');
        unlink($tmp);
        @unlink($this->config->dlDir . '/' . $result['id'] . '/y.txt');
        $this->assertNull($service->getForDownload($result['id'], 'y.txt'));
    }

    public function test_mime_fallback_from_extension(): void
    {
        $service = new UploadService($this->config);
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, '%PDF-1.4 fake');
        $result = $service->storeUploadedFile('doc.pdf', $tmp, null);
        unlink($tmp);
        $row = $service->getForDownload($result['id'], 'doc.pdf');
        $this->assertNotNull($row);
        $this->assertNotSame('', (string) $row['mime_type']);
    }

    public function test_sanitize_empty_and_long_names(): void
    {
        $service = new UploadService($this->config);
        $this->assertSame('upload.bin', $service->sanitizeFilename('...'));
        $long = str_repeat('a', 250) . '.txt';
        $out = $service->sanitizeFilename($long);
        $this->assertLessThanOrEqual(200, strlen($out));
        $this->assertStringEndsWith('.txt', $out);
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
