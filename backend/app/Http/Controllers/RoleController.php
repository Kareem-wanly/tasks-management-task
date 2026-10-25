<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    use AuthorizesRequests;

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $roles = Role::with('permissions:id,name,description')
            ->withCount(['permissions', 'users'])
            ->get();

        return response()->json([
            'data' => $roles,
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validated();

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'] ?? $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (! empty($validated['permissions'])) {
            $this->guardPermissionGrant($request, $role, $validated['permissions']);
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'role' => $role->load('permissions:id,name,description'),
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

        $systemRoles = ['Administrator', 'Project Manager', 'Member'];

        // Prevent renaming system roles to ensure they remain consistent and protected
        if (in_array($role->name, $systemRoles) && $request->filled('name') && $request->name !== $role->name) {
            return response()->json([
                'message' => 'System roles cannot be renamed.',
            ], 403);
        }

        // Prevent assigning a system role name to any other role
        if (! in_array($role->name, $systemRoles) && $request->filled('name') && in_array($request->name, $systemRoles)) {
            return response()->json([
                'message' => 'Cannot rename a role to a reserved system role name.',
            ], 403);
        }

        $validated = $request->validated();
        $role->update(array_intersect_key($validated, array_flip(['name', 'display_name', 'description'])));

        if (array_key_exists('permissions', $validated)) {
            $this->guardPermissionGrant($request, $role, $validated['permissions']);
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions:id,name,description'),
        ]);
    }

    private function guardPermissionGrant(Request $request, Role $role, array $ids): void
    {
        if ($request->user()->hasRole('Administrator')) {
            return;
        }

        abort_if($role->name === 'Administrator', 403, 'Only an Administrator can modify permissions for this role.');

        $userPermissionIds = $request->user()->roles()
            ->with('permissions')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('id')
            ->unique()
            ->toArray();

        $unauthorized = array_diff($ids, $userPermissionIds);

        abort_if(! empty($unauthorized), 403, 'You cannot grant permissions that you do not possess.');
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        if (in_array($role->name, ['Administrator', 'Project Manager', 'Member'])) {
            return response()->json([
                'message' => "Protected system role [{$role->name}] cannot be deleted",
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
            'permissions' => ['required', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $this->guardPermissionGrant($request, $role, $request->permissions);

        $role->permissions()->sync($request->permissions);

        return response()->json([
            'message' => 'Role permissions synchronized successfully',
            'role' => $role->load('permissions:id,name,description'),
        ]);
    }
}
