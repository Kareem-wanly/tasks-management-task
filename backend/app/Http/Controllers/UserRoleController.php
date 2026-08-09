<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserRoleController extends Controller
{
    public function syncRoles(Request $request, User $user): JsonResponse
{
    $request->validate([
        'roles'   => ['required', 'array'],
        'roles.*' => ['integer', 'exists:roles,id'],
    ]);

    $adminRole = \App\Models\Role::where('name', 'Administrator')->first();

    
    if ($adminRole && $user->roles->contains($adminRole->id) && !in_array($adminRole->id, $request->roles)) {

    $adminCount = \App\Models\User::whereHas('roles', function ($query) use ($adminRole) {
            $query->where('roles.id', $adminRole->id);
        })->count();

        if ($adminCount <= 1) {
            return response()->json([
                'message' => 'Cannot revoke Administrator role from the final active administrator'
            ], 422);
        }
    }

    $user->roles()->sync($request->roles);

    return response()->json([
        'message' => 'User roles synchronized successfully',
        'user'    => $user->load('roles:id,name,description'),
    ]);
}
}