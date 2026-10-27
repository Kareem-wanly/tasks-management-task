# Frontend Code Review

**Project:** `frontend/` (React 19 + Vite 8 + React Router 7)
**Reviewed:** `main` at `ec083a6` (after backend PR #41), assuming the open items in [BACKEND_REVIEW_3.md](BACKEND_REVIEW_3.md) are done
**Date:** 2026-10-25 (round 2; first review 2026-10-18 at `406d662`)

The frontend code has **not changed** since the first review (the last frontend commit is PR #29). The backend has changed a lot, so I re-read every file in `frontend/src` against the **current** API instead of only re-checking the old list. Each item now has a status:

- 🔴 **Open**: still broken, same as in round 1
- 🟡 **Partly**: the backend side is done, the frontend side is not
- 🆕 **New**: new in this round, mostly because the backend API changed under the frontend
- ⚪ **Withdrawn**: the round 1 finding was wrong

Items keep their round 1 numbers (F1–F21). New items start at F22. Backend references: **#n** / **Nn** are in [BACKEND_REVIEW.md](BACKEND_REVIEW.md), **Rn** in [BACKEND_REVIEW_2.md](BACKEND_REVIEW_2.md), and **B-Fn** in [BACKEND_REVIEW_3.md](BACKEND_REVIEW_3.md).

Checked as part of the review: `npm run build` passes (one 361 kB JS chunk, 102 kB gzipped). `oxlint` reports 8 warnings and no errors. There are no tests.

---

## Summary

| Status       | Items                                                                   |
| ------------ | ----------------------------------------------------------------------- |
| 🆕 New       | F22, F23, F24, F25, F26, F27, F28                                       |
| 🔴 Open      | F2, F3, F5, F6, F7, F8, F9, F10, F11, F12, F15, F16, F17, F18, F19, F21 |
| 🟡 Partly    | F4, F13, F14, F20                                                       |
| ⚪ Withdrawn | F1                                                                      |

| Severity    | Items                                    |
| ----------- | ---------------------------------------- |
| 🔴 Critical | F2, F3, F22, F24                         |
| 🟠 High     | F4, F6, F7, F23, F25, F26                |
| 🟡 Medium   | F5, F8, F9, F10, F11, F12, F13, F14, F27 |
| 🔵 Low      | F15–F21, F28                             |

### Suggested order

1. **API layer first (one PR):** F18, F2, F12, F3, F9. Pagination, response shapes and auth handling all live in `apiClient`, `AuthContext` and the `*Api.js` files. Fix them once there, and most page-level guessing goes away.
2. **Broken features (one PR):** F22, F24, F23, F4, F25, F26, F27
3. **Permissions (needs one small backend change, see F6):** F6, F7, F11, F17
4. **Dropdowns and dashboard (uses the new** `/options` **endpoints):** F14, F13
5. **Correctness:** F8, F10, F5
6. **Cleanup:** F16, F19, F20, F28, F15, F21

---

## 🔴 Critical

### F2. Tasks and Users pagination never goes past page 1 — 🔴 Open

**Files:** `frontend/src/pages/tasks/TasksPage.jsx` lines 257–269, `frontend/src/pages/users/UsersPage.jsx` lines 72–81

**Problem:** `apiClient` already returns the JSON body, but both pages read the paging info one level too deep:

- **Tasks:** `GET /tasks` now returns a Resource collection, `{ data, links, meta }`. The page reads `res.data?.meta || res.data`. `res.data` is the **array of rows**, so `meta` is the array and `last_page` is always 1. The paging info sits in `res.meta`, which the page never reads.
- **Users:** `GET /users` still returns a raw Laravel paginator, `{ data, current_page, last_page, ... }`. The page checks `res.data?.meta || res.data?.current_page`, which is never true for an array, so `meta` is never set and the pagination bar never appears.

Result: the **Next** button is always disabled, Tasks says "0 total tasks", and only the first page can ever be seen. Projects pagination works only because its code happens to read `res.meta`.

**How to fix:** Normalize once, in the API layer, and handle both shapes until backend R21 makes every list a Resource collection:

```js
// api/paginate.js
export function toPage(body) {
  const meta = body.meta ?? body; // Resource collection or raw paginator
  return {
    items: body.data ?? [],
    currentPage: meta.current_page ?? 1,
    lastPage: meta.last_page ?? 1,
    perPage: meta.per_page ?? 10,
    total: meta.total ?? 0,
  };
}

// tasksApi.js / usersApi.js / projectsApi.js
getAll: async (params) => toPage(await apiClient.get(`/tasks?${new URLSearchParams(params)}`)),
```

Pages then read `page.items` and `page.lastPage` and never touch the raw body.

---

### F3. An expired or revoked token leaves the user stuck in the app — 🔴 Open

**Files:** `frontend/src/api/apiClient.js` lines 64–70, `frontend/src/context/AuthContext.jsx`

**Problem:** On a 401, `apiClient` deletes the token and dispatches the window event `auth:unauthorized`, but **nothing listens for it**. `AuthContext` keeps the user, so `ProtectedRoute` keeps showing the app, and every later request fails with 401. Tokens now expire after **one day** (`config/sanctum.php`), so every user will hit this once a day.

**How to fix:** Listen for the event in `AuthProvider` and clear the session, which makes `ProtectedRoute` redirect to `/login`:

```jsx
useEffect(() => {
  const onUnauthorized = () => {
    setUser(null);
    setRoles([]);
    setPermissions([]);
    showToast("Your session expired. Please sign in again.", "info");
  };
  window.addEventListener("auth:unauthorized", onUnauthorized);
  return () => window.removeEventListener("auth:unauthorized", onUnauthorized);
}, []);
```

`ToastProvider` is currently inside `AuthProvider` in `App.jsx`. Swap their order so `AuthProvider` can call `useToast()`.

---

### F22. Editing a task unassigns it for every user without `tasks.assign` — 🆕 New

**File:** `frontend/src/pages/projects/TaskFormModal.jsx` line 132
**Cause:** backend #27 (`TaskResource` replaced the raw model)

**Problem:** When the user can't assign, the form tries to keep the current assignee:

```js
assigned_to: canAssign && formData.assigned_to
  ? Number(formData.assigned_to)
  : (isEdit ? (task.assigned_to || null) : null),
```

`TaskResource` no longer has an `assigned_to` field, only a nested `assignee` object. So `task.assigned_to` is `undefined`, the payload sends `assigned_to: null`, and the backend clears the assignee. **Members don't have** `tasks.assign`**, so every time a Member edits a task (title, description, anything), the task becomes unassigned.**

**How to fix:** Don't send fields the user didn't change, and read the id from the nested object:

```js
const payload = { title, description, status, priority, due_date };
if (canAssign) {
  payload.assigned_to = formData.assigned_to
    ? Number(formData.assigned_to)
    : null;
}
// otherwise leave assigned_to out: the backend keeps the current value
```

Also add `assigned_to` and `project_id` to `TaskResource`, since several screens still look for them (`TaskFormModal`, `DashboardPage`, `ProjectDetailsPage`).

---

### F24. User roles can't be seen or changed from the Users page — 🆕 New

**Files:** `frontend/src/pages/users/UsersPage.jsx` (Role column), `UserModal.jsx` lines 75–79, `AssignRoleModal.jsx`

**Problem:** Three separate breaks, which together make roles unmanageable from the UI:

1. The **Role** column reads `u.role?.display_name`. The backend sends `roles` (an array), so every row shows **"No Role"**.
2. **Edit user** sends `role_id`. `UserController::update` only accepts `name` and `email`, so the role is silently dropped and the toast still says "User updated successfully". (Creating a user with `role_id` does work since R26.)
3. `AssignRoleModal`, which calls the correct endpoint (`PUT /users/{id}/roles`), is **not rendered anywhere**. Nothing imports it.

**How to fix:**

- Show `u.roles.map((r) => r.display_name || r.name).join(', ')`.
- Remove `role_id` and `is_active` from the edit payload.
- Add a **Manage roles** action to each row, shown with `can('roles.assign')`, that opens `AssignRoleModal`. The backend blocks changing your own roles (R7). Hide the action on your own row too, so users don't get a 403.

---

## 🟠 High

### F4. Several screens call endpoints that don't exist or drop the data — 🟡 Partly

**Files:** `UserModal.jsx`, `activitiesApi.js`, `ProjectDetailsPage.jsx`

**Fixed by the backend:** `POST /users` works now (#1). `PUT /me` and `PUT /me/password` exist.

**Still open:**

- **Edit user** still sends `password` and `password_confirmation` when the field is filled. `UserController::update` ignores them, so **the password silently doesn't change** while the toast reports success. Either add a password field to the backend update (admin reset) or remove it from the edit form.
- `is_active` is sent on create and edit, but there's no such column. Remove the checkbox.
- `activitiesApi.getByTask` calls `/tasks/{id}/activities`, which has no route. Delete it.
- The project **Activity** tab reads `project.activities`. `ProjectController::show` doesn't include activities, so the tab is always empty. Load it with `activitiesApi.getByProject(id)` when the tab opens. The endpoint is paginated (`{ data, meta }`), so use `toPage` from F2.
- `authApi.updateProfile` / `updatePassword` have no UI. Either add a small profile page or delete them.

---

### F6. Buttons don't match what the backend allows — 🔴 Open (rules updated)

**Files:** `TaskDetailsPage.jsx` lines 210–214, `TasksPage.jsx`, `ProjectsPage.jsx`, `ProjectDetailsPage.jsx` lines 192–232

**Problem:** The UI decides from permission names alone. The policies also check ownership, membership and assignment:

| Action            | Frontend shows it when                  | Backend allows it when (current policies)                                         |
| ----------------- | --------------------------------------- | --------------------------------------------------------------------------------- |
| Edit task         | `tasks.update`                          | member **and** (project owner, task creator or `tasks.update`)                    |
| Delete task       | `tasks.delete`                          | member **and** (project owner, task creator or `tasks.delete`)                    |
| Change status     | `tasks.change_status` or `tasks.update` | member **and** (owner, `tasks.update`, or `tasks.change_status` **and** assignee) |
| Add/remove member | `projects.manage_members`               | project owner, or `projects.manage_members` **and** member                        |
| Assign a task     | `tasks.assign`                          | not checked at all (see B-F7)                                                     |

Examples:

- A task creator without `tasks.delete` can delete on the backend but never sees the button.
- A project owner without `projects.manage_members` can't see **Add Member** on their own project.
- A user with only `tasks.change_status` sees the status dropdown on every task and gets 403 on tasks that aren't assigned to them.

**How to fix:** Let the backend send what the current user can do with each item, so the rules live only in the policies:

```php
// TaskResource
'can' => [
    'update'        => $request->user()->can('update', $this->resource),
    'update_status' => $request->user()->can('updateStatus', $this->resource),
    'delete'        => $request->user()->can('delete', $this->resource),
],

// ProjectResource (show only)
'can' => [
    'update'         => $request->user()->can('update', $this->resource),
    'archive'        => $request->user()->can('archive', $this->resource),
    'delete'         => $request->user()->can('delete', $this->resource),
    'manage_members' => $request->user()->can('manageMembers', $this->resource),
],
```

```jsx
{task.can?.update && <button onClick={...}>Edit Task</button>}
```

Keep `can('…')` only for page-level actions that have no item yet, such as **Create Project**.

---

### F7. Members get an empty project filter on the Tasks page — 🔴 Open

**File:** `frontend/src/pages/tasks/TasksPage.jsx` lines 182–203

**Problem:** Projects and users are loaded together with `Promise.all`. `/users` needs `users.view`, which Members don't have, so it returns 403. That rejects the whole `Promise.all`, so the project list is thrown away too, and the error only goes to the console. Members end up with an empty "All Projects" filter.

**How to fix:** Use the new lightweight endpoints and load them independently:

```js
const [projRes, userRes] = await Promise.allSettled([
  projectsApi.options(), // GET /projects/options
  can("users.view") ? usersApi.options() : Promise.resolve({ data: [] }), // GET /users/options
]);
if (projRes.status === "fulfilled")
  dispatch({ type: "SET_PROJECTS", payload: projRes.value.data });
if (userRes.status === "fulfilled")
  dispatch({ type: "SET_USERS", payload: userRes.value.data });
```

Hide the assignee filter when the user list is empty.

---

### F23. The Due Date filter on the Tasks page is silently ignored — 🆕 New

**File:** `frontend/src/pages/tasks/TasksPage.jsx` lines 212, 251, 448–458
**Cause:** backend R10

**Problem:** The page sends `due_date=YYYY-MM-DD` for an exact date. The backend now supports only `due_date_from` and `due_date_to`, so the parameter is ignored and the list doesn't change. The URL and the input still show the date, so it looks like a filter with no matches.

**How to fix:** Replace the single date input with **From** / **To** inputs that send `due_date_from` / `due_date_to`. For an exact day, send the same date to both.

---

### F25. Project details shows only the first 15 tasks, and its statistics are wrong — 🆕 New

**File:** `frontend/src/pages/projects/ProjectDetailsPage.jsx` lines 41–50, 181–186

**Problem:** `GET /projects/{id}/tasks` is paginated (15 per page). The page reads only the first page and has no pagination. So a project with 40 tasks shows **15**, and **Task Statistics** (total, completed, in progress, progress %) is computed from those 15. Any failure of that request is also swallowed (`.catch(() => ({ data: [] }))`), so it looks like the project has no tasks.

**How to fix:**

- Paginate the Tasks tab with `toPage` (F2), or link to `/tasks?project_id={id}`, which already filters, sorts and pages.
- Return the counts with the project instead of computing them in the browser, e.g. in `ProjectController::show`:
  ```php
  $project->loadCount([
      'tasks',
      'tasks as completed_tasks_count' => fn ($q) => $q->where('status', 'completed'),
      'tasks as in_progress_tasks_count' => fn ($q) => $q->where('status', 'in_progress'),
  ]);
  ```
- Show an error if the request fails instead of an empty list.

---

### F26. Add Member lists only the 10 newest users — 🆕 New

**File:** `frontend/src/pages/projects/AddMemberModal.jsx` lines 20–43

**Problem:** `usersApi.getAll()` is called without `per_page`, so `/users` returns the default page of **10** users, newest first. Older users can never be added. If those 10 are all members already, the modal says _"All system users are already members of this project."_ `/users` also needs `users.view`, so a project owner without it only gets "Failed to fetch available users list".

**How to fix:** Use `GET /users/options` (id, name, email, up to 1000) and filter out current members on the client. If owners without `users.view` must be able to add members, `UserController::options` should authorize on "can manage members of at least one project" instead of `users.view`. That is a backend decision.

---

## 🟡 Medium

### F5. The auth token is kept in two `localStorage` keys — 🔴 Open

**Files:** `AuthContext.jsx` lines 58, 98–99, 119–120, `apiClient.js` line 7, `PermissionGuard.jsx`

**Problem:** `login` and `register` save the same token as both `auth_token` and `token`. The code reads and deletes both, plus `auth_user` and `user`, which are never written. Two keys make it easy to clear one and miss the other.

There's no XSS bug today: React escapes output and there's no `dangerouslySetInnerHTML`. Tokens now expire after one day, which lowers the risk from round 1.

**How to fix:** Keep one key behind one module, so only one file touches storage:

```js
// api/tokenStorage.js
const KEY = "auth_token";
export const getToken = () => localStorage.getItem(KEY);
export const setToken = (t) => localStorage.setItem(KEY, t);
export const clearToken = () => localStorage.removeItem(KEY);
```

For stronger protection later, move to Sanctum's cookie-based SPA authentication.

---

### F8. Slow responses can overwrite newer ones — 🔴 Open

**Files:** `TasksPage.jsx`, `ProjectsPage.jsx`, `UsersPage.jsx`

**Problem:** Every filter, search or page change starts a new request, and older requests are never cancelled or ignored. If the response for "des" arrives after the one for "design", the table shows "des" results under a search box that says "design".

**How to fix:** `apiClient` already spreads `options` into `fetch`, so it can take an `AbortSignal`. Cancel the previous request when the effect re-runs:

```js
useEffect(
  () => {
    const controller = new AbortController();
    tasksApi
      .getAll(params, { signal: controller.signal })
      .then(handleSuccess)
      .catch((err) => {
        if (err.name !== "AbortError") handleError(err);
      });
    return () => controller.abort();
  },
  [
    /* params */
  ],
);
```

`apiClient` wraps every `fetch` failure as a network error (`status: 0`), so let `AbortError` pass through unchanged. TanStack Query (F14) does this automatically.

---

### F9. A network error during startup logs the user out — 🔴 Open

**File:** `frontend/src/context/AuthContext.jsx` lines 74–80

**Problem:** On load, the app calls `/me` to restore the session, and **any** failure deletes the token, including network errors (`status: 0`) and 500s. If the API is briefly down while the page loads, the user is logged out even though the token is valid.

**How to fix:** Clear the token only when the server rejects it:

```js
} catch (error) {
  if (error.status === 401) {
    clearToken();
    setUser(null);
  } else {
    setStartupError("Could not reach the server. Please try again.");
  }
}
```

Also remove the leftover `authApi.getCurrentUser ? … : authApi.me()` check on line 66. `authApi.me` doesn't exist.

---

### F10. Dates show one day early for users west of UTC — 🔴 Open

**Files:** `formatDate` in `ProjectsPage.jsx`, `ProjectDetailsPage.jsx`, `TasksPage.jsx`, `TaskDetailsPage.jsx`, `DashboardPage.jsx`, and the overdue checks

**Problem:** Project `start_date` / `due_date` and task `due_date` are plain dates. The Resources send them as midnight UTC (`2026-10-20T00:00:00.000000Z`). `new Date(...).toLocaleDateString()` converts that to the browser's time zone, so anyone west of UTC sees **Oct 19**. The overdue checks in `TasksPage`, `TaskDetailsPage` and `DashboardPage` use the same parsing, so a task can show as overdue a day early.

**How to fix:**

- Backend: send plain dates as plain dates (`$this->due_date?->toDateString()` in the Resources).
- Frontend: parse them without a time zone, in one shared helper:
  ```js
  export const formatDateOnly = (value) => {
    if (!value) return "—";
    const [y, m, d] = value.slice(0, 10).split("-").map(Number);
    return new Date(y, m - 1, d).toLocaleDateString("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  };
  ```
- Use `task.is_overdue` from the API instead of recomputing overdue in three places.

---

### F11. Role create and edit routes only need `roles.view`, and routes are declared twice — 🔴 Open

**File:** `frontend/src/routes/AppRoutes.jsx` lines 33–50

**Problem:**

- `roles/create` and `roles/:id` sit under `PermissionRoute requiredPermission="roles.view"`, so a view-only user can open both forms and fill them in before the save fails with 403.
- `tasks/:id` is declared twice (lines 34 and 36), and `roles` is declared twice (lines 43 and 49).

**How to fix:**

```jsx
<Route element={<PermissionRoute requiredPermission="roles.view" />}>
  <Route path="roles" element={<RolesPage />} />
</Route>
<Route element={<PermissionRoute requiredPermission="roles.create" />}>
  <Route path="roles/create" element={<RoleFormPage />} />
</Route>
<Route element={<PermissionRoute requiredPermission="roles.update" />}>
  <Route path="roles/:id" element={<RoleFormPage />} />
</Route>
```

Remove the second `tasks/:id` route and the second `roles` block.

---

### F12. Passing custom headers to `apiClient` drops the auth token — 🔴 Open

**File:** `frontend/src/api/apiClient.js` lines 22–26

**Problem:** `...options` is spread **after** the merged `headers`, so a caller's `options.headers` replaces them, and `Authorization`, `Accept` and `Content-Type` are lost. No caller passes headers yet, so this is a trap for the next feature, such as a file upload.

**How to fix:**

```js
const config = { ...options, method: options.method || "GET", headers };
```

---

### F13. The dashboard downloads lists to count them — 🟡 Partly

**File:** `frontend/src/pages/dashboard/DashboardPage.jsx` lines 24–96

**Problem:**

- The page asks for `per_page: 150`, but the backend caps it at **100**. Past 100 tasks or 100 projects, every number on the dashboard is wrong, with no warning.
- "Accessible Projects" shows `projects.length`, which is at most 100, instead of `meta.total`.
- If either request fails, `Promise.all` hides the whole dashboard behind one error.

**Done on the backend:** the 100 cap, and `per_page` validation (R16).
**Still missing:** a stats endpoint.

**How to fix:** Add `GET /dashboard/stats`, which returns the counts with SQL, and render those:

```php
return response()->json(['data' => [
    'projects_total'    => Project::accessibleBy($user)->count(),
    'projects_active'   => Project::accessibleBy($user)->where('status', 'active')->count(),
    'tasks_by_status'   => Task::visibleTo($user)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
    'tasks_by_priority' => Task::visibleTo($user)->selectRaw('priority, count(*) as total')->groupBy('priority')->pluck('total', 'priority'),
    'overdue'           => Task::visibleTo($user)->overdue()->count(),
    'assigned_to_me'    => Task::where('assigned_to', $user->id)->count(),
]]);
```

`Task::visibleTo()` is a new scope with the same filter as `TaskController::index`. Per-project progress can come from `withCount` on the projects list.

---

### F14. Dropdown lists are refetched everywhere, with no caching — 🟡 Partly

**Files:** `TasksPage.jsx` line 187, `TaskFormModal.jsx` line 67, `ProjectsPage.jsx` line 67, `AddMemberModal.jsx` line 23, `DashboardPage.jsx`

**Done on the backend:** `GET /users/options` and `GET /projects/options` exist (R16).
**Still open:** the frontend doesn't use them. It still downloads full paginated lists (`per_page: 150`, `/users?per_page=100`, `/users` with no size) every time a page or modal opens.

**How to fix:**

1. Add `usersApi.options()` and `projectsApi.options()` and use them for every dropdown and filter.
2. Cache them with **TanStack Query** (or a small shared hook), so they load once per session:
   ```jsx
   const { data: projects = [] } = useQuery({
     queryKey: ["projects", "options"],
     queryFn: () => projectsApi.options(),
     staleTime: 5 * 60 * 1000,
   });
   ```

Note that `/projects/options` leaves out archived projects. That is right for "create task" and wrong for the Tasks filter, where archived projects should stay visible. Add `?include_archived=1` if the filter needs them.

---

### F27. The project form offers an "Archived" status the backend rejects — 🆕 New

**Files:** `ProjectFormModal.jsx` line 144, `ProjectsPage.jsx`, `ProjectDetailsPage.jsx`

**Problem:**

- The status dropdown includes **Archived**, but `StoreProjectRequest` / `UpdateProjectRequest` accept only `active, in_progress, on_hold, completed`, so saving returns 422.
- Archiving is done through `PATCH /projects/{id}/archive`, which now **toggles** (backend `toggleArchive`). The UI hides the Archive button once a project is archived and has no **Unarchive** button, so an archived project can't be restored from the UI.

**How to fix:** Remove **Archived** from the form. Show **Unarchive** when `project.status === 'archived'` (same endpoint, same permission).

---

## 🔵 Low / design & consistency

### F15. The whole app ships as one bundle — 🔴 Open

**File:** `AppRoutes.jsx`

All pages are imported statically, so the login page downloads every screen (361 kB, 102 kB gzipped). Lazy-load the pages behind login:

```jsx
const DashboardPage = lazy(() => import("../pages/dashboard/DashboardPage"));
<Suspense fallback={<LoadingSpinner />}>
  <Routes>…</Routes>
</Suspense>;
```

### F16. Shared components are unused, and dead or duplicate components remain — 🔴 Open

**Folder:** `frontend/src/components/layout/common/` and others

- The PR #29 components (`Button`, `Input`, `Select`, `Textarea`, `Modal`, `LoadingSpinner`, `EmptyState`, `ErrorState`, `SearchInput`, `StatusBadge`, `PriorityBadge`, `FormError`, `Notification`, `Can`) are **not used by any page**.
- Dead code: `Sidebar.jsx` and `Header.jsx` (the layout is `AppLayout.jsx`, see F1), `PermissionGuard.jsx` (reads `localStorage.user`, which is never written), `pages/NotFoundPage.jsx` (the routes use `pages/errors/NotFoundPage.jsx`), `common/ConfirmModal.jsx` (every page imports `pages/projects/ConfirmModal.jsx`), and `RoleModal.jsx`. `RolesPage` renders `RoleModal` but nothing ever opens it, since **Edit** links to `/roles/:id`.
- `common/` sits inside `layout/` although these aren't layout components.

**How to fix:** Delete the dead files. Move `common/` to `src/components/common/`, then replace the hand-built badges, spinners, empty states and confirm modals page by page.

### F17. Permission checks are copied into six files, with admin checks that never match — 🔴 Open

**Files:** `TasksPage.jsx`, `TaskDetailsPage.jsx`, `ProjectsPage.jsx`, `TaskFormModal.jsx`, `RolesPage.jsx`, `UserModal.jsx`

Each file defines its own `checkPermission` that falls back to `user.role === 'admin'`, `user.is_admin` or `user.permissions`. The `/me` user has none of these fields, and `useAuth()` always provides `can`, so the fallbacks never run. `AuthContext.can` has the same dead `is_admin` / `user.role` checks. `UsersPage` falls back to `true` when `can` is missing.

Delete every local helper and use `const { can } = useAuth()` directly. Keep the admin rule in one place, `AuthContext.can`, based on the `Administrator` role.

### F18. Every page guesses the response shape — 🔴 Open

**Files:** all pages, `AuthContext.jsx`

The code tries several paths everywhere: `res.data?.data || res.data || res`, `payload.user || payload.data || payload`, `res.projects`, `res.tasks`, `p.title || p.name`, `task.assigned_user || task.assignee`. Guessing is exactly how F2 and F22 happened: the wrong guess fails silently.

Give each API function **one** fixed return value, and remove the fallbacks from the pages:

```js
// tasksApi.js
getAll:  async (params) => toPage(await apiClient.get(`/tasks?${new URLSearchParams(params)}`)),
getById: async (id) => (await apiClient.get(`/tasks/${id}`)).data,
```

Current shapes to code against, after B-F5:

| Endpoint                                   | Body                                                                                 |
| ------------------------------------------ | ------------------------------------------------------------------------------------ |
| `GET /projects`, `GET /tasks`              | `{ data, links, meta }`                                                              |
| `GET /users`, `GET /projects/{id}/tasks`   | raw paginator `{ data, current_page, … }`                                            |
| `GET /tasks/{id}/comments`, `…/activities` | `{ data, meta }`                                                                     |
| `GET /projects/{id}`, `GET /tasks/{id}`    | `{ data }`                                                                           |
| `/login`, `/register`, `/me`               | `{ user, … }` (`user.roles` is a list of names, `user.effective_permissions` is set) |
| Roles, permissions, options                | `{ data }`                                                                           |

### F19. Errors are shown four different ways, including `alert()` — 🔴 Open

**Files:** `ProjectDetailsPage.jsx`, `ProjectsPage.jsx`, `RolesPage.jsx`, `TaskDetailsPage.jsx`

There are 10 `alert()` calls in those four files, toasts in three other pages, inline boxes elsewhere, and some errors only in the console (Tasks filter data, the Projects member list). Use `showToast(message, 'error')` for failed actions, and inline alerts only when a page can't load.

### F20. Status and priority values are hard-coded in many files — 🟡 Partly

**Files:** `DashboardPage.jsx`, `ProjectDetailsPage.jsx`, `TaskFormModal.jsx`, `TaskDetailsPage.jsx`, `TasksPage.jsx`, `ProjectFormModal.jsx`, `ProjectsPage.jsx`

**Done:** the backend task enum is now `todo, in_progress, review, completed` (#8), which matches what the frontend sends.
**Still open:** each file keeps its own lists, labels and colors. Some include values that don't exist (`pending`, `done`), and the project form includes `archived` (F27).

Keep them in one module and use them in filters, forms and badges:

```js
// constants/statuses.js
export const TASK_STATUSES = [
  { value: "todo", label: "To Do" },
  { value: "in_progress", label: "In Progress" },
  { value: "review", label: "Review" },
  { value: "completed", label: "Completed" },
];
export const TASK_PRIORITIES = ["low", "medium", "high", "urgent"];
export const PROJECT_STATUSES = [
  "active",
  "in_progress",
  "on_hold",
  "completed",
];
```

### F21. No error boundary, lint warnings, and no tests — 🔴 Open

- There's no error boundary. One render error blanks the whole app.
- `oxlint` reports 8 warnings: 6 `exhaustive-deps` in `ProjectsPage`, `ProjectDetailsPage` and `AddMemberModal`, an unused `catch` variable, and 2 `only-export-components`. `ProjectDetailsPage` refetches only when `id` changes.
- No test runner is installed.

Wrap the routes in an error boundary, fix the warnings (`useCallback` for the fetch functions), run `npm run lint` in CI, and add Vitest + React Testing Library. Start with `toPage` (F2), the 401 flow (F3), and the task edit payload (F22), so those can't break again.

### F28. Smaller display bugs — 🆕 New

- **Projects table:** "+N members" never shows. `GET /projects` returns `members_count`, not `members`.
- **Tasks page:** the delete confirmation passes `isDanger`, but `ConfirmModal` reads `danger`, so **Delete** renders as a normal blue button (`TasksPage.jsx` line 647).
- `ProtectedRoute`**:** the loading spinner uses Tailwind classes (`min-h-screen`, `animate-spin`, …), but Tailwind isn't installed, so it renders as an empty `div`.
- `AppLayout`**:** the top bar shows `roles[0]`, the role **name** (e.g. `Project Manager`), and falls back to "Member" for users with no role. Show `display_name`, and nothing when the user has no role.
- `TaskFormModal`**:** receives a `members` prop from `ProjectDetailsPage` and ignores it, then fetches the whole project again to build the same list.

---

## ⚪ Withdrawn

### F1. The Users and Roles links are hidden from everyone — ⚪ Withdrawn

Round 1 said `Sidebar.jsx` hides the Users and Roles links because it checks `hasRole('admin')`. That code is wrong, but `Sidebar.jsx` **is never rendered**. The app uses `AppLayout.jsx`, which already shows both links with `can('users.view')` / `can('roles.view')`. There's no user-facing bug; the file is dead code (see F16).
