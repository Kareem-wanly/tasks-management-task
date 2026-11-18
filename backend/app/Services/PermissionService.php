<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\PermissionRepositoryInterface;
use App\Services\Contracts\PermissionServiceInterface;
use Illuminate\Support\Collection;

final readonly class PermissionService implements PermissionServiceInterface
{
    public function __construct(private PermissionRepositoryInterface $permissions) {}

    public function list(): Collection
    {
        return $this->permissions->all();
    }
}
