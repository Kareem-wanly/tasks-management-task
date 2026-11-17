<?php

namespace App\Integrations\Bitrix24;

use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use InvalidArgumentException;

/**
 * The only place that talks to the Bitrix24 REST API.
 */
class BitrixClient
{
    public const MAX_BATCH_COMMANDS = 50;

    /** Classic REST, and REST 3.0 (newer methods such as task results). */
    private const REST_PATH = '/rest/';

    private const REST_V3_PATH = '/rest/api/';

    /** Bytes per read when streaming a download. */
    private const DOWNLOAD_CHUNK_BYTES = 1048576;

    /** List methods return fixed pages of 50 rows. */
    public const PAGE_SIZE = 50;

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
    public function callAsUser(HasBitrixToken $user, string $method, array $params = [], ?int $timeout = null): array
    {
        return $this->callWithRefresh($user, $method, $params, $timeout, self::REST_PATH);
    }

    /**
     * Call a REST 3.0 method (/rest/api/...) as a user, e.g. task results.
     *
     * @return array<string, mixed> The decoded body: result (with item or items).
     */
    public function callV3AsUser(HasBitrixToken $user, string $method, array $params = []): array
    {
        return $this->callWithRefresh($user, $method, $params, null, self::REST_V3_PATH);
    }

    /** @return array<string, mixed> */
    public function callV3AsService(string $method, array $params = []): array
    {
        return $this->callV3AsUser($this->serviceAccount(), $method, $params);
    }

    /** @return array<string, mixed> */
    private function callWithRefresh(HasBitrixToken $user, string $method, array $params, ?int $timeout, string $path): array
    {
        $token = $this->tokens->validTokenFor($user);

        try {
            return $this->send($token->accessToken, $method, $params, $timeout, $path);
        } catch (BitrixException $e) {
            if (! $e->isExpiredToken()) {
                throw $e;
            }

            return $this->send($this->tokens->validTokenFor($user, force: true)->accessToken, $method, $params, $timeout, $path);
        }
    }

    /**
     * Streams a file from a Bitrix download URL (one Bitrix signed for the
     * user who asked for it) to $write, chunk by chunk, so large files never
     * sit in memory.
     *
     * @param  callable(string): void  $write
     */
    public function streamDownload(string $url, callable $write): void
    {
        if (parse_url($url, PHP_URL_HOST) !== config('bitrix.portal')) {
            throw BitrixException::cannotSync('Bitrix24 returned a download link to another site.');
        }

        try {
            $body = Http::withOptions(['stream' => true])->timeout(config('bitrix.timeout'))->get($url)->toPsrResponse()->getBody();
        } catch (ConnectionException $e) {
            throw BitrixException::unreachable($e->getMessage());
        }

        while (! $body->eof()) {
            $write($body->read(self::DOWNLOAD_CHUNK_BYTES));
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

    /**
     * Run a batch with the service account's token (background sync).
     *
     * @param  array<string, array{0: string, 1?: array}>  $commands
     * @return array<string, mixed>
     */
    public function batchAsService(array $commands, bool $halt = false): array
    {
        return $this->batch($this->serviceAccount(), $commands, $halt);
    }

    /**
     * Walk every page of a list method with the service account: one call
     * for the first page and its total, then the rest batched 50 at a time.
     *
     * @param  callable(array, int): void  $onPage  Receives each page's `result` and the total Bitrix reported.
     * @return int The total Bitrix reported.
     */
    public function eachPageAsService(string $method, array $params, callable $onPage): int
    {
        $first = $this->callAsService($method, [...$params, 'start' => 0]);
        $total = (int) ($first['total'] ?? 0);
        $onPage($first['result'] ?? [], $total);

        $starts = [];
        for ($start = self::PAGE_SIZE; $start < $total; $start += self::PAGE_SIZE) {
            $starts[] = $start;
        }

        foreach (array_chunk($starts, self::MAX_BATCH_COMMANDS) as $chunk) {
            $commands = [];
            foreach ($chunk as $start) {
                $commands["page_{$start}"] = [$method, [...$params, 'start' => $start]];
            }

            $batch = $this->batchAsService($commands);

            // A failed page would leave rows missing; fail so the caller can re-run.
            if (! empty($batch['result_error'])) {
                throw BitrixException::fromResponse(reset($batch['result_error']), 200);
            }

            foreach ($chunk as $start) {
                $onPage($batch['result']["page_{$start}"] ?? [], $total);
            }
        }

        return $total;
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
    private function send(string $accessToken, string $method, array $params, ?int $timeout = null, string $path = self::REST_PATH): array
    {
        $url = 'https://'.config('bitrix.portal').$path.($path === self::REST_PATH ? "{$method}.json" : $method);

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = Http::acceptJson()
                    ->timeout($timeout ?? config('bitrix.timeout'))
                    ->post($url, [...$params, 'auth' => $accessToken]);
            } catch (ConnectionException $e) {
                throw BitrixException::unreachable($e->getMessage());
            }

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
