<?php

declare(strict_types=1);

/**
 * QR-first encode endpoint (browser studio + optional short-lived artifact).
 * Does not replace POST /api/v1/upload.php — agents keep using upload for file hosting.
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

use SanctumTmp\QrNormalize;
use SanctumTmp\QrRender;
use SanctumTmp\UploadApi;
use SanctumTmp\UploadService;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    sanctum_tmp_json(405, ['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$raw = (string) file_get_contents('php://input');
if ($raw === '') {
    sanctum_tmp_json(400, ['status' => 'error', 'message' => 'Missing JSON body']);
    exit;
}

// Soft ceiling before we even try — QR capacity is ~2–3 KiB; allow headroom for UTF-8.
if (strlen($raw) > 16384) {
    sanctum_tmp_json(413, ['status' => 'error', 'message' => 'Payload too large to attempt QR encoding', 'code' => 'too_large']);
    exit;
}

$body = json_decode($raw, true);
if (!is_array($body)) {
    sanctum_tmp_json(400, ['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$content = isset($body['content']) ? (string) $body['content'] : '';
$type = isset($body['type']) ? (string) $body['type'] : 'auto';
$format = isset($body['format']) ? strtolower((string) $body['format']) : 'svg';
$ecl = isset($body['ecl']) ? (string) $body['ecl'] : 'M';
$scale = isset($body['scale']) ? (int) $body['scale'] : 5;
$logo = isset($body['logo_base64']) ? (string) $body['logo_base64'] : null;
$logoPct = isset($body['logo_size_pct']) ? (int) $body['logo_size_pct'] : 22;
$persist = !empty($body['persist']);
$filenameHint = isset($body['filename']) ? (string) $body['filename'] : 'payload.txt';

if ($content === '') {
    sanctum_tmp_json(422, ['status' => 'error', 'message' => 'Content is required', 'code' => 'empty']);
    exit;
}

$normalized = QrNormalize::process($content, $type);
if (!empty($normalized['error'])) {
    sanctum_tmp_json(422, [
        'status' => 'error',
        'message' => (string) $normalized['error'],
        'code' => 'validation',
        'type' => $normalized['type'] ?? $type,
    ]);
    exit;
}

$payload = (string) ($normalized['encoded'] ?? '');
if ($payload === '') {
    sanctum_tmp_json(422, ['status' => 'error', 'message' => 'Could not encode content', 'code' => 'empty']);
    exit;
}

$rendered = QrRender::tryEncode(
    $payload,
    $format,
    $ecl,
    $scale,
    ($logo !== null && $logo !== '') ? $logo : null,
    $logoPct,
);

if ($rendered === null) {
    sanctum_tmp_json(422, [
        'status' => 'error',
        'message' => 'Content will not fit in a QR code; use file hosting instead',
        'code' => 'qr_capacity',
        'encoded' => $payload,
        'type' => $normalized['type'] ?? $type,
    ]);
    exit;
}

$response = [
    'status' => 'success',
    'data' => [
        'mode' => 'qr',
        'encoded' => $payload,
        'type' => $normalized['type'] ?? $type,
        'format' => $rendered['format'],
        'mime_type' => $rendered['mime_type'],
        'data_base64' => $rendered['data_base64'],
        'data_uri' => $rendered['data_uri'],
        'width' => $rendered['width'],
        'height' => $rendered['height'],
        'ecl_effective' => $rendered['ecl_effective'],
        'suggestion' => $normalized['suggestion'] ?? null,
        'suggestion_reason' => $normalized['suggestion_reason'] ?? null,
        'persisted' => false,
    ],
];

if ($persist) {
    $config = sanctum_tmp_config();
    $tmp = tempnam(sys_get_temp_dir(), 'tmqr_');
    if ($tmp === false) {
        sanctum_tmp_json(500, ['status' => 'error', 'message' => 'Failed to allocate temp file for persist']);
        exit;
    }
    try {
        if (file_put_contents($tmp, $payload) === false) {
            throw new RuntimeException('Failed to write persist temp');
        }
        $service = new UploadService($config);
        $safeName = $filenameHint !== '' ? $filenameHint : 'payload.txt';
        $stored = $service->storeUploadedFile($safeName, $tmp, 'text/plain');
        $uploadPayload = UploadApi::successPayload($stored);
        $response['data']['persisted'] = true;
        $response['data']['url'] = $uploadPayload['data']['url'];
        $response['data']['direct_url'] = $uploadPayload['data']['direct_url'];
        $response['data']['id'] = $uploadPayload['data']['id'];
        $response['data']['filename'] = $uploadPayload['data']['filename'];
    } catch (Throwable $e) {
        @unlink($tmp);
        sanctum_tmp_json(500, ['status' => 'error', 'message' => 'QR ok but persist failed: ' . $e->getMessage()]);
        exit;
    }
    @unlink($tmp);
}

sanctum_tmp_json(200, $response);
