<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Bitrix\BitrixInstallDTO;
use App\DTOs\Bitrix\BitrixInstallResultDTO;

interface BitrixInstallServiceInterface
{
    /**
     * Installs from the ONAPPINSTALL event or the install page, and says how it
     * ended. A refused event throws BitrixInstallRefusedException (JSON for
     * Bitrix's server); a refused page is an outcome, shown to the admin as a page.
     */
    public function install(BitrixInstallDTO $install): BitrixInstallResultDTO;
}
