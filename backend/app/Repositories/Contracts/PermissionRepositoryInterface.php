<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface PermissionRepositoryInterface
{
    /** Every permission (id, name, display_name, description). */
    public function all(): Collection;

    /**
     * IDs of the permissions the given roles grant.
     *
     * @param  list<int>  $roleIds
     * @return Collection<int, int>
     */
    public function idsGrantedByRoles(array $roleIds): Collection;
}
