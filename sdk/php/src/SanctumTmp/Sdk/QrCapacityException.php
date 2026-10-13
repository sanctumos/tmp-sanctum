<?php

declare(strict_types=1);

namespace SanctumTmp\Sdk;

/**
 * Content will not fit in a QR — fall back to upload.
 */
class QrCapacityException extends ValidationException
{
    /** @param array<string,mixed>|null $response */
    public function __construct(
        string $message,
        int $httpStatus = 422,
        ?array $response = null,
        public readonly ?string $encoded = null,
        public readonly ?string $contentType = null,
    ) {
        parent::__construct($message, $httpStatus, 'qr_capacity', $response);
    }
}
