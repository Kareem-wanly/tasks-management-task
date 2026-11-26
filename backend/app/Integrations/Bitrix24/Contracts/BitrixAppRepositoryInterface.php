<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

/**
 * The app and the portal in Bitrix24 (app.info, user.current, user.admin,
 * department.get, event.bind), through the gateway. Used by sign-in and
 * install; reads and writes only, the services decide.
 */
interface BitrixAppRepositoryInterface
{
    /**
     * The employee a token belongs to (user.current). It doubles as the
     * check that Bitrix accepts the token: a refused token throws.
     *
     * @return array<string, mixed>
     */
    public function currentUser(string $accessToken): array;

    /**
     * The app a token was issued for (app.info). For a local app its CODE is the client_id.
     *
     * @return array<string, mixed>
     */
    public function appInfo(string $accessToken): array;

    /** Whether the token's employee is a portal administrator (user.admin). */
    public function isAdmin(string $accessToken): bool;

    /**
     * The installed portal's app info, scopes, installer, admin flag and first
     * page of departments, in one batch. Failed parts are in result_error.
     *
     * @return array<string, mixed> The raw batch answer (result, result_error, result_total).
     */
    public function portalDetails(HasBitrixToken $portal): array;

    /**
     * The rest of the company structure after its first page (department.get
     * has no "next", only a total).
     *
     * @param  list<array<string, mixed>>  $firstPage
     * @return list<array<string, mixed>> Every department.
     */
    public function departments(HasBitrixToken $portal, array $firstPage, int $total): array;

    /**
     * Binds each event to our handler URL, in one batch.
     *
     * @param  list<string>  $events
     * @return array<string, mixed> The raw batch answer; failed binds are in result_error.
     */
    public function bindEvents(HasBitrixToken $portal, array $events, string $handlerUrl): array;
}
