<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role; 
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserRoleController extends Controller
{
    use AuthorizesRequests;

    
    public function index(User $user): JsonResponse
    {
        return response()->json([
            'data' => $user->roles()->get(['roles.id', 'name', 'display_name'])
        ]);
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        
        if (!$request->user()->hasPermission('roles.assign') && !$request->user()->hasPermission('roles.manage')) {
            return response()->json([
                'message' => 'This action is unauthorized.'
            ], 403);
        }

        $request->validate([
            'roles'   => ['required', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        $adminRole = Role::where('name', 'Administrator')->first();

        if ($adminRole) {
            $userHasAdminRole = $user->roles()->where('roles.id', $adminRole->id)->exists();
            $newRolesExcludeAdmin = !in_array($adminRole->id, $request->roles);

            if ($userHasAdminRole && $newRolesExcludeAdmin) {
                $adminCount = User::whereHas('roles', function ($q) use ($adminRole) {
                    $q->where('roles.id', $adminRole->id);
                })->count();

                if ($adminCount <= 1) {
                    return response()->json([
                        'message' => 'Cannot revoke Administrator role from the final active administrator.'
                    ], 422);
                }
            }
        }

        $user->roles()->sync($request->roles);

        return response()->json([
            'message' => 'User roles updated successfully',
            'user'    => $user->load('roles:id,name,display_name'),
        ]);
    }
}