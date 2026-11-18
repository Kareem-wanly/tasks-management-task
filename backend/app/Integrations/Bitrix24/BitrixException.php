<?php

namespace App\Integrations\Bitrix24;

use RuntimeException;

class BitrixException extends RuntimeException
{
    public const NO_TOKEN = 'NO_TOKEN';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly int $status = 0,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    /**
     * Build from a Bitrix error body: {"error": "...", "error_description": "..."}.
     * The body never contains tokens, so it is safe to keep in the message.
     */
    public static function fromResponse(?array $body, int $status): self
    {
        $code = (string) ($body['error'] ?? '');
        $description = (string) ($body['error_description'] ?? '');

        return new self($code !== '' ? $code : "HTTP_{$status}", $description, $status);
    }

    public static function missingToken(): self
    {
        return new self(self::NO_TOKEN, 'No Bitrix24 token is stored for this account.');
    }

    public function isExpiredToken(): bool
    {
        return $this->errorCode === 'expired_token';
    }

    public function isRateLimited(): bool
    {
        return in_array($this->errorCode, ['QUERY_LIMIT_EXCEEDED', 'OPERATION_TIME_LIMIT', 'OVERLOAD_LIMIT'], true);
    }

    /**
     * Retried inline with backoff, as the implementation plan specifies.
     * OPERATION_TIME_LIMIT waits for a basket reset, so it is not retried.
     */
    public function isRetryable(): bool
    {
        return in_array($this->errorCode, ['QUERY_LIMIT_EXCEEDED', 'OVERLOAD_LIMIT'], true);
    }

    public function isAccessDenied(): bool
    {
        return $this->errorCode === 'ACCESS_DENIED';
    }

    public function isMissingToken(): bool
    {
        return $this->errorCode === self::NO_TOKEN;
    }
}
