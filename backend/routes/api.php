<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BitrixAuthController;
use App\Http\Controllers\BitrixEventController;
use App\Http\Controllers\BitrixInstallController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskChatController;
use App\Http\Controllers\TaskChecklistController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskFileController;
use App\Http\Controllers\TaskMemberController;
use App\Http\Controllers\TaskPriorityController;
use App\Http\Controllers\TaskResultController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\TaskTimeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Bitrix24 calls this itself, so there is no user auth.
Route::post('/bitrix/install', [BitrixInstallController::class, 'install']);
// Verified by the application token inside the event, not by a user session.
Route::post('/bitrix/events', [BitrixEventController::class, 'handle']);
Route::post('/auth/bitrix/exchange', [BitrixAuthController::class, 'exchange'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/users/options', [UserController::class, 'options']);
    Route::get('/projects/options', [ProjectController::class, 'options']);

    // 1. Authentication & Profile
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'updatePassword']);

    // 2. User & User Roles Management
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    // No delete: Bitrix24 can't delete users over REST, so they are deactivated.
    Route::patch('/users/{user}/active', [UserController::class, 'setActive']);

    Route::get('/users/{user}/roles', [UserRoleController::class, 'index']);
    Route::put('/users/{user}/roles', [UserRoleController::class, 'syncRoles']);

    // 3. Roles & Permissions Management
    Route::get('/permissions', [PermissionController::class, 'index']);

    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/{role}', [RoleController::class, 'show']);
    Route::put('/roles/{role}', [RoleController::class, 'update']);
    Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
    Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

    // 4. Projects Management & Members
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{project}', [ProjectController::class, 'show']);
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
    Route::match(['post', 'patch'], '/projects/{project}/archive', [ProjectController::class, 'toggleArchive']);

    Route::get('/projects/{project}/members', [ProjectController::class, 'members']);
    Route::post('/projects/{project}/members', [ProjectController::class, 'addMember']);
    Route::delete('/projects/{project}/members/{user}', [ProjectController::class, 'removeMember']);

    // 5. Tasks Management
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/projects/{project}/tasks', [TaskController::class, 'indexByProject']);
    Route::post('/projects/{project}/tasks', [TaskController::class, 'storeByProject']);

    Route::get('/tasks/{task}', [TaskController::class, 'show']);
    Route::put('/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);
    Route::put('/tasks/{task}/members', [TaskMemberController::class, 'update']);
    Route::post('/tasks/{task}/watch', [TaskMemberController::class, 'watch']);
    Route::delete('/tasks/{task}/watch', [TaskMemberController::class, 'unwatch']);
    Route::post('/tasks/{task}/delegate', [TaskMemberController::class, 'delegate']);
    Route::get('/tasks/{task}/checklist', [TaskChecklistController::class, 'index']);
    Route::post('/tasks/{task}/checklist', [TaskChecklistController::class, 'store']);
    Route::patch('/checklist-items/{item}', [TaskChecklistController::class, 'update']);
    Route::delete('/checklist-items/{item}', [TaskChecklistController::class, 'destroy']);
    Route::post('/checklist-items/{item}/move', [TaskChecklistController::class, 'move']);
    Route::get('/tasks/{task}/files', [TaskFileController::class, 'index']);
    Route::post('/tasks/{task}/files', [TaskFileController::class, 'store']);
    Route::delete('/task-files/{file}', [TaskFileController::class, 'destroy']);
    Route::get('/task-files/{file}/download', [TaskFileController::class, 'download']);
    Route::get('/tasks/{task}/results', [TaskResultController::class, 'index']);
    Route::post('/tasks/{task}/results', [TaskResultController::class, 'store']);
    Route::put('/task-results/{result}', [TaskResultController::class, 'update']);
    Route::delete('/task-results/{result}', [TaskResultController::class, 'destroy']);
    Route::get('/tasks/{task}/time', [TaskTimeController::class, 'index']);
    Route::post('/tasks/{task}/time', [TaskTimeController::class, 'store']);
    Route::put('/time-entries/{entry}', [TaskTimeController::class, 'update']);
    Route::delete('/time-entries/{entry}', [TaskTimeController::class, 'destroy']);
    Route::post('/tasks/{task}/timer', [TaskTimeController::class, 'start']);
    Route::post('/tasks/{task}/timer/stop', [TaskTimeController::class, 'stop']);

    Route::get('/task-statuses', [TaskStatusController::class, 'index']);
    Route::post('/task-statuses', [TaskStatusController::class, 'store']);
    Route::put('/task-statuses/{taskStatus}', [TaskStatusController::class, 'update']);
    Route::delete('/task-statuses/{taskStatus}', [TaskStatusController::class, 'destroy']);

    Route::get('/task-priorities', [TaskPriorityController::class, 'index']);
    Route::post('/task-priorities', [TaskPriorityController::class, 'store']);
    Route::put('/task-priorities/{taskPriority}', [TaskPriorityController::class, 'update']);
    Route::delete('/task-priorities/{taskPriority}', [TaskPriorityController::class, 'destroy']);

    // 6. Task chat (Bitrix24 step 4.7; replaced comments)
    Route::get('/tasks/{task}/chat', [TaskChatController::class, 'index']);
    Route::post('/tasks/{task}/chat', [TaskChatController::class, 'store']);
    Route::put('/task-chat-messages/{message}', [TaskChatController::class, 'update']);
    Route::delete('/task-chat-messages/{message}', [TaskChatController::class, 'destroy']);
    Route::get('/task-chat-messages/{message}/files/{file}/download', [TaskChatController::class, 'download'])->whereNumber('file');

    // 7. Activity Logs
    Route::get('/activities', [ProjectController::class, 'allActivities']);
    Route::get('/projects/{project}/activities', [ProjectController::class, 'activities']);

});
