<?php

namespace App\Http\Controllers;

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
            'roles' => $roles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Role::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
        ]);

        if (! empty($validated['permissions'])) {
            $role->permissions()->sync($validated['permissions']);
        }

        return response()->json([
            'message' => 'Role created successfully',
            'data' => $role->load('permissions:id,name,description')->loadCount(['permissions', 'users']),
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

        // #37. Prevent renaming system roles to ensure they remain consistent and protected
        if (in_array($role->name, $systemRoles) && $request->filled('name') && $request->name !== $role->name) {
            return response()->json([
                'message' => 'System roles cannot be renamed.',
            ], 403);
        }

        // #37. Prevent assigning a system role name to any other role
        if (! in_array($role->name, $systemRoles) && $request->filled('name') && in_array($request->name, $systemRoles)) {
            return response()->json([
                'message' => 'Cannot rename a role to a reserved system role name.',
            ], 403);
        }

        $role->update($request->only(['name', 'display_name', 'description']));

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return response()->json([
            'message' => 'Role updated successfully',
            'role' => $role->load('permissions:id,name,description'),
        ]);
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
        // #37. Prevent non-Administrator users from modifying the permissions of the Administrator role
        if ($role->name === 'Administrator' && ! $request->user()->hasRole('Administrator')) {
            return response()->json([
                'message' => 'Only an Administrator can modify permissions for this role.',
            ], 403);
        }

        $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['integer', 'exists:permissions,id'],
        ]);

        if (! $request->user()->hasRole('Administrator')) {
            $userPermissionIds = $request->user()->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('id')
                ->unique()
                ->toArray();

            $unauthorizedPermissions = array_diff($request->permissions, $userPermissionIds);

            if (! empty($unauthorizedPermissions)) {
                return response()->json([
                    'message' => 'You cannot grant permissions that you do not possess.',
                ], 403);
            }
        }

        $role->permissions()->sync($request->permissions);

        return response()->json([
            'message' => 'Role permissions synchronized successfully',
            'role' => $role->load('permissions:id,name,description'),
        ]);
    }
}
