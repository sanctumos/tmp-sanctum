<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/views.php';

use SanctumTmp\UploadApi;
use SanctumTmp\UploadService;

$browserForm = defined('SANCTUM_TMP_BROWSER_UPLOAD') && SANCTUM_TMP_BROWSER_UPLOAD;
$wantsJson = UploadApi::wantsJsonResponse($browserForm);
$config = sanctum_tmp_config();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if ($wantsJson) {
        sanctum_tmp_json(405, ['status' => 'error', 'message' => 'Method not allowed']);
    } else {
        render_upload_error('Method not allowed', 405);
    }
    exit;
}

$incoming = UploadApi::resolveIncomingFiles();
if ($incoming === null) {
    if ($wantsJson) {
        sanctum_tmp_json(400, ['status' => 'error', 'message' => 'Missing file field']);
    } else {
        render_upload_error('Missing file field');
    }
    exit;
}

$mode = $incoming['mode'];
$files = $incoming['files'];

if ($mode === 'multi' && count($files) > $config->maxBatchSize) {
    $message = 'Batch exceeds maximum of ' . $config->maxBatchSize . ' files';
    if ($wantsJson) {
        sanctum_tmp_json(400, ['status' => 'error', 'message' => $message]);
    } else {
        render_upload_error($message);
    }
    exit;
}

if ($mode === 'single') {
    $file = $files[0];
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
    $mime = isset($file['type']) && $file['type'] !== '' ? (string) $file['type'] : null;

    try {
        $service = new UploadService($config);
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
    exit;
}

// Multi-upload batch
$service = new UploadService($config);
$fileResults = [];
foreach ($files as $file) {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $fileResults[] = UploadApi::fileErrorResult(
            $file,
            'Upload error (code ' . (int) ($file['error'] ?? 0) . ')'
        );
        continue;
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $name = (string) ($file['name'] ?? 'upload.bin');
    $mime = isset($file['type']) && $file['type'] !== '' ? (string) $file['type'] : null;

    try {
        $result = $service->storeUploadedFile($name, $tmp, $mime);
        $single = UploadApi::successPayload($result);
        $fileResults[] = array_merge(['status' => 'success'], $single['data']);
    } catch (Throwable $e) {
        $fileResults[] = UploadApi::fileErrorResult($file, $e->getMessage());
    }
}

$payload = UploadApi::successMultiPayload($fileResults);
$httpStatus = match ($payload['status']) {
    'success' => 200,
    'partial' => 207,
    default => 500,
};

if ($wantsJson) {
    sanctum_tmp_json($httpStatus, $payload);
} else {
    if ($payload['status'] === 'error') {
        $firstMsg = $fileResults[0]['message'] ?? 'All uploads failed';
        render_upload_error((string) $firstMsg, 500);
    } else {
        render_upload_success_multi($payload);
    }
}
