<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        // Validate the request data
        $validated = $request->validated();

        // Create a new user
        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']), // Hash the password
        ]);
        // Assign the Member role
        $memberRole = Role::where('name', 'Member')->first(); // Find the Member role
        if ($memberRole) {
            $user->roles()->attach($memberRole->id);
        }
        // Create a new token for the user
        $token = $user->createToken('auth_token')->plainTextToken;

        // Return a JSON response with the user data and token

        return response()->json([
            'message'      => 'User registered successfully',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => new UserResource($user->load('roles')),
        ], 201); // Return a 201 Created response
    }

    public function login(Request $request): JsonResponse
    {
    $credentials = $request->validate([
        'email'    => ['required', 'string', 'email'],
        'password' => ['required', 'string'],
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json([
            'message' => 'Invalid login credentials'
        ], 401);
    }

    $user = User::where('email', $request->email)->firstOrFail();
    $token = $user->createToken('auth_token')->plainTextToken;

    return response()->json([
        'message'      => 'Logged in successfully',
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'user'         => new UserResource($user->load('roles')),
    ]);
    }

public function me(Request $request): JsonResponse
    {
    return response()->json([
        'user' => new UserResource($request->user()->load('roles')),
    ]);
    }

public function logout(Request $request): JsonResponse
    {
    $request->user()->currentAccessToken()->delete();

    return response()->json([
        'message' => 'Logged out successfully',
    ]);
    }
}
