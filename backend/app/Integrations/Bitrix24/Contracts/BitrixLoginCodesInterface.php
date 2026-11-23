<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Models\User;

/** One-time codes that hand a Bitrix24 sign-in to the browser that started it. */
interface BitrixLoginCodesInterface
{
    /** A code bound to this browser's nonce, valid for a minute. */
    public function issue(User $user, string $nonce): string;

    /**
     * The user the code was issued for, when the nonce is the one it was bound
     * to; null when the code is unknown, expired, already used or for another
     * browser. Either way the code is gone after this call.
     */
    public function redeem(string $code, string $nonce): ?User;
}
