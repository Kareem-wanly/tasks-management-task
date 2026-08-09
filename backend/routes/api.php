<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserRoleController;



Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

//protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/permissions', [PermissionController::class, 'index']);

    
    Route::apiResource('roles', RoleController::class);

    Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

    Route::post('/users/{user}/roles', [UserRoleController::class, 'syncRoles']);

});

