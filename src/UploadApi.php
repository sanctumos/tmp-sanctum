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
