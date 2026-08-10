<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::middleware('auth:sanctum')->group(function () {

    // 1. Authentication & Profile Management
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);

    // 2. User Management & User Roles
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']); // لإتاحة إنشاء مستخدم من قبل الأدمن (users.create)
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);
    
    Route::get('/users/{user}/roles', [UserRoleController::class, 'index']);
    Route::put('/users/{user}/roles', [UserRoleController::class, 'syncRoles']); // مطابقة مع HTTP PUT في كراسة الشروط

    // 3. Roles & Permissions Management
    Route::get('/permissions', [PermissionController::class, 'index']);
    
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{role}', [RoleController::class, 'show']);
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
    Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']); // مطابقة مع HTTP PUT في كراسة الشروط

    // 4. Projects Management & Members
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::patch('/projects/{project}/archive', [ProjectController::class, 'archive']);
    
    Route::get('/projects/{project}/members', [ProjectController::class, 'getMembers']);
    Route::post('/projects/{project}/members', [ProjectController::class, 'addMember']);
    Route::delete('/projects/{project}/members/{user}', [ProjectController::class, 'removeMember']);
    
    Route::get('/projects/{project}/tasks', [TaskController::class, 'indexByProject']);

    // 5. Tasks Management
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::post('/projects/{project}/tasks', [TaskController::class, 'storeByProject']);
    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);

    // 6. Comments Management
    Route::get('/tasks/{task}/comments', [CommentController::class, 'index']);
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    // 7. Activity Logs
    Route::get('/activities', [ProjectController::class, 'allActivities']);
    Route::get('/projects/{project}/activities', [ProjectController::class, 'activities']);
});