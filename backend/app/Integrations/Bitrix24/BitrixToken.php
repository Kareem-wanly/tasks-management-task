<?php

namespace App\Integrations\Bitrix24;

use Carbon\CarbonImmutable;

final class BitrixToken
{
    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly CarbonImmutable $expiresAt,
        public readonly ?string $domain = null,
        public readonly ?string $memberId = null,
        public readonly array $scopes = [],
    ) {}

    /**
     * The OAuth "domain" field is oauth.bitrix.info, so the portal domain is
     * taken from client_endpoint (https://<portal>/rest/).
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
