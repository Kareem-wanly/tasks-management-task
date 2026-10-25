# Backend Code Review — Round 3 (Final)

**Project:** tasks-management-task (Laravel 12 + Sanctum)
**Reviewed:** `origin/fix/round2-all` (`09829eb`), which contains `main` (PR #39) plus the R19–R27 cleanup and tests
**Date:** 2026-10-25
**Previous reviews:** [BACKEND_REVIEW.md](BACKEND_REVIEW.md), [BACKEND_REVIEW_2.md](BACKEND_REVIEW_2.md)

This is the last backend review before moving on to the frontend. It lists only what is still open after round 2: one regression introduced by the fixes, a few small leftovers, and the backend changes the frontend now has to follow.

Every item marked **(reproduced)** was confirmed by running a request against the branch, not only by reading it.

Severity legend: 🔴 Critical · 🟠 High · 🟡 Medium · 🔵 Low / cleanup

---

## Summary

| Area                       | Result                                                      |
| -------------------------- | ----------------------------------------------------------- |
| Round 2 items R1–R18       | **All fixed**, each covered by a test in `Round2ReviewTest` |
| Round 2 items R19–R27      | R19, R20, R24 fixed · R21–R23, R25–R27 partly done          |
| Test suite                 | **27/27 pass** on `fix/round2-all` (5 on `main`)            |
| `./vendor/bin/pint --test` | Passes on `fix/round2-all` (fails on 25 files on `main`)    |
| New issues                 | 1 High (F1), 2 Medium, 5 Low                                |

### Branches

| Branch                                  | Action                                                                                                                                                                            |
| --------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `fix/round2-all`                        | **Merge into** `main` after F1 is fixed. It is the complete version.                                                                                                              |
| `refactor/round2-cleanup-and-standards` | **Close without merging.** Its only commit has no parent, so it shares no history with `main`; compared with `main` it deletes the whole `frontend/` folder and the review files. |

### Suggested order

1. **Before the frontend starts (one small PR on top of** `fix/round2-all`**):** F1, F4, F5, then merge into `main`.
2. **Can run in parallel with frontend work:** F2, F3, F6–F10.
3. **Done together with the frontend:** the [Frontend handoff](#frontend-handoff) section.

---

## 🟠 High

### F1. Administrators can be deleted, and users can delete themselves

**File:** `backend/app/Http/Controllers/UserController.php` (`destroy`), `backend/app/Policies/UserPolicy.php` (`delete`)
**From:** R11 (regression)

**Problem:** The R11 fix replaced the old guard (`Administrator users cannot be deleted directly`, 403) with the project-owner check (409) instead of adding the check next to it. `UserPolicy::delete` only checks `users.delete`. A user whose role has only `users.view` + `users.delete` can:

- delete an Administrator who owns no projects → **200** **(reproduced)**
- delete their own account → **200** **(reproduced)**

The seeded admin is only protected by accident, because they own projects (409).

**How to fix:** Keep the 409 check and add both guards back in front of it. Put them in the controller, not the policy, because `Gate::before` lets admins skip the policy:

```php
public function destroy(Request $request, User $user): JsonResponse
{
    $this->authorize('delete', $user);

    abort_if($user->is($request->user()), 403, 'You cannot delete your own account.');
    abort_if(
        $user->hasRole('Administrator') && ! $request->user()->hasRole('Administrator'),
        403,
        'Only an Administrator can delete another Administrator.'
    );

    if (Project::withTrashed()->where('owner_id', $user->id)->exists()) {
        // existing 409 response
    }
    ...
}
```

Add a test for each of the two cases to `Round2ReviewTest`.

---

## 🟡 Medium

### F2. Renaming `users.manage-roles` leaves a duplicate in existing databases

**File:** `backend/database/seeders/RolesAndPermissionsSeeder.php` line 51
**From:** R25 / R27

**Problem:** The name was changed only in the seeder. The seeder uses `firstOrCreate`, so on any database that was already seeded it creates a new `users.manage_roles` row and keeps the old `users.manage-roles` row, with its role links, next to it.

**How to fix:** Add a migration that renames the row in place:

```php
public function up(): void
{
    $old = DB::table('permissions')->where('name', 'users.manage-roles')->first();
    if (! $old) {
        return;
    }

    $new = DB::table('permissions')->where('name', 'users.manage_roles')->first();
    if ($new) {
        // Seeder already created the new row: move links, drop the old one
        DB::table('permission_role')->where('permission_id', $old->id)->update(['permission_id' => $new->id]);
        DB::table('permissions')->where('id', $old->id)->delete();
    } else {
        DB::table('permissions')->where('id', $old->id)->update(['name' => 'users.manage_roles']);
    }
}
```

Check the pivot table name against the migrations before using it. If `update` would create duplicate `(role_id, permission_id)` pairs, delete those first.

---

### F3. Deleting a project skips the task observers, and restoring it brings back too much

**File:** `backend/app/Models/Project.php` (`booted`)
**From:** R6

**Problem:**

1. `$project->tasks()->delete()` is a single bulk query, so `TaskObserver::deleted` never runs and no `task_deleted` activity is logged.
2. `restoring` restores **every** trashed task of the project, including tasks that were deleted on purpose before the project was deleted.

**How to fix:**

```php
static::deleting(function (Project $project) {
    $method = $project->isForceDeleting() ? 'forceDelete' : 'delete';
    $project->tasks()->get()->each->{$method}();
});

static::restoring(function (Project $project) {
    // deleted_at is still set while "restoring" runs
    $project->tasks()->onlyTrashed()
        ->where('deleted_at', '>=', $project->deleted_at)
        ->restore();
});
```

---

## 🔵 Low / cleanup

### F4. `RoleController::update` still syncs unvalidated input

**File:** `backend/app/Http/Controllers/RoleController.php` lines 84–86
**From:** R22

`update()` reads `$validated` for the role fields but then uses `$request->has('permissions')` and `$request->permissions` for the sync. Use the validated data:

```php
if (array_key_exists('permissions', $validated)) {
    $this->guardPermissionGrant($request, $role, $validated['permissions']);
    $role->permissions()->sync($validated['permissions']);
}
```

### F5. Role write endpoints answer under `role`, the list under `data`

**File:** `RoleController::store` (line 48), `update` (line 57/91), `syncPermissions` (line 149)
**From:** R21

`index` returns `{ data }`, but `store`, `update` and `syncPermissions` return `{ message, role }`. Before round 2, `store` returned `data`, so this change is new. Return `data` everywhere (ideally through a `RoleResource`) so the frontend has one shape to read.

### F6. Membership and permission-id logic is still duplicated

**From:** R23

`Project::hasMember()` and `Project::scopeAccessibleBy()` now exist, but the old query is still copied in:

- `TaskPolicy::isProjectMember` (line 24) and `TaskPolicy::create` (line 48)
- `ProjectPolicy::canAccess` (line 13)
- `CommentPolicy::isProjectMember` (line 20)
- `ProjectController::addMember` (line 166) and `removeMember` (line 196)

Replace each with `$project->hasMember($user)`. Note that `hasMember` also counts the owner, so in `addMember` keep the "already a member" check on `members()` only.

In the same way, `User::permissionIds()` was added, but `RoleController::guardPermissionGrant` (line 103) and `UserRoleController::syncRoles` (line 47) still build the same list by hand. Call `$request->user()->permissionIds()` instead. `User::getEffectivePermissions()` (line 58) looks unused now. Delete it if nothing calls it.

### F7. Seeded permissions that nothing checks

**From:** R25

- `TaskPolicy::assign` (`tasks.assign`) was added but is never called. Either call `$this->authorize('assign', $task)` in `TaskController::update` / `storeByProject` when `assigned_to` is present, or delete the method and the permission.
- `permissions.assign` is still seeded and never checked.
- There is still no `Permission` enum or constants class. Permission names are typed as strings across the policies and the seeder. This can wait, but it is the cheapest protection against another `manage-roles` / `manage_roles` mismatch.

### F8. Review-ticket comments left in the code

**From:** R26

- `backend/routes/api.php` line 13: `// #6. No rate limit`. Wrong now as well, since the block below it does have `throttle:5,1`.
- `backend/app/Http/Controllers/RoleController.php` lines 67 and 74: `// #37. ...`

Delete them, or rewrite them to say _why_ the code exists.

### F9. Naming and return types

**From:** R27

- `User::memberProjects()` (line 78) and `ActivityLog::subject()` (line 31) still have no return type (`BelongsToMany` / `MorphTo`).
- `User::projects()` (line 85) is kept as an alias of `memberProjects()`. Switch the remaining callers and remove it.
- `create_activitylogs_table`: **leave as is.** Renaming a migration that has already run on other machines makes Laravel try to run it again. Not worth it.

### F10. Remaining inline validation and raw model responses

**From:** R21, R22

Still validated inline: `CommentController::store`/`update`, `UserController::store`/`update`, `ProjectController::addMember`, `UserRoleController::syncRoles`, `TaskController::updateStatus`. Still returning raw models or raw paginators: users, comments, activities, members, `toggleArchive`, `updateStatus`.
