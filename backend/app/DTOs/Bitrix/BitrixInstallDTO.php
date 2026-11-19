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
     * @param  array<string, mixed>  $auth  The event's auth block.
     */
    public static function fromEvent(#[SensitiveParameter] array $auth, mixed $language): self
    {
        return new self(
            memberId: self::text($auth['member_id'] ?? null),
            domain: self::text($auth['domain'] ?? null),
            applicationToken: self::text($auth['application_token'] ?? null),
            accessToken: self::text($auth['access_token'] ?? null),
            refreshToken: self::text($auth['refresh_token'] ?? null),
            expiresIn: isset($auth['expires_in']) ? (int) $auth['expires_in'] : null,
            scope: (string) ($auth['scope'] ?? ''),
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
            expiresIn: isset($page['AUTH_EXPIRES']) ? (int) $page['AUTH_EXPIRES'] : null,
            scope: (string) ($page['APPLICATION_SCOPE'] ?? ''),
            language: self::text($page['LANG'] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }
}
