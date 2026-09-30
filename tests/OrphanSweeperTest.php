<?php

declare(strict_types=1);

namespace SanctumTmp\Tests;

use PHPUnit\Framework\TestCase;
use SanctumTmp\Config;
use SanctumTmp\Database;
use SanctumTmp\OrphanSweeper;
use SanctumTmp\UploadService;
use SanctumTmp\UsageLog;

final class OrphanSweeperTest extends TestCase
{
    private string $root;
    private Config $config;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/tmp-sanctum-orphan-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/db', 0755, true);
        mkdir($this->root . '/storage', 0755, true);
        mkdir($this->root . '/public/dl', 0755, true);
        mkdir($this->root . '/public/includes', 0755, true);
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

    public function test_keeps_live_upload_dirs(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'live-body');
        $service = new UploadService($this->config);
        $result = $service->storeUploadedFile('keep.txt', $tmp, 'text/plain');
        unlink($tmp);

        $dlPath = $this->config->dlDir . '/' . $result['id'] . '/keep.txt';
        $this->assertFileExists($dlPath);

        $stats = (new OrphanSweeper($this->config))->run();
        $this->assertSame(0, $stats['deleted']);
        $this->assertFileExists($dlPath);
    }

    public function test_deletes_orphan_under_uploads_and_logs(): void
    {
        $orphanId = 'orphan01';
        $dir = $this->config->dlDir . '/' . $orphanId;
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/leftover.bin', 'orphan-bytes');

        $stats = (new OrphanSweeper($this->config))->run();
        $this->assertSame(1, $stats['deleted']);
        $this->assertSame(1, $stats['orphan_ids']);
        $this->assertDirectoryDoesNotExist($dir);

        $pdo = Database::connection($this->config);
        $row = $pdo->query(
            "SELECT event, upload_id, filename, size_bytes FROM usage_events WHERE event = 'orphan_sweep'"
        )->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertSame(UsageLog::EVENT_ORPHAN_SWEEP, $row['event']);
        $this->assertSame($orphanId, $row['upload_id']);
        $this->assertSame('leftover.bin', $row['filename']);
        $this->assertSame(12, (int) $row['size_bytes']);
    }

    public function test_deletes_orphan_under_storage(): void
    {
        $orphanId = 'storOrph';
        $dir = $this->config->storageDir . '/' . $orphanId;
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/old.mp4', str_repeat('v', 100));

        $stats = (new OrphanSweeper($this->config))->run();
        $this->assertSame(1, $stats['deleted']);
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertGreaterThanOrEqual(100, $stats['bytes']);
    }

    public function test_same_orphan_id_in_both_trees_is_one_event(): void
    {
        $orphanId = 'bothOrph';
        mkdir($this->config->dlDir . '/' . $orphanId, 0755, true);
        mkdir($this->config->storageDir . '/' . $orphanId, 0755, true);
        file_put_contents($this->config->dlDir . '/' . $orphanId . '/a.txt', 'a');
        file_put_contents($this->config->storageDir . '/' . $orphanId . '/a.txt', 'a');

        $stats = (new OrphanSweeper($this->config))->run();
        $this->assertSame(1, $stats['deleted']);
        $this->assertDirectoryDoesNotExist($this->config->dlDir . '/' . $orphanId);
        $this->assertDirectoryDoesNotExist($this->config->storageDir . '/' . $orphanId);

        $pdo = Database::connection($this->config);
        $n = (int) $pdo->query(
            "SELECT COUNT(*) FROM usage_events WHERE event = 'orphan_sweep' AND upload_id = 'bothOrph'"
        )->fetchColumn();
        $this->assertSame(1, $n);
    }

    public function test_ignores_non_id_directories(): void
    {
        mkdir($this->config->dlDir . '/.cache', 0755, true);
        file_put_contents($this->config->dlDir . '/.cache/x', 'nope');
        mkdir($this->config->dlDir . '/not-an-id!', 0755, true);

        $stats = (new OrphanSweeper($this->config))->run();
        $this->assertSame(0, $stats['deleted']);
        $this->assertDirectoryExists($this->config->dlDir . '/.cache');
    }

    public function test_deletes_empty_orphan_dir_and_nested_files(): void
    {
        $emptyId = 'emptyOr1';
        mkdir($this->config->dlDir . '/' . $emptyId, 0755, true);

        $nestedId = 'nested01';
        mkdir($this->config->dlDir . '/' . $nestedId . '/sub', 0755, true);
        file_put_contents($this->config->dlDir . '/' . $nestedId . '/sub/deep.bin', 'nested');

        $stats = (new OrphanSweeper($this->config))->run();
        $this->assertSame(2, $stats['deleted']);
        $this->assertDirectoryDoesNotExist($this->config->dlDir . '/' . $emptyId);
        $this->assertDirectoryDoesNotExist($this->config->dlDir . '/' . $nestedId);
    }

    public function test_missing_roots_are_skipped(): void
    {
        // Point storage at a missing path — sweeper should still scan dlDir only.
        file_put_contents(
            $this->root . '/public/includes/config.local.php',
            '<?php return ' . var_export([
                'base_url' => 'https://tmp.test',
                'db_path' => $this->root . '/db/test.db',
                'storage_dir' => $this->root . '/storage-does-not-exist',
                'dl_dir' => $this->root . '/public/dl',
                'max_bytes' => 1048576,
                'ttl_seconds' => 3600,
            ], true) . ';'
        );
        $config = new Config($this->root);
        Database::reset();
        $orphanId = 'missRoot';
        mkdir($config->dlDir . '/' . $orphanId, 0755, true);
        file_put_contents($config->dlDir . '/' . $orphanId . '/f.txt', 'x');
        $stats = (new OrphanSweeper($config))->run();
        $this->assertSame(1, $stats['deleted']);
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
