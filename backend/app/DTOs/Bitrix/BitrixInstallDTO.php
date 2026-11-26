<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use SensitiveParameter;

/**
 * An app install, from Bitrix24's ONAPPINSTALL event or from the install page
 * an admin opens inside Bitrix24. A missing value is null: the service decides
 * which ones each way of installing needs.
 */
final readonly class BitrixInstallDTO
{
    public function __construct(
        public ?string $memberId,
        public ?string $domain,
        #[SensitiveParameter] public ?string $applicationToken,
        #[SensitiveParameter] public ?string $accessToken,
        #[SensitiveParameter] public ?string $refreshToken,
        /** Seconds the access token has left; null: unknown. */
        public ?int $expiresIn = null,
        public string $scope = '',
        public ?string $language = null,
    ) {}

    /**
     * @param  array<mixed>  $auth  The event's auth block.
     */
    public static function fromEvent(#[SensitiveParameter] array $auth, mixed $language): self
    {
        return new self(
            memberId: self::text($auth['member_id'] ?? null),
            domain: self::text($auth['domain'] ?? null),
            applicationToken: self::text($auth['application_token'] ?? null),
            accessToken: self::text($auth['access_token'] ?? null),
            refreshToken: self::text($auth['refresh_token'] ?? null),
            expiresIn: self::number($auth['expires_in'] ?? null),
            scope: self::text($auth['scope'] ?? null) ?? '',
            language: self::text($language),
        );
    }

    /**
     * @param  array<string, mixed>  $page  The install page's parameters.
     */
    public static function fromPage(#[SensitiveParameter] array $page): self
    {
        return new self(
            memberId: self::text($page['member_id'] ?? null),
            domain: self::text($page['DOMAIN'] ?? null),
            applicationToken: self::text($page['APPLICATION_TOKEN'] ?? null),
            accessToken: self::text($page['AUTH_ID'] ?? null),
            refreshToken: self::text($page['REFRESH_ID'] ?? null),
            expiresIn: self::number($page['AUTH_EXPIRES'] ?? null),
            scope: self::text($page['APPLICATION_SCOPE'] ?? null) ?? '',
            language: self::text($page['LANG'] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    private static function number(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
