<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use App\Enums\BitrixInstallSource;
use SensitiveParameter;

/**
 * An app install, from Bitrix24's ONAPPINSTALL event or from the install page
 * an admin opens inside Bitrix24. A missing value is null: the service decides
 * which ones each way of installing needs.
 */
final readonly class BitrixInstallDTO
{
    public function __construct(
        /** Which way it came; null when the call is neither the event nor the page. */
        public ?BitrixInstallSource $source,
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
     * Raw values are cast here: Bitrix sends numbers and text alike as strings, and empty means missing.
     *
     * @param  array{source: ?BitrixInstallSource, member_id?: mixed, domain?: mixed, application_token?: mixed, access_token?: mixed, refresh_token?: mixed, expires_in?: mixed, scope?: mixed, language?: mixed}  $data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            source: $data['source'],
            memberId: self::text($data['member_id'] ?? null),
            domain: self::text($data['domain'] ?? null),
            applicationToken: self::text($data['application_token'] ?? null),
            accessToken: self::text($data['access_token'] ?? null),
            refreshToken: self::text($data['refresh_token'] ?? null),
            expiresIn: self::number($data['expires_in'] ?? null),
            scope: self::text($data['scope'] ?? null) ?? '',
            language: self::text($data['language'] ?? null),
        );
    }

    /**
     * @param  array<mixed>  $auth  The event's auth block.
     */
    public static function fromEvent(#[SensitiveParameter] array $auth, mixed $language): self
    {
        return self::fromArray([
            'source' => BitrixInstallSource::Event,
            'member_id' => $auth['member_id'] ?? null,
            'domain' => $auth['domain'] ?? null,
            'application_token' => $auth['application_token'] ?? null,
            'access_token' => $auth['access_token'] ?? null,
            'refresh_token' => $auth['refresh_token'] ?? null,
            'expires_in' => $auth['expires_in'] ?? null,
            'scope' => $auth['scope'] ?? null,
            'language' => $language,
        ]);
    }

    /**
     * @param  array<string, mixed>  $page  The install page's parameters.
     */
    public static function fromPage(#[SensitiveParameter] array $page): self
    {
        return self::fromArray([
            'source' => BitrixInstallSource::Page,
            'member_id' => $page['member_id'] ?? null,
            'domain' => $page['DOMAIN'] ?? null,
            'application_token' => $page['APPLICATION_TOKEN'] ?? null,
            'access_token' => $page['AUTH_ID'] ?? null,
            'refresh_token' => $page['REFRESH_ID'] ?? null,
            'expires_in' => $page['AUTH_EXPIRES'] ?? null,
            'scope' => $page['APPLICATION_SCOPE'] ?? null,
            'language' => $page['LANG'] ?? null,
        ]);
    }

    /** A call to the install path that is neither the event nor the page. */
    public static function unsupported(): self
    {
        return self::fromArray(['source' => null]);
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
