<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/views.php';

use SanctumTmp\UploadApi;
use SanctumTmp\UploadService;

$browserForm = defined('SANCTUM_TMP_BROWSER_UPLOAD') && SANCTUM_TMP_BROWSER_UPLOAD;
$wantsJson = UploadApi::wantsJsonResponse($browserForm);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($wantsJson) {
        sanctum_tmp_json(405, ['status' => 'error', 'message' => 'Method not allowed']);
    } else {
        render_upload_error('Method not allowed', 405);
    }
    exit;
}

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    if ($wantsJson) {
        sanctum_tmp_json(400, ['status' => 'error', 'message' => 'Missing file field']);
    } else {
        render_upload_error('Missing file field');
    }
    exit;
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $payload = ['status' => 'error', 'message' => 'Upload error', 'code' => $file['error'] ?? 0];
    if ($wantsJson) {
        sanctum_tmp_json(400, $payload);
    } else {
        render_upload_error('Upload error (code ' . (int) ($file['error'] ?? 0) . ')');
    }
    exit;
}

$tmp = (string) ($file['tmp_name'] ?? '');
$name = (string) ($file['name'] ?? 'upload.bin');
$mime = isset($file['type']) ? (string) $file['type'] : null;

try {
    $service = new UploadService(sanctum_tmp_config());
    $result = $service->storeUploadedFile($name, $tmp, $mime);
    $payload = UploadApi::successPayload($result);
    if ($wantsJson) {
        sanctum_tmp_json(200, $payload);
    } else {
        render_upload_success($payload);
    }
} catch (Throwable $e) {
    if ($wantsJson) {
        sanctum_tmp_json(500, ['status' => 'error', 'message' => $e->getMessage()]);
    } else {
        render_upload_error($e->getMessage(), 500);
    }
}
