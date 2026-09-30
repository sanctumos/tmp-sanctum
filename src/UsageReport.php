<?php

declare(strict_types=1);

namespace SanctumTmp;

use PDO;

/**
 * Rollup of usage_events for ops visibility (uploads/day, bytes, top IPs/UAs, etc.).
 */
final class UsageReport
{
    public function __construct(
        private readonly Config $config,
        private readonly ?PDO $pdo = null,
    ) {
    }

    private function pdo(): PDO
    {
        return $this->pdo ?? Database::connection($this->config);
    }

    /**
     * @return array{
     *   days: int,
     *   since: int,
     *   until: int,
     *   uploads_per_day: array<string, int>,
     *   bytes_per_day: array<string, int>,
     *   top_ips: array<array{client_ip: string, count: int}>,
     *   top_uas: array<array{user_agent: string, count: int}>,
     *   purge_count: int,
     *   orphan_sweep_count: int,
     *   upload_count: int,
     *   download_count: int
     * }
     */
    public function summarize(int $days = 7, ?int $until = null): array
    {
        if ($days < 1) {
            $days = 1;
        }
        $until = $until ?? time();
        $since = $until - ($days * 86400);

        $pdo = $this->pdo();

        $uploadsPerDay = $this->countByDay($pdo, UsageLog::EVENT_UPLOAD, $since, $until);
        $bytesPerDay = $this->bytesByDay($pdo, UsageLog::EVENT_UPLOAD, $since, $until);

        return [
            'days' => $days,
            'since' => $since,
            'until' => $until,
            'uploads_per_day' => $uploadsPerDay,
            'bytes_per_day' => $bytesPerDay,
            'top_ips' => $this->topColumn($pdo, 'client_ip', $since, $until, 10),
            'top_uas' => $this->topColumn($pdo, 'user_agent', $since, $until, 10),
            'purge_count' => $this->countEvent($pdo, UsageLog::EVENT_PURGE, $since, $until),
            'orphan_sweep_count' => $this->countEvent($pdo, UsageLog::EVENT_ORPHAN_SWEEP, $since, $until),
            'upload_count' => $this->countEvent($pdo, UsageLog::EVENT_UPLOAD, $since, $until),
            'download_count' => $this->countEvent($pdo, UsageLog::EVENT_DOWNLOAD, $since, $until),
        ];
    }

    /**
     * Format summarize() output as plain text for CLI.
     *
     * @param array<string, mixed> $data
     */
    public static function formatText(array $data): string
    {
        $lines = [];
        $lines[] = sprintf(
            'Usage report — last %d day(s) (%s → %s UTC)',
            (int) $data['days'],
            gmdate('Y-m-d H:i', (int) $data['since']),
            gmdate('Y-m-d H:i', (int) $data['until'])
        );
        $lines[] = '';
        $lines[] = sprintf(
            'Totals: uploads=%d downloads=%d purges=%d orphan_sweeps=%d',
            (int) $data['upload_count'],
            (int) $data['download_count'],
            (int) $data['purge_count'],
            (int) $data['orphan_sweep_count']
        );
        $lines[] = '';
        $lines[] = 'Uploads / day:';
        /** @var array<string, int> $upd */
        $upd = $data['uploads_per_day'];
        if ($upd === []) {
            $lines[] = '  (none)';
        } else {
            ksort($upd);
            foreach ($upd as $day => $n) {
                $bytes = (int) (($data['bytes_per_day'][$day] ?? 0));
                $lines[] = sprintf('  %s  uploads=%d  bytes=%d', $day, $n, $bytes);
            }
        }
        $lines[] = '';
        $lines[] = 'Top IPs:';
        /** @var list<array{client_ip: string, count: int}> $ips */
        $ips = $data['top_ips'];
        if ($ips === []) {
            $lines[] = '  (none)';
        } else {
            foreach ($ips as $row) {
                $lines[] = sprintf('  %s  %d', $row['client_ip'], $row['count']);
            }
        }
        $lines[] = '';
        $lines[] = 'Top User-Agents:';
        /** @var list<array{user_agent: string, count: int}> $uas */
        $uas = $data['top_uas'];
        if ($uas === []) {
            $lines[] = '  (none)';
        } else {
            foreach ($uas as $row) {
                $ua = $row['user_agent'];
                if (strlen($ua) > 80) {
                    $ua = substr($ua, 0, 77) . '...';
                }
                $lines[] = sprintf('  [%d] %s', $row['count'], $ua);
            }
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    /**
     * @return array<string, int>
     */
    private function countByDay(PDO $pdo, string $event, int $since, int $until): array
    {
        $stmt = $pdo->prepare(
            "SELECT strftime('%Y-%m-%d', created_at, 'unixepoch') AS day, COUNT(*) AS n
             FROM usage_events
             WHERE event = :event AND created_at >= :since AND created_at < :until
             GROUP BY day
             ORDER BY day"
        );
        $stmt->execute([':event' => $event, ':since' => $since, ':until' => $until]);
        $out = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $out[(string) $row['day']] = (int) $row['n'];
        }
        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function bytesByDay(PDO $pdo, string $event, int $since, int $until): array
    {
        $stmt = $pdo->prepare(
            "SELECT strftime('%Y-%m-%d', created_at, 'unixepoch') AS day, COALESCE(SUM(size_bytes), 0) AS bytes
             FROM usage_events
             WHERE event = :event AND created_at >= :since AND created_at < :until
             GROUP BY day
             ORDER BY day"
        );
        $stmt->execute([':event' => $event, ':since' => $since, ':until' => $until]);
        $out = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $out[(string) $row['day']] = (int) $row['bytes'];
        }
        return $out;
    }

    private function countEvent(PDO $pdo, string $event, int $since, int $until): int
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM usage_events
             WHERE event = :event AND created_at >= :since AND created_at < :until'
        );
        $stmt->execute([':event' => $event, ':since' => $since, ':until' => $until]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array{client_ip?: string, user_agent?: string, count: int}>
     */
    private function topColumn(PDO $pdo, string $column, int $since, int $until, int $limit): array
    {
        if (!in_array($column, ['client_ip', 'user_agent'], true)) {
            return [];
        }
        $sql = "SELECT {$column} AS val, COUNT(*) AS n
                FROM usage_events
                WHERE created_at >= :since AND created_at < :until
                  AND {$column} IS NOT NULL AND {$column} != ''
                GROUP BY {$column}
                ORDER BY n DESC
                LIMIT {$limit}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':since' => $since, ':until' => $until]);
        $out = [];
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            if ($column === 'client_ip') {
                $out[] = ['client_ip' => (string) $row['val'], 'count' => (int) $row['n']];
            } else {
                $out[] = ['user_agent' => (string) $row['val'], 'count' => (int) $row['n']];
            }
        }
        return $out;
    }
}
