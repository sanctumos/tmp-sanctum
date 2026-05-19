<?php

declare(strict_types=1);

namespace SanctumTmp;

final class Config
{
    public readonly string $baseUrl;
    public readonly string $rootDir;
    public readonly string $dbPath;
    public readonly string $storageDir;
    public readonly string $dlDir;
    public readonly int $maxBytes;
    public readonly int $ttlSeconds;

    public function __construct(?string $rootDir = null)
    {
        $this->rootDir = $rootDir ?? dirname(__DIR__);
        $local = $this->rootDir . '/public/includes/config.local.php';
        $overrides = is_file($local) ? (require $local) : [];
        if (!is_array($overrides)) {
            $overrides = [];
        }

        $this->baseUrl = rtrim(
            (string) ($overrides['base_url'] ?? getenv('SANCTUM_TMP_BASE_URL') ?: 'https://tmp.sanctumos.org'),
            '/'
        );
        $this->dbPath = (string) ($overrides['db_path'] ?? $this->rootDir . '/db/tmp-sanctum.db');
        $this->storageDir = (string) ($overrides['storage_dir'] ?? $this->rootDir . '/storage');
        $this->dlDir = (string) ($overrides['dl_dir'] ?? $this->rootDir . '/public/dl');
        $this->maxBytes = (int) ($overrides['max_bytes'] ?? getenv('SANCTUM_TMP_MAX_BYTES') ?: 104857600);
        $this->ttlSeconds = (int) ($overrides['ttl_seconds'] ?? getenv('SANCTUM_TMP_TTL_SECONDS') ?: 259200);
    }
}
