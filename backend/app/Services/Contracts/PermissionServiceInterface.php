<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use Illuminate\Support\Collection;

interface PermissionServiceInterface
{
    /** @return Collection<int, \App\Models\Permission> */
    public function list(): Collection;

    /**
     * IDs of the permissions the given roles grant.
     *
     * @param  list<int>  $roleIds
     * @return Collection<int, int<0, max>>
     */
    public function idsGrantedByRoles(array $roleIds): Collection;
}
