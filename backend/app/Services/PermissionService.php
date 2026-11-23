<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Services\Contracts\PermissionServiceInterface;
use Illuminate\Support\Collection;

final readonly class PermissionService implements PermissionServiceInterface
{
    public function __construct(private PermissionRepositoryInterface $permissions) {}

    /** @return Collection<int, \App\Models\Permission> */
    public function list(): Collection
    {
        return $this->permissions->all();
    }

    /**
     * @param  list<int>  $roleIds
     * @return Collection<int, int<0, max>>
     */
    public function idsGrantedByRoles(array $roleIds): Collection
    {
        return $this->permissions->idsGrantedByRoles($roleIds);
    }
}
