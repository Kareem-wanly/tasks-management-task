<?php

declare(strict_types=1);

namespace Tests\Feature\Bitrix24;

use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Models\BitrixPortal;
use App\Services\BitrixInstallService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class BitrixInstallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'bitrix.portal' => 'portal.test',
            'bitrix.client_id' => 'app-id',
            'bitrix.client_secret' => 'app-secret',
            'bitrix.public_url' => 'https://public.test',
        ]);
        Sleep::fake();
    }

    public function test_install_saves_the_portal_and_binds_events(): void
    {
        $this->fakeBitrix();

        $this->post('/api/bitrix/install', $this->payload())->assertOk();

        $portal = BitrixPortal::sole();
        $this->assertSame('member-1', $portal->member_id);
        $this->assertSame('portal.test', $portal->domain);
        $this->assertSame('app-token-1', $portal->application_token);
        $this->assertSame('install-access', $portal->access_token);
        $this->assertSame('install-refresh', $portal->refresh_token);
        $this->assertTrue($portal->token_expires_at->isFuture());
        $this->assertSame(BitrixInstallService::REQUIRED_SCOPES, $portal->scopes);
        $this->assertSame(40, $portal->app_info['ID']);
        $this->assertSame(['ID' => '1', 'NAME' => 'Admin', 'LAST_NAME' => 'User', 'EMAIL' => 'admin@portal.test'], $portal->installer);
        $this->assertTrue($portal->installer_is_admin);
        $this->assertSame([['ID' => '1', 'NAME' => 'HQ', 'PARENT' => null, 'SORT' => '500']], $portal->departments);
        $this->assertSame('en', $portal->language);

        Http::assertSent(fn (Request $r): bool => ($r['cmd']['OnTaskAdd'] ?? null)
            === 'event.bind?'.http_build_query(['event' => 'OnTaskAdd', 'handler' => 'https://public.test/api/bitrix/events']));
        Http::assertSent(fn (Request $r): bool => count($r['cmd'] ?? []) === count(BitrixInstallService::EVENTS));
    }

    public function test_tokens_are_encrypted_at_rest(): void
    {
        $this->fakeBitrix();

        $this->post('/api/bitrix/install', $this->payload())->assertOk();

        $row = DB::table('bitrix_portals')->first();
        $this->assertNotSame('install-access', $row->access_token);
        $this->assertNotSame('install-refresh', $row->refresh_token);
        $this->assertNotSame('app-token-1', $row->application_token);
    }

    public function test_reinstall_event_signed_with_the_saved_token_updates_the_portal(): void
    {
        $this->fakeBitrix(bindErrors: ['OnTaskAdd' => ['error' => 'ERROR_CORE', 'error_description' => 'Handler already binded']]);

        $this->post('/api/bitrix/install', $this->payload())->assertOk();
        $this->post('/api/bitrix/install', $this->payload(['access_token' => 'second-access']))->assertOk();

        $this->assertSame('second-access', BitrixPortal::sole()->access_token);
    }

    /** Review A1: whoever knows member_id must not be able to take the portal over. */
    public function test_forged_install_event_on_an_installed_portal_is_refused(): void
    {
        $this->fakeBitrix();
        $this->post('/api/bitrix/install', $this->payload())->assertOk();

        $this->post('/api/bitrix/install', $this->payload([
            'access_token' => 'attacker-access',
            'refresh_token' => 'attacker-refresh',
            'application_token' => 'attacker-app-token',
        ]))->assertForbidden();

        $portal = BitrixPortal::sole();
        $this->assertSame('app-token-1', $portal->application_token);
        $this->assertSame('install-access', $portal->access_token);
        $this->assertSame('install-refresh', $portal->refresh_token);
    }

    /** Review A1: the first install event is only trusted after Bitrix confirms its token is our app's. */
    public function test_install_event_with_a_token_of_another_app_is_refused(): void
    {
        $this->fakeBitrix(appCode: 'someone-elses-app');

        $this->post('/api/bitrix/install', $this->payload())->assertForbidden();

        $this->assertDatabaseCount('bitrix_portals', 0);
    }

    /** Review A2: the installer's token becomes the service account, so only an admin may install. */
    public function test_non_admin_install_is_refused_and_saves_no_token(): void
    {
        $this->fakeBitrix(isAdmin: false);

        $this->post('/api/bitrix/install', $this->payload())->assertForbidden();
        $this->post($this->pageUrl(), $this->pagePayload())
            ->assertForbidden()
            ->assertSee('Only a Bitrix24 administrator can install this app.')
            ->assertDontSee('BX24.installFinish()', false);

        $this->assertDatabaseCount('bitrix_portals', 0);
    }

    public function test_install_is_saved_even_when_fetching_details_fails(): void
    {
        $this->fakeBitrix(detailsFail: true);

        $this->post('/api/bitrix/install', $this->payload())->assertOk();

        $portal = BitrixPortal::sole();
        $this->assertSame('install-access', $portal->access_token);
        $this->assertNull($portal->app_info);
    }

    public function test_install_event_without_user_tokens_is_refused(): void
    {
        Http::fake();

        $this->post('/api/bitrix/install', $this->payload(['access_token' => '', 'refresh_token' => '']))->assertStatus(422);

        $this->assertDatabaseCount('bitrix_portals', 0);
        Http::assertNothingSent();
    }

    public function test_install_from_another_portal_is_rejected(): void
    {
        Http::fake();

        $this->post('/api/bitrix/install', $this->payload(['domain' => 'evil.test']))->assertForbidden();

        $this->assertDatabaseCount('bitrix_portals', 0);
        Http::assertNothingSent();
    }

    public function test_other_events_are_rejected(): void
    {
        $this->post('/api/bitrix/install', ['event' => 'ONAPPUNINSTALL'] + $this->payload())->assertStatus(400);

        $this->assertDatabaseCount('bitrix_portals', 0);
    }

    public function test_install_page_saves_the_portal_and_finishes_install(): void
    {
        $this->fakeBitrix();

        $this->post($this->pageUrl(), $this->pagePayload())
            ->assertOk()
            ->assertSee('BX24.installFinish()', false)
            ->assertSee('https://api.bitrix24.com/api/v1/', false);

        $portal = BitrixPortal::sole();
        $this->assertSame('member-1', $portal->member_id);
        $this->assertSame('page-app-token', $portal->application_token);
        $this->assertSame('page-access', $portal->access_token);
        $this->assertSame('page-refresh', $portal->refresh_token);
        $this->assertSame('en', $portal->language);
        $this->assertTrue($portal->installer_is_admin);
        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/rest/app.info.json') && $r['auth'] === 'page-access');
        Http::assertSent(fn (Request $r): bool => isset($r['cmd']['OnTaskAdd']));
    }

    public function test_install_page_with_a_token_bitrix_rejects_is_refused(): void
    {
        Http::fake(['*' => Http::response(['error' => 'invalid_token', 'error_description' => 'The access token provided is invalid.'], 401)]);

        $this->post($this->pageUrl(), $this->pagePayload(['AUTH_ID' => 'forged']))->assertForbidden();

        $this->assertDatabaseCount('bitrix_portals', 0);
    }

    /** Review A2: a valid token of another app on the portal is not ours to keep. */
    public function test_install_page_with_a_token_of_another_app_is_refused(): void
    {
        $this->fakeBitrix(appCode: 'someone-elses-app');

        $this->post($this->pageUrl(), $this->pagePayload())->assertForbidden()->assertSee('not issued for this app');

        $this->assertDatabaseCount('bitrix_portals', 0);
    }

    public function test_install_page_from_another_portal_is_rejected(): void
    {
        Http::fake();

        $this->post($this->pageUrl('evil.test'), $this->pagePayload())->assertForbidden();

        $this->assertDatabaseCount('bitrix_portals', 0);
        Http::assertNothingSent();
    }

    public function test_install_page_without_required_fields_is_rejected(): void
    {
        Http::fake();

        $this->post($this->pageUrl(), $this->pagePayload(['APPLICATION_TOKEN' => '']))->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_call_as_service_uses_the_installed_portal_token(): void
    {
        $this->fakeBitrix();
        $this->post('/api/bitrix/install', $this->payload())->assertOk();

        Http::fake(['https://portal.test/rest/*' => Http::response(['result' => []])]);
        resolve(BitrixGatewayInterface::class)->callAsService('event.get');

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/rest/event.get.json') && $r['auth'] === 'install-access');
    }

    private function payload(array $auth = []): array
    {
        return [
            'event' => 'ONAPPINSTALL',
            'data' => ['VERSION' => '1', 'ACTIVE' => 'Y', 'INSTALLED' => 'Y', 'LANGUAGE_ID' => 'en'],
            'ts' => '1731230000',
            'auth' => array_merge([
                'access_token' => 'install-access',
                'refresh_token' => 'install-refresh',
                'expires_in' => '3600',
                'scope' => 'task,sonet_group,socialnetwork,user,im,disk,department',
                'domain' => 'portal.test',
                'server_endpoint' => 'https://oauth.bitrix.info/rest/',
                'status' => 'L',
                'client_endpoint' => 'https://portal.test/rest/',
                'member_id' => 'member-1',
                'application_token' => 'app-token-1',
            ], $auth),
        ];
    }

    /**
     * Answers the details batch and the event.bind batch.
     */
    private function pageUrl(string $domain = 'portal.test'): string
    {
        return "/api/bitrix/install?DOMAIN={$domain}&PROTOCOL=1&LANG=en&APP_SID=sid-1";
    }

    /**
     * What the admin's browser posts when Bitrix opens the install page.
     */
    private function pagePayload(array $overrides = []): array
    {
        return array_merge([
            'AUTH_ID' => 'page-access',
            'AUTH_EXPIRES' => '3600',
            'REFRESH_ID' => 'page-refresh',
            'SERVER_ENDPOINT' => 'https://oauth.bitrix.info/rest/',
            'APPLICATION_TOKEN' => 'page-app-token',
            'APPLICATION_SCOPE' => 'task,sonet_group,socialnetwork,user,im,disk,department',
            'member_id' => 'member-1',
            'status' => 'L',
            'PLACEMENT' => 'DEFAULT',
            'PLACEMENT_OPTIONS' => '{"any":"1\/"}',
        ], $overrides);
    }

    /**
     * @param  string  $appCode  What app.info names as the app (a local app's CODE is its client_id).
     */
    private function fakeBitrix(bool $isAdmin = true, array $bindErrors = [], string $appCode = 'app-id', bool $detailsFail = false): void
    {
        Http::fake(function (Request $request) use ($isAdmin, $bindErrors, $appCode, $detailsFail) {
            if (str_contains($request->url(), '/rest/app.info.json')) {
                return Http::response(['result' => ['ID' => 40, 'CODE' => $appCode, 'STATUS' => 'L', 'INSTALLED' => false]]);
            }

            if (str_contains($request->url(), '/rest/user.admin.json')) {
                return Http::response(['result' => $isAdmin]);
            }

            if ($detailsFail) {
                return Http::response(['error' => 'ACCESS_DENIED', 'error_description' => 'Access denied'], 403);
            }

            if (isset($request['cmd']['app'])) {
                return Http::response(['result' => [
                    'result' => [
                        'app' => ['ID' => 40, 'STATUS' => 'L', 'INSTALLED' => true],
                        'scope' => ['task', 'sonet_group', 'socialnetwork', 'user', 'im', 'disk', 'department'],
                        'me' => ['ID' => '1', 'NAME' => 'Admin', 'LAST_NAME' => 'User', 'EMAIL' => 'admin@portal.test', 'PERSONAL_PHONE' => 'x'],
                        'admin' => $isAdmin,
                        'departments' => [['ID' => '1', 'NAME' => 'HQ', 'PARENT' => null, 'SORT' => '500', 'UF_HEAD' => '1']],
                    ],
                    'result_error' => [],
                    'result_total' => ['departments' => 1],
                ]]);
            }

            return Http::response(['result' => [
                'result' => array_fill_keys(array_diff(BitrixInstallService::EVENTS, array_keys($bindErrors)), true),
                'result_error' => $bindErrors,
            ]]);
        });
    }
}
