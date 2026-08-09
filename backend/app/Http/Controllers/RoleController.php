<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions:id,name,description')->get();

        return response()->json([
            'roles' => $roles,
        ]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
{
    
    $role = Role::create([
        'name'         => $request->name,
        'display_name' => $request->display_name ?? $request->name,
        'description'  => $request->description,
    ]);

    
    if ($request->has('permissions')) {
        $role->permissions()->sync($request->permissions);
    }

    return response()->json([
        'message' => 'Role created successfully',
        'role'    => $role->load('permissions:id,name,description'),
    ], 201); 
}

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'role' => $role->load('permissions:id,name,description'),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
{
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
    $request->validate([
        'permissions'   => ['required', 'array'],
        'permissions.*' => ['integer', 'exists:permissions,id'],
    ]);

    $role->permissions()->sync($request->permissions);

    return response()->json([
        'message'     => 'Role permissions synchronized successfully',
        'role'        => $role->load('permissions:id,name,description'),
    ]);
}
}