<?php

namespace App\Integrations\Bitrix24;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One-time codes that hand a Bitrix24 sign-in over to the frontend. The
 * browser gets a short-lived code in the URL instead of an API token, and
 * trades it once for the token through POST /api/auth/bitrix/exchange.
 * Only a hash of the code is stored.
 */
class BitrixLoginCodes
{
    public const LENGTH = 64;

    private const TTL_SECONDS = 60;

    public function issue(User $user): string
    {
        $code = Str::random(self::LENGTH);
        Cache::put($this->key($code), $user->id, self::TTL_SECONDS);

        return $code;
    }

    /** The user the code was issued for; null when it is unknown, expired or already used. */
    public function redeem(string $code): ?User
    {
        $userId = Cache::pull($this->key($code));

        return $userId === null ? null : User::find($userId);
    }

    private function key(string $code): string
    {
        return 'bitrix-login-code:'.hash('sha256', $code);
    }
}
