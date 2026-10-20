<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserRoleController extends Controller
{
    use AuthorizesRequests;

    public function index(User $user): JsonResponse
    {
        // #5.  Add $this->authorize('view', $user); at the top of the method.
        $this->authorize('viewRoles', $user);

        return response()->json([
            'success' => true,
            'message' => 'User roles retrieved successfully',
            'data' => $user->roles()->get(['roles.id', 'name', 'display_name']),
        ]);
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        if (! $request->user()->hasPermission('roles.assign') && ! $request->user()->hasPermission('roles.manage')) {
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $request->validate([
            'roles' => ['required', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        return DB::transaction(function () use ($request, $user) {
            $adminRole = Role::where('name', 'Administrator')->first();

            if ($adminRole) {
                $userHasAdminRole = $user->roles()->where('roles.id', $adminRole->id)->exists();
                $assigningAdmin = in_array($adminRole->id, $request->roles);

                if (($assigningAdmin || $userHasAdminRole) && ! $request->user()->hasRole('Administrator')) {
                    abort(403, 'Only an Administrator can assign or revoke the Administrator role.');
                }

                $newRolesExcludeAdmin = ! $assigningAdmin;

                if ($userHasAdminRole && $newRolesExcludeAdmin) {
                    $adminCount = User::whereHas('roles', function ($q) use ($adminRole) {
                        $q->where('roles.id', $adminRole->id);
                    })->lockForUpdate()->count();

                    if ($adminCount <= 1) {
                        return response()->json([
                            'message' => 'Cannot revoke Administrator role from the final active administrator.',
                        ], 422);
                    }
                }
            }

            $user->roles()->sync($request->roles);

            return response()->json([
                'message' => 'User roles updated successfully',
                'user' => $user->load('roles:id,name,display_name'),
            ]);
        });
    }
}
