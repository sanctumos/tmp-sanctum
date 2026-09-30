<?php

declare(strict_types=1);

namespace SanctumTmp;

use PDO;
use RuntimeException;

final class UploadService
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
     * @return array{view_url: string, direct_url: string, id: string, filename: string}
     */
    public function storeUploadedFile(string $originalName, string $tmpPath, ?string $clientMime = null): array
    {
        if (!is_readable($tmpPath)) {
            throw new RuntimeException('Upload temp file not readable');
        }
        $size = filesize($tmpPath);
        if ($size === false || $size < 1) {
            throw new RuntimeException('Empty upload');
        }
        if ($size > $this->config->maxBytes) {
            throw new RuntimeException('File exceeds maximum size');
        }

        $filename = $this->sanitizeFilename($originalName);
        $id = $this->generateId();
        $mime = $this->detectMime($tmpPath, $clientMime, $filename);

        // Single copy: bytes live only under the public uploads tree (dlDir).
        // storage_rel is kept for schema back-compat and equals dl_rel.
        $rel = $id . '/' . $filename;
        $dlAbs = $this->config->dlDir . '/' . $rel;

        $this->ensureDir(dirname($dlAbs));

        if (!copy($tmpPath, $dlAbs)) {
            throw new RuntimeException('Failed to store file');
        }

        $now = time();
        $expires = $now + $this->config->ttlSeconds;
        $stmt = $this->pdo()->prepare(
            'INSERT INTO uploads (id, filename, mime_type, size_bytes, storage_rel, dl_rel, created_at, expires_at)
             VALUES (:id, :filename, :mime, :size, :storage_rel, :dl_rel, :created, :expires)'
        );
        $stmt->execute([
            ':id' => $id,
            ':filename' => $filename,
            ':mime' => $mime,
            ':size' => $size,
            ':storage_rel' => $rel,
            ':dl_rel' => $rel,
            ':created' => $now,
            ':expires' => $expires,
        ]);

        $this->usageLog()->record(
            UsageLog::EVENT_UPLOAD,
            $id,
            $filename,
            $mime,
            (int) $size,
            UsageLog::clientIpFromServer($_SERVER),
            UsageLog::userAgentFromServer($_SERVER),
            $now,
        );

        $viewUrl = $this->config->baseUrl . '/' . rawurlencode($id) . '/' . rawurlencode($filename);
        $directUrl = $this->config->baseUrl . $this->config->dlUrlPrefix . '/' . rawurlencode($id) . '/' . rawurlencode($filename);

        return [
            'view_url' => $viewUrl,
            'direct_url' => $directUrl,
            'id' => $id,
            'filename' => $filename,
        ];
    }

    /**
     * Log a successful download serve (PHP fallthrough path).
     *
     * @param array<string, mixed> $row uploads row
     */
    public function recordDownload(array $row): void
    {
        $this->usageLog()->record(
            UsageLog::EVENT_DOWNLOAD,
            isset($row['id']) ? (string) $row['id'] : null,
            isset($row['filename']) ? (string) $row['filename'] : null,
            isset($row['mime_type']) ? (string) $row['mime_type'] : null,
            isset($row['size_bytes']) ? (int) $row['size_bytes'] : null,
            UsageLog::clientIpFromServer($_SERVER),
            UsageLog::userAgentFromServer($_SERVER),
        );
    }

    public function getForDownload(string $id, string $filename): ?array
    {
        if (!$this->isValidId($id) || !$this->isValidFilename($filename)) {
            return null;
        }
        $stmt = $this->pdo()->prepare(
            'SELECT * FROM uploads WHERE id = :id AND filename = :filename LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':filename' => $filename]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        if ((int) $row['expires_at'] < time()) {
            return null;
        }
        $dlAbs = $this->config->dlDir . '/' . $row['dl_rel'];
        if (!is_file($dlAbs)) {
            return null;
        }
        return $row;
    }

    public function purgeExpired(): int
    {
        $now = time();
        $stmt = $this->pdo()->prepare(
            'SELECT id, filename, mime_type, size_bytes, storage_rel, dl_rel
             FROM uploads WHERE expires_at < :now'
        );
        $stmt->execute([':now' => $now]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $count = 0;
        $log = $this->usageLog();
        foreach ($rows as $row) {
            $this->deleteFiles($row);
            $del = $this->pdo()->prepare('DELETE FROM uploads WHERE id = :id AND filename = :filename');
            $del->execute([':id' => $row['id'], ':filename' => $row['filename']]);
            $log->record(
                UsageLog::EVENT_PURGE,
                (string) $row['id'],
                (string) $row['filename'],
                isset($row['mime_type']) ? (string) $row['mime_type'] : null,
                isset($row['size_bytes']) ? (int) $row['size_bytes'] : null,
            );
            $count++;
        }
        return $count;
    }

    private function usageLog(): UsageLog
    {
        return new UsageLog($this->pdo());
    }

    /**
     * Delete the single published copy under dlDir. Also removes a legacy
     * storage/ twin if one still exists from pre-single-copy uploads.
     *
     * @param array<string, mixed> $row
     */
    private function deleteFiles(array $row): void
    {
        $paths = [
            $this->config->dlDir . '/' . $row['dl_rel'],
        ];
        // Legacy dual-write: storage_rel may point at a separate storage/ copy.
        if (
            isset($row['storage_rel'])
            && is_string($row['storage_rel'])
            && $row['storage_rel'] !== ''
            && $row['storage_rel'] !== ($row['dl_rel'] ?? null)
        ) {
            $paths[] = $this->config->storageDir . '/' . $row['storage_rel'];
        } elseif (isset($row['id'])) {
            // Pre-single-copy rows often had storage_rel === dl_rel but the
            // bytes lived under storage/ as a second copy — try that path too.
            $legacy = $this->config->storageDir . '/' . $row['id'] . '/' . ($row['filename'] ?? '');
            if (is_file($legacy)) {
                $paths[] = $legacy;
            }
        }
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $idDirDl = $this->config->dlDir . '/' . $row['id'];
        $idDirStorage = $this->config->storageDir . '/' . $row['id'];
        $this->removeDirIfEmpty($idDirDl);
        $this->removeDirIfEmpty($idDirStorage);
    }

    private function removeDirIfEmpty(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }
        $filtered = array_diff($entries, ['.', '..']);
        if ($filtered === []) {
            @rmdir($dir);
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory: ' . $dir);
        }
    }

    private function generateId(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $len = 8;
        $id = '';
        for ($i = 0; $i < $len; $i++) {
            $id .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return $id;
    }

    /**
     * Extensions that a web server could execute as code if they land in the
     * publicly served uploads tree. Nginx already refuses to run PHP under the
     * /uploads/ prefix, but neutralizing the name here is defense-in-depth so a
     * dangerous file can never be written with an executable extension at all.
     */
    private const DANGEROUS_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'pht', 'phtml',
        'phar', 'shtml', 'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'asp', 'aspx',
        'jsp', 'jspx', 'exe', 'com', 'htaccess', 'htpasswd',
    ];

    public function sanitizeFilename(string $name): string
    {
        $base = basename(str_replace('\\', '/', $name));
        $base = preg_replace('/[^\w.\-()+@ ]+/u', '_', $base) ?? 'upload.bin';
        $base = trim($base, ".\x00..\x20");
        if ($base === '' || $base === '.' || $base === '..') {
            $base = 'upload.bin';
        }
        if (strlen($base) > 200) {
            $ext = pathinfo($base, PATHINFO_EXTENSION);
            $stem = substr(pathinfo($base, PATHINFO_FILENAME), 0, 180);
            $base = $ext !== '' ? $stem . '.' . $ext : $stem;
        }
        return $this->neutralizeExecutable($base);
    }

    /**
     * If any extension segment is server-executable (covers double-extension
     * tricks like "shell.php.jpg"), append ".txt" so the stored/served file is
     * inert regardless of web server handler mappings.
     */
    private function neutralizeExecutable(string $base): string
    {
        $segments = explode('.', strtolower($base));
        array_shift($segments); // drop the stem; keep only extension segments
        foreach ($segments as $seg) {
            if (in_array($seg, self::DANGEROUS_EXTENSIONS, true)) {
                return $base . '.txt';
            }
        }
        return $base;
    }

    private function detectMime(string $path, ?string $clientMime, string $filename): string
    {
        if ($clientMime !== null && str_contains($clientMime, '/')) {
            return explode(';', $clientMime)[0];
        }
        if (function_exists('mime_content_type')) {
            $detected = mime_content_type($path);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }

    public function isValidId(string $id): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{6,32}$/', $id);
    }

    public function isValidFilename(string $filename): bool
    {
        return $filename !== ''
            && $filename === basename($filename)
            && !str_contains($filename, '..')
            && (bool) preg_match('/^[\w.\-()+@ ]{1,200}$/', $filename);
    }
}
