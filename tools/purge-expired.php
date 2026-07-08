#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use SanctumTmp\Config;
use SanctumTmp\UploadService;

// The live data root differs from the repo checkout on multihost (the deployed
// docroot's parent, e.g. /var/www/tmp.sanctumos.org). Allow the cron to target it
// via SANCTUM_TMP_ROOT or an argv path; otherwise fall back to the repo default.
$root = getenv('SANCTUM_TMP_ROOT') ?: ($argv[1] ?? '');
$config = new Config($root !== '' ? $root : null);
$count = (new UploadService($config))->purgeExpired();
fwrite(STDOUT, date('c') . " root=" . $config->rootDir . " purged={$count}\n");
