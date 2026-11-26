<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use SensitiveParameter;

/**
 * The one-time sign-in code from the OAuth callback, and the nonce of the
 * browser that asked for it.
 */
final readonly class ExchangeLoginCodeDTO
{
    public function __construct(
        #[SensitiveParameter] public string $code,
        public string $nonce,
    ) {}

    /**
     * @param  array{code: string, nonce: string}  $data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(code: $data['code'], nonce: $data['nonce']);
    }
}
