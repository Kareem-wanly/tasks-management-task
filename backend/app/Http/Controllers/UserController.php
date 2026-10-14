<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class UserController extends Controller
{
    use AuthorizesRequests;

    
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('roles:id,name,display_name');

        if ($request->filled('search')) {
            $search = $request->query('search');
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

        $perPage = (int) $request->query('per_page', 10);
        
        if ($request->query('per_page') === 'all' || $perPage === -1) {
            $users = $query->orderBy('name', 'asc')->get(['id', 'name', 'email', 'created_at']);
            return response()->json([
                'data' => $users
            ]);
        }

        $users = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json($users);
    }

    
    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json([
            'data' => $user->load('roles:id,name,display_name')
        ]);
    }

    
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name'  => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User details updated successfully',
            'data'    => $user
        ]);
    }

    
    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->hasRole('Administrator')) {
            return response()->json([
                'message' => 'Administrator users cannot be deleted directly.'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully'
        ]);
    }
}