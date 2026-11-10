<?php

namespace App\Http\Controllers;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\TokenManager;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Sign in with Bitrix24".
 *
 * - OAuth: our login button → redirect() → Bitrix consent → GET callback with
 *   code + state.
 * - App opened inside Bitrix: Bitrix POSTs AUTH_ID/REFRESH_ID to the same
 *   handler path. Not in the plan, but it is what Bitrix sends on every open.
 *
 * Both end with a Sanctum token handed to the frontend in the URL fragment,
 * so it never reaches server logs.
 */
class BitrixAuthController extends Controller
{
    private const STATE_KEY = 'bitrix_oauth_state';

    public function __construct(
        private readonly TokenManager $tokens,
        private readonly BitrixClient $bitrix,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        // The state lives in the session cookie, so it must be set on the same
        // host Bitrix returns to (the public handler path), not on localhost.
        // Compare hosts only: behind ngrok Laravel sees http while the public URL is https.
        $public = rtrim((string) config('bitrix.public_url'), '/');
        $publicHost = parse_url($public, PHP_URL_HOST);
        if ($publicHost && $request->getHttpHost() !== $publicHost.(parse_url($public, PHP_URL_PORT) ? ':'.parse_url($public, PHP_URL_PORT) : '')) {
            return redirect()->away("{$public}/auth/bitrix/redirect");
        }

        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away('https://'.config('bitrix.portal').'/oauth/authorize/?'.http_build_query([
            'client_id' => config('bitrix.client_id'),
            'state' => $state,
        ]));
    }

    public function callback(Request $request): RedirectResponse
    {
        return $request->isMethod('post')
            ? $this->fromAppOpen($request)
            : $this->fromOAuth($request);
    }

    private function fromOAuth(Request $request): RedirectResponse
    {
        $expected = (string) $request->session()->pull(self::STATE_KEY, '');

        if ($expected === '' || ! hash_equals($expected, (string) $request->query('state', ''))) {
            return $this->fail('invalid_state');
        }

        if (! $this->isOurPortal($request->query('domain')) || ! $request->filled('code')) {
            return $this->fail('unknown_portal');
        }

        try {
            $token = $this->tokens->exchangeCode((string) $request->query('code'));
        } catch (BitrixException $e) {
            return $this->fail('exchange_failed');
        }

        // The token response names the portal it was issued for.
        if (! $this->isOurPortal($token->domain)) {
            return $this->fail('unknown_portal');
        }

        return $this->signIn($token);
    }

    private function fromAppOpen(Request $request): RedirectResponse
    {
        if (! $this->isOurPortal($request->input('DOMAIN')) || ! $request->filled(['AUTH_ID', 'REFRESH_ID'])) {
            return $this->fail('unknown_portal');
        }

        return $this->signIn(new BitrixToken(
            accessToken: (string) $request->input('AUTH_ID'),
            refreshToken: (string) $request->input('REFRESH_ID'),
            expiresAt: CarbonImmutable::now()->addSeconds((int) ($request->input('AUTH_EXPIRES') ?: 3600)),
            domain: (string) $request->input('DOMAIN'),
            memberId: $request->input('member_id'),
        ));
    }

    /**
     * user.current doubles as the check that Bitrix accepts the token.
     */
    private function signIn(BitrixToken $token): RedirectResponse
    {
        try {
            $profile = $this->bitrix->callWithToken($token->accessToken, 'user.current')['result'] ?? [];
        } catch (BitrixException $e) {
            return $this->fail('bitrix_rejected');
        }

        if (empty($profile['ID'])) {
            return $this->fail('bitrix_rejected');
        }

        $user = $this->resolveUser($profile);

        if (! $user->is_active) {
            return $this->fail('inactive');
        }

        $user->storeBitrixToken($token);

        $sanctum = $user->createToken('bitrix_auth')->plainTextToken;

        return redirect()->away(config('bitrix.frontend_callback_url').'#token='.urlencode($sanctum));
    }

    /**
     * Find by Bitrix ID, else link an existing account with the same email,
     * else create a Member.
     */
    private function resolveUser(array $profile): User
    {
        $bitrixId = (int) $profile['ID'];
        $email = Str::lower(trim((string) ($profile['EMAIL'] ?? '')));
        $name = trim(($profile['NAME'] ?? '').' '.($profile['LAST_NAME'] ?? ''));

        $attributes = [
            'bitrix_user_id' => $bitrixId,
            'avatar_url' => $profile['PERSONAL_PHOTO'] ?? null,
            'position' => $profile['WORK_POSITION'] ?? null,
            'bitrix_synced_at' => now(),
        ];

        return DB::transaction(function () use ($bitrixId, $email, $name, $attributes) {
            $user = User::where('bitrix_user_id', $bitrixId)->first()
                ?? ($email !== '' ? User::whereRaw('LOWER(email) = ?', [$email])->whereNull('bitrix_user_id')->first() : null);

            if ($user) {
                $user->forceFill($attributes)->save();

                return $user;
            }

            $user = User::forceCreate([
                ...$attributes,
                'name' => $name !== '' ? $name : "Bitrix user {$bitrixId}",
                // users.email is required; a Bitrix account may have none.
                'email' => $email !== '' ? $email : "bitrix-{$bitrixId}@".config('bitrix.portal'),
                'password' => null,
                // Set explicitly: the DB default isn't on the model until a reload.
                'is_active' => true,
            ]);

            if ($member = Role::where('name', 'Member')->first()) {
                $user->roles()->attach($member->id);
            }

            return $user;
        });
    }

    private function isOurPortal(mixed $domain): bool
    {
        return is_string($domain) && $domain !== '' && hash_equals((string) config('bitrix.portal'), $domain);
    }

    private function fail(string $reason): RedirectResponse
    {
        return redirect()->away(config('bitrix.frontend_callback_url').'#error='.$reason);
    }
}
