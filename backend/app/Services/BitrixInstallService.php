<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Bitrix\BitrixInstallDTO;
use App\Exceptions\Bitrix\BitrixException;
use App\Exceptions\Bitrix\BitrixInstallRefusedException;
use App\Integrations\Bitrix24\BitrixPayload;
use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\BitrixAppRepositoryInterface;
use App\Models\BitrixPortal;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;
use App\Repositories\Contracts\BitrixTokenRepositoryInterface;
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

    public function __construct(
        private BitrixAppRepositoryInterface $bitrixApp,
        private BitrixPortalRepositoryInterface $portals,
        private BitrixTokenRepositoryInterface $tokens,
    ) {}

    public function installFromEvent(BitrixInstallDTO $install): void
    {
        $this->ensureOurPortal($install->domain);

        if (empty($install->memberId) || empty($install->applicationToken) || empty($install->accessToken) || empty($install->refreshToken)) {
            throw BitrixInstallRefusedException::missingEventAuth();
        }

        // Anyone who knows member_id could forge this event: an installed portal
        // only accepts it with the application token Bitrix gave us before.
        $installed = $this->portals->findByMemberId($install->memberId);
        if ($installed instanceof BitrixPortal && ! hash_equals((string) $installed->application_token, $install->applicationToken)) {
            throw BitrixInstallRefusedException::applicationTokenMismatch();
        }

        $this->ensureInstallerMayInstall($install->accessToken);

        $this->savePortal($install);
    }

    /**
     * The page request comes from a browser, so anyone could forge it: the
     * token is trusted only after Bitrix confirms it is our app's and an admin's.
     */
    public function installFromPage(BitrixInstallDTO $install): void
    {
        $this->ensureOurPortal($install->domain);

        if (empty($install->refreshToken) || empty($install->memberId) || empty($install->applicationToken)) {
            throw BitrixInstallRefusedException::missingParameters();
        }

        $this->ensureInstallerMayInstall((string) $install->accessToken);

        $this->savePortal($install);
    }

    /** The token must be our app's (a local app's app.info CODE is its client_id) and a portal admin's. */
    private function ensureInstallerMayInstall(string $accessToken): void
    {
        try {
            $app = $this->bitrixApp->appInfo($accessToken);
            $isAdmin = $this->bitrixApp->isAdmin($accessToken);
        } catch (BitrixException) {
            throw BitrixInstallRefusedException::tokenNotAccepted();
        }

        if (! hash_equals($this->configText('bitrix.client_id'), BitrixPayload::of($app, 'app.info')->stringOrNull('CODE') ?? '')) {
            throw BitrixInstallRefusedException::tokenForAnotherApp();
        }

        if (! $isAdmin) {
            throw BitrixInstallRefusedException::notAnAdministrator();
        }
    }

    private function ensureOurPortal(mixed $domain): void
    {
        if (! is_string($domain) || ! hash_equals($this->configText('bitrix.portal'), $domain)) {
            throw BitrixInstallRefusedException::unknownPortal();
        }
    }

    /** Called after the checks above, so the ids and tokens are there. */
    private function savePortal(BitrixInstallDTO $install): void
    {
        $scopes = $this->splitScopes($install->scope);

        $portal = $this->portals->saveInstall((string) $install->memberId, [
            'domain' => $install->domain,
            'application_token' => $install->applicationToken,
            'scopes' => $scopes,
            'language' => $install->language,
            'installed_at' => now(),
        ]);

        $this->tokens->store($portal, new BitrixToken(
            accessToken: (string) $install->accessToken,
            refreshToken: (string) $install->refreshToken,
            expiresAt: CarbonImmutable::now()->addSeconds($install->expiresIn ?: 3600),
            domain: (string) $install->domain,
            memberId: $install->memberId,
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
        $batch = BitrixPayload::of($this->bitrixApp->portalDetails($portal), 'batch');
        $this->logBatchErrors($batch, 'details');

        $scopes = $batch->has('result.scope') ? array_values($batch->strings('result.scope')) : ($portal->scopes ?? []);

        $this->portals->update($portal, [
            'app_info' => $batch->has('result.app') ? $batch->payload('result.app')->toArray() : null,
            'scopes' => $scopes,
            'installer' => array_intersect_key($batch->payload('result.me')->toArray(), array_flip(['ID', 'NAME', 'LAST_NAME', 'EMAIL', 'WORK_POSITION'])) ?: null,
            'installer_is_admin' => (bool) ($batch->payload('result')->toArray()['admin'] ?? false),
            'departments' => $this->departments($portal, $batch->rows('result.departments'), $batch->intOrNull('result_total.departments') ?? 0),
        ]);

        $missing = array_values(array_diff(self::REQUIRED_SCOPES, $scopes));
        if ($missing !== []) {
            Log::warning('Bitrix24 app is missing required scopes.', ['missing' => $missing]);
        }
    }

    /**
     * Every department, trimmed to the fields we keep.
     *
     * @param  list<array<string, mixed>>  $firstPage
     * @return list<array<string, mixed>>
     */
    private function departments(BitrixPortal $portal, array $firstPage, int $total): array
    {
        $departments = $this->bitrixApp->departments($portal, $firstPage, $total);

        return array_map(fn (array $d): array => array_intersect_key($d, array_flip(['ID', 'NAME', 'PARENT', 'SORT'])), $departments);
    }

    private function bindEvents(BitrixPortal $portal): void
    {
        $handler = rtrim($this->configText('bitrix.public_url'), '/').'/api/bitrix/events';

        $this->logBatchErrors(BitrixPayload::of($this->bitrixApp->bindEvents($portal, self::EVENTS, $handler), 'event.bind'), 'event.bind');
    }

    /**
     * ERROR_CORE "Handler already binded" is expected on reinstall/update.
     */
    private function logBatchErrors(BitrixPayload $batch, string $stage): void
    {
        $errors = array_filter($batch->payload('result_error')->toArray(), fn (mixed $error): bool => ! is_array($error) || ($error['error'] ?? '') !== 'ERROR_CORE');

        if ($errors !== []) {
            Log::warning("Bitrix24 install: some {$stage} calls failed.", ['errors' => $errors]);
        }
    }

    /** A setting that may be unset: then it matches nothing. */
    private function configText(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }

    /** @return list<string> */
    private function splitScopes(string $scope): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $scope))));
    }
}
