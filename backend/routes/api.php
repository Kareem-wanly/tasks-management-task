<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BitrixAuthController;
use App\Http\Controllers\Api\V1\BitrixEventController;
use App\Http\Controllers\Api\V1\BitrixInstallController;
use App\Http\Controllers\Api\V1\BitrixSyncController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\TaskChatController;
use App\Http\Controllers\Api\V1\TaskChecklistController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TaskFileController;
use App\Http\Controllers\Api\V1\TaskHistoryController;
use App\Http\Controllers\Api\V1\TaskMemberController;
use App\Http\Controllers\Api\V1\TaskPriorityController;
use App\Http\Controllers\Api\V1\TaskResultController;
use App\Http\Controllers\Api\V1\TaskStatusController;
use App\Http\Controllers\Api\V1\TaskTimeController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\UserRoleController;
use Illuminate\Support\Facades\Route;

/*
 * Bitrix24 calls these itself, at the URLs registered in the app's settings
 * there, so they stay outside the API version.
 */
// No user auth: Bitrix24 installs the app.
Route::post('/bitrix/install', [BitrixInstallController::class, 'install'])->name('api.bitrix.install');
// Verified by the application token inside the event, not by a user session.
Route::post('/bitrix/events', [BitrixEventController::class, 'handle'])->name('api.bitrix.events');

/*
 * The app's API, as the SPA calls it: /api/v1/...
 */
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::middleware('throttle:5,1')->group(function (): void {
        Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
    });

    // Sign-in with Bitrix24, called by the SPA (the OAuth redirect itself is in routes/web.php).
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('/auth/bitrix/exchange', [BitrixAuthController::class, 'exchange'])->name('auth.bitrix.exchange');
        Route::post('/auth/bitrix/app-session', [BitrixAuthController::class, 'appSession'])->name('auth.bitrix.app-session');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/users/options', [UserController::class, 'options'])->name('users.options');
        Route::get('/projects/options', [ProjectController::class, 'options'])->name('projects.options');

        // 1. Authentication & Profile
        Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/me', [AuthController::class, 'me'])->name('me.show');
        Route::put('/me', [AuthController::class, 'updateProfile'])->name('me.update');
        Route::put('/me/password', [AuthController::class, 'updatePassword'])->name('me.password.update');

        // 2. User & User Roles Management
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        // No delete: Bitrix24 can't delete users over REST, so they are deactivated.
        Route::patch('/users/{user}/active', [UserController::class, 'setActive'])->name('users.active.update');

        Route::get('/users/{user}/roles', [UserRoleController::class, 'index'])->name('users.roles.index');
        Route::put('/users/{user}/roles', [UserRoleController::class, 'syncRoles'])->name('users.roles.sync');

        // 3. Roles & Permissions Management
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}', [RoleController::class, 'show'])->name('roles.show');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');

        // 4. Projects Management & Members
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::match(['post', 'patch'], '/projects/{project}/archive', [ProjectController::class, 'toggleArchive'])->name('projects.archive.toggle');

        Route::get('/projects/{project}/members', [ProjectController::class, 'members'])->name('projects.members.index');
        Route::post('/projects/{project}/members', [ProjectController::class, 'addMember'])->name('projects.members.store');
        Route::delete('/projects/{project}/members/{user}', [ProjectController::class, 'removeMember'])->name('projects.members.destroy');

        // 5. Tasks Management
        Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
        Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::get('/projects/{project}/tasks', [TaskController::class, 'indexByProject'])->name('projects.tasks.index');
        Route::post('/projects/{project}/tasks', [TaskController::class, 'storeByProject'])->name('projects.tasks.store');

        Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
        Route::put('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status.update');
        Route::put('/tasks/{task}/members', [TaskMemberController::class, 'update'])->name('tasks.members.update');
        Route::post('/tasks/{task}/watch', [TaskMemberController::class, 'watch'])->name('tasks.watch.store');
        Route::delete('/tasks/{task}/watch', [TaskMemberController::class, 'unwatch'])->name('tasks.watch.destroy');
        Route::post('/tasks/{task}/delegate', [TaskMemberController::class, 'delegate'])->name('tasks.delegate');
        Route::get('/tasks/{task}/checklist', [TaskChecklistController::class, 'index'])->name('tasks.checklist.index');
        Route::post('/tasks/{task}/checklist', [TaskChecklistController::class, 'store'])->name('tasks.checklist.store');
        Route::patch('/checklist-items/{item}', [TaskChecklistController::class, 'update'])->name('checklist-items.update');
        Route::delete('/checklist-items/{item}', [TaskChecklistController::class, 'destroy'])->name('checklist-items.destroy');
        Route::post('/checklist-items/{item}/move', [TaskChecklistController::class, 'move'])->name('checklist-items.move');
        Route::get('/tasks/{task}/files', [TaskFileController::class, 'index'])->name('tasks.files.index');
        Route::post('/tasks/{task}/files', [TaskFileController::class, 'store'])->name('tasks.files.store');
        Route::delete('/task-files/{file}', [TaskFileController::class, 'destroy'])->name('task-files.destroy');
        Route::get('/task-files/{file}/download', [TaskFileController::class, 'download'])->name('task-files.download');
        Route::get('/tasks/{task}/history', [TaskHistoryController::class, 'index'])->name('tasks.history.index');
        Route::get('/tasks/{task}/results', [TaskResultController::class, 'index'])->name('tasks.results.index');
        Route::post('/tasks/{task}/results', [TaskResultController::class, 'store'])->name('tasks.results.store');
        Route::put('/task-results/{result}', [TaskResultController::class, 'update'])->name('task-results.update');
        Route::delete('/task-results/{result}', [TaskResultController::class, 'destroy'])->name('task-results.destroy');
        Route::get('/tasks/{task}/time', [TaskTimeController::class, 'index'])->name('tasks.time.index');
        Route::post('/tasks/{task}/time', [TaskTimeController::class, 'store'])->name('tasks.time.store');
        Route::put('/time-entries/{entry}', [TaskTimeController::class, 'update'])->name('time-entries.update');
        Route::delete('/time-entries/{entry}', [TaskTimeController::class, 'destroy'])->name('time-entries.destroy');
        Route::post('/tasks/{task}/timer', [TaskTimeController::class, 'start'])->name('tasks.timer.start');
        Route::post('/tasks/{task}/timer/stop', [TaskTimeController::class, 'stop'])->name('tasks.timer.stop');

        Route::get('/task-statuses', [TaskStatusController::class, 'index'])->name('task-statuses.index');
        Route::post('/task-statuses', [TaskStatusController::class, 'store'])->name('task-statuses.store');
        Route::put('/task-statuses/{taskStatus}', [TaskStatusController::class, 'update'])->name('task-statuses.update');
        Route::delete('/task-statuses/{taskStatus}', [TaskStatusController::class, 'destroy'])->name('task-statuses.destroy');

        Route::get('/task-priorities', [TaskPriorityController::class, 'index'])->name('task-priorities.index');
        Route::post('/task-priorities', [TaskPriorityController::class, 'store'])->name('task-priorities.store');
        Route::put('/task-priorities/{taskPriority}', [TaskPriorityController::class, 'update'])->name('task-priorities.update');
        Route::delete('/task-priorities/{taskPriority}', [TaskPriorityController::class, 'destroy'])->name('task-priorities.destroy');

        // 6. Task chat (Bitrix24 step 4.7; replaced comments)
        Route::get('/tasks/{task}/chat', [TaskChatController::class, 'index'])->name('tasks.chat.index');
        Route::post('/tasks/{task}/chat', [TaskChatController::class, 'store'])->name('tasks.chat.store');
        Route::put('/task-chat-messages/{message}', [TaskChatController::class, 'update'])->name('task-chat-messages.update');
        Route::delete('/task-chat-messages/{message}', [TaskChatController::class, 'destroy'])->name('task-chat-messages.destroy');
        Route::get('/task-chat-messages/{message}/files/{file}/download', [TaskChatController::class, 'download'])->whereNumber('file')->name('task-chat-messages.files.download');

        // 7. Activity Logs
        Route::get('/activities', [ProjectController::class, 'allActivities'])->name('activities.index');
        Route::get('/projects/{project}/activities', [ProjectController::class, 'activities'])->name('projects.activities.index');

        // 8. Bitrix24 sync status (step 5.1)
        Route::get('/bitrix/sync', [BitrixSyncController::class, 'show'])->name('bitrix-sync.show');
        Route::post('/bitrix/sync', [BitrixSyncController::class, 'start'])->middleware('throttle:6,1')->name('bitrix-sync.start');
    });
});
