<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

use SanctumTmp\UploadService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sanctum_tmp_json(405, ['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    sanctum_tmp_json(400, ['status' => 'error', 'message' => 'Missing file field']);
    exit;
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    sanctum_tmp_json(400, ['status' => 'error', 'message' => 'Upload error', 'code' => $file['error'] ?? 0]);
    exit;
}

$tmp = (string) ($file['tmp_name'] ?? '');
$name = (string) ($file['name'] ?? 'upload.bin');
$mime = isset($file['type']) ? (string) $file['type'] : null;

try {
    $service = new UploadService(sanctum_tmp_config());
    $result = $service->storeUploadedFile($name, $tmp, $mime);
    sanctum_tmp_json(200, [
        'status' => 'success',
        'data' => [
            'url' => $result['view_url'],
            'direct_url' => $result['direct_url'],
            'id' => $result['id'],
            'filename' => $result['filename'],
        ],
    ]);
} catch (Throwable $e) {
    sanctum_tmp_json(500, ['status' => 'error', 'message' => $e->getMessage()]);
}
