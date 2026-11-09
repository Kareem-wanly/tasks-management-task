---
name: frontend-structure
description: Folder and file structure for the React frontend in frontend/src (feature-based). Use whenever creating, moving, renaming or splitting files under frontend/src, adding a new page, feature, component, hook, API module or type, or deciding where code belongs and how files import each other. Works together with frontend-standards (TypeScript and coding rules).
---

# Frontend structure (feature-based)

The frontend is organized **by feature**, not by file type: everything one feature needs lives in one folder, and only truly shared code lives outside it. Imports flow one way: **shared → features → app**. The boundaries are enforced by `no-restricted-imports` in `frontend/.oxlintrc.json`, so `npm run check` fails on a violation.

For TypeScript, the 250-line limit and coding rules, also follow the `frontend-standards` skill.

## The tree

```
frontend/src/
├── main.tsx              entry: renders <App />
├── app/                  application shell: may import anything
│   ├── App.tsx           providers + router
│   ├── routes/           AppRoutes.tsx, ProtectedRoute.tsx
│   └── pages/            pages that belong to no feature (ForbiddenPage, NotFoundPage)
├── features/             one folder per business feature
│   ├── auth/  dashboard/  projects/  roles/  tasks/  users/
├── api/                  HTTP modules per backend resource (tasksApi, projectsApi, ...) + queryKeys.ts
├── lib/                  infrastructure: apiClient, paginate, tokenStorage, queryClient, forms (applyServerErrors),
│                         utils (cn), auth/AuthContext, toast/ToastContext (sonner)
├── components/
│   ├── ui/               shadcn primitives (button.tsx, dialog.tsx, ... kebab-case, generated) + our shared
│   │                     UI (FormDialog, ConfirmModal, ErrorState, LoadingSpinner, PascalCase)
│   ├── form/             react-hook-form fields: TextField, TextareaField, SelectField
│   ├── data/             DataTable, Pagination
│   └── layout/           AppLayout, PageHeader
├── hooks/                generic hooks (useApiMutation, useDebounce, usePagedList)
│   └── queries/          React Query hooks used by several features (useRoles, useUserOptions, useProjectOptions)
├── utils/                pure helpers (format.ts)
├── constants/            enums, labels, badge classes (statuses.ts)
├── types/                API types (api.ts)
├── styles/               tailwind.css (Tailwind + shadcn theme), legacy global CSS
└── assets/               images and icons
```

## Inside a feature

Reference implementation: **`features/users/`** (already migrated to React Query, react-hook-form + zod and shadcn). Copy its shape for new or migrated features.

```
features/users/
├── UsersPage.tsx              page: one per route, at the feature root
├── schemas.ts                 zod schemas + z.infer form types + default values
├── components/                UI used only by this feature
│   ├── UsersTable.tsx         columns for the shared DataTable
│   ├── UsersFilters.tsx
│   ├── UserFormDialog.tsx     FormDialog + useForm + useApiMutation
│   └── AssignRolesDialog.tsx
├── hooks/
│   └── useUsersList.ts        useQuery + filters kept in the URL
└── index.ts                   public API of the feature
```
Other optional files: `xxxReducer.ts` (complex page state), `types.ts` and `utils.ts` (feature-only types and pure helpers).

| Part | Contains | Must not contain |
|---|---|---|
| `XxxPage.tsx` | reads route params, calls the feature hooks, composes components, owns which dialog is open | fetch logic, long tables or forms |
| `components/` | rendering: props in, events out; form dialogs own their `useForm` and save mutation | list/page data loading |
| `hooks/` | `useXxx`: `useQuery` with `queryKeys`, filters, URL sync | JSX, `useEffect` fetching |
| `schemas.ts` | zod schemas, `z.infer` types, empty form values | API calls, JSX |
| `xxxReducer.ts` | `State`, `Action` union, reducer, initial state | React imports |
| `types.ts` / `utils.ts` | feature-only types and pure functions | API shapes (those go in `types/api.ts`) |
| `index.ts` | named re-exports of what `app/` or another feature uses | `export *`, internal components |

- A small feature (about 6 files or fewer, e.g. `auth`, `roles`) stays **flat**: no `components/` or `hooks/` subfolders.
- A feature with several independent screens may split by screen (`features/projects/details/`, `features/projects/list/`).

## Where does new code go?

| The code is... | Put it in |
|---|---|
| used by one feature only | that feature's folder |
| used by two or more features | the matching shared folder (`components/ui`, `hooks`, `utils`, `constants`) |
| a call to a backend endpoint | `api/<resource>Api.ts` (shared, because resources are read by several features) |
| a React Query key | `api/queryKeys.ts` |
| a query used by two or more features | `hooks/queries/useXxx.ts` |
| a zod schema / form values type | `features/<name>/schemas.ts` |
| a new shadcn primitive | `npx shadcn@latest add <name>` → `components/ui/` |
| a reusable form field or data-display block | `components/form/` or `components/data/` |
| a TypeScript shape of an API response | `types/api.ts` |
| app-wide infrastructure (HTTP client, auth, toasts, storage) | `lib/` |
| a route, provider, or page that belongs to no feature | `app/` |
| a new business area with its own pages | a new `features/<name>/` with an `index.ts` |

When code you put in a feature becomes needed by a second feature, **move it to shared** in the same change. Don't import it from the other feature's internals.

## Import rules (enforced)

1. **Shared code never imports features or app.** `api, lib, components, hooks, utils, constants, types` must not import `@/features/...` or `@/app/...`.
2. **A feature imports another feature only through its index**: `import { TaskFormModal } from '@/features/tasks'`. Deep paths like `@/features/tasks/components/TaskFormModal` are rejected.
3. **No climbing out of a feature with `../../`.** Use `@/` for shared code.
4. **`app/` imports features through their index**: `import { TasksPage } from '@/features/tasks'`.
5. **Path style**: relative (`./`, `../`) inside the same feature or folder; the `@/` alias (`@/api/tasksApi`, `@/lib/auth/AuthContext`) for everything else. A feature never imports its own `index.ts`.
6. Avoid circular dependencies between features. If A needs B and B needs A, move the shared part to shared code.

## Naming

| Kind | Convention | Example |
|---|---|---|
| Feature folder | lowercase plural noun | `tasks`, `projects` |
| Component file | PascalCase, same name as its default export | `TasksTable.tsx` → `TasksTable` |
| Page | `XxxPage.tsx` | `TaskDetailsPage.tsx` |
| Dialog | `XxxDialog.tsx` (`XxxFormDialog.tsx` for forms) | `UserFormDialog.tsx`, `AssignRolesDialog.tsx` |
| shadcn primitive | kebab-case, as generated, named exports | `button.tsx` → `Button` |
| Schemas | `schemas.ts`, `xxxSchema` + `XxxFormValues` | `userFormSchema`, `UserFormValues` |
| Hook | `useXxx.ts` | `useTasksList.ts` |
| API module | `<resource>Api.ts` | `projectsApi.ts` |
| Reducer | `<name>Reducer.ts` | `tasksReducer.ts` |
| Stylesheet | none for new code: Tailwind classes in JSX. Legacy `XxxPage.css` files go away when a feature is migrated | |
| Constants | `SCREAMING_SNAKE_CASE` | `TASK_STATUS_LABELS` |

## Inside a file

- **One component per file.** A small private subcomponent may stay only if it is used by nothing else and the file stays under the line limit.
- **Order**:
  1. External imports (`react`, `react-router-dom`)
  2. `@/` imports
  3. Relative imports
  4. The CSS import, last
  5. Types and the `XxxProps` interface
  6. The component
  7. Small private helpers, if any
- **Exports**: our components use `export default`; shadcn primitives keep their named exports. Hooks, utils, constants and types use named exports. Don't export what nobody imports.
- **Responsibilities**:
  - Pages compose.
  - Components render.
  - Hooks fetch and hold state.
  - `api/` talks HTTP.
  - `utils/` holds pure functions.
  - `constants/` holds data, not logic.

## Adding a new feature (checklist)

1. Add endpoints to `api/<resource>Api.ts`, response types to `types/api.ts`, and keys to `api/queryKeys.ts`.
2. Create `features/<name>/XxxPage.tsx` using `PageHeader`, `DataTable`, `Pagination` and Tailwind classes. No `.css` file.
3. Put the list query in `hooks/useXxx.ts` (`useQuery`), forms in `components/XxxFormDialog.tsx` with `schemas.ts`, and writes in `useApiMutation`.
4. Add `features/<name>/index.ts` exporting the page(s).
5. Register the route in `app/routes/AppRoutes.tsx` as a `lazy(() => import('@/features/<name>')...)` page, with `<ProtectedRoute permission="..." />` if needed.
6. Add a nav link in `components/layout/AppLayout.tsx` if it belongs in the sidebar.

## Done checklist

1. `cd frontend && npm run check` exits with code 0 (TypeScript, lint, 250-line limit, import boundaries).
2. `npm run build` succeeds.
3. No empty folders and no unused files or exports left behind after moves.
4. Moved files were moved with `git mv`, so their history is kept.
