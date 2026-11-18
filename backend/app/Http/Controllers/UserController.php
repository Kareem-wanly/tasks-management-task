<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\SetUserActiveRequest;
use App\Http\Requests\ShowUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Requests\UserOptionsRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(ListUsersRequest $request): JsonResponse
    {
        return response()->json($this->users->list($request->filters(), $request->perPage(10)));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'User created successfully',
            'data' => $this->users->invite($request->user(), $request->validated()),
        ], 201);
    }

    public function show(ShowUserRequest $request, User $user): JsonResponse
    {
        return response()->json(['data' => $this->users->show($user)]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        return response()->json([
            'message' => 'User details updated successfully',
            'data' => $this->users->update($request->user(), $user, $request->validated()),
        ]);
    }

    public function setActive(SetUserActiveRequest $request, User $user): JsonResponse
    {
        $active = $request->boolean('active');

        return response()->json([
            'message' => $active ? 'User activated' : 'User deactivated',
            'data' => $this->users->setActive($request->user(), $user, $active),
        ]);
    }

    public function options(UserOptionsRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->users->options()]);
    }
}
