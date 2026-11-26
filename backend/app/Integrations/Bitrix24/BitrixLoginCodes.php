<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One-time codes that hand a Bitrix24 sign-in over to the frontend. The
 * browser gets a short-lived code in the URL instead of an API token, and
 * trades it once for the token through POST /api/auth/bitrix/exchange.
 *
 * A code is bound to the nonce the browser made when the user clicked
 * "Sign in with Bitrix24" (kept in its sessionStorage): a code sent to
 * another browser can't be traded there (login CSRF). Only hashes are stored.
 */
final class BitrixLoginCodes
{
    public const LENGTH = 64;

    /** What a browser nonce looks like (crypto.randomUUID() fits). */
    public const NONCE_PATTERN = '/^[A-Za-z0-9-]{20,128}$/';

    private const TTL_SECONDS = 60;

    public function issue(User $user, string $nonce): string
    {
        $code = Str::random(self::LENGTH);
        Cache::put($this->key($code), ['user_id' => $user->id, 'nonce' => hash('sha256', $nonce)], self::TTL_SECONDS);

        return $code;
    }

    /**
     * The user the code was issued for, when the nonce is the one it was bound
     * to; null when the code is unknown, expired, already used or for another
     * browser. Either way the code is gone after this call.
     */
    public function redeem(string $code, string $nonce): ?User
    {
        $issued = Cache::pull($this->key($code));

        if (! is_array($issued) || ! hash_equals((string) ($issued['nonce'] ?? ''), hash('sha256', $nonce))) {
            return null;
        }

        return User::query()->find((int) ($issued['user_id'] ?? 0));
    }

    public static function isNonce(mixed $value): bool
    {
        return is_string($value) && preg_match(self::NONCE_PATTERN, $value) === 1;
    }

    private function key(string $code): string
    {
        return 'bitrix-login-code:'.hash('sha256', $code);
    }
}
