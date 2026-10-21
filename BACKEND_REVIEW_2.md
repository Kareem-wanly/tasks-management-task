# Backend Code Review — Round 2

**Project:** tasks-management-task (Laravel 12 + Sanctum)
**Reviewed:** `main` after merging the fix PRs 1–6 (`fix/pr1` … `refactor/pr6`)
**Date:** 2026-10-21
**Previous review:** [BACKEND_REVIEW.md](BACKEND_REVIEW.md)

This review lists only what is **still open** after the fix PRs: issues from the first review that weren't fixed or were only partly fixed, and new issues that the fixes introduced.
Of the 50 items in the first review, **26 are fixed** and don't appear here.

Every item marked **(reproduced)** was confirmed by running a request against the merged code, not only by reading it.

Severity legend: 🔴 Critical · 🟠 High · 🟡 Medium · 🔵 Low / cleanup

---

## Summary

| Severity    | Count | Items                    |
| ----------- | ----- | ------------------------ |
| 🔴 Critical | 3     | R1, R2, R3               |
| 🟠 High     | 6     | R4, R5, R6, R7, R8, R9   |
| 🟡 Medium   | 9     | R10–R18                  |
| 🔵 Low      | 9     | R19–R27                  |

**Test suite:** 5 tests pass, but only 3 are real, and none of them cover the items below. `./vendor/bin/pint --test` fails on 13 files.

### Suggested order

1. **Hotfix PR:** R1, R2, R3, R4, R5, R6 (each is a few lines)
2. **Authorization PR:** R7, R11, R12, R13, R14
3. **Performance PR:** R8, R9
4. **Data PR:** R10, R15, R16, R17, R18
5. **Cleanup PR:** R19–R27, plus a Feature test for every item fixed above

---

## 🔴 Critical

### R1. `roles.update` alone can still give a role every permission

**File:** `backend/app/Http/Controllers/RoleController.php` lines 88–90 (`update`)
**From:** #3

**Problem:** `syncPermissions()` now refuses to grant permissions the user doesn't have, but `update()` syncs `permissions` too, with no check at all. A user whose only permission is `roles.update` can send `PUT /roles/{their role}` with every permission id and become an administrator in all but name. **(reproduced: 31/31 permissions granted)**

**How to fix:** Remove permission syncing from `update()` so `PUT /roles/{role}/permissions` is the only way in, or move the check from `syncPermissions()` into a private method and call it from both:

```php
private function guardPermissionGrant(Request $request, Role $role, array $ids): void
{
    if ($request->user()->hasRole('Administrator')) {
        return;
    }

    abort_if($role->name === 'Administrator', 403);
    abort_if(array_diff($ids, $request->user()->permissionIds()), 403,
        'You cannot grant permissions that you do not possess.');
}
```

---

### R2. Removing a project member always returns 404

**File:** `backend/app/Http/Controllers/ProjectController.php` lines 196–216 (`removeMember`)
**From:** #34

**Problem:** The parameter was changed to `User $user`, but the query still treats it like an id: `where('users.id', $user)`. That passes the model object instead of its id, so the check never matches and every request returns *"User is not a member of this project."* The activity log message also writes the whole user as JSON. The "remove member" feature is broken. **(reproduced)**

**How to fix:**

```php
if (! $project->members()->where('users.id', $user->id)->exists()) { ... }

$project->members()->detach($user->id);

'description' => "{$user->name} was removed from project '{$project->title}'.",
```

---

### R3. `ActivityLog` still can't load on Linux

**File:** `backend/app/Models/Activitylog.php`
**From:** #2

**Problem:** `project.php` was renamed, but `Activitylog.php` still holds `class ActivityLog`. Windows ignores letter case, so it works locally. On a Linux server, PSR-4 looks for `ActivityLog.php` and every endpoint that logs activity (project/task create, update, delete, member add/remove) fails with *Class not found*.

**How to fix:** On Windows, git needs two steps to change only the letter case:

```bash
git mv backend/app/Models/Activitylog.php backend/app/Models/Tmp.php
git mv backend/app/Models/Tmp.php backend/app/Models/ActivityLog.php
composer dump-autoload
```

---

## 🟠 High

### R4. Tasks can be assigned to users outside the project

**Files:** `TaskController::storeByProject` (line 172), `TaskController::update` (line 185), `StoreTaskRequest`, `UpdateTaskRequest`
**From:** #29 (regression)

**Problem:** Before the fixes, `storeByProject` called `store()`, which checked that the assignee belongs to the project, and `update` did the same check inline. Both live endpoints now use FormRequests that only check `exists:users,id`. Any user id can be assigned. **(reproduced: create → 201, update → 200)**

**How to fix:** Create the custom rule the first review suggested and use it in both requests:

```php
// app/Rules/ProjectMember.php
public function validate(string $attribute, mixed $value, Closure $fail): void
{
    if (! $this->project->hasMember($value)) {
        $fail('The assigned user must be a member of the project.');
    }
}

// StoreTaskRequest
'assigned_to' => ['nullable', 'integer', 'exists:users,id', new ProjectMember($this->route('project'))],

// UpdateTaskRequest
'assigned_to' => ['nullable', 'integer', 'exists:users,id', new ProjectMember($this->route('task')->project)],
```

---

### R5. Any project member can change any task's status

**File:** `backend/app/Policies/TaskPolicy.php` lines 57–65 (`updateStatus`)
**From:** #10

**Problem:** The controller now calls the right ability, but the policy only checks membership, and then checks it a second time. It never checks `tasks.change_status`, and `isAssignee()` is never called. A member whose role only has `tasks.view` can still change status. **(reproduced: 200)**

**How to fix:**

```php
public function updateStatus(User $user, Task $task): bool
{
    if (! $this->isProjectMember($user, $task)) {
        return false;
    }

    return $task->project->owner_id === $user->id
        || $user->hasPermission('tasks.update')
        || ($user->hasPermission('tasks.change_status') && $this->isAssignee($user, $task));
}
```

---

### R6. Tasks of a soft-deleted project return 500

**Files:** `backend/app/Models/Project.php`, `backend/app/Policies/TaskPolicy.php` lines 27, 52, 63, 73
**From:** #12 (still open)

**Problem:** After a project is soft-deleted, its tasks stay active and `$task->project` is `null`, so `$task->project->owner_id` crashes. **(reproduced: `GET /tasks/{id}` → 500, task not trashed)**

**How to fix:** Both parts from the first review are still needed:

```php
// Project model
protected static function booted(): void
{
    static::deleting(fn (Project $project) => $project->tasks()->delete());
    static::restoring(fn (Project $project) => $project->tasks()->withTrashed()->restore());
}

// TaskPolicy::isProjectMember
$project = $task->project;
if (! $project) {
    return false;
}
```

---

### R7. A user with `roles.assign` can give themselves any non-admin role

**File:** `backend/app/Http/Controllers/UserRoleController.php` (`syncRoles`)
**From:** #3

**Problem:** The Administrator role is protected now. Any other role, including *Project Manager* or a custom role with `roles.update`, can be assigned by anyone with `roles.assign`, including to their own account. Combined with R1, that leads straight to full access. **(reproduced: 200)**

**How to fix:** Apply the same rule as `syncPermissions`: a non-admin may only assign roles whose permissions they already have, and may not change their own roles.

```php
abort_if($user->is($request->user()) && ! $request->user()->hasRole('Administrator'), 403);

$requested = Permission::whereHas('roles', fn ($q) => $q->whereIn('roles.id', $request->roles))->pluck('id');
abort_if($requested->diff($request->user()->permissionIds())->isNotEmpty(), 403);
```

---

### R8. The permission cache is never used, and it breaks `save()`

**File:** `backend/app/Models/User.php` lines 36–75
**From:** #20

**Problem:** Two separate issues:

1. `hasPermission()` and `hasRole()` still query the database on every call. Only `UserResource` uses `permissionNames()`. Two `hasPermission` calls plus one `hasRole` run **4 queries**. The duplicate `before()` in every policy is still there too, so admins are looked up twice per `authorize()`. **(reproduced)**
2. `$this->permissionsCache ??= ...` isn't a declared property, so Eloquent stores it as a **model attribute**. After `permissionNames()` runs, any `$user->save()` fails with `no such column: permissionsCache`, and the value also appears in `$user->toArray()`. **(reproduced)**

**How to fix:**

```php
protected ?Collection $permissionCache = null;
protected ?Collection $roleCache = null;

public function permissionNames(): Collection
{
    return $this->permissionCache ??= $this->roles()->with('permissions:id,name')->get()
        ->pluck('permissions')->flatten()->pluck('name')->unique()->values();
}

public function hasPermission(string $name): bool
{
    return $this->permissionNames()->contains($name);
}

public function hasRole(string $name): bool
{
    return ($this->roleCache ??= $this->roles()->pluck('name'))->contains($name);
}
```

Then delete `before()` from `TaskPolicy`, `ProjectPolicy`, `CommentPolicy`, `RolePolicy`, `UserPolicy` and `PermissionPolicy`, since `Gate::before` in `AppServiceProvider` already lets admins through. Clear the caches in `UserRoleController::syncRoles` after `sync()`.

---

### R9. `effective_permissions` runs one query per user and exposes everyone's permissions

**File:** `backend/app/Http/Resources/UserResource.php` line 19
**From:** #23 (made worse)

**Problem:** `UserResource` now always calls `permissionNames()`. It's used for project owners, project members, task assignees and comment authors, so every nested user costs a query. `GET /projects/{id}` with 21 members ran **49 queries**. Every member's full permission list is also sent to anyone who can view the project. **(reproduced)**

**How to fix:** Only include permissions when the caller explicitly asks for them (`/me`, login, register):

```php
'effective_permissions' => $this->when(
    $request->routeIs('me', 'login', 'register'),
    fn () => $this->resource->permissionNames()->values()
),
```

Alternatively, add a separate `AuthUserResource` for those three endpoints and keep `UserResource` to `id`, `name`, `email`.

---

## 🟡 Medium

### R10. The `due_date_from` filter was deleted

**File:** `backend/app/Http/Controllers/TaskController.php` lines 57–63
**From:** N3

**Problem:** `due_date_to` was added back, but `due_date_from` was removed. Requests with `due_date_from` are now silently ignored. **(reproduced)**

**How to fix:** Add it back next to `due_date_to`:

```php
if ($request->filled('due_date_from')) {
    $query->whereDate('due_date', '>=', $request->query('due_date_from'));
}
```

---

### R11. Deleting a user who owns a project returns 500

**File:** `backend/app/Http/Controllers/UserController.php` line 98
**From:** #17

**Problem:** The new migration correctly sets `projects.owner_id` to `restrictOnDelete`, but `destroy()` doesn't handle the foreign-key error. The client gets a 500. Soft-deleted projects still block the delete too. **(reproduced)**

**How to fix:** Check first and return a clear error:

```php
if (Project::withTrashed()->where('owner_id', $user->id)->exists()) {
    return response()->json([
        'message' => 'Transfer or delete this user\'s projects before deleting the user.',
    ], 409);
}
```

---

### R12. `GET /users/{user}/roles` is admin-only by accident

**File:** `backend/app/Http/Controllers/UserRoleController.php` lines 18–19
**From:** #5

**Problem:** It authorizes `viewRoles`, which doesn't exist in `UserPolicy`. A missing ability is denied, so every non-admin gets 403, even when reading their own roles. The comment above the line describes a different fix (`view`). **(reproduced)**

**How to fix:** Add the ability and remove the comment:

```php
// UserPolicy
public function viewRoles(User $currentUser, User $user): bool
{
    return $currentUser->is($user) || $currentUser->hasPermission('users.view');
}
```

---

### R13. Project Managers and Members can't open `/activities`

**File:** `backend/database/seeders/RolesAndPermissionsSeeder.php` lines 68–81
**From:** #38

**Problem:** The fix correctly checks `activities.view` and scopes the query. However, the seeder only gives `activities.view` to Administrator, so PMs and Members always get 403. **(reproduced)**

**How to fix:** Decide who should see activity. If PMs should, add `'activities.view'` to `$pmPermissionNames` (and to `$memberPermissionNames` if Members should too). The query is already limited to the user's own projects.

---

### R14. Task creation checks `projects.update` instead of `tasks.create`

**File:** `backend/app/Http/Controllers/TaskController.php` line 174
**From:** #28 (regression)

**Problem:** `storeByProject` authorizes `update` on the project. The `tasks.create` permission is no longer checked anywhere on a live route. A role with `tasks.create` but not `projects.update` can't create tasks, and the reverse is allowed.

**How to fix:**

```php
$this->authorize('create', [Task::class, $project]);

// TaskPolicy
public function create(User $user, Project $project): bool
{
    return $user->hasPermission('tasks.create') && $project->hasMember($user);
}
```

---

### R15. `completed_at` stays empty when a task is created as completed

**Files:** `backend/app/Models/Task.php` line 48, `TaskController::updateStatus` lines 203–215
**From:** #29

**Problem:** The model only has an `updating` hook. Creating a task with `status: completed` leaves `completed_at` as `NULL`. **(reproduced)** `updateStatus()` also still sets `completed_at` by hand, which duplicates the hook.

**How to fix:** Move the logic into a `saving` hook, which runs for both create and update, and delete lines 203–215 from the controller:

```php
static::saving(function (Task $task) {
    if ($task->isDirty('status') || ! $task->exists) {
        $task->completed_at = $task->status === 'completed' ? ($task->completed_at ?? now()) : null;
    }
});
```

---

### R16. `per_page` limit doesn't match what the frontend asks for

**Files:** `ProjectController`, `TaskController`, `UserController` line 36
**From:** N4

**Problem:**

- The frontend requests `per_page: 150` in `DashboardPage`, `TasksPage` and `TaskFormModal`. The backend now caps at 100, so dashboard totals and dropdowns are silently wrong once there are more than 100 items. **(reproduced)**
- `?per_page=all` now becomes `(int) 'all' = 0` and returns **1** row.
- The lightweight `/users/options` endpoint was never added.

**How to fix:**

- Add `GET /users/options` and `GET /projects/options` that return only `id` and `name`/`title` (no pagination, but with a hard limit such as 1000) for dropdowns.
- Compute dashboard numbers on the server, for example a `GET /dashboard/stats` that returns counts, instead of downloading lists.
- Return 422 for a non-numeric `per_page`.

---

### R17. The task status migration doesn't convert existing rows

**File:** `backend/database/migrations/2026_10_20_150556_update_status_enum_in_tasks_table.php`
**From:** #8

**Problem:** It changes the enum from `in_review` to `review` without updating existing data. On MySQL in strict mode, the `ALTER` fails if any row still has `in_review`. Without strict mode, those rows become empty strings.

**How to fix:** Widen the enum, convert the data, then narrow it:

```php
$table->enum('status', ['todo', 'in_progress', 'in_review', 'review', 'completed'])->change();
DB::table('tasks')->where('status', 'in_review')->update(['status' => 'review']);
$table->enum('status', ['todo', 'in_progress', 'review', 'completed'])->default('todo')->change();
```

---

### R18. Changing the project due date doesn't check existing tasks

**File:** `ProjectController::update`
**From:** #14 (second half)

**Problem:** A task due date can't be later than the project due date when the task is saved, but moving the project's due date earlier leaves existing tasks past it.

**How to fix:** In `UpdateProjectRequest::withValidator`, reject a `due_date` earlier than the latest unfinished task's `due_date`, or return the list of affected tasks so the user can confirm.

---

## 🔵 Low / cleanup

### R19. Projects still accept both `name` and `title` — #32

`StoreProjectRequest` line 18 and `UpdateProjectRequest` line 18 still accept `name`. Accept only `title`, update the frontend, and remove the `name` → `title` mapping in `ProjectController::store` and `update`. Also, `title` is `nullable` in `UpdateProjectRequest`, so sending `"title": null` causes a SQL error. Use `['sometimes', 'required', 'string', 'max:255']`.

### R20. Comments are validated before authorization — #33

In `CommentController::store`, `validate()` (line 43) runs before `authorize()` (line 47). Swap them, and add `'max:5000'` to `body` in `store` and `update`.

### R21. Responses still come in several shapes — #27

- Only projects and tasks use Resources.
- Users, roles, permissions, comments, activities, members, `archive`, `updateStatus`, `store` (users) and `updateProfile` still return raw models.
- `GET /tasks` returns `{ data, links, meta }`, while `GET /projects/{id}/tasks` and `GET /users` return the raw paginator `{ data, current_page, last_page, ... }`.
- Roles and permissions still return each list twice (`roles` + `data`).

Return a Resource from every endpoint and remove the duplicate keys once the frontend reads only `data`.

### R22. FormRequests are only partly adopted — #28

- `StoreRoleRequest` exists but `RoleController::store` still validates inline.
- `RoleController::update` uses `$request->only()` instead of `$request->validated()`.
- Comments, users, `addMember`, `syncRoles` and `updateStatus` validate inline.

### R23. Membership logic is still duplicated — #29

The "owner or member" query is copied in 4 policies and 3 controllers. Add `Project::hasMember(User|int $user): bool` and a `Project::scopeAccessibleBy(User $user)` query scope, and use them everywhere.

### R24. Manual permission checks — #30

`UserController::store` (line 45), `ProjectController::allActivities` (line 269) and `CommentController::index` (line 19) still call `hasPermission()` directly. Use `$this->authorize(...)` with policy methods (`UserPolicy::create`, an `ActivityLogPolicy::viewAny`, `CommentPolicy::viewAny`).

### R25. Permission names — #15

- Seeded but never checked: `permissions.assign`, `projects.manage_members`, `tasks.assign`, `users.manage-roles` (and `tasks.change_status`, until R5 is fixed).
- Names are still written as strings in ~40 places.

Add a `Permission` enum or class constants, use it in the seeder and the policies, and either use or remove the unused names.

### R26. Dead code, leftover comments and formatting — N5, #7

- `TaskController::store` (lines 113–170) has no route. It still has the old `strtotime` check and the commented-out `Task::create` line. Delete it.
- Review-ticket comments were left in the code: `// #4.`, `// #5.` (which describes a different fix than the code below it), `// #37.` and `// fix:`. Comments should explain *why*, not refer to review items.
- `config/sanctum.php` line 53: `60 * 24` is **1 day**, not "60 days" as the comment says.
- `./vendor/bin/pint --test` fails on 13 files. Run Pint and add it to CI or a pre-commit hook.
- `UserController::store` creates a user with **no role**. Attach `Member` by default, inside a transaction, like `register`.
- `.env.example` now defaults to `APP_ENV=production` / `APP_DEBUG=false`, which makes local setup harder (`migrate` asks for confirmation). Keep local values in `.env.example` and set production values in the deploy steps.

### R27. Naming and tests — Naming, #36

Still open from the first review's naming table:

| Current                                           | Rename to                                            |
| ------------------------------------------------- | ---------------------------------------------------- |
| `Activitylog.php`                                 | `ActivityLog.php` (see R3)                           |
| `create_activitylogs_table` migration             | `create_activity_logs_table`                         |
| `ProjectController::getMembers`                   | `members`                                            |
| `ProjectController::archive` (toggles on and off) | `toggleArchive`, or separate `archive` / `unarchive` |
| `User::projects()`                                | `memberProjects()`                                   |
| `users.manage-roles`                              | `users.manage_roles`                                 |

Add return types to `User::projects()` and `ActivityLog::subject()`.

**Tests:** there are 3 real Feature tests (register, login, non-admin can't assign Administrator). Add one Feature test for each item R1–R18 first, since each one is a bug that has already reached `main` once.
