<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\support\Facades\Hash;

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
}
