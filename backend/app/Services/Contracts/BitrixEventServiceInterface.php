<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface BitrixEventServiceInterface
{
    /**
     * Bitrix signs every event with the application token it gave us at
     * install; anything else is not from our portal.
     */
    public function isFromOurPortal(mixed $applicationToken, mixed $memberId): bool;

    /** @param  array<string, mixed>  $data  The event's data. */
    public function handle(string $event, array $data): void;
}
