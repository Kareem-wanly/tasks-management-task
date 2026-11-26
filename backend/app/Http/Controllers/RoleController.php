<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteRoleRequest;
use App\Http\Requests\ListRolesRequest;
use App\Http\Requests\ShowRoleRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Role;
use App\Services\Contracts\RoleServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RoleController extends Controller
{
    public function __construct(private readonly RoleServiceInterface $roles) {}

    public function index(ListRolesRequest $request): AnonymousResourceCollection
    {
        return RoleResource::collection($this->roles->list());
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Role created successfully',
            'data' => new RoleResource($this->roles->create($request->actor(), $request->toDto())),
        ], 201);
    }

    public function show(ShowRoleRequest $request, Role $role): JsonResponse
    {
        return response()->json(['role' => new RoleResource($this->roles->show($role))]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        return response()->json([
            'message' => 'Role updated successfully',
            'data' => new RoleResource($this->roles->update($request->actor(), $role, $request->toDto())),
        ]);
    }

    public function destroy(DeleteRoleRequest $request, Role $role): JsonResponse
    {
        $this->roles->delete($role);

        return response()->json(['message' => 'Role deleted successfully']);
    }

    public function syncPermissions(SyncRolePermissionsRequest $request, Role $role): JsonResponse
    {
        return response()->json([
            'message' => 'Role permissions synchronized successfully',
            'data' => new RoleResource($this->roles->syncPermissions($request->actor(), $role, $request->toDto())),
        ]);
    }
}
