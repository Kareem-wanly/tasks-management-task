<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use Illuminate\Support\Collection;

interface PermissionServiceInterface
{
    /** @return Collection<int, \App\Models\Permission> */
    public function list(): Collection;
}
