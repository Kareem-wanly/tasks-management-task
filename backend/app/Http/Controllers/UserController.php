<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;

class UserController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('roles:id,name,display_name');

        if ($request->filled('search')) {
            $search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $role = $request->query('role');
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role)
                    ->orWhere('id', $role);
            });
        }

        if ($request->has('per_page') && ! is_numeric($request->query('per_page'))) {
            return response()->json([
                'message' => 'The per_page parameter must be an integer.',
            ], 422);
        }

        $perPage = min(max((int) $request->query('per_page', 10), 1), 100);

        $users = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            if (! empty($validated['role_id'])) {
                $user->roles()->attach($validated['role_id']);
            } else {
                $memberRole = Role::firstOrCreate(['name' => 'Member'], [
                    'display_name' => 'Member',
                    'description' => 'Standard team member with basic access',
                ]);
                $user->roles()->attach($memberRole->id);
            }

            return $user;
        });

        return response()->json([
            'message' => 'User created successfully',
            'data' => $user->load('roles:id,name,display_name'),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => $user->load('roles:id,name,display_name'),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user->update($request->validated());

        return response()->json([
            'message' => 'User details updated successfully',
            'data' => $user,
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        abort_if($user->is($request->user()), 403, 'You cannot delete your own account.');
        abort_if(
            $user->hasRole('Administrator') && ! $request->user()->hasRole('Administrator'),
            403,
            'Only an Administrator can delete another Administrator.'
        );

        if (Project::withTrashed()->where('owner_id', $user->id)->exists()) {
            return response()->json([
                'message' => "Transfer or delete this user's projects before deleting the user.",
            ], 409);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function options(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $users = User::orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'data' => $users,
        ]);
    }
}
