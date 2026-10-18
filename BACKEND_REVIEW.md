# Backend Code Review

**Project:** tasks-management-task (Laravel 12 + Sanctum)
**Reviewed:** `main` at commit `406d662` (after PR #29)
**Date:** 2026-10-14
**Updated:** 2026-10-18 (second pass: added #37–#44, corrected #6)

This review covers the backend only: routes, controllers, policies, models, form requests, resources, migrations, seeders and config.
Each finding has its **status** after the latest PRs, the **problem**, and **how to fix it**. The snippets show the direction, not the full fix.

Status legend: ✅ Fixed · 🟡 Partly fixed · 🔴 Still open · 🆕 New (introduced by the recent PRs)

---

## Summary

| Area                 | Fixed | Partly fixed | Still open | New |
| -------------------- | ----- | ------------ | ---------- | --- |
| Critical             | 0     | 0            | 10         | 2   |
| Bugs                 | 2     | 3            | 9          | 1   |
| Performance          | 0     | 1            | 9          | 1   |
| Design & consistency | 0     | 2            | 8          | 1   |

### Suggested order

1. **Next PR (quick, each one breaks a feature):** N1, N2, #1 (together with #38), #2, #10, #41
2. **Security PR:** #3 + #37, #4, #5, #6, #38, #39, #40
3. **Data integrity:** #8, #15, #16, #17
4. **Performance:** #20, #21, N4, #42, #43
5. **Cleanup:** Resources + FormRequests everywhere (#27, #28), naming, run Pint

---

## 🆕 New issues from the recent PRs

### N1. New project statuses don't exist in the database (500 error)

**File:** `backend/app/Http/Controllers/ProjectController.php` (store + update validation)

**Problem:** Validation now accepts `in_progress` and `on_hold`, but the migration enum is still `['active', 'completed', 'archived']`. Saving either value causes a SQL error.

**How to fix:** Add a new migration that changes the `status` column to include the new values, or change the column to `string` and control the values with a PHP enum. The validation list and the database list must always be identical.

---

### N2. The task list's admin check never matches, so everyone only sees their own tasks

**File:** `backend/app/Http/Controllers/TaskController.php` lines 35–37

**Problem:** The code checks `$user->role === 'admin'` and `$user->is_admin`. Neither exists on the User model; the project uses a `roles` relation with a role named `Administrator`. As a result:

- Admins and PMs only see tasks assigned to them.
- The `assigned_to` filter never runs.
- Members can't see other tasks in their own projects.

**How to fix:** Use the existing helper, and filter by project membership instead of by assignee:

```php
if (!$user->hasRole('Administrator')) {
    $query->whereHas('project', fn ($q) => $q->where('owner_id', $user->id)
        ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id)));
}
```

---

### N3. The `due_date_to` filter was deleted

**File:** `backend/app/Http/Controllers/TaskController.php`

**Problem:** It was removed when the `overdue` filter was added, probably by accident.

**How to fix:** Add the `due_date_to` block back next to `due_date_from`.

---

### N4. `?per_page=all` on `/users` returns the whole table

**File:** `backend/app/Http/Controllers/UserController.php`

**Problem:** There's no upper limit, so the full users table is returned in one response.

**How to fix:** Add a light endpoint such as `/users/options` that returns only `id` and `name` for dropdowns, or cap the count (for example 500).

---

### N5. Indentation got worse

**Files:** `ProjectController.php`, `RoleController.php`

**Problem:** Indentation levels are mixed, and some methods have their bodies outdented.

**How to fix:** Run `./vendor/bin/pint` once before the next PR, and add it to CI or a pre-commit hook.

---

## 🔴 Critical: broken endpoints or security holes

### 1. Routes point to controller methods that don't exist — 🔴 Still open

**File:** `backend/routes/api.php`

**Problem:**

- `PUT /me` → `AuthController::updateProfile` (does not exist)
- `PUT /me/password` → `AuthController::updatePassword` (does not exist)
- `POST /users` → `UserController::store` (does not exist)
- `GET /activities` → `ProjectController::index`, which returns projects instead of activities

Calling any of these returns a 500 error.

**How to fix:** Either write the missing methods or delete the routes. Point `/activities` to the right method:

```php
Route::get('/activities', [ProjectController::class, 'allActivities']);
```

⚠️ Fix **#38** in the same PR. `allActivities()` returns every project's logs, so pointing the route at it as-is opens a data leak.

---

### 2. File names don't match class names (breaks on Linux) — 🔴 Still open

**Files:** `backend/app/Models/project.php`, `backend/app/Models/Activitylog.php`, `AuthController.php` line 10

**Problem:** `project.php` holds class `Project`, and `Activitylog.php` holds class `ActivityLog`. Windows ignores letter case, but PSR-4 autoloading on a Linux server does not, so you'll get "Class not found" errors after deploying. `use Illuminate\support\Facades\Hash` (lowercase `support`) has the same risk.

**How to fix:** Rename the files to `Project.php` and `ActivityLog.php`, then run `composer dump-autoload`. Change `Illuminate\support` to `Illuminate\Support`.

---

### 3. Users with `roles.assign` can make themselves Administrator — 🔴 Still open

**Files:** `UserRoleController::syncRoles`, `RoleController::update`, `RoleController::syncPermissions`

**Problem:**

- A non-admin with `roles.assign` can assign the `Administrator` role, including to their own account.
- A user with `roles.update` can add every permission to their own role.
- The `Administrator` role can be renamed, which silently breaks every `hasRole('Administrator')` check. Renaming can also be used to take over the admin role. See **#37**.

**How to fix:** Only an Administrator should be able to give or remove the Administrator role, and system roles shouldn't be renameable.

```php
if (in_array($adminRole->id, $request->roles) && !$request->user()->hasRole('Administrator')) {
    abort(403);
}
```

---

### 4. `TaskPolicy::before` lets Project Managers into every project — 🔴 Still open

**File:** `backend/app/Policies/TaskPolicy.php`

**Problem:** `before()` returns `true` for anyone with `projects.update`, so every PM can view, edit and delete tasks in projects they aren't a member of.

**How to fix:** Remove `hasPermission('projects.update')` from `before()`. Keep only the admin check there, or delete `before()` completely since `Gate::before` in `AppServiceProvider` already handles admins. Each policy method should then check project membership.

---

### 5. `GET /users/{user}/roles` has no authorization — 🔴 Still open

**File:** `backend/app/Http/Controllers/UserRoleController.php` (`index`)

**Problem:** Any logged-in user can read anyone's roles.

**How to fix:** Add `$this->authorize('view', $user);` at the top of the method.

---

### 6. No rate limit, tokens never expire, CORS open to all — 🔴 Still open

**Files:** `routes/api.php`, `config/sanctum.php`, `config/cors.php`

**Problem:** Login and register can be brute-forced, and tokens are valid forever. These two are the real risk. CORS allows every origin (`allowed_origins: ['*']`), but since the API uses Bearer tokens and `supports_credentials` is `false`, browsers never attach credentials automatically, so another website can't act as a logged-in user. Restricting CORS is hardening, not an urgent fix.

**How to fix:**

```php
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});
```

- `config/sanctum.php`: set `'expiration' => 60 * 24`.
- `config/cors.php`: set `allowed_origins` to your frontend URL, read from `.env`.
- Once tokens expire, schedule cleanup too. See **#42**.

---

### 37. `roles.update` alone is enough to become Administrator — 🔴 Still open

**Files:** `RoleController::update`, `RoleController::destroy`, `UpdateRoleRequest`

**Problem:** Nothing stops a role from being renamed, and the system decides who is admin by the role's **name** (`hasRole('Administrator')`, `Gate::before`). A user with only `roles.update` can:

1. Rename the `Administrator` role to anything else (e.g. `Old Admin`).
2. Rename their own role to `Administrator`.
3. Pass every admin check and get full access.

The delete protection also matches on the name (`in_array($role->name, ['Administrator', ...])`), so renaming the `Administrator` role and then deleting it removes admin from every admin user.

**How to fix:** Lock the names of system roles, and stop relying on a name that can be changed:

```php
// RoleController::update
$systemRoles = ['Administrator', 'Project Manager', 'Member'];

if (in_array($role->name, $systemRoles) && $request->has('name') && $request->name !== $role->name) {
    abort(403, 'System roles cannot be renamed.');
}
```

Add `Rule::notIn($systemRoles)` to the `name` rule in `StoreRoleRequest` and `UpdateRoleRequest`, so no other role can take a system name. A stronger fix is an `is_system` boolean column on `roles`, checked for rename and delete instead of the name.

---

### 38. `allActivities()` returns every project's activity log to any user — 🔴 Still open

**File:** `backend/app/Http/Controllers/ProjectController.php` (`allActivities`)

**Problem:** The method only checks `authorize('viewAny', Project::class)`, which needs nothing more than `projects.view`. Every Member has that, and the query isn't scoped to the user's projects. Today the route points to `index` (see **#1**), but once #1 is fixed, every user can read the activity history of every project, including projects they aren't a member of. The seeded `activities.view` permission is never checked anywhere.

**How to fix:** Check `activities.view` and limit the query to projects the user can access (admins see everything):

```php
abort_unless($request->user()->hasPermission('activities.view'), 403);

$query = ActivityLog::with('user:id,name,email')->latest();

if (!$request->user()->hasRole('Administrator')) {
    $query->whereHas('project', fn ($q) => $q->where('owner_id', $request->user()->id)
        ->orWhereHas('members', fn ($m) => $m->where('users.id', $request->user()->id)));
}
```

---

### 39. Debug mode turns the 500 errors into data leaks — 🔴 Still open

**Files:** `backend/.env.example`, `.env` on the server

**Problem:** `.env.example` ships with `APP_ENV=local` and `APP_DEBUG=true`. If the server is set up by copying it, every exception returns the full stack trace to the client. The known SQL errors (**N1**, **#8**) would then expose the SQL query, table names, database driver and file paths to any user.

**How to fix:** On every server that isn't a developer machine, set:

```env
APP_ENV=production
APP_DEBUG=false
```

Mark these two in `.env.example` with a comment as values to change on deploy, and add `php artisan config:cache` to the deploy steps so a missing `.env` value can't silently enable debug.

---

### 40. Comment permissions are never checked when reading comments — 🔴 Still open

**Files:** `CommentController::index`, `TaskController::show`, `CommentPolicy::view`

**Problem:** `CommentController::index` authorizes `view` on the **task**, and `TaskController::show` loads all comments with the task. Neither one checks `comments.view`, and `CommentPolicy::view` is never called. A role that has `tasks.view` but not `comments.view` can still read every comment, so taking `comments.view` away from a role has no effect.

**How to fix:** Check the comment permission in both places:

```php
// CommentController::index
$this->authorize('view', $task);
abort_unless($request->user()->hasPermission('comments.view'), 403);

// TaskController::show: only load comments if the user may see them
$relations = ['project:id,title', 'assignee:id,name,email'];
if ($request->user()->hasPermission('comments.view')) {
    $relations[] = 'comments.user:id,name,email';
}
```

Alternatively, add a `viewAny(User $user, Task $task)` method to `CommentPolicy` and call `$this->authorize('viewAny', [Comment::class, $task])`.

---

## 🟠 Bugs: logic errors

### 7. Each task was created twice — ✅ Fixed

**File:** `TaskController::store`

**Status:** The duplicate is now commented out.

**Remaining advice:** Delete the commented line (`//$task = Task::create(...)`) instead of keeping it.

---

### 8. Status/priority values in the database don't match validation — 🟡 Partly fixed

**Files:** tasks migration, `TaskController`

**Problem:** `urgent` is now allowed ✅, but the status is still sent as `review` while the DB enum has `in_review`. Saving `review` still fails with a SQL error.

**How to fix:** Pick one list and use it everywhere, ideally a PHP enum:

```php
enum TaskStatus: string {
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Completed = 'completed';
}
// validation: Rule::enum(TaskStatus::class)
```

Then make the migration match it.

---

### 9. Members got 403 on `GET /tasks` — 🟡 Partly fixed

**File:** `TaskPolicy`, `TaskController::index`

**Status:** `viewAny()` was added ✅.

**Remaining:** The list is now filtered incorrectly. See **N2**.

---

### 10. `updateStatus` checks the wrong policy method — 🔴 Still open

**File:** `TaskController::updateStatus`

**Problem:** It calls `authorize('update')` instead of `'updateStatus'`, so assignees with `tasks.change_status` can't change the status of their own tasks.

**How to fix:** `$this->authorize('updateStatus', $task);`

---

### 11. Task priority couldn't be updated — ✅ Fixed

`priority` is now in the `update` validation rules.

---

### 12. Soft-deleting a project crashes its tasks — 🔴 Still open

**Files:** `Project` model, `TaskPolicy::isProjectMember`

**Problem:** After a project is soft-deleted, `$task->project` is `null`, so `->owner_id` crashes. The project's tasks are also never soft-deleted along with it.

**How to fix:** Soft-delete the tasks when the project is deleted, and use null-safe access in the policy:

```php
// Project model
protected static function booted(): void
{
    static::deleting(fn ($project) => $project->tasks()->delete());
}

// TaskPolicy
$task->project?->owner_id
```

---

### 13. Task due date vs. project due date fails because of the time part — 🔴 Still open

**File:** `TaskController::store` / `update`

**Problem:** The task due date is a `datetime` and the project due date is a `date`. A task due at 15:00 on the project's last day gets rejected.

**How to fix:** Compare by day with Carbon instead of `strtotime`:

```php
Carbon::parse($validated['due_date'])->startOfDay()->gt($project->due_date)
```

---

### 14. Project status can be set to `archived` through `update` — 🔴 Still open

**File:** `ProjectController::update`

**Problem:** This skips the archive permission. Changing the project due date also doesn't check whether existing tasks now fall after it.

**How to fix:** Remove `archived` from the allowed values in `update`. Archiving should only go through the archive endpoint with `authorize('archive', $project)`.

---

### 15. Permission names don't match between code and seeders — 🔴 Still open

**Files:** policies, seeders

**Problem:** Code checks `users.manage`, `tasks.manage` and `projects.edit`, but none of them are seeded. `users.manage-roles`, `permissions.assign`, `projects.manage_members`, `tasks.assign` and `activities.view` are seeded but never checked.

**How to fix:** Keep every permission name in one place (constants or an enum) and use those in both the seeder and the policies. Remove names that aren't seeded, or add them to the seeder.

---

### 16. Two seeders conflict — 🟡 Partly fixed

**Files:** `DatabaseSeeder.php`, `RolesAndPermissionsSeeder.php`

**Status:** The Dutch comments were removed ✅.

**Remaining:**

- Both seeders give the same roles different permission sets, and the second one wins.
- The admin password is `password123` in one and `Password123!` in the other.
- `User::factory()->create` fails if the seeder runs a second time.

**How to fix:** Keep roles and permissions only in `RolesAndPermissionsSeeder`. `DatabaseSeeder` should only call other seeders and add demo data. Use `firstOrCreate` for the admin user.

---

### 17. Deleting a user deletes their projects and tasks — 🔴 Still open

**Files:** projects, tasks and comments migrations

**Problem:** `owner_id`, `created_by` and `comments.user_id` use `onDelete('cascade')`.

**How to fix:** Use `->restrictOnDelete()` to block the delete, or `->nullable()->nullOnDelete()`. Soft deletes on users are better than hard deletes.

---

### 18. Two requests at the same time can remove the last admin — 🔴 Still open

**File:** `UserRoleController::syncRoles`

**How to fix:** Wrap the admin count and the `sync` in `DB::transaction()`, and use `lockForUpdate()` on the count query.

---

### 19. No transactions on multi-step writes — 🔴 Still open

**Files:** `AuthController::register`, `ProjectController::store`

**Problem:** Creating a user and then attaching a role (or a project and then a member) can fail halfway and leave partial data.

**How to fix:** Wrap each one in `DB::transaction(function () { ... });`.

---

### 41. Validation and other errors can come back as a redirect or HTML page instead of JSON — 🔴 Still open

**File:** `backend/bootstrap/app.php`

**Problem:** Only `AuthenticationException` and `AuthorizationException` are forced to JSON. For everything else, Laravel decides by the `Accept` header. If a client calls the API without `Accept: application/json`:

- A validation error returns a **302 redirect** instead of a 422 with the `errors` object.
- A missing model returns an HTML 404 page.
- A server error returns an HTML 500 page.

The frontend then can't show field errors and gets HTML where it expects JSON. It's easy to miss because tools like Postman and the axios config usually send the header.

**How to fix:** Make every `api/*` route always render JSON. One line in `withExceptions` covers all exception types:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson()
    );
    // ... existing render callbacks
})
```

---

## 🟡 Performance

### 20. Every permission check runs SQL queries — 🔴 Still open

**Files:** `User` model, all policies, `AppServiceProvider`

**Problem:** `hasRole()` and `hasPermission()` query the database on every call. One `authorize()` call runs `Gate::before`, then the policy's own `before`, then 1–3 permission queries, then membership queries: about 5–8 queries.

**How to fix:** Load the permissions once per request and keep them in memory:

```php
private ?Collection $permissionCache = null;

public function permissionNames(): Collection
{
    return $this->permissionCache ??= $this->roles()->with('permissions')->get()
        ->pluck('permissions')->flatten()->pluck('name')->unique();
}

public function hasPermission(string $name): bool
{
    return $this->permissionNames()->contains($name);
}
```

Do the same for `hasRole`, and remove the duplicate `before()` from each policy.

---

### 21. `per_page` has no limit — 🔴 Still open

**Files:** `ProjectController`, `TaskController`, `UserController`

**How to fix:** `$perPage = min(max((int) $request->query('per_page', 15), 1), 100);`

---

### 22. Lists with no pagination — 🟡 Partly fixed

**Status:** `/users` is paginated now ✅ (but see **N4**).

**Remaining:** Comments, activities and project tasks still use `->get()`. `TaskController::show` now also loads every comment.

**How to fix:** Replace `->get()` with `->paginate($perPage)`.

---

### 23. `effective_permissions` in `UserResource` runs extra queries — 🔴 Still open

**How to fix:** Use the cached `permissionNames()` from #20 instead of `getEffectivePermissions()`.

---

### 24. Missing database indexes — 🔴 Still open

**How to fix:** Add a migration with `$table->index('status'); $table->index('due_date');` on `tasks`, and `$table->index('status');` on `projects`.

---

### 25. Login looks up the user twice and uses a session guard for a token API — 🔴 Still open

**File:** `AuthController::login`

**How to fix:**

```php
$user = User::where('email', $request->email)->first();

if (!$user || !Hash::check($request->password, $user->password)) {
    return response()->json(['message' => 'Invalid login credentials'], 401);
}
```

---

### 26. The password is hashed manually although the model already does it — 🔴 Still open

**File:** `AuthController::register`

**How to fix:** Pass `'password' => $validated['password']` and let the `hashed` cast handle it.

---

### 42. Access tokens are never cleaned up — 🔴 Still open

**Files:** `AuthController::login`, `AuthController::logout`, `routes/console.php`

**Problem:** Every login creates a new token, and logout only deletes the current one. Tokens from other devices or sessions that were never logged out stay forever (see **#6**: no expiration). The `personal_access_tokens` table grows with every login, and each authenticated request looks a token up in it. Old tokens also stay valid, so a leaked token never stops working.

**How to fix:** After setting `expiration` in `config/sanctum.php` (#6), prune expired tokens on a schedule:

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily();
```

Make sure the scheduler runs on the server (`php artisan schedule:run` every minute via cron). Optionally, delete the user's older tokens on login if one active session per user is enough.

---

### 43. The project list loads every member of every project — 🔴 Still open

**File:** `backend/app/Http/Controllers/ProjectController.php` (`index`, line 61)

**Problem:** `index` eager-loads `members:id,name,email` for every project on the page. A page of 15 projects with 30 members each returns 450 user rows the list view doesn't need, and the response grows with team size.

**How to fix:** Use a count in the list, and keep the full member list for `show` and `getMembers`:

```php
$projects = $query->with('owner:id,name,email')
    ->withCount(['members', 'tasks'])
    ->paginate($perPage);
```

If the list shows member avatars, load a few with a limited query or return only the count plus the first few.

---

### 44. Search uses `LIKE '%term%'`, which can't use indexes — 🔴 Still open

**Files:** `UserController::index`, `ProjectController::index`, `TaskController::index`

**Problem:** All three search with a leading `%`, so the database scans the whole table on every search. That's fine at the current size but gets slow as the tables grow. User input also isn't escaped, so a search for `%` or `_` acts as a wildcard and returns unexpected rows.

**How to fix:** Escape the wildcard characters now:

```php
$search = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $request->query('search'));
$query->where('title', 'like', "%{$search}%");
```

When the tables get large, switch to a prefix search (`"{$search}%"`, which can use an index) or a MySQL full-text index (`$table->fullText(['title', 'description'])` with `whereFullText`).

---

## 🔵 Design & consistency

### 27. Each endpoint returns a different response shape — 🟡 Partly fixed

**Status:** `data` was added next to the old keys (`roles` + `data`, `permissions` + `data`). That's fine for a migration period.

**How to fix:** Return Resources everywhere: `ProjectResource::collection($projects)` or `new TaskResource($task)`. Paginated collections add `meta` and `links` automatically. Remove the old keys once the frontend only reads `data`.

---

### 28. Validation sits inside controllers — 🟡 Partly fixed

**Status:** `RoleController::store` now validates `description` and `permissions` ✅, but it still uses inline rules.

**How to fix:** Make a FormRequest per action (`StoreTaskRequest`, `UpdateTaskRequest`, `StoreProjectRequest`, ...) and use `$request->validated()`. Use the existing `StoreRoleRequest`.

---

### 29. The same logic is copied in several places — 🔴 Still open

**How to fix:**

- Assignee-is-a-member check → a custom rule `new ProjectMember($project)`
- `is_overdue` → an accessor on `Task` added to `$appends`
- `completed_at` → keep it only in the model hook (add a `creating` hook too) and remove it from the controllers
- Membership check → `Project::hasMember(User $user): bool`, used everywhere

---

### 30. Some controllers check permissions by hand — 🔴 Still open

**Files:** `PermissionController`, `UserRoleController`

**Problem:** `UserPolicy::manageRoles` and `ProjectPolicy::archive` exist but are never called.

**How to fix:** Replace the manual `hasPermission` checks with `$this->authorize(...)`. Create a `PermissionPolicy` if needed.

---

### 31. Activity logging is patchy — 🔴 Still open

**File:** `ProjectController`

**Problem:** Only member add/remove is logged, and `class_exists(\App\Models\ActivityLog::class)` is pointless.

**How to fix:** Create `ProjectObserver` and `TaskObserver` (or Events + Listeners) that log created, updated and deleted. Use a normal `use App\Models\ActivityLog;` import.

---

### 32. Projects accept both `name` and `title` — 🔴 Still open

**How to fix:** Accept only `title`, and update the frontend to send `title`.

---

### 33. Comments are validated before authorization — 🔴 Still open

**File:** `CommentController::store`

**How to fix:** Call `authorize()` first, then validate. Add `'body' => ['required', 'string', 'max:5000']`.

---

### 34. `removeMember(int $userId)` works only by parameter order — 🔴 Still open

**How to fix:** Change it to `removeMember(Project $project, User $user)` to use model binding and get a 404 for unknown users.

---

### 35. The pivot `role` column is never used — 🔴 Still open

**File:** `project_user` migration, `Project::members()`

**How to fix:** Add `->withPivot('role')` to `members()`, or drop the column.

---

### 36. No tests — 🔴 Still open

**How to fix:** Write Feature tests for login, the main policy rules (member vs. PM vs. admin), and task create/update. Start with the bugs in this document so they can't come back.

---

## 🟣 Naming — 🔴 Still open

| Current                                           | Rename to                                            |
| ------------------------------------------------- | ---------------------------------------------------- |
| `project.php`, `Activitylog.php`                  | `Project.php`, `ActivityLog.php`                     |
| `create_activitylogs_table` migration             | `create_activity_logs_table`                         |
| `ProjectController::getMembers`                   | `members`                                            |
| `ProjectController::archive` (toggles on and off) | `toggleArchive`, or separate `archive` / `unarchive` |
| `ProjectPolicy::isMember` (also counts the owner) | `canAccess`                                          |
| `User::projects()`                                | `memberProjects()` + add `ownedProjects()`           |
| `$newRolesExcludeAdmin`                           | `$isRevokingAdmin`                                   |
| `users.manage-roles`                              | `users.manage_roles`                                 |

Also add return types to `User::projects()`, `User::assignedTasks()` and `ActivityLog::subject()`, and run `./vendor/bin/pint` to fix formatting across all files.
