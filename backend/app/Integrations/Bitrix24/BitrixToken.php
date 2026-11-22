<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use Carbon\CarbonImmutable;

final readonly class BitrixToken
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public CarbonImmutable $expiresAt,
        public ?string $domain = null,
        public ?string $memberId = null,
        public array $scopes = [],
    ) {}

    /**
     * The OAuth "domain" field is oauth.bitrix.info, so the portal domain is
     * taken from client_endpoint (https://<portal>/rest/).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromOAuthResponse(array $data): self
    {
        $endpoint = $data['client_endpoint'] ?? null;

        return new self(
            accessToken: (string) $data['access_token'],
            refreshToken: (string) $data['refresh_token'],
            expiresAt: CarbonImmutable::now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
            domain: $endpoint ? (parse_url($endpoint, PHP_URL_HOST) ?: null) : null,
            memberId: $data['member_id'] ?? null,
            scopes: array_values(array_filter(explode(',', (string) ($data['scope'] ?? '')))),
        );
    }

    public function expiresWithin(int $seconds): bool
    {
        return $this->expiresAt->lte(CarbonImmutable::now()->addSeconds($seconds));
    }
}
