<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::with('permissions:id,name,description')->get();

        return response()->json([
            'data'  => $roles,
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name'         => ['required', 'string', 'max:255', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:255'],
        ]);

        $role = Role::create($validated);

        return response()->json([
            'message' => 'Role created successfully',
            'data'    => $role,
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        $this->authorize('view', $role);

        return response()->json([
            'role' => $role->load('permissions:id,name,description'),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $role->update($request->only(['name', 'display_name', 'description']));

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role'    => $role->load('permissions:id,name,description'),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        if (in_array($role->name, ['Administrator', 'Project Manager', 'Member'])) {
            return response()->json([
                'message' => "Protected system role [{$role->name}] cannot be deleted"
            ], 403);
        }

        $role->delete();

        return response()->json([
            'message' => 'Role deleted successfully',
        ]);
    }

    public function syncPermissions(Request $request, Role $role): JsonResponse
    {
        $this->authorize('update', $role);

        $request->validate([
            'permissions'   => ['required', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role->permissions()->sync($request->permissions);

        return response()->json([
            'message' => 'Role permissions synchronized successfully',
            'role'    => $role->load('permissions:id,name,description'),
        ]);
    }
}