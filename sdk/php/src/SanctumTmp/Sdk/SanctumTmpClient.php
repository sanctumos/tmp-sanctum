<?php

declare(strict_types=1);

namespace SanctumTmp\Sdk;

/**
 * PHP client for tmp.sanctumos.org (or self-hosted Sanctum Temp).
 *
 * Method surface mirrors sanctum_tmp_sdk (Python): upload, encode, encodeOrUpload, download.
 * Public API — no API key.
 */
class SanctumTmpClient
{
    public const DEFAULT_BASE = 'https://tmp.sanctumos.org';
    public const DEFAULT_UA = 'sanctum-tmp-sdk/0.1.0 (PHP)';

    private string $baseUrl;
    private string $userAgent;
    private int $timeoutSeconds;

    public function __construct(
        ?string $baseUrl = null,
        ?string $userAgent = null,
        int $timeoutSeconds = 120,
    ) {
        $envBase = getenv('SANCTUM_TMP_BASE_URL');
        $this->baseUrl = rtrim(
            $baseUrl
                ?? ($envBase !== false && $envBase !== '' ? $envBase : self::DEFAULT_BASE),
            '/'
        );
        $envUa = getenv('SANCTUM_TMP_USER_AGENT');
        $this->userAgent = $userAgent
            ?? ($envUa !== false && $envUa !== '' ? $envUa : self::DEFAULT_UA);
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function uploadUrl(): string
    {
        return $this->baseUrl . '/api/v1/upload.php';
    }

    public function encodeUrl(): string
    {
        return $this->baseUrl . '/api/v1/encode.php';
    }

    /**
     * Lightweight liveness: fetch agent discovery markdown.
     *
     * @return array{status:string,base_url:string,agents_md_bytes:int}
     */
    public function health(): array
    {
        $text = $this->requestRaw('GET', '/AGENTS.md');
        return [
            'status' => 'ok',
            'base_url' => $this->baseUrl,
            'agents_md_bytes' => strlen($text),
        ];
    }

    /**
     * Upload a local file via multipart field `file`.
     *
     * @return array<string,mixed>
     */
    public function uploadFile(
        string $path,
        ?string $filename = null,
        ?string $mime = null,
        string $field = 'file',
    ): array {
        if (!is_file($path) || !is_readable($path)) {
            throw new ValidationException('File not readable: ' . $path);
        }
        $name = $filename ?? basename($path);
        $type = $mime ?? (mime_content_type($path) ?: 'application/octet-stream');
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new ApiException('Failed to read file: ' . $path);
        }
        return $this->uploadBytes($bytes, $name, $type, $field);
    }

    /**
     * Upload raw bytes as a single multipart part.
     *
     * @return array<string,mixed>
     */
    public function uploadBytes(
        string $data,
        string $filename,
        string $mime = 'application/octet-stream',
        string $field = 'file',
    ): array {
        return $this->multipartUpload([[$field, $filename, $mime, $data]]);
    }

    /**
     * Upload multiple local files via multipart `files[]`.
     *
     * @param list<string> $paths
     * @return array<string,mixed>
     */
    public function uploadFiles(array $paths, string $field = 'files[]'): array
    {
        if ($paths === []) {
            throw new ValidationException('No files to upload');
        }
        $parts = [];
        foreach ($paths as $path) {
            if (!is_file($path) || !is_readable($path)) {
                throw new ValidationException('File not readable: ' . $path);
            }
            $bytes = file_get_contents($path);
            if ($bytes === false) {
                throw new ApiException('Failed to read file: ' . $path);
            }
            $name = basename($path);
            $mime = mime_content_type($path) ?: 'application/octet-stream';
            $parts[] = [$field, $name, $mime, $bytes];
        }
        return $this->multipartUpload($parts);
    }

    /**
     * Host UTF-8 text as a short-lived file.
     *
     * @return array<string,mixed>
     */
    public function uploadText(
        string $text,
        string $filename = 'share.txt',
        string $mime = 'text/plain',
    ): array {
        return $this->uploadBytes($text, $filename, $mime);
    }

    /**
     * QR-first encode via POST /api/v1/encode.php.
     *
     * @param array{
     *   type?:string,format?:string,ecl?:string,scale?:int,persist?:bool,
     *   logo_base64?:?string,logo_size_pct?:int,filename?:string
     * } $options
     * @return array<string,mixed>
     */
    public function encode(string $content, array $options = []): array
    {
        $body = array_merge([
            'content' => $content,
            'type' => 'auto',
            'format' => 'svg',
            'ecl' => 'M',
            'scale' => 5,
            'persist' => false,
            'logo_size_pct' => 22,
            'filename' => 'payload.txt',
        ], $options);
        $body['content'] = $content;
        return $this->requestJson('POST', '/api/v1/encode.php', $body);
    }

    /**
     * Match product default: try QR encode; on qr_capacity (or preferQr=false) host as file.
     *
     * Adds sanctum_tmp_mode: "qr" | "file".
     *
     * @return array<string,mixed>
     */
    public function encodeOrUpload(
        string $content,
        string $type = 'auto',
        string $format = 'svg',
        string $filename = 'share.txt',
        bool $preferQr = true,
    ): array {
        if (!$preferQr) {
            $out = $this->uploadText($content, $filename);
            $out['sanctum_tmp_mode'] = 'file';
            return $out;
        }
        try {
            $out = $this->encode($content, [
                'type' => $type,
                'format' => $format,
                'filename' => $filename,
            ]);
            $out['sanctum_tmp_mode'] = 'qr';
            return $out;
        } catch (QrCapacityException) {
            $out = $this->uploadText($content, $filename);
            $out['sanctum_tmp_mode'] = 'file';
            $out['sanctum_tmp_fallback'] = 'qr_capacity';
            return $out;
        }
    }

    /**
     * POST /api/v1/qr.php — HTML fragment (website results helper).
     *
     * @param array<string,mixed> $payload
     */
    public function qrHtmlFragment(array $payload): string
    {
        return $this->requestRaw(
            'POST',
            '/api/v1/qr.php',
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
            ['Content-Type: application/json; charset=utf-8'],
            expectJson: false,
        );
    }

    /** GET raw bytes from a direct_url (absolute or path on this host). */
    public function download(string $directUrl): string
    {
        if (str_starts_with($directUrl, '/')) {
            $directUrl = $this->baseUrl . $directUrl;
        }
        return $this->requestRaw('GET', $directUrl, null, [], expectJson: false, absolute: true);
    }

    /**
     * @param list<array{0:string,1:string,2:string,3:string}> $parts field, filename, mime, data
     * @return array<string,mixed>
     */
    private function multipartUpload(array $parts): array
    {
        $boundary = '----SanctumTmp' . bin2hex(random_bytes(8));
        $body = '';
        foreach ($parts as [$field, $filename, $mime, $data]) {
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="' . $field . '"; filename="' . $filename . "\"\r\n";
            $body .= 'Content-Type: ' . $mime . "\r\n\r\n";
            $body .= $data . "\r\n";
        }
        $body .= '--' . $boundary . "--\r\n";
        return $this->requestJson(
            'POST',
            '/api/v1/upload.php',
            null,
            $body,
            ['Content-Type: multipart/form-data; boundary=' . $boundary],
        );
    }

    /**
     * @param array<string,mixed>|null $jsonBody
     * @param list<string> $extraHeaders
     * @return array<string,mixed>
     */
    private function requestJson(
        string $method,
        string $path,
        ?array $jsonBody = null,
        ?string $rawBody = null,
        array $extraHeaders = [],
    ): array {
        if ($jsonBody !== null) {
            $rawBody = json_encode($jsonBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($rawBody === false) {
                throw new ValidationException('Failed to encode JSON body');
            }
            $extraHeaders[] = 'Content-Type: application/json; charset=utf-8';
        }
        $text = $this->requestRaw($method, $path, $rawBody, $extraHeaders, expectJson: true);
        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new ApiException('Invalid JSON response: ' . substr($text, 0, 200));
        }
        return $data;
    }

    /**
     * @param list<string> $extraHeaders
     */
    private function requestRaw(
        string $method,
        string $path,
        ?string $body = null,
        array $extraHeaders = [],
        bool $expectJson = false,
        bool $absolute = false,
    ): string {
        $url = $absolute ? $path : ($this->baseUrl . $path);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new ApiException('curl_init failed');
        }
        $headers = array_merge(
            ['Accept: application/json, text/plain, */*', 'User-Agent: ' . $this->userAgent],
            $extraHeaders,
        );
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new ApiException('HTTP request failed: ' . $err, $status);
        }
        $text = (string) $raw;
        if ($status >= 400 || ($expectJson && $this->jsonLooksLikeError($text))) {
            $this->throwForError($status, $text);
        }
        return $text;
    }

    private function jsonLooksLikeError(string $text): bool
    {
        $trim = ltrim($text);
        if ($trim === '' || $trim[0] !== '{') {
            return false;
        }
        $data = json_decode($text, true);
        return is_array($data) && ($data['status'] ?? null) === 'error';
    }

    private function throwForError(int $status, string $text): void
    {
        $data = json_decode($text, true);
        if (!is_array($data)) {
            throw new ApiException('HTTP ' . $status . ': ' . substr($text, 0, 200), $status);
        }
        $message = (string) ($data['message'] ?? $data['error'] ?? ('API error: ' . $status));
        $code = isset($data['code']) ? (string) $data['code'] : null;
        if ($code === 'qr_capacity' || ($status === 422 && str_contains($message, 'QR'))) {
            throw new QrCapacityException(
                $message,
                $status,
                $data,
                isset($data['encoded']) ? (string) $data['encoded'] : null,
                isset($data['type']) ? (string) $data['type'] : null,
            );
        }
        if (in_array($status, [400, 413, 422], true)) {
            throw new ValidationException($message, $status, $code, $data);
        }
        throw new ApiException($message, $status, $code, $data);
    }
}
