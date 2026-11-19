<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\Bitrix\BitrixInstallRefusedException;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\BitrixGatewayInterface;
use App\Models\BitrixPortal;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;
use App\Services\Contracts\BitrixInstallServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Step 1.4: installing the app on our portal, reported by Bitrix24 in two
 * ways: the ONAPPINSTALL event (server to server) or the install page (the
 * admin's browser). Nothing is saved until Bitrix confirms the install token
 * belongs to our app and to a portal administrator: the token becomes the
 * service account the background sync runs with. A portal that is already
 * installed only accepts an install event signed with its saved application
 * token. Once accepted, a failure while fetching extra details is logged
 * instead of rejecting the install.
 */
final readonly class BitrixInstallService implements BitrixInstallServiceInterface
{
    public const REQUIRED_SCOPES = ['task', 'sonet_group', 'socialnetwork', 'user', 'im', 'disk', 'department'];

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

    public function __construct(
        private BitrixGatewayInterface $bitrix,
        private BitrixPortalRepositoryInterface $portals,
    ) {}

    /** @param  array<string, mixed>  $auth  The event's auth block. */
    public function installFromEvent(array $auth, ?string $language): void
    {
        $this->ensureOurPortal($auth['domain'] ?? null);

        if (empty($auth['member_id']) || empty($auth['application_token']) || empty($auth['access_token']) || empty($auth['refresh_token'])) {
            throw BitrixInstallRefusedException::missingEventAuth();
        }

        // Anyone who knows member_id could forge this event: an installed portal
        // only accepts it with the application token Bitrix gave us before.
        $installed = $this->portals->findByMemberId((string) $auth['member_id']);
        if ($installed instanceof BitrixPortal && ! hash_equals((string) $installed->application_token, (string) $auth['application_token'])) {
            throw BitrixInstallRefusedException::applicationTokenMismatch();
        }

        $this->ensureInstallerMayInstall((string) $auth['access_token']);

        $this->savePortal([
            'member_id' => $auth['member_id'],
            'domain' => $auth['domain'],
            'application_token' => $auth['application_token'],
            'access_token' => $auth['access_token'],
            'refresh_token' => $auth['refresh_token'],
            'expires_in' => $auth['expires_in'] ?? null,
            'scope' => $auth['scope'] ?? '',
            'language' => $language,
        ]);
    }

    /**
     * The page request comes from a browser, so anyone could forge it: the
     * token is trusted only after Bitrix confirms it is our app's and an admin's.
     *
     * @param  array<string, mixed>  $page  The install page's parameters.
     */
    public function installFromPage(array $page): void
    {
        $this->ensureOurPortal($page['DOMAIN'] ?? null);

        foreach (['REFRESH_ID', 'member_id', 'APPLICATION_TOKEN'] as $key) {
            if (empty($page[$key])) {
                throw BitrixInstallRefusedException::missingParameters();
            }
        }

        $this->ensureInstallerMayInstall((string) ($page['AUTH_ID'] ?? ''));

        $this->savePortal([
            'member_id' => $page['member_id'],
            'domain' => $page['DOMAIN'],
            'application_token' => $page['APPLICATION_TOKEN'],
            'access_token' => $page['AUTH_ID'],
            'refresh_token' => $page['REFRESH_ID'],
            'expires_in' => $page['AUTH_EXPIRES'] ?? null,
            'scope' => $page['APPLICATION_SCOPE'] ?? '',
            'language' => $page['LANG'] ?? null,
        ]);
    }

    /** The token must be our app's (a local app's app.info CODE is its client_id) and a portal admin's. */
    private function ensureInstallerMayInstall(string $accessToken): void
    {
        try {
            $app = $this->bitrix->callWithToken($accessToken, 'app.info')['result'] ?? [];
            $isAdmin = (bool) ($this->bitrix->callWithToken($accessToken, 'user.admin')['result'] ?? false);
        } catch (BitrixException) {
            throw BitrixInstallRefusedException::tokenNotAccepted();
        }

        if (! hash_equals((string) config('bitrix.client_id'), (string) ($app['CODE'] ?? ''))) {
            throw BitrixInstallRefusedException::tokenForAnotherApp();
        }

        if (! $isAdmin) {
            throw BitrixInstallRefusedException::notAnAdministrator();
        }
    }

    private function ensureOurPortal(mixed $domain): void
    {
        if (! is_string($domain) || ! hash_equals((string) config('bitrix.portal'), $domain)) {
            throw BitrixInstallRefusedException::unknownPortal();
        }
    }

    /**
     * @param  array{member_id: string, domain: string, application_token: string, access_token: string, refresh_token: string, expires_in: mixed, scope: string, language: ?string}  $install
     */
    private function savePortal(array $install): void
    {
        $scopes = $this->splitScopes($install['scope']);

        $portal = $this->portals->saveInstall($install['member_id'], [
            'domain' => $install['domain'],
            'application_token' => $install['application_token'],
            'scopes' => $scopes,
            'language' => $install['language'],
            'installed_at' => now(),
        ]);

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

        $this->portals->update($portal, [
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
    }

    /** department.get returns 50 per page and has no "next", only "total". */
    private function departments(BitrixPortal $portal, array $firstPage, int $total): array
    {
        $departments = $firstPage;

        for ($start = self::DEPARTMENTS_PAGE_SIZE; $start < $total; $start += self::DEPARTMENTS_PAGE_SIZE) {
            $departments = [...$departments, ...($this->bitrix->callAsUser($portal, 'department.get', ['START' => $start])['result'] ?? [])];
        }

        return array_map(fn (array $d): array => array_intersect_key($d, array_flip(['ID', 'NAME', 'PARENT', 'SORT'])), $departments);
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

    /** ERROR_CORE "Handler already binded" is expected on reinstall/update. */
    private function logBatchErrors(array $batch, string $stage): void
    {
        $errors = array_filter((array) ($batch['result_error'] ?? []), fn (array $error): bool => ($error['error'] ?? '') !== 'ERROR_CORE');

        if ($errors !== []) {
            Log::warning("Bitrix24 install: some {$stage} calls failed.", ['errors' => $errors]);
        }
    }

    /** @return list<string> */
    private function splitScopes(string $scope): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $scope))));
    }
}
