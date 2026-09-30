<?php

declare(strict_types=1);

namespace SanctumTmp\Tests;

use PHPUnit\Framework\TestCase;
use SanctumTmp\Config;
use SanctumTmp\Database;
use SanctumTmp\UploadService;
use SanctumTmp\UsageLog;

final class UsageLogTest extends TestCase
{
    private string $root;
    private Config $config;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/tmp-sanctum-usage-' . bin2hex(random_bytes(4));
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
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
    }

    public function test_upload_logs_usage_event_with_ip_and_ua(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'UsageLogTestBot/1.0';

        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'hello-usage');
        $service = new UploadService($this->config);
        $result = $service->storeUploadedFile('note.txt', $tmp, 'text/plain');
        unlink($tmp);

        $pdo = Database::connection($this->config);
        $rows = $pdo->query(
            "SELECT event, upload_id, filename, mime_type, size_bytes, client_ip, user_agent
             FROM usage_events WHERE event = 'upload'"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows);
        $this->assertSame('upload', $rows[0]['event']);
        $this->assertSame($result['id'], $rows[0]['upload_id']);
        $this->assertSame('note.txt', $rows[0]['filename']);
        $this->assertSame('text/plain', $rows[0]['mime_type']);
        $this->assertSame(11, (int) $rows[0]['size_bytes']);
        $this->assertSame('203.0.113.10', $rows[0]['client_ip']);
        $this->assertSame('UsageLogTestBot/1.0', $rows[0]['user_agent']);
    }

    public function test_download_logs_usage_event(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.7';
        $_SERVER['HTTP_USER_AGENT'] = 'Downloader/2';

        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'dl-body');
        $service = new UploadService($this->config);
        $result = $service->storeUploadedFile('a.bin', $tmp, 'application/octet-stream');
        unlink($tmp);

        $row = $service->getForDownload($result['id'], 'a.bin');
        $this->assertNotNull($row);
        $service->recordDownload($row);

        $pdo = Database::connection($this->config);
        $dl = $pdo->query(
            "SELECT event, upload_id, filename, client_ip, user_agent
             FROM usage_events WHERE event = 'download'"
        )->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($dl);
        $this->assertSame('download', $dl['event']);
        $this->assertSame($result['id'], $dl['upload_id']);
        $this->assertSame('a.bin', $dl['filename']);
        $this->assertSame('198.51.100.7', $dl['client_ip']);
        $this->assertSame('Downloader/2', $dl['user_agent']);
    }

    public function test_purge_logs_and_survives_row_deletion(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, 'purge-me');
        $service = new UploadService($this->config);
        $result = $service->storeUploadedFile('gone.txt', $tmp, 'text/plain');
        unlink($tmp);

        $pdo = Database::connection($this->config);
        $pdo->exec('UPDATE uploads SET expires_at = ' . (time() - 1));
        $n = $service->purgeExpired();
        $this->assertSame(1, $n);

        $uploadsLeft = (int) $pdo->query('SELECT COUNT(*) FROM uploads')->fetchColumn();
        $this->assertSame(0, $uploadsLeft);

        $purge = $pdo->query(
            "SELECT event, upload_id, filename, mime_type, size_bytes, client_ip
             FROM usage_events WHERE event = 'purge'"
        )->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($purge);
        $this->assertSame('purge', $purge['event']);
        $this->assertSame($result['id'], $purge['upload_id']);
        $this->assertSame('gone.txt', $purge['filename']);
        $this->assertSame('text/plain', $purge['mime_type']);
        $this->assertNull($purge['client_ip']);

        // upload + purge both remain
        $events = (int) $pdo->query('SELECT COUNT(*) FROM usage_events')->fetchColumn();
        $this->assertSame(2, $events);
    }

    public function test_client_ip_prefers_xff_first_hop(): void
    {
        $this->assertSame(
            '1.2.3.4',
            UsageLog::clientIpFromServer([
                'HTTP_X_FORWARDED_FOR' => '1.2.3.4, 5.6.7.8',
                'REMOTE_ADDR' => '10.0.0.1',
            ])
        );
        $this->assertSame(
            '10.0.0.9',
            UsageLog::clientIpFromServer(['REMOTE_ADDR' => '10.0.0.9'])
        );
    }

    public function test_user_agent_truncated_to_500(): void
    {
        $long = str_repeat('A', 600);
        $ua = UsageLog::userAgentFromServer(['HTTP_USER_AGENT' => $long]);
        $this->assertSame(500, strlen((string) $ua));

        $pdo = Database::connection($this->config);
        (new UsageLog($pdo))->record(
            UsageLog::EVENT_UPLOAD,
            'abc12345',
            'x.txt',
            'text/plain',
            1,
            '127.0.0.1',
            $long,
        );
        $stored = $pdo->query('SELECT user_agent FROM usage_events LIMIT 1')->fetchColumn();
        $this->assertSame(500, strlen((string) $stored));
    }

    public function test_schema_creates_usage_events_on_connect(): void
    {
        $pdo = Database::connection($this->config);
        $name = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='usage_events'"
        )->fetchColumn();
        $this->assertSame('usage_events', $name);
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
