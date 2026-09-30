<?php

declare(strict_types=1);

namespace SanctumTmp;

use PDO;

/**
 * Append-only usage events that outlive ephemeral upload rows and files.
 */
final class UsageLog
{
    public const EVENT_UPLOAD = 'upload';
    public const EVENT_DOWNLOAD = 'download';
    public const EVENT_PURGE = 'purge';
    public const EVENT_ORPHAN_SWEEP = 'orphan_sweep';

    private const UA_MAX_LEN = 500;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(
        string $event,
        ?string $uploadId = null,
        ?string $filename = null,
        ?string $mimeType = null,
        ?int $sizeBytes = null,
        ?string $clientIp = null,
        ?string $userAgent = null,
        ?int $createdAt = null,
    ): void {
        $ua = $userAgent;
        if ($ua !== null && strlen($ua) > self::UA_MAX_LEN) {
            $ua = substr($ua, 0, self::UA_MAX_LEN);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO usage_events
                (event, upload_id, filename, mime_type, size_bytes, client_ip, user_agent, created_at)
             VALUES
                (:event, :upload_id, :filename, :mime_type, :size_bytes, :client_ip, :user_agent, :created_at)'
        );
        $stmt->execute([
            ':event' => $event,
            ':upload_id' => $uploadId,
            ':filename' => $filename,
            ':mime_type' => $mimeType,
            ':size_bytes' => $sizeBytes,
            ':client_ip' => $clientIp,
            ':user_agent' => $ua,
            ':created_at' => $createdAt ?? time(),
        ]);
    }

    /**
     * Prefer first hop of X-Forwarded-For, else REMOTE_ADDR.
     *
     * @param array<string, mixed> $server
     */
    public static function clientIpFromServer(array $server): ?string
    {
        $xff = $server['HTTP_X_FORWARDED_FOR'] ?? null;
        if (is_string($xff) && $xff !== '') {
            $first = trim(explode(',', $xff, 2)[0]);
            if ($first !== '') {
                return $first;
            }
        }
        $addr = $server['REMOTE_ADDR'] ?? null;
        return is_string($addr) && $addr !== '' ? $addr : null;
    }

    /**
     * @param array<string, mixed> $server
     */
    public static function userAgentFromServer(array $server): ?string
    {
        $ua = $server['HTTP_USER_AGENT'] ?? null;
        if (!is_string($ua) || $ua === '') {
            return null;
        }
        return strlen($ua) > self::UA_MAX_LEN ? substr($ua, 0, self::UA_MAX_LEN) : $ua;
    }
}
