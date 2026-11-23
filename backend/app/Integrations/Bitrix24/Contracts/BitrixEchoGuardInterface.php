<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

/**
 * Step 3.5: tells our own change coming back from Bitrix24 (an echo, already
 * saved here) from a change someone made in Bitrix (a conflict).
 */
interface BitrixEchoGuardInterface
{
    /**
     * @param  array<string, mixed>|null  $attributes  Our columns for the fetched task; null when it is gone from Bitrix.
     * @param  array{accomplice: list<int>, auditor: list<int>}|null  $members  Its accomplices and auditors (Bitrix IDs).
     * @return bool True when the event is the echo of our change: nothing to apply.
     */
    public function isTaskEcho(int $bitrixTaskId, ?array $attributes, ?array $members = null): bool;

    /**
     * @param  array<string, mixed>|null  $attributes  Our columns for the fetched group; null when it is gone from Bitrix.
     */
    public function isProjectEcho(int $bitrixGroupId, ?array $attributes, bool $closed): bool;
}
