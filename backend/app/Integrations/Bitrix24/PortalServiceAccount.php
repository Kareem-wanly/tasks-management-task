<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24;

use App\Exceptions\Bitrix\BitrixException;
use App\Integrations\Bitrix24\Contracts\BitrixServiceAccountInterface;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;
use App\Repositories\Contracts\BitrixPortalRepositoryInterface;

/** The service account is the installed portal, whose token the installer gave us. */
final readonly class PortalServiceAccount implements BitrixServiceAccountInterface
{
    public function __construct(private BitrixPortalRepositoryInterface $portals) {}

    public function holder(): HasBitrixToken
    {
        return $this->portals->current() ?? throw BitrixException::missingToken();
    }
}
