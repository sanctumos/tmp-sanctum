<?php

declare(strict_types=1);

/**
 * Serves /dl/{id}/{filename} when nginx routes missing physical files here.
 * Primary path: static file under public/dl/{id}/{filename} (written on upload).
 */

require_once dirname(__DIR__) . '/includes/bootstrap.php';

use SanctumTmp\UploadService;

$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$prefix = '/dl/';
if (!str_starts_with($uri, $prefix)) {
    http_response_code(404);
    exit('Not found');
}
$rest = substr($uri, strlen($prefix));
$parts = explode('/', $rest, 2);
if (count($parts) !== 2) {
    http_response_code(404);
    exit('Not found');
}

[$id, $filename] = $parts;
$id = rawurldecode($id);
$filename = rawurldecode($filename);

$service = new UploadService(sanctum_tmp_config());
$row = $service->getForDownload($id, $filename);
if ($row === null) {
    http_response_code(404);
    exit('Not found or expired');
}

$config = sanctum_tmp_config();
$path = $config->dlDir . '/' . $row['dl_rel'];
if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$service->recordDownload($row);

$mime = (string) ($row['mime_type'] ?: 'application/octet-stream');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
header('Cache-Control: no-cache');
readfile($path);
exit;
