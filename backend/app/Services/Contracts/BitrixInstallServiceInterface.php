<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface BitrixInstallServiceInterface
{
    /** @param  array<string, mixed>  $auth  The event's auth block. */
    public function installFromEvent(array $auth, ?string $language): void;

    /**
     * The page request comes from a browser, so anyone could forge it: the
     * token is trusted only after Bitrix confirms it is our app's and an admin's.
     *
     * @param  array<string, mixed>  $page  The install page's parameters.
     */
    public function installFromPage(array $page): void;
}
