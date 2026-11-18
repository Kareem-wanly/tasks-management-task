<?php

namespace App\Services;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use Illuminate\Support\Collection;

class PermissionService
{
    public function __construct(private readonly PermissionRepositoryInterface $permissions) {}

    public function list(): Collection
    {
        return $this->permissions->all();
    }
}
