<?php

declare(strict_types=1);

$autoload = null;
foreach ([dirname(__DIR__, 2), dirname(__DIR__, 3)] as $root) {
    $candidate = $root . '/vendor/autoload.php';
    if (is_file($candidate)) {
        $autoload = $candidate;
        break;
    }
}
if ($autoload === null) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Server misconfiguration: run composer install at deploy root']);
    exit;
}
require_once $autoload;

use SanctumTmp\Config;

function sanctum_tmp_config(): Config
{
    static $config = null;
    if ($config === null) {
        // Parent of docroot (public/ or html/ on multihost).
        $config = new Config(dirname(__DIR__, 2));
    }
    return $config;
}

function sanctum_tmp_json(int $status, array $payload): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}
