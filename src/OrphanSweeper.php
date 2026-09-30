<?php

declare(strict_types=1);

namespace SanctumTmp;

use PDO;

/**
 * Delete upload id directories under dlDir / storage that have no live uploads row.
 * Logs each removal as usage_events event=orphan_sweep before deleting.
 */
final class OrphanSweeper
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
     * @return array{scanned: int, orphan_ids: int, deleted: int, bytes: int}
     */
    public function run(): array
    {
        $liveIds = $this->liveUploadIds();
        $orphanDirs = $this->findOrphanIdDirs($liveIds);

        $deleted = 0;
        $bytes = 0;
        $log = new UsageLog($this->pdo());

        foreach ($orphanDirs as $id => $paths) {
            $meta = $this->inspectOrphan($paths);
            $log->record(
                UsageLog::EVENT_ORPHAN_SWEEP,
                $id,
                $meta['filename'],
                $meta['mime_type'],
                $meta['size_bytes'],
            );
            foreach ($paths as $dir) {
                $bytes += $this->dirByteSize($dir);
                $this->rmTree($dir);
            }
            $deleted++;
        }

        return [
            'scanned' => $this->lastScanCount,
            'orphan_ids' => count($orphanDirs),
            'deleted' => $deleted,
            'bytes' => $bytes,
        ];
    }

    private int $lastScanCount = 0;

    /**
     * @return array<string, true>
     */
    private function liveUploadIds(): array
    {
        $ids = [];
        $stmt = $this->pdo()->query('SELECT DISTINCT id FROM uploads');
        if ($stmt === false) {
            return $ids;
        }
        while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
            $id = (string) $row['id'];
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        return $ids;
    }

    /**
     * Map orphan id => list of absolute id-dir paths (may appear under uploads and storage).
     *
     * @param array<string, true> $liveIds
     * @return array<string, list<string>>
     */
    private function findOrphanIdDirs(array $liveIds): array
    {
        $roots = array_unique(array_filter([
            $this->config->dlDir,
            $this->config->storageDir,
        ]));

        $orphans = [];
        $scanned = 0;
        foreach ($roots as $root) {
            if (!is_dir($root)) {
                continue;
            }
            $entries = scandir($root);
            if ($entries === false) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $root . '/' . $entry;
                if (!is_dir($path)) {
                    continue;
                }
                if (!$this->looksLikeUploadId($entry)) {
                    continue;
                }
                $scanned++;
                if (isset($liveIds[$entry])) {
                    continue;
                }
                $orphans[$entry] ??= [];
                $orphans[$entry][] = $path;
            }
        }
        $this->lastScanCount = $scanned;
        return $orphans;
    }

    private function looksLikeUploadId(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{6,32}$/', $name);
    }

    /**
     * @param list<string> $paths
     * @return array{filename: ?string, mime_type: ?string, size_bytes: ?int}
     */
    private function inspectOrphan(array $paths): array
    {
        $filename = null;
        $size = 0;
        foreach ($paths as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $entries = scandir($dir);
            if ($entries === false) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $file = $dir . '/' . $entry;
                if (!is_file($file)) {
                    continue;
                }
                if ($filename === null) {
                    $filename = $entry;
                }
                $sz = filesize($file);
                if ($sz !== false) {
                    $size += $sz;
                }
            }
        }
        return [
            'filename' => $filename,
            'mime_type' => null,
            'size_bytes' => $size > 0 ? $size : null,
        ];
    }

    private function dirByteSize(string $dir): int
    {
        $total = 0;
        if (!is_dir($dir)) {
            return 0;
        }
        $entries = scandir($dir);
        if ($entries === false) {
            return 0;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $total += $this->dirByteSize($path);
            } elseif (is_file($path)) {
                $sz = filesize($path);
                if ($sz !== false) {
                    $total += $sz;
                }
            }
        }
        return $total;
    }

    private function rmTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->rmTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
