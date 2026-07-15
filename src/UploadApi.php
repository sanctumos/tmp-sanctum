<?php

declare(strict_types=1);

namespace SanctumTmp;

final class UploadApi
{
    /**
     * @param array{view_url: string, direct_url: string, id: string, filename: string} $result
     * @return array{status: string, data: array<string, string>}
     */
    public static function successPayload(array $result): array
    {
        return [
            'status' => 'success',
            'data' => [
                'url' => $result['view_url'],
                'direct_url' => $result['direct_url'],
                'id' => $result['id'],
                'filename' => $result['filename'],
            ],
        ];
    }

    /**
     * @param list<array{status: string, url?: string, direct_url?: string, id?: string, filename?: string, message?: string}> $fileResults
     * @return array{status: string, data: array{count: int, succeeded: int, failed: int, files: list<array<string, string>>}}
     */
    public static function successMultiPayload(array $fileResults): array
    {
        $succeeded = 0;
        $failed = 0;
        foreach ($fileResults as $row) {
            if (($row['status'] ?? '') === 'success') {
                $succeeded++;
            } else {
                $failed++;
            }
        }
        $overall = $failed === 0 ? 'success' : ($succeeded === 0 ? 'error' : 'partial');

        return [
            'status' => $overall,
            'data' => [
                'count' => count($fileResults),
                'succeeded' => $succeeded,
                'failed' => $failed,
                'files' => $fileResults,
            ],
        ];
    }

    /**
     * Whether the client opted into multi-upload (files[] field or explicit flag).
     */
    public static function isMultiUploadRequested(): bool
    {
        if (isset($_FILES['files']) && is_array($_FILES['files'])) {
            return true;
        }
        $multi = strtolower(trim((string) ($_GET['multi'] ?? $_POST['multi'] ?? '')));
        if ($multi === '1' || $multi === 'true' || $multi === 'yes') {
            return true;
        }
        if (strtolower(trim((string) ($_SERVER['HTTP_X_SANCTUM_TMP_MULTI'] ?? ''))) === '1') {
            return true;
        }

        return false;
    }

    /**
     * Normalize a $_FILES entry (single or array-of-files) into a list of file slots.
     *
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    public static function normalizeFilesArray(array $entry): array
    {
        if (!isset($entry['name']) || !is_array($entry['name'])) {
            return [[
                'name' => (string) ($entry['name'] ?? 'upload.bin'),
                'type' => (string) ($entry['type'] ?? ''),
                'tmp_name' => (string) ($entry['tmp_name'] ?? ''),
                'error' => (int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($entry['size'] ?? 0),
            ]];
        }

        $count = count($entry['name']);
        $files = [];
        for ($i = 0; $i < $count; $i++) {
            $files[] = [
                'name' => (string) ($entry['name'][$i] ?? 'upload.bin'),
                'type' => (string) ($entry['type'][$i] ?? ''),
                'tmp_name' => (string) ($entry['tmp_name'][$i] ?? ''),
                'error' => (int) ($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($entry['size'][$i] ?? 0),
            ];
        }

        return $files;
    }

    /**
     * Resolve incoming multipart files for single or multi mode.
     *
     * @return array{mode: 'single'|'multi', files: list<array{name: string, type: string, tmp_name: string, error: int, size: int}>}|null
     */
    public static function resolveIncomingFiles(): ?array
    {
        if (isset($_FILES['files']) && is_array($_FILES['files'])) {
            $files = self::normalizeFilesArray($_FILES['files']);
            if ($files === []) {
                return null;
            }

            return ['mode' => 'multi', 'files' => $files];
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            return null;
        }

        $files = self::normalizeFilesArray($_FILES['file']);
        if ($files === []) {
            return null;
        }

        $isArrayUpload = isset($_FILES['file']['name']) && is_array($_FILES['file']['name']);
        if ($isArrayUpload && self::isMultiUploadRequested()) {
            return ['mode' => 'multi', 'files' => $files];
        }

        if ($isArrayUpload && count($files) > 1) {
            // Multiple files without opt-in: process first only (backward-safe).
            return ['mode' => 'single', 'files' => [array_values($files)[0]]];
        }

        return ['mode' => 'single', 'files' => [array_values($files)[0]]];
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     * @return array{status: string, filename: string, message?: string, url?: string, direct_url?: string, id?: string}
     */
    public static function fileErrorResult(array $file, string $message): array
    {
        return [
            'status' => 'error',
            'filename' => (string) ($file['name'] ?? 'upload.bin'),
            'message' => $message,
        ];
    }

    public static function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode upload response');
        }

        return $json;
    }

    /**
     * Browser form posts should get HTML; API clients and /api/* paths get JSON.
     */
    public static function wantsJsonResponse(bool $browserFormUpload = false): bool
    {
        if ($browserFormUpload) {
            return false;
        }

        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        if (str_contains($uri, '/api/')) {
            return true;
        }

        if (ClientMode::isAutomatedClient()) {
            return true;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json')
            && (!str_contains($accept, 'text/html') || str_starts_with($accept, 'application/json'))) {
            return true;
        }

        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            return true;
        }

        return false;
    }
}
