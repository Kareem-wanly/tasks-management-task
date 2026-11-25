<?php

declare(strict_types=1);

namespace App\DTOs\Bitrix;

use SensitiveParameter;

/**
 * What Bitrix24's OAuth callback brought back, once its state matched the
 * one we kept: the authorization code, the portal it names, and the nonce of
 * the browser that started the sign-in.
 */
final readonly class BitrixCallbackDTO
{
    public function __construct(
        #[SensitiveParameter] public string $code,
        /** The portal Bitrix names in the callback; null when it sent none. */
        public ?string $domain,
        public string $nonce,
    ) {}

    /**
     * @param  array{code: string, domain: ?string, nonce: string}  $data
     */
    public static function fromArray(#[SensitiveParameter] array $data): self
    {
        return new self(code: $data['code'], domain: $data['domain'], nonce: $data['nonce']);
    }
}
