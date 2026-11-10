<?php

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Integrations\Bitrix24\TokenManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use InvalidArgumentException;
use Tests\TestCase;

class BitrixClientTest extends TestCase
{
    private const REST = 'https://portal.test/rest/*';

    private const OAUTH = 'https://oauth.bitrix.info/oauth/token/*';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'bitrix.portal' => 'portal.test',
            'bitrix.client_id' => 'app-id',
            'bitrix.client_secret' => 'app-secret',
            'bitrix.oauth_server' => 'https://oauth.bitrix.info',
        ]);
        Sleep::fake();
    }

    private function holder(?BitrixToken $token): HasBitrixToken
    {
        return new class($token) implements HasBitrixToken
        {
            public function __construct(public ?BitrixToken $token) {}

            public function bitrixToken(): ?BitrixToken
            {
                return $this->token;
            }

            public function storeBitrixToken(BitrixToken $token): void
            {
                $this->token = $token;
            }

            public function bitrixTokenLockKey(): string
            {
                return 'bitrix-token:test';
            }
        };
    }

    private function token(int $expiresInSeconds = 3600, string $access = 'old-access', string $refresh = 'old-refresh'): BitrixToken
    {
        return new BitrixToken($access, $refresh, CarbonImmutable::now()->addSeconds($expiresInSeconds));
    }

    private function oauthResponse(): array
    {
        return [
            'access_token' => 'new-access',
            'refresh_token' => 'new-refresh',
            'expires_in' => 3600,
            'client_endpoint' => 'https://portal.test/rest/',
            'member_id' => 'member-1',
            'scope' => 'task,user',
        ];
    }

    private function client(): BitrixClient
    {
        return app(BitrixClient::class);
    }

    public function test_token_about_to_expire_is_refreshed_and_stored(): void
    {
        Http::fake([
            self::OAUTH => Http::response($this->oauthResponse()),
            self::REST => Http::response(['result' => ['ID' => '1']]),
        ]);
        $holder = $this->holder($this->token(expiresInSeconds: 30));

        $this->client()->callAsUser($holder, 'user.current');

        $this->assertSame('new-access', $holder->token->accessToken);
        $this->assertSame('new-refresh', $holder->token->refreshToken);
        $this->assertSame('portal.test', $holder->token->domain);
        $this->assertSame(['task', 'user'], $holder->token->scopes);
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://oauth.bitrix.info/oauth/token/')
            && $r['grant_type'] === 'refresh_token'
            && $r['refresh_token'] === 'old-refresh'
            && $r['client_secret'] === 'app-secret');
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/rest/user.current.json') && $r['auth'] === 'new-access');
    }

    public function test_valid_token_is_not_refreshed(): void
    {
        Http::fake([self::REST => Http::response(['result' => true])]);

        $this->client()->callAsUser($this->holder($this->token()), 'user.current');

        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'oauth.bitrix.info'));
    }

    public function test_expired_token_reply_refreshes_once_and_retries(): void
    {
        Http::fake([
            self::OAUTH => Http::response($this->oauthResponse()),
            self::REST => Http::sequence()
                ->push(['error' => 'expired_token', 'error_description' => 'The access token provided has expired.'], 401)
                ->push(['result' => ['ID' => '7']]),
        ]);
        $holder = $this->holder($this->token());

        $body = $this->client()->callAsUser($holder, 'user.current');

        $this->assertSame(['ID' => '7'], $body['result']);
        $this->assertSame('new-access', $holder->token->accessToken);
        Http::assertSentCount(3);
    }

    public function test_gives_up_after_three_rate_limit_retries(): void
    {
        Http::fake([self::REST => Http::response(['error' => 'QUERY_LIMIT_EXCEEDED', 'error_description' => 'Too many requests'], 503)]);

        try {
            $this->client()->callAsUser($this->holder($this->token()), 'tasks.task.list');
            $this->fail('Expected a BitrixException.');
        } catch (BitrixException $e) {
            $this->assertTrue($e->isRateLimited());
            $this->assertSame(503, $e->status);
        }

        Http::assertSentCount(4);
        Sleep::assertSleptTimes(3);
    }

    public function test_rate_limit_retry_succeeds_when_limit_clears(): void
    {
        Http::fake([
            self::REST => Http::sequence()
                ->push(['error' => 'QUERY_LIMIT_EXCEEDED'], 503)
                ->push(['result' => ['ok' => true]]),
        ]);

        $body = $this->client()->callAsUser($this->holder($this->token()), 'tasks.task.list');

        $this->assertSame(['ok' => true], $body['result']);
        Sleep::assertSleptTimes(1);
    }

    public function test_overload_limit_is_retried_like_rate_limit(): void
    {
        Http::fake([self::REST => Http::response(['error' => 'OVERLOAD_LIMIT'], 401)]);

        $this->expectException(BitrixException::class);

        try {
            $this->client()->callAsUser($this->holder($this->token()), 'user.current');
        } finally {
            Http::assertSentCount(4);
            Sleep::assertSleptTimes(3);
        }
    }

    public function test_operation_time_limit_is_not_retried(): void
    {
        Http::fake([self::REST => Http::response(['error' => 'OPERATION_TIME_LIMIT'], 429)]);

        $this->expectException(BitrixException::class);

        try {
            $this->client()->callAsUser($this->holder($this->token()), 'tasks.task.list');
        } finally {
            Http::assertSentCount(1);
            Sleep::assertNeverSlept();
        }
    }

    public function test_call_as_service_uses_the_bound_service_account(): void
    {
        Http::fake([self::REST => Http::response(['result' => true])]);
        app()->instance(BitrixClient::SERVICE_ACCOUNT, $this->holder($this->token(access: 'service-access')));

        $this->client()->callAsService('event.get');

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/rest/event.get.json') && $r['auth'] === 'service-access');
    }

    public function test_call_as_service_is_refused_without_a_bound_account(): void
    {
        Http::fake();
        unset(app()[BitrixClient::SERVICE_ACCOUNT]);

        try {
            $this->client()->callAsService('event.get');
            $this->fail('Expected a BitrixException.');
        } catch (BitrixException $e) {
            $this->assertTrue($e->isMissingToken());
        }

        Http::assertNothingSent();
    }

    public function test_call_is_refused_without_a_token(): void
    {
        Http::fake();

        try {
            $this->client()->callAsUser($this->holder(null), 'user.current');
            $this->fail('Expected a BitrixException.');
        } catch (BitrixException $e) {
            $this->assertTrue($e->isMissingToken());
        }

        Http::assertNothingSent();
    }

    public function test_other_errors_become_bitrix_exception(): void
    {
        Http::fake([self::REST => Http::response(['error' => 'ACCESS_DENIED', 'error_description' => 'Access denied'], 403)]);

        try {
            $this->client()->callAsUser($this->holder($this->token()), 'tasks.task.delete');
            $this->fail('Expected a BitrixException.');
        } catch (BitrixException $e) {
            $this->assertTrue($e->isAccessDenied());
            $this->assertSame('Access denied', $e->getMessage());
        }
    }

    public function test_batch_encodes_commands(): void
    {
        Http::fake([self::REST => Http::response(['result' => ['result' => ['me' => ['ID' => '1']], 'result_error' => []]])]);

        $result = $this->client()->batch($this->holder($this->token()), [
            'me' => ['user.current'],
            'dept' => ['department.get', ['ID' => 5]],
        ]);

        $this->assertSame(['ID' => '1'], $result['result']['me']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/rest/batch.json')
            && $r['halt'] === 0
            && $r['cmd'] === ['me' => 'user.current', 'dept' => 'department.get?ID=5']);
    }

    public function test_batch_rejects_more_than_fifty_commands(): void
    {
        Http::fake();
        $this->expectException(InvalidArgumentException::class);

        $this->client()->batch($this->holder($this->token()), array_fill_keys(range(1, 51), ['user.current']));
    }

    public function test_exchange_code_returns_token(): void
    {
        Http::fake([self::OAUTH => Http::response($this->oauthResponse())]);

        $token = app(TokenManager::class)->exchangeCode('auth-code');

        $this->assertSame('new-access', $token->accessToken);
        $this->assertSame('member-1', $token->memberId);
        Http::assertSent(fn (Request $r) => $r['grant_type'] === 'authorization_code' && $r['code'] === 'auth-code');
    }

    public function test_oauth_error_is_raised(): void
    {
        Http::fake([self::OAUTH => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid grant'], 400)]);

        try {
            app(TokenManager::class)->exchangeCode('stale-code');
            $this->fail('Expected a BitrixException.');
        } catch (BitrixException $e) {
            $this->assertSame('invalid_grant', $e->errorCode);
        }
    }
}
