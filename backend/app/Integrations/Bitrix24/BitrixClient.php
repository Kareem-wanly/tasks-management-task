<?php

namespace App\Integrations\Bitrix24;

use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use InvalidArgumentException;

/**
 * The only place that talks to the Bitrix24 REST API.
 */
class BitrixClient
{
    public const MAX_BATCH_COMMANDS = 50;

    /**
     * Container key for the service account used by background sync,
     * bound to the installed portal in AppServiceProvider.
     */
    public const SERVICE_ACCOUNT = 'bitrix.service_account';

    private const RATE_LIMIT_RETRIES = 3;

    public function __construct(private readonly TokenManager $tokens) {}

    /**
     * Call a method with the token of a user, so Bitrix applies that user's
     * permissions and records the change under their name.
     *
     * @return array<string, mixed> The decoded body: result, plus next/total for list methods.
     */
    public function callAsUser(HasBitrixToken $user, string $method, array $params = []): array
    {
        $token = $this->tokens->validTokenFor($user);

        try {
            return $this->send($token->accessToken, $method, $params);
        } catch (BitrixException $e) {
            if (! $e->isExpiredToken()) {
                throw $e;
            }

            return $this->send($this->tokens->validTokenFor($user, force: true)->accessToken, $method, $params);
        }
    }

    /**
     * Call a method with the service account's token (background sync).
     *
     * @return array<string, mixed>
     */
    public function callAsService(string $method, array $params = []): array
    {
        return $this->callAsUser($this->serviceAccount(), $method, $params);
    }

    /**
     * Call a method with a raw access token (e.g. the one in the install
     * event). The token cannot be refreshed here, so expiry is final.
     *
     * @return array<string, mixed>
     */
    public function callWithToken(string $accessToken, string $method, array $params = []): array
    {
        return $this->send($accessToken, $method, $params);
    }

    /**
     * Run up to 50 calls in one request.
     *
     * @param  array<string, array{0: string, 1?: array}>  $commands  key => [method, params]
     * @return array<string, mixed> result.result, result_error, result_total, result_next, result_time
     */
    public function batch(HasBitrixToken $holder, array $commands, bool $halt = false): array
    {
        return $this->callAsUser($holder, 'batch', $this->batchParams($commands, $halt))['result'];
    }

    private function serviceAccount(): HasBitrixToken
    {
        if (! app()->bound(self::SERVICE_ACCOUNT)) {
            throw BitrixException::missingToken();
        }

        return app(self::SERVICE_ACCOUNT);
    }

    private function batchParams(array $commands, bool $halt): array
    {
        if ($commands === [] || count($commands) > self::MAX_BATCH_COMMANDS) {
            throw new InvalidArgumentException('A Bitrix24 batch takes between 1 and '.self::MAX_BATCH_COMMANDS.' commands.');
        }

        $cmd = [];
        foreach ($commands as $key => $command) {
            $query = http_build_query($command[1] ?? []);
            $cmd[$key] = $query === '' ? $command[0] : "{$command[0]}?{$query}";
        }

        return ['halt' => $halt ? 1 : 0, 'cmd' => $cmd];
    }

    /**
     * @return array<string, mixed>
     */
    private function send(string $accessToken, string $method, array $params): array
    {
        $url = 'https://'.config('bitrix.portal')."/rest/{$method}.json";

        for ($attempt = 0; ; $attempt++) {
            $response = Http::acceptJson()
                ->timeout(config('bitrix.timeout'))
                ->post($url, [...$params, 'auth' => $accessToken]);

            $body = $response->json();

            if ($response->successful() && is_array($body) && ! isset($body['error'])) {
                return $body;
            }

            $error = BitrixException::fromResponse(is_array($body) ? $body : null, $response->status());

            if (! $error->isRetryable() || $attempt >= self::RATE_LIMIT_RETRIES) {
                throw $error;
            }

            Sleep::for(2 ** $attempt)->seconds();
        }
    }
}
