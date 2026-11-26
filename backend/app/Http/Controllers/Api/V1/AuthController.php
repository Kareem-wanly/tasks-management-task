<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\AuthenticatedRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Http\JsonResponse;

final class AuthController extends Controller
{
    public function __construct(private readonly AuthServiceInterface $auth) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        [$user, $token] = $this->auth->register($request->toDto());

        return $this->signedIn('User registered successfully', $user, $token, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        [$user, $token] = $this->auth->login($request->toDto());

        return $this->signedIn('Logged in successfully', $user, $token);
    }

    public function me(AuthenticatedRequest $request): JsonResponse
    {
        return response()->json(['user' => new UserResource($this->auth->profile($request->actor()))]);
    }

    public function logout(AuthenticatedRequest $request): JsonResponse
    {
        $this->auth->logout($request->actor());

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new UserResource($this->auth->updateProfile($request->actor(), $request->toDto())),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $this->auth->updatePassword($request->actor(), $request->toDto());

        return response()->json(['success' => true, 'message' => 'Password updated successfully']);
    }

    private function signedIn(string $message, User $user, string $token, int $status = 200): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], $status);
    }
}
