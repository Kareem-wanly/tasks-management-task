<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Bitrix\BitrixInstallDTO;

interface BitrixInstallServiceInterface
{
    /** Bitrix24's ONAPPINSTALL event. */
    public function installFromEvent(BitrixInstallDTO $install): void;

    /**
     * The page request comes from a browser, so anyone could forge it: the
     * token is trusted only after Bitrix confirms it is our app's and an admin's.
     */
    public function installFromPage(BitrixInstallDTO $install): void;
}
