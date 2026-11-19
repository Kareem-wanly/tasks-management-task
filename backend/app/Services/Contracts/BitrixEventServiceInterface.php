<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DTOs\Bitrix\BitrixEventDTO;

interface BitrixEventServiceInterface
{
    /**
     * Bitrix signs every event with the application token it gave us at
     * install; anything else is not from our portal.
     */
    public function isFromOurPortal(mixed $applicationToken, mixed $memberId): bool;

    public function handle(BitrixEventDTO $bitrixEvent): void;
}
