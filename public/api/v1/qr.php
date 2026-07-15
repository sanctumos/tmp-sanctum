<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/views.php';

use SanctumTmp\UploadApi;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

$raw = (string) file_get_contents('php://input');
if ($raw === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Missing JSON body'], JSON_UNESCAPED_SLASHES);
    exit;
}

if (strlen($raw) > 8192) {
    http_response_code(413);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Payload too large for QR encoding'], JSON_UNESCAPED_SLASHES);
    exit;
}

$decoded = json_decode($raw, true);
if (!is_array($decoded)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON'], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $json = UploadApi::encodePayload($decoded);
} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
sanctum_tmp_render_qr_block(
    $json,
    'Scan to copy the batch API JSON on your phone — no OCR.'
);
