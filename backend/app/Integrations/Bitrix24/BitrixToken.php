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
     * @param  array<mixed>  $data  The decoded OAuth answer.
     */
    public static function fromOAuthResponse(array $data): self
    {
        $oauth = BitrixPayload::of($data, 'oauth/token');
        $endpoint = $oauth->stringOrNull('client_endpoint');

        return new self(
            accessToken: $oauth->string('access_token'),
            refreshToken: $oauth->string('refresh_token'),
            expiresAt: CarbonImmutable::now()->addSeconds($oauth->intOrNull('expires_in') ?? 3600),
            domain: $endpoint ? (parse_url($endpoint, PHP_URL_HOST) ?: null) : null,
            memberId: $oauth->stringOrNull('member_id'),
            scopes: array_values(array_filter(explode(',', $oauth->stringOrNull('scope') ?? ''))),
        );
    }

    public function expiresWithin(int $seconds): bool
    {
        return $this->expiresAt->lte(CarbonImmutable::now()->addSeconds($seconds));
    }
}
