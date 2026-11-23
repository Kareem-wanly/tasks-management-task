<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListPermissionsRequest;
use App\Http\Resources\PermissionResource;
use App\Services\Contracts\PermissionServiceInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PermissionController extends Controller
{
    public function __construct(private readonly PermissionServiceInterface $permissions) {}

    public function index(ListPermissionsRequest $request): AnonymousResourceCollection
    {
        return PermissionResource::collection($this->permissions->list());
    }
}
