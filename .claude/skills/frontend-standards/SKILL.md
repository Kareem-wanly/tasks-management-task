---
name: frontend-standards
description: Coding standards for the React + TypeScript frontend in frontend/. Use whenever creating, editing, reviewing or refactoring any file under frontend/src — components, pages, hooks, forms, API modules, types or styles — and before reporting frontend work as done. Enforces the 250-line file limit, zero `any`, zero TypeScript errors, typed variables, TanStack Query for data, react-hook-form + zod for forms, Tailwind + shadcn/ui for UI, and the project's auth and error-handling rules.
---

# Frontend standards

Stack: React 19, Vite, React Router 7, TypeScript (`strict`), TanStack Query, react-hook-form + zod, Tailwind CSS v4 + shadcn/ui, sonner, oxlint. Backend is Laravel; its response shapes live in `frontend/src/types/api.ts`.

These rules are hard requirements, not suggestions. Most are enforced by `npm run check` (run in `frontend/`), which runs `tsc -b` and `oxlint`. Work is not done until it passes with zero errors.

## 1. File size: 250 lines maximum

- No file under `frontend/src` may exceed **250 lines**, blank lines and comments included. oxlint enforces this (`eslint/max-lines`).
- Plan the split *before* writing. If a new file will grow past ~200 lines, split it up front.
- How to split, in order of preference:
  1. **Child components** for each visual block (filters bar, table, row, pagination, modal) in the same feature folder, e.g. `features/tasks/components/TasksFilters.tsx`, `features/tasks/components/TasksTable.tsx`.
  2. **Custom hooks** for data loading and state (`useTasksQuery`, `useProjectMembers`) in the feature folder, or `src/hooks/` when shared.
  3. **Pure helpers** (formatting, mapping, badge classes) in `src/utils/`.
  4. **Constants** (status lists, labels, colors) in `src/constants/`.
  5. **Reducers** and their action types in their own file next to the page.
- Legacy debt: the files listed under `overrides` in `frontend/.oxlintrc.json` are still over the limit and only warn. **When you edit one of them, split it below 250 lines in the same change and remove it from that list.** Never add a file to that list.

## 2. TypeScript: no `any`, no errors

- `tsc -b` must report **zero errors**. Never leave a type error for later.
- **No `any`**: not `: any`, `as any`, `any[]`, `Record<string, any>` or `Function`. oxlint rejects them. Use a precise type, a union, a generic, or `unknown` plus narrowing.
- **No suppressions**: no `@ts-ignore`, `@ts-expect-error`, `@ts-nocheck`, and no `eslint-disable` without a one-line reason on the same line.
- **No non-null assertions (`!`)**. Narrow with `if`, optional chaining or an early return. The only allowed one is `document.getElementById('root')!` in `main.tsx`.
- **No casting API data in components.** `as` belongs only inside `src/api/` and `src/lib/` where raw JSON is typed. In components, fix the type at its source.
- Don't weaken `tsconfig` (`strict`, `noUnusedLocals`, `noUnusedParameters` stay on).

### Typing variables and functions

- **Props**: every component gets a named `interface XxxProps`. Extend intrinsic props when spreading (`extends React.ButtonHTMLAttributes<HTMLButtonElement>`).
- **State**: give `useState` a type argument whenever the initial value doesn't describe the full type: `useState<Task | null>(null)`, `useState<Project[]>([])`, `useState<ValidationErrors>({})`.
- **Refs**: `useRef<HTMLInputElement>(null)`.
- **Events**: `React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>`, `React.FormEvent<HTMLFormElement>`, `React.MouseEvent<HTMLButtonElement>`.
- **Reducers**: a `State` interface and a discriminated union for actions (`{ type: 'SET_PAGE'; payload: number } | ...`). The `switch` must be exhaustive.
- **Exported functions** in `src/api/`, `src/hooks/` and `src/utils/` declare their parameter and return types.
- **Domain types** come from `src/types/api.ts` (`Task`, `Project`, `User`, `UserListItem`, `Role`, `Page<T>`...). Don't redeclare them locally. If the backend changes a shape, update `types/api.ts` first and follow the compiler errors.
- Prefer `type` unions over `enum` (e.g. `TaskStatus = 'todo' | 'in_progress' | ...`).
- `catch (err)` is `unknown`. Narrow it with `isAbortError(err)`, `err instanceof ApiError` and `errorMessage(err, 'fallback')` from `src/lib/apiClient`.

## 3. Data fetching (TanStack Query)

- Reference implementation: `src/features/users/` (list query, filters in the URL, mutations, dialogs).
- Pages and components **never** call `fetch` or `apiClient` directly. Query functions call the modules in `src/api/` (`tasksApi`, `projectsApi`, `usersApi`, `rolesApi`, `commentsApi`, `activitiesApi`, `authApi`).
- **Reads use `useQuery`**, with keys from `src/api/queryKeys.ts` and the `signal` passed through:
  ```ts
  useQuery({
    queryKey: queryKeys.users.list(params),
    queryFn: ({ signal }) => usersApi.getAll(params, { signal }),
    placeholderData: keepPreviousData, // paginated lists
  });
  ```
  Wrap a feature's query in a hook (`features/<name>/hooks/useXxx.ts`). Queries several features need live in `src/hooks/queries/` (`useRoles`, `useUserOptions`, `useProjectOptions`). Add new keys to `queryKeys`; never write a key array inline.
- **Writes use `useApiMutation`** (`src/hooks/useApiMutation.ts`). It shows the success and error toasts and invalidates queries:
  ```ts
  const remove = useApiMutation({
    mutationFn: (id: number) => usersApi.delete(id),
    successMessage: 'User deleted.',
    errorMessage: 'Failed to delete user.',
    invalidate: [queryKeys.users.all],
  });
  ```
  Refresh data by invalidating keys, never by calling a `reload()` or refetching by hand.
- **No `useEffect` + `useState` fetching** in new or edited code. React Query already handles abort, caching, races and loading state. Effects remain only for non-fetch side effects (syncing the URL, resetting a form when a dialog opens).
- Read loading and error state from the query: `isPending` for the first load, `isFetching` for background refreshes, `isError` with `refetch` for a retry.
- Every API function returns **one fixed shape**: the unwrapped `data`, or `Page<T>` from `toPage()` for paginated lists. Never write shape-guessing fallbacks such as `res.data?.data || res.data || res`. If the shape is wrong, fix the API function.
- Build query strings with `toQuery()`, which drops empty values.
- Dropdowns and filters use the `/options` hooks, never a large `getAll({ per_page })`. Pass `enabled: can('users.view')` when the endpoint needs a permission.
- The backend caps `per_page` at 100. Never request more, and never compute totals from a page of items: use `total` or a server-side count.
- Legacy code still uses `useEffect` fetching. Migrate a feature to the pattern above when you touch it.

## 4. Auth and permissions

- Use `const { can, hasRole, user } = useAuth()` only. No local permission helpers, and no `useAuth() || {}` or `can ? can(x) : false` guards (`useAuth` always returns a value).
- The Administrator rule lives only in `AuthContext.can`.
- Page-level access goes in the routes (`<ProtectedRoute permission="..." />`). Create and edit routes need `*.create` / `*.update`, not `*.view`.
- Hide actions the user can't perform. Don't show your own row's destructive actions (delete self, change own roles).
- The token is read and written only through `src/lib/tokenStorage.ts`. Never touch `localStorage` for auth elsewhere.

## 5. Forms (react-hook-form + zod)

- Every form uses `useForm` with `zodResolver(schema)`. Never use one `useState` per field or a hand-written `handleChange`.
- Schemas live in `features/<name>/schemas.ts` next to their default values. The values type comes from the schema: `export type UserFormValues = z.infer<typeof userSchema>`. Don't write the type by hand.
- Render fields with the shared components in `src/components/form/`: `TextField`, `TextareaField`, `SelectField`. Each one shows the label, input and error, and is typed by `name`:
  ```tsx
  <TextField control={form.control} name="email" label="Email" type="email" />
  ```
  For custom inputs (checkbox lists, pickers), use RHF `Controller` with shadcn `Field`, `FieldLabel` and `FieldError`.
- Submit through a mutation: `onSubmit={form.handleSubmit((values) => save.mutate(values))}`, and disable the button with `save.isPending`.
- **422 errors** go onto the fields: `onError: (err) => applyServerErrors(err, form.setError)` (`src/lib/forms.ts`). It returns `true` when it handled the error, so `useApiMutation` skips the toast.
- Form dialogs use `FormDialog` (`src/components/ui/FormDialog.tsx`). Reset the form when the dialog opens (`form.reset(...)` in an effect on `open`).
- Keep select values as strings in the form (`role_id: '' | '3'`) and convert them in `mutationFn`. Select option values can't be `''`, so an empty value means "nothing selected".

## 6. Errors and feedback

- Toasts use sonner through `showToast(message, 'success' | 'error' | 'info')` (from `useToast()` or imported from `src/lib/toast/ToastContext`). Mutations get them automatically from `useApiMutation`.
- Failed **page loads**: an inline error with a retry (`<ErrorState onRetry={() => query.refetch()} />`), not a toast.
- Never use `alert()`, `confirm()` or `prompt()`. Use `ConfirmModal` with `danger` for destructive actions.
- Never swallow errors silently (`.catch(() => [])`). At minimum show an inline message.

## 7. UI and styling (Tailwind + shadcn/ui)

- Build UI from shadcn components in `src/components/ui/` (`button`, `input`, `select`, `dialog`, `table`, `badge`, `card`, `field`, `checkbox`, ...). Add new ones with `npx shadcn@latest add <name>`; don't hand-write a component shadcn already provides.
- Use the shared building blocks before writing your own: `DataTable` and `Pagination` (`src/components/data/`), `PageHeader` (`src/components/layout/`), `FormDialog`, `ConfirmModal`, `ErrorState`.
- Style with Tailwind classes in JSX. Use the theme tokens (`bg-background`, `text-muted-foreground`, `border`, `text-destructive`, `bg-primary/10`) rather than raw colors. Combine conditional classes with `cn()` from `@/lib/utils`.
- **No new `.css` files** and no inline `style={{}}` in features. When a feature is migrated, delete its old CSS file.
- Icons come from `lucide-react`. Icon-only buttons need an `aria-label`.
- You may edit generated shadcn files to change the design system, but keep their API, and don't put feature logic in them.

## 8. React rules

- Hooks are called unconditionally at the top level. Never write `useX ? useX() : fallback`.
- Effect dependency arrays are complete. If you must omit a dependency, explain why in the `eslint-disable-next-line` comment.
- Derive values during render instead of copying them into state. Use `useMemo` only for expensive work or referential stability.
- Lists use stable `key`s (ids, not the array index) when an id exists.
- Keep URL-driven state (filters, page, sort) in `useSearchParams` so it survives a reload.
- Accessibility: every input has a label or `aria-label`, and use `<button type="button">` unless it submits.
- Never use `dangerouslySetInnerHTML` (oxlint rejects it).

## 9. Data display

- Task statuses and priorities are data, not constants: read them with `useTaskLookups()` (`src/hooks/queries/`), which gives the ordered lists, `statusOptions`/`priorityOptions` for selects, `statusLabel`/`statusTone` for badges, and `isFinal(status)`. A task is done when `isFinal(task.status)`, never `status === 'completed'`. Only actions that need a fixed value use the built-in keys in `src/constants/statuses.ts` (`TASK_DONE_STATUS`, `TASK_REOPEN_STATUS`, `URGENT_PRIORITY`).
- Project statuses must match the backend exactly: `active | in_progress | on_hold | completed | archived` (`archived` is set only through the archive toggle, never in a form). Don't invent values like `done` or `pending`. Their labels and tones live in `src/constants/statuses.ts`, not per file.
- Prefer server-computed flags (`task.is_overdue`, `*_count`) over recomputing them in the browser.
- Format dates with `src/utils/format.ts`: `formatDateOnly` for calendar dates (`due_date`, `start_date`; no timezone shift), `formatTimestampDate` and `formatDateTime` for timestamps.

## 10. Structure and style

- Folder layout, import boundaries and naming follow the `frontend-structure` skill (feature-based: `src/features/<name>/`, shared code in `src/components`, `src/hooks`, `src/utils`, `src/constants`, `src/types`, infrastructure in `src/lib`).
- One component per file, named like the file (`TasksTable.tsx` exports `TasksTable`).
- Use `import type` for type-only imports (`verbatimModuleSyntax` is on).
- Delete dead code instead of commenting it out. Don't leave unused files, exports or `console.log` behind (`console.error` in a `catch` is fine).
- Match the surrounding code's naming and style. Keep comments short and explain *why*, not *what*.

## Done checklist

Before saying frontend work is finished:

1. `cd frontend && npm run check` exits with code 0, with no `max-lines` warning on files you touched.
2. `npm run build` succeeds.
3. No file you created or edited is over 250 lines (`wc -l` if in doubt).
4. Search your diff for `any`, `as `, `!.`, `@ts-`, `alert(`, `localStorage`, `style={{` and new `.css` files, and justify or remove each hit.
5. New or edited data code uses `useQuery`/`useApiMutation`, not `useEffect` fetching. New or edited forms use react-hook-form + zod.
6. Report the result honestly. If a check fails, say which one and show the output.
