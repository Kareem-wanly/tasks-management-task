<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ShowUserRolesRequest;
use App\Http\Requests\SyncUserRolesRequest;
use App\Models\User;
use App\Services\UserRoleService;
use Illuminate\Http\JsonResponse;

class UserRoleController extends Controller
{
    public function __construct(private readonly UserRoleService $roles) {}

    public function index(ShowUserRolesRequest $request, User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User roles retrieved successfully',
            'data' => $this->roles->list($user),
        ]);
    }

    public function syncRoles(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        return response()->json([
            'message' => 'User roles updated successfully',
            'user' => $this->roles->sync($request->user(), $user, $request->toDto()),
        ]);
    }
}
