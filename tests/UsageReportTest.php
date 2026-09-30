<?php

declare(strict_types=1);

namespace SanctumTmp\Tests;

use PHPUnit\Framework\TestCase;
use SanctumTmp\Config;
use SanctumTmp\Database;
use SanctumTmp\UsageLog;
use SanctumTmp\UsageReport;

final class UsageReportTest extends TestCase
{
    private string $root;
    private Config $config;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/tmp-sanctum-report-' . bin2hex(random_bytes(4));
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

    public function test_summarize_counts_events_and_top_ips(): void
    {
        $pdo = Database::connection($this->config);
        $log = new UsageLog($pdo);
        $now = time();
        $log->record(UsageLog::EVENT_UPLOAD, 'idAAAAAA', 'a.txt', 'text/plain', 100, '1.1.1.1', 'BotA', $now);
        $log->record(UsageLog::EVENT_UPLOAD, 'idBBBBBB', 'b.txt', 'text/plain', 50, '1.1.1.1', 'BotA', $now);
        $log->record(UsageLog::EVENT_UPLOAD, 'idCCCCCC', 'c.txt', 'text/plain', 25, '2.2.2.2', 'BotB', $now - 86400);
        $log->record(UsageLog::EVENT_DOWNLOAD, 'idAAAAAA', 'a.txt', 'text/plain', 100, '1.1.1.1', 'BotA', $now);
        $log->record(UsageLog::EVENT_PURGE, 'idDDDDDD', 'old.txt', 'text/plain', 10, null, null, $now);
        $log->record(UsageLog::EVENT_ORPHAN_SWEEP, 'orphan99', 'x.bin', null, 999, null, null, $now);

        $report = new UsageReport($this->config);
        $data = $report->summarize(7, $now + 1);

        $this->assertSame(3, $data['upload_count']);
        $this->assertSame(1, $data['download_count']);
        $this->assertSame(1, $data['purge_count']);
        $this->assertSame(1, $data['orphan_sweep_count']);
        // 2 uploads + 1 download share 1.1.1.1
        $this->assertSame(3, $data['top_ips'][0]['count']);
        $this->assertSame('1.1.1.1', $data['top_ips'][0]['client_ip']);
        $this->assertNotEmpty($data['uploads_per_day']);
        $this->assertGreaterThan(0, array_sum($data['bytes_per_day']));

        $text = UsageReport::formatText($data);
        $this->assertStringContainsString('uploads=3', $text);
        $this->assertStringContainsString('orphan_sweeps=1', $text);
        $this->assertStringContainsString('1.1.1.1', $text);
        $this->assertStringContainsString('Top User-Agents', $text);
    }

    public function test_summarize_empty_window(): void
    {
        Database::connection($this->config);
        $data = (new UsageReport($this->config))->summarize(3);
        $this->assertSame(0, $data['upload_count']);
        $this->assertSame([], $data['top_ips']);
        $text = UsageReport::formatText($data);
        $this->assertStringContainsString('(none)', $text);
    }

    public function test_days_clamped_to_at_least_one(): void
    {
        Database::connection($this->config);
        $data = (new UsageReport($this->config))->summarize(0);
        $this->assertSame(1, $data['days']);
    }

    public function test_format_truncates_long_user_agent(): void
    {
        $long = str_repeat('Z', 120);
        $text = UsageReport::formatText([
            'days' => 1,
            'since' => time() - 86400,
            'until' => time(),
            'uploads_per_day' => [],
            'bytes_per_day' => [],
            'top_ips' => [],
            'top_uas' => [['user_agent' => $long, 'count' => 9]],
            'purge_count' => 0,
            'orphan_sweep_count' => 0,
            'upload_count' => 0,
            'download_count' => 0,
        ]);
        $this->assertStringContainsString('...', $text);
        $this->assertStringContainsString('[9]', $text);
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
