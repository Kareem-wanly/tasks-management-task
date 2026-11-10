<?php

namespace App\Http\Controllers;

use App\Integrations\Bitrix24\BitrixClient;
use App\Integrations\Bitrix24\BitrixException;
use App\Integrations\Bitrix24\BitrixToken;
use App\Models\BitrixPortal;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Handles the two ways Bitrix24 reports an install on the "Initial
 * installation path":
 *
 * - Install page: the admin's browser opens the path with AUTH_ID, REFRESH_ID,
 *   APPLICATION_TOKEN... Bitrix treats the app as not installed until the page
 *   calls BX24.installFinish(), so we answer with a page that does.
 * - ONAPPINSTALL event: Bitrix's server posts event/auth[...] (API-only apps,
 *   and on every update).
 *
 * Installation always succeeds for our portal, whoever installs the app:
 * the data is saved first, and a failure while fetching extra details is
 * logged instead of rejecting the install.
 */
class BitrixInstallController extends Controller
{
    public const REQUIRED_SCOPES = ['task', 'sonet_group', 'user', 'im', 'disk', 'department'];

    public const EVENTS = [
        'OnTaskAdd',
        'OnTaskUpdate',
        'OnTaskDelete',
        'OnTaskCommentAdd',
        'OnSonetGroupAdd',
        'OnSonetGroupUpdate',
        'OnSonetGroupDelete',
    ];

    private const DEPARTMENTS_PAGE_SIZE = 50;

    public function __construct(private readonly BitrixClient $bitrix) {}

    public function install(Request $request): Response
    {
        if ($request->input('event') === 'ONAPPINSTALL') {
            return $this->fromEvent($request);
        }

        if ($request->filled('AUTH_ID')) {
            return $this->fromInstallPage($request);
        }

        return response()->json(['message' => 'Unsupported request.'], 400);
    }

    private function fromEvent(Request $request): JsonResponse
    {
        $auth = (array) $request->input('auth', []);

        if (! $this->isOurPortal($auth['domain'] ?? null)) {
            return response()->json(['message' => 'Unknown portal.'], 403);
        }

        if (empty($auth['member_id']) || empty($auth['application_token'])) {
            return response()->json(['message' => 'Missing member_id or application_token.'], 422);
        }

        $this->savePortal([
            'member_id' => $auth['member_id'],
            'domain' => $auth['domain'],
            'application_token' => $auth['application_token'],
            'access_token' => $auth['access_token'] ?? null,
            'refresh_token' => $auth['refresh_token'] ?? null,
            'expires_in' => $auth['expires_in'] ?? null,
            'scope' => $auth['scope'] ?? '',
            'language' => $request->input('data.LANGUAGE_ID'),
        ]);

        return response()->json(['message' => 'Installed.']);
    }

    /**
     * The page request comes from a browser, so anyone could forge it. The
     * token is only trusted after Bitrix itself accepts it.
     */
    private function fromInstallPage(Request $request): Response
    {
        $domain = $request->input('DOMAIN');

        if (! $this->isOurPortal($domain)) {
            return response()->json(['message' => 'Unknown portal.'], 403);
        }

        if (! $request->filled(['REFRESH_ID', 'member_id', 'APPLICATION_TOKEN'])) {
            return response()->json(['message' => 'Missing install parameters.'], 422);
        }

        try {
            $this->bitrix->callWithToken($request->input('AUTH_ID'), 'app.info');
        } catch (BitrixException $e) {
            return response()->json(['message' => 'Bitrix24 did not accept the install token.'], 403);
        }

        $this->savePortal([
            'member_id' => $request->input('member_id'),
            'domain' => $domain,
            'application_token' => $request->input('APPLICATION_TOKEN'),
            'access_token' => $request->input('AUTH_ID'),
            'refresh_token' => $request->input('REFRESH_ID'),
            'expires_in' => $request->input('AUTH_EXPIRES'),
            'scope' => $request->input('APPLICATION_SCOPE', ''),
            'language' => $request->input('LANG'),
        ]);

        return response()->view('bitrix.install-finish');
    }

    private function isOurPortal(mixed $domain): bool
    {
        return is_string($domain) && hash_equals((string) config('bitrix.portal'), $domain);
    }

    /**
     * @param  array{member_id: string, domain: string, application_token: string, access_token: ?string, refresh_token: ?string, expires_in: mixed, scope: string, language: ?string}  $install
     */
    private function savePortal(array $install): void
    {
        $scopes = $this->splitScopes($install['scope']);

        $portal = BitrixPortal::updateOrCreate(
            ['member_id' => $install['member_id']],
            [
                'domain' => $install['domain'],
                'application_token' => $install['application_token'],
                'scopes' => $scopes,
                'language' => $install['language'],
                'installed_at' => now(),
            ]
        );

        // The event carries tokens only when it is linked to a user.
        if (empty($install['access_token']) || empty($install['refresh_token'])) {
            Log::warning('Bitrix24 install carried no user tokens; service account not set.', ['member_id' => $portal->member_id]);

            return;
        }

        $portal->storeBitrixToken(new BitrixToken(
            accessToken: $install['access_token'],
            refreshToken: $install['refresh_token'],
            expiresAt: CarbonImmutable::now()->addSeconds((int) ($install['expires_in'] ?: 3600)),
            domain: $install['domain'],
            memberId: $install['member_id'],
            scopes: $scopes,
        ));

        try {
            $this->fetchPortalDetails($portal);
            $this->bindEvents($portal);
        } catch (BitrixException $e) {
            Log::warning('Bitrix24 install saved, but fetching details failed.', [
                'member_id' => $portal->member_id,
                'error' => $e->errorCode,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function fetchPortalDetails(BitrixPortal $portal): void
    {
        $batch = $this->bitrix->batch($portal, [
            'app' => ['app.info'],
            'scope' => ['scope'],
            'me' => ['user.current'],
            'admin' => ['user.admin'],
            'departments' => ['department.get'],
        ]);

        $result = $batch['result'] ?? [];
        $this->logBatchErrors($batch, 'details');

        $me = $result['me'] ?? [];
        $scopes = is_array($result['scope'] ?? null) ? array_values($result['scope']) : ($portal->scopes ?? []);
        $isAdmin = (bool) ($result['admin'] ?? false);

        $portal->update([
            'app_info' => $result['app'] ?? null,
            'scopes' => $scopes,
            'installer' => array_intersect_key($me, array_flip(['ID', 'NAME', 'LAST_NAME', 'EMAIL', 'WORK_POSITION'])) ?: null,
            'installer_is_admin' => $isAdmin,
            'departments' => $this->departments($portal, $result['departments'] ?? [], (int) ($batch['result_total']['departments'] ?? 0)),
        ]);

        $missing = array_values(array_diff(self::REQUIRED_SCOPES, $scopes));
        if ($missing !== []) {
            Log::warning('Bitrix24 app is missing required scopes.', ['missing' => $missing]);
        }

        if (! $isAdmin) {
            Log::warning('Bitrix24 app was installed by a non-admin; background sync runs with their permissions.', [
                'installer_id' => $me['ID'] ?? null,
            ]);
        }
    }

    /**
     * department.get returns 50 per page and has no "next", only "total".
     */
    private function departments(BitrixPortal $portal, array $firstPage, int $total): array
    {
        $departments = $firstPage;

        for ($start = self::DEPARTMENTS_PAGE_SIZE; $start < $total; $start += self::DEPARTMENTS_PAGE_SIZE) {
            $departments = [...$departments, ...($this->bitrix->callAsUser($portal, 'department.get', ['START' => $start])['result'] ?? [])];
        }

        return array_map(
            fn (array $d) => array_intersect_key($d, array_flip(['ID', 'NAME', 'PARENT', 'SORT'])),
            $departments
        );
    }

    private function bindEvents(BitrixPortal $portal): void
    {
        $handler = rtrim((string) config('bitrix.public_url'), '/').'/api/bitrix/events';

        $commands = [];
        foreach (self::EVENTS as $event) {
            $commands[$event] = ['event.bind', ['event' => $event, 'handler' => $handler]];
        }

        $this->logBatchErrors($this->bitrix->batch($portal, $commands), 'event.bind');
    }

    /**
     * ERROR_CORE "Handler already binded" is expected on reinstall/update.
     */
    private function logBatchErrors(array $batch, string $stage): void
    {
        $errors = array_filter(
            (array) ($batch['result_error'] ?? []),
            fn ($error) => ($error['error'] ?? '') !== 'ERROR_CORE'
        );

        if ($errors !== []) {
            Log::warning("Bitrix24 install: some {$stage} calls failed.", ['errors' => $errors]);
        }
    }

    /**
     * @return list<string>
     */
    private function splitScopes(string $scope): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $scope))));
    }
}
