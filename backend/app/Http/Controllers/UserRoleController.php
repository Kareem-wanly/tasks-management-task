<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;

class UserRoleController extends Controller
{
    use AuthorizesRequests;

    public function index(User $user): JsonResponse
    {
        $this->authorize('viewRoles', $user);

        return response()->json([
            'success' => true,
            'message' => 'User roles retrieved successfully',
            'data'    => $user->roles()->get(['roles.id', 'name', 'display_name', 'description']),
        ]);
    }

    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $this->authorize('manageRoles', $user);

        abort_if(
            $user->is($request->user()) && ! $request->user()->hasRole('Administrator'),
            403,
            'You cannot modify your own roles.'
        );

        $request->validate([
            'roles'   => ['required', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ]);

        // R7: التحقق من أن الرتب المطلوبة لا تمنح صلاحيات لا يملكها المستخدم الحالي
        if (! $request->user()->hasRole('Administrator')) {
            $requestedPermissionIds = Permission::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $request->roles))
                ->pluck('id');

            $myPermissionIds = $request->user()->roles()
                ->with('permissions')
                ->get()
                ->pluck('permissions')
                ->flatten()
                ->pluck('id')
                ->unique();

            abort_if(
                $requestedPermissionIds->diff($myPermissionIds)->isNotEmpty(),
                403,
                'You cannot assign roles with permissions that you do not possess.'
            );
        }

        return DB::transaction(function () use ($request, $user) {
            $adminRole = Role::where('name', 'Administrator')->first();

            if ($adminRole) {
                $userHasAdminRole = $user->roles()->where('roles.id', $adminRole->id)->exists();
                $assigningAdmin   = in_array($adminRole->id, $request->roles);

                if (($assigningAdmin || $userHasAdminRole) && ! $request->user()->hasRole('Administrator')) {
                    abort(403, 'Only an Administrator can assign or revoke the Administrator role.');
                }

                $isRevokingAdmin = ! $assigningAdmin;

                if ($userHasAdminRole && $isRevokingAdmin) {
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
                'user'    => $user->load('roles:id,name,display_name'),
            ]);
        });
    }


}
