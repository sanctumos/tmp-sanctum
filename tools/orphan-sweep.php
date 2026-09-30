#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use SanctumTmp\Config;
use SanctumTmp\OrphanSweeper;

// Same root resolution as purge-expired.php — target the LIVE data root on multihost.
$root = getenv('SANCTUM_TMP_ROOT') ?: ($argv[1] ?? '');
$config = new Config($root !== '' ? $root : null);
$result = (new OrphanSweeper($config))->run();
fwrite(
    STDOUT,
    date('c')
    . ' root=' . $config->rootDir
    . " scanned={$result['scanned']}"
    . " orphan_ids={$result['orphan_ids']}"
    . " deleted={$result['deleted']}"
    . " bytes={$result['bytes']}\n"
);
