<?php

declare(strict_types=1);

namespace SanctumTmp\Sdk;

/**
 * Base error for the Sanctum Temp PHP SDK.
 */
class SanctumTmpException extends \RuntimeException
{
    /** @param array<string,mixed>|null $response */
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly ?string $errorCode = null,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message, $httpStatus);
    }
}
