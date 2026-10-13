<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/views.php';

use SanctumTmp\UploadService;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = '/' . trim($uri, '/');

if ($uri === '/docs/api' || $uri === '/docs/API.md') {
    if ($uri === '/docs/API.md') {
        $path = sanctum_tmp_doc_path('API.md');
        sanctum_tmp_send_markdown_file($path, 'API.md');
        exit;
    }
    render_api_docs();
    exit;
}

if ($uri === '/app' || $uri === '/app/' || $uri === '/download' || $uri === '/download/') {
    render_app_download();
    exit;
}

if ($uri === '/docs/android' || $uri === '/docs/android/' || $uri === '/docs/ANDROID.md') {
    if ($uri === '/docs/ANDROID.md') {
        sanctum_tmp_send_markdown_file(sanctum_tmp_doc_path('ANDROID.md'), 'ANDROID.md');
        exit;
    }
    render_markdown_doc('ANDROID.md', 'Sanctum Share (Android)');
    exit;
}

if ($uri === '/docs/branding' || $uri === '/docs/branding/' || $uri === '/docs/BRANDING.md') {
    if ($uri === '/docs/BRANDING.md') {
        sanctum_tmp_send_markdown_file(sanctum_tmp_doc_path('BRANDING.md'), 'BRANDING.md');
        exit;
    }
    render_markdown_doc('BRANDING.md', 'Branding');
    exit;
}

if ($uri === '/AGENTS.md') {
    sanctum_tmp_send_markdown_file(sanctum_tmp_agents_path(), 'AGENTS.md');
    exit;
}

if ($uri === '/' || $uri === '/index.php') {
    if ($method === 'GET' && isset($_GET['uploaded']) && (string) $_GET['uploaded'] === '1') {
        render_upload_results_page();
        exit;
    }
    if ($method === 'POST' && (isset($_FILES['file']) || isset($_FILES['files']))) {
        define('SANCTUM_TMP_BROWSER_UPLOAD', true);
        require __DIR__ . '/api/v1/upload.php';
        exit;
    }
    render_home();
    exit;
}

if (str_starts_with($uri, '/dl/')) {
    require __DIR__ . '/dl/index.php';
    exit;
}

if (str_starts_with($uri, '/api/')) {
    http_response_code(404);
    sanctum_tmp_json(404, ['status' => 'error', 'message' => 'Unknown API path']);
    exit;
}

$trimmed = trim($uri, '/');
$parts = explode('/', $trimmed, 2);
if (count($parts) === 2) {
    [$id, $filename] = $parts;
    $id = rawurldecode($id);
    $filename = rawurldecode($filename);
    $service = new UploadService(sanctum_tmp_config());
    $row = $service->getForDownload($id, $filename);
    if ($row !== null) {
        render_view($row, $id, $filename);
        exit;
    }
}

render_not_found();
