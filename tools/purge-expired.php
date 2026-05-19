#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use SanctumTmp\Config;
use SanctumTmp\UploadService;

$config = new Config();
$count = (new UploadService($config))->purgeExpired();
fwrite(STDOUT, date('c') . " purged={$count}\n");
