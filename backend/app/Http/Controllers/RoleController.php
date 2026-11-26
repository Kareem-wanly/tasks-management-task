<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\DeleteRoleRequest;
use App\Http\Requests\ListRolesRequest;
use App\Http\Requests\ShowRoleRequest;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\SyncRolePermissionsRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Services\Contracts\RoleServiceInterface;
use Illuminate\Http\JsonResponse;

final class RoleController extends Controller
{
    public function __construct(private readonly RoleServiceInterface $roles) {}

    public function index(ListRolesRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->roles->list()]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Role created successfully',
            'data' => $this->roles->create($request->actor(), $request->toDto()),
        ], 201);
    }

    public function show(ShowRoleRequest $request, Role $role): JsonResponse
    {
        return response()->json(['role' => $this->roles->show($role)]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        return response()->json([
            'message' => 'Role updated successfully',
            'data' => $this->roles->update($request->actor(), $role, $request->toDto()),
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
            'data' => $this->roles->syncPermissions($request->actor(), $role, $request->toDto()),
        ]);
    }
}
