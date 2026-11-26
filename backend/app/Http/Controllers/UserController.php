<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\SetUserActiveRequest;
use App\Http\Requests\ShowUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserOptionsRequest;
use App\Http\Resources\UserListItemResource;
use App\Http\Resources\UserOptionResource;
use App\Models\User;
use App\Services\Contracts\UserServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class UserController extends Controller
{
    public function __construct(private readonly UserServiceInterface $users) {}

    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        return UserListItemResource::collection($this->users->list($request->toDto(), $request->perPage(10)));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'User created successfully',
            'data' => new UserListItemResource($this->users->invite($request->actor(), $request->toDto())),
        ], 201);
    }

    public function show(ShowUserRequest $request, User $user): JsonResponse
    {
        return response()->json(['data' => new UserListItemResource($this->users->show($user))]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return response()->json([
            'message' => 'User details updated successfully',
            'data' => new UserListItemResource($this->users->update($request->actor(), $user, $request->toDto())),
        ]);
    }

    public function setActive(SetUserActiveRequest $request, User $user): JsonResponse
    {
        $data = $request->toDto();

        return response()->json([
            'message' => $data->active ? 'User activated' : 'User deactivated',
            'data' => new UserListItemResource($this->users->setActive($request->actor(), $user, $data)),
        ]);
    }

    public function options(UserOptionsRequest $request): AnonymousResourceCollection
    {
        return UserOptionResource::collection($this->users->options());
    }
}
