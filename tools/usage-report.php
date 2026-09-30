#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use SanctumTmp\Config;
use SanctumTmp\UsageReport;

$root = getenv('SANCTUM_TMP_ROOT') ?: '';
$days = 7;

foreach ($argv as $i => $arg) {
    if ($i === 0) {
        continue;
    }
    if (preg_match('/^--days=(\d+)$/', $arg, $m)) {
        $days = max(1, (int) $m[1]);
        continue;
    }
    if ($arg === '--days' && isset($argv[$i + 1])) {
        $days = max(1, (int) $argv[$i + 1]);
        continue;
    }
    if ($arg === '--help' || $arg === '-h') {
        fwrite(STDOUT, "Usage: php tools/usage-report.php [--days=N] [SANCTUM_TMP_ROOT]\n");
        fwrite(STDOUT, "  Default --days=7. Env SANCTUM_TMP_ROOT or positional path sets data root.\n");
        exit(0);
    }
    if ($arg !== '' && !str_starts_with($arg, '-') && $root === '') {
        $root = $arg;
    }
}

$config = new Config($root !== '' ? $root : null);
$report = new UsageReport($config);
$data = $report->summarize($days);
fwrite(STDOUT, UsageReport::formatText($data));
