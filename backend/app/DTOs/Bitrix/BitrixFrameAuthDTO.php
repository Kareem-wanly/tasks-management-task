<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use SensitiveParameter;

/**
 * The tokens Bitrix24's JS SDK (BX24.getAuth()) gave the app page opened
 * inside Bitrix24: the employee's own, checked with Bitrix before sign-in.
 */
final readonly class BitrixFrameAuthDTO
{
    public function __construct(
        #[SensitiveParameter] public string $accessToken,
        #[SensitiveParameter] public string $refreshToken,
        /** Seconds the access token has left. */
        public int $expiresIn,
        public string $domain,
        public ?string $memberId = null,
    ) {}

    /**
     * @param  array{access_token: string, refresh_token: string, expires_in: int, domain: string, member_id?: ?string}  $data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(
            accessToken: $data['access_token'],
            refreshToken: $data['refresh_token'],
            expiresIn: $data['expires_in'],
            domain: $data['domain'],
            memberId: $data['member_id'] ?? null,
        );
    }
}
