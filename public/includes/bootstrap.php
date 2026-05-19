<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use SanctumTmp\Config;

function sanctum_tmp_config(): Config
{
    static $config = null;
    if ($config === null) {
        $config = new Config();
    }
    return $config;
}

function sanctum_tmp_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}
