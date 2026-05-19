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
        $overrides = [];
        foreach (['html/includes/config.local.php', 'public/includes/config.local.php'] as $rel) {
            $local = $this->rootDir . '/' . $rel;
            if (is_file($local)) {
                $loaded = require $local;
                if (is_array($loaded)) {
                    $overrides = $loaded;
                }
                break;
            }
        }
        if (!is_array($overrides)) {
            $overrides = [];
        }

        $this->baseUrl = rtrim(
            (string) ($overrides['base_url'] ?? getenv('SANCTUM_TMP_BASE_URL') ?: 'https://tmp.sanctumos.org'),
            '/'
        );
        $this->dbPath = (string) ($overrides['db_path'] ?? $this->rootDir . '/db/tmp-sanctum.db');
        $this->storageDir = (string) ($overrides['storage_dir'] ?? $this->rootDir . '/storage');
        $defaultDl = is_dir($this->rootDir . '/html/dl')
            ? $this->rootDir . '/html/dl'
            : $this->rootDir . '/public/dl';
        $this->dlDir = (string) ($overrides['dl_dir'] ?? $defaultDl);
        $this->maxBytes = (int) ($overrides['max_bytes'] ?? getenv('SANCTUM_TMP_MAX_BYTES') ?: 104857600);
        $this->ttlSeconds = (int) ($overrides['ttl_seconds'] ?? getenv('SANCTUM_TMP_TTL_SECONDS') ?: 259200);
    }
}
