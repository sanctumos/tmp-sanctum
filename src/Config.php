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
    public readonly string $dlUrlPrefix;
    public readonly int $maxBytes;
    public readonly int $ttlSeconds;
    /** Max files per multi-upload batch (DoS guard). */
    public readonly int $maxBatchSize;

    public function __construct(?string $rootDir = null)
    {
        $this->rootDir = $rootDir ?? dirname(__DIR__);
        $overrides = [];
        // Search order: a parent-level file (one directory above the docroot) is
        // preferred because it survives the multihost `rsync --delete` deploy;
        // in-docroot locations are kept for dev and backward compatibility.
        foreach ([
            'config.local.php',
            'html/includes/config.local.php',
            'public/includes/config.local.php',
        ] as $rel) {
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
        // Legacy dual-write path. New uploads write ONLY to dlDir; storageDir is
        // retained so OrphanSweeper / purge can clean leftover storage/{id}/ trees.
        $this->storageDir = (string) ($overrides['storage_dir'] ?? $this->rootDir . '/storage');
        // Single copy of bytes: published under `uploads/` so multihost deploy
        // (rsync -a --delete with --exclude='uploads/') preserves them across
        // the every-2-minute deploy instead of wiping them.
        $defaultDl = is_dir($this->rootDir . '/html')
            ? $this->rootDir . '/html/uploads'
            : $this->rootDir . '/public/uploads';
        $this->dlDir = (string) ($overrides['dl_dir'] ?? $defaultDl);
        $this->dlUrlPrefix = '/' . trim(
            (string) ($overrides['dl_url_prefix'] ?? getenv('SANCTUM_TMP_DL_URL_PREFIX') ?: 'uploads'),
            '/'
        );
        $this->maxBytes = (int) ($overrides['max_bytes'] ?? getenv('SANCTUM_TMP_MAX_BYTES') ?: 104857600);
        $this->ttlSeconds = (int) ($overrides['ttl_seconds'] ?? getenv('SANCTUM_TMP_TTL_SECONDS') ?: 259200);
        $this->maxBatchSize = (int) ($overrides['max_batch_size'] ?? getenv('SANCTUM_TMP_MAX_BATCH_SIZE') ?: 20);
        if ($this->maxBatchSize < 1) {
            $this->maxBatchSize = 1;
        }
    }
}
