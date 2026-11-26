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
}
