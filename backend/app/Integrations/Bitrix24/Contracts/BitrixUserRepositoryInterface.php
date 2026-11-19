<?php

declare(strict_types=1);

namespace App\Integrations\Bitrix24\Contracts;

use App\Models\User;

/**
 * Employees in Bitrix24 (user.*), through the gateway, with the acting
 * employee's token (Bitrix requires the right to invite or edit users).
 * Reads and writes only; UserService decides when and whether.
 */
interface BitrixUserRepositoryInterface
{
    /**
     * Invites the person (Bitrix emails them) into the given department.
     *
     * @return int Their new Bitrix user ID.
     */
    public function invite(User $actor, string $email, string $name, int $departmentId): int;

    /** Sends a new name and/or email (null: unchanged). */
    public function update(User $actor, int $bitrixUserId, ?string $name, ?string $email): void;

    public function setActive(User $actor, int $bitrixUserId, bool $active): void;

    /**
     * Every employee, page by page, read with the service account (deactivated ones too).
     *
     * @param  callable(list<array<string, mixed>>, int): void  $onPage  The page's rows and the portal total.
     * @return int The portal total.
     */
    public function eachPage(callable $onPage): int;
}
