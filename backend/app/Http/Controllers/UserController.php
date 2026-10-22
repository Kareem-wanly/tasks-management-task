<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Project;

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

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('users.create'), 403, 'Unauthorized.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user,
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => $user->load('roles:id,name,display_name'),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User details updated successfully',
            'data' => $user,
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

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
