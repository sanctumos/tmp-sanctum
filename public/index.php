<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

use SanctumTmp\UploadService;

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = '/' . trim($uri, '/');

if ($uri === '/' || $uri === '/index.php') {
    if ($method === 'POST' && isset($_FILES['file'])) {
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

http_response_code(404);
echo '<!DOCTYPE html><html><body><p>Not found or expired.</p></body></html>';

function render_home(): void
{
    $config = sanctum_tmp_config();
    header('Content-Type: text/html; charset=utf-8');
    $maxMb = (int) round($config->maxBytes / 1048576);
    $ttlDays = (int) round($config->ttlSeconds / 86400);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>Sanctum temporary files</title></head><body>';
    echo '<h1>tmp.sanctumos.org</h1>';
    echo '<p>Ephemeral uploads. Max ' . htmlspecialchars((string) $maxMb) . ' MiB, expires in ~' . htmlspecialchars((string) $ttlDays) . ' days.</p>';
    echo '<form method="post" enctype="multipart/form-data"><input type="file" name="file" required> ';
    echo '<button type="submit">Upload</button></form>';
    echo '<p>API: <code>POST /api/v1/upload.php</code> (multipart <code>file</code>)</p>';
    echo '</body></html>';
}

/** @param array<string, mixed> $row */
function render_view(array $row, string $id, string $filename): void
{
    $config = sanctum_tmp_config();
    $direct = $config->baseUrl . $config->dlUrlPrefix . '/' . rawurlencode($id) . '/' . rawurlencode($filename);
    $expires = date('Y-m-d H:i:s T', (int) $row['expires_at']);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><title>' . htmlspecialchars($filename) . '</title></head><body>';
    echo '<h1>' . htmlspecialchars($filename) . '</h1>';
    echo '<table><tr><th>Size</th><td>' . htmlspecialchars((string) $row['size_bytes']) . ' bytes</td></tr>';
    echo '<tr><th>MIME</th><td>' . htmlspecialchars((string) ($row['mime_type'] ?? '')) . '</td></tr>';
    echo '<tr><th>Expires</th><td>' . htmlspecialchars($expires) . '</td></tr></table>';
    echo '<p><a href="' . htmlspecialchars($direct) . '">Direct download</a> (use this URL for bots/APIs)</p>';
    echo '</body></html>';
}
