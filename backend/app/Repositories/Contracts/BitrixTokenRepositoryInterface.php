<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Integrations\Bitrix24\BitrixToken;
use App\Integrations\Bitrix24\Contracts\HasBitrixToken;

/** Where a Bitrix24 token is kept: on the user who signed in, or on the installed portal. */
interface BitrixTokenRepositoryInterface
{
    public function store(HasBitrixToken $holder, BitrixToken $token): void;
}
