<?php

declare(strict_types=1);

namespace App\Exceptions\Bitrix;

use App\Exceptions\BusinessException;

/**
 * Bitrix24 did not do what we asked. The named constructors return the
 * specific failure (each with its own status and message for the caller);
 * code that only needs "Bitrix failed" catches this base class, and
 * $errorCode still tells the Bitrix error apart (expired_token, ACCESS_DENIED...).
 */
abstract class BitrixException extends BusinessException
{
    public const NO_TOKEN = 'NO_TOKEN';

    public const UNREACHABLE = 'UNREACHABLE';

    public const CANNOT_SYNC = 'CANNOT_SYNC';

    public const UNEXPECTED_RESPONSE = 'UNEXPECTED_RESPONSE';

    final public function __construct(
        public readonly string $errorCode,
        string $message = '',
        /** Bitrix's own HTTP status, when it answered (0 otherwise). */
        public readonly int $httpStatus = 0,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode);
    }

    /**
     * Build from a Bitrix error body: {"error": "...", "error_description": "..."},
     * or REST 3.0's {"error": {"code": "...", "message": "..."}}.
     * The body never contains tokens, so it is safe to keep in the message.
     *
     * @param  array<mixed>|null  $body  The decoded JSON body, as Bitrix sent it.
     */
    public static function fromResponse(?array $body, int $status): BitrixRequestFailedException
    {
        $error = $body['error'] ?? '';
        $code = self::scalarText(is_array($error) ? ($error['code'] ?? '') : $error);
        $description = self::scalarText(is_array($error) ? ($error['message'] ?? '') : ($body['error_description'] ?? ''));

        return new BitrixRequestFailedException($code !== '' ? $code : "HTTP_{$status}", $description, $status);
    }

    public static function missingToken(): BitrixTokenMissingException
    {
        return new BitrixTokenMissingException(self::NO_TOKEN, 'No Bitrix24 token is stored for this account.');
    }

    /**
     * Timeout or network failure: Bitrix never answered.
     * The reason comes from the HTTP client and can quote the full URL, whose query
     * string may carry tokens or the client secret (SEC-5), so it is redacted here.
     */
    public static function unreachable(string $reason): BitrixUnavailableException
    {
        return new BitrixUnavailableException(self::UNREACHABLE, self::redactSecrets($reason));
    }

    /** Our data can't be expressed in Bitrix (e.g. a user who isn't in Bitrix). */
    public static function cannotSync(string $message): BitrixCannotSyncException
    {
        return new BitrixCannotSyncException(self::CANNOT_SYNC, $message);
    }

    /** Bitrix answered in a shape its API doesn't document; $detail names the field, not its value. */
    public static function unexpectedResponse(string $source, string $detail): BitrixUnexpectedResponseException
    {
        return new BitrixUnexpectedResponseException(self::UNEXPECTED_RESPONSE, "Unexpected Bitrix24 answer from {$source}: {$detail}.");
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

    /** An error field as text; anything that isn't a plain value counts as missing. */
    private static function scalarText(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
