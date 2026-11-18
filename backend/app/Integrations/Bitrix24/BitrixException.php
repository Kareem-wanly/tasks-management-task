<?php

namespace App\Integrations\Bitrix24;

use RuntimeException;

class BitrixException extends RuntimeException
{
    public const NO_TOKEN = 'NO_TOKEN';

    public const UNREACHABLE = 'UNREACHABLE';

    public const CANNOT_SYNC = 'CANNOT_SYNC';

    public function __construct(
        public readonly string $errorCode,
        string $message = '',
        public readonly int $status = 0,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    /**
     * Build from a Bitrix error body: {"error": "...", "error_description": "..."},
     * or REST 3.0's {"error": {"code": "...", "message": "..."}}.
     * The body never contains tokens, so it is safe to keep in the message.
     */
    public static function fromResponse(?array $body, int $status): self
    {
        $error = $body['error'] ?? '';
        $code = (string) (is_array($error) ? ($error['code'] ?? '') : $error);
        $description = (string) (is_array($error) ? ($error['message'] ?? '') : ($body['error_description'] ?? ''));

        return new self($code !== '' ? $code : "HTTP_{$status}", $description, $status);
    }

    public static function missingToken(): self
    {
        return new self(self::NO_TOKEN, 'No Bitrix24 token is stored for this account.');
    }

    /**
     * Timeout or network failure: Bitrix never answered.
     * The reason comes from the HTTP client and can quote the full URL, whose query
     * string may carry tokens or the client secret (SEC-5), so it is redacted here.
     */
    public static function unreachable(string $reason): self
    {
        return new self(self::UNREACHABLE, self::redactSecrets($reason));
    }

    /** Drop URL query strings and any token-like key=value pair from a message. */
    public static function redactSecrets(string $text): string
    {
        $text = (string) preg_replace('~(https?://[^\s?#"\'<>]+)\?[^\s"\'<>]*~i', '$1?[redacted]', $text);

        return (string) preg_replace(
            '~\b(auth|access_token|refresh_token|client_secret|code)=[^\s&"\'<>]+~i',
            '$1=[redacted]',
            $text,
        );
    }

    /** Our data can't be expressed in Bitrix (e.g. a user who isn't in Bitrix). */
    public static function cannotSync(string $message): self
    {
        return new self(self::CANNOT_SYNC, $message);
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
        return in_array($this->errorCode, ['ACCESS_DENIED', 'BITRIX_REST_V3_EXCEPTION_ACCESSDENIEDEXCEPTION'], true);
    }

    public function isMissingToken(): bool
    {
        return $this->errorCode === self::NO_TOKEN;
    }
}
