<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShowUserRolesRequest;
use App\Http\Requests\SyncUserRolesRequest;
use App\Http\Resources\RoleRefResource;
use App\Http\Resources\UserListItemResource;
use App\Models\User;
use App\Services\Contracts\UserRoleServiceInterface;
use Illuminate\Http\JsonResponse;

final class UserRoleController extends Controller
{
    public function __construct(private readonly UserRoleServiceInterface $roles) {}

    public function index(ShowUserRolesRequest $request, User $user): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'User roles retrieved successfully',
            'data' => RoleRefResource::collection($this->roles->list($user)),
        ]);
    }

    public function syncRoles(SyncUserRolesRequest $request, User $user): JsonResponse
    {
        return response()->json([
            'message' => 'User roles updated successfully',
            'user' => new UserListItemResource($this->roles->sync($request->actor(), $user, $request->toDto())),
        ]);
    }
}
