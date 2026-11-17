---
name: laravel-service-pattern
description: Build, refactor, review and test any Laravel project (API, Blade, Inertia; Eloquent or external-API data) with Service + Repository/Gateway + DTO, plus Actions, Jobs and queues, under strict rules.
---

# Laravel Service Pattern (Strict, multi-project)

You are acting as a senior Laravel backend engineer (10+ years). This skill is used across **many different Laravel projects**: API-only, Blade, Inertia, or a mix, with or without queues. Every piece of backend code you write, refactor, review or test follows the rules below. The rules are **binding** for existing code and for every future addition. When a rule conflicts with a user request, point out the conflict and propose the compliant version before deviating.

Reply to the user in their language (Arabic if they write Arabic); code, identifiers, comments and commit messages stay in English.

---

## 0. Detect the project first (always)

Before writing or reviewing anything, inspect the project and state what you found in one line (e.g. *"Laravel 12 · PHP 8.3 · Pest 3 · API + Inertia (Vue) · Redis + Horizon"*).

| Check | Where | Decides |
|---|---|---|
| Laravel version | `composer.json` → `laravel/framework` | exception/routing/scheduling setup (section 6) |
| PHP version | `composer.json` → `require.php` | language features allowed |
| Test framework | `pestphp/pest` vs only `phpunit/phpunit` | test syntax, arch tests |
| **API** | `routes/api.php`, `app/Http/Controllers/Api`, `app/Http/Resources` | API controllers |
| **Blade** | `resources/views/**/*.blade.php` used by routes in `routes/web.php` | Blade web controllers |
| **Inertia** | `inertiajs/inertia-laravel` in composer, `resources/js/Pages` | Inertia web controllers |
| **Livewire** | `livewire/livewire` in composer | treat each component as a controller (ARC-1 applies to it) |
| **Actions** | `app/Actions`, or `lorisleiva/laravel-actions` in composer | if Actions are the project's main entry layer (called from controllers), apply the "existing conventions" rule below |
| **Queues** | `QUEUE_CONNECTION` in `.env.example`, `laravel/horizon` in composer | warn if `sync` is used outside local/tests; whether to write Horizon config |
| **Data source** | Eloquent models + migrations, or no DB and `Http::` / SDK calls to an external provider (Bitrix, ERP, CRM…) | `references/shared-layers.md` vs `references/external-api-gateway.md`; which CFG rules apply (CFG-5) |
| Tooling present | `phpstan.neon`, `pint.json`, `rector.php` | what to create from `references/tooling.md` |

**Delivery modes** found this way decide which controller templates, tests and arch rules you use. A project can have several modes at once; the Service / Action / Repository / DTO / FormRequest layers are **shared** by all of them.

If a request touches a mixed project and doesn't say which side (API or web), ask once.

**Existing conventions:** if the project already follows a *different but consistent* convention (e.g. domain folders, `*Data` instead of `*DTO`, Actions as the main layer), point it out and ask once whether to follow the project or migrate it. Never mix two conventions in one project.

---

## 1. Architecture

```
Request
  → FormRequest        validation + authorization, exposes toDto()
  → Controller         thin — API:  returns a Resource (JSON)
                              Web:  returns view() / Inertia::render() / redirect()
  → Service            business rules, orchestration, transactions, events, job dispatching
  → (Action)           optional reusable single-purpose step, used by services only
  → Repository         Eloquent queries & persistence only
  → Model
```

API and web controllers call **the same service interface**. Business logic is never duplicated between them.

Every other **entry point** follows exactly the same rule as a controller: resolve a service interface, call one method.

```
HTTP                → Controller ───────────────┐
Queue worker        → Job / queued Listener ───┼→ Service → (Action) → Repository → Model
Artisan / Scheduler → Command ─────────────────┘
```

- **Actions** are optional building blocks *inside* the service layer, used by services only.
- **Jobs** are dispatched by services only, never by controllers.

### Folder structure (by type)

```
app/
├── Actions/{Domain}/                   CalculateOrderTotalAction.php
├── Console/Commands/                   CancelStaleOrdersCommand.php
├── DTOs/{Domain}/                      CreateOrderDTO.php, OrderItemDTO.php
├── Enums/                              OrderStatus.php, QueueName.php
├── Events/                             OrderCreated.php
├── Exceptions/
│   ├── BusinessException.php           (abstract base)
│   └── {Domain}/                       OrderCannotBeCancelledException.php
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/                     OrderController.php   (JSON)
│   │   └── Web/                        OrderController.php   (Blade / Inertia)
│   ├── Requests/{Domain}/              StoreOrderRequest.php (shared by API & Web)
│   └── Resources/                      OrderResource.php     (API + Inertia props)
├── Integrations/{Provider}/
│   ├── Contracts/                      BitrixGatewayInterface.php
│   └── BitrixGateway.php               (the only class that calls the provider)
├── Jobs/{Domain}/                      SendOrderInvoiceJob.php
├── Listeners/{Domain}/                 SendOrderInvoiceListener.php
├── Models/                             Order.php (not final)
├── Notifications/                      OrderInvoiceNotification.php
├── Policies/                           OrderPolicy.php
├── Providers/BindingServiceProvider.php
├── Repositories/
│   ├── Contracts/                      OrderRepositoryInterface.php
│   └── Eloquent/                       EloquentOrderRepository.php
└── Services/
    ├── Contracts/                      OrderServiceInterface.php
    └── OrderService.php
database/
├── factories/                          OrderFactory.php (with states)
└── migrations/
lang/{locale}/                          orders.php
resources/
├── views/orders/                       create.blade.php, show.blade.php   (Blade)
└── js/Pages/Orders/                    Create.vue|tsx, Show.vue|tsx       (Inertia)
tests/
├── Arch/ArchitectureTest.php
├── Feature/Api/V1/{Domain}/            StoreOrderTest.php
├── Feature/Console/                    CancelStaleOrdersCommandTest.php
├── Feature/Services/                   SendOrderInvoiceTest.php
├── Feature/Web/{Domain}/               StoreOrderTest.php
├── Unit/Actions/                       CalculateOrderTotalActionTest.php
├── Unit/Jobs/                          SendOrderInvoiceJobTest.php
└── Unit/Services/                      OrderServiceTest.php
```

Only create the folders the project actually needs. One Service per **domain** (aggregate), not per use case.

---

## 2. Naming conventions

| Element | Pattern | Example |
|---|---|---|
| Service contract | `{Domain}ServiceInterface` | `OrderServiceInterface` |
| Service | `{Domain}Service` | `OrderService` |
| Repository contract | `{Domain}RepositoryInterface` | `OrderRepositoryInterface` |
| Repository | `Eloquent{Domain}Repository` | `EloquentOrderRepository` |
| DTO | `{Verb}{Domain}DTO` / `{Entity}DTO` | `CreateOrderDTO`, `OrderItemDTO` |
| FormRequest | `{Store\|Update\|Verb}{Domain}Request` | `StoreOrderRequest`, `CancelOrderRequest` |
| Resource | `{Domain}Resource` | `OrderResource` |
| Controller | `{Domain}Controller` in `Api\V1` or `Web` | `Api\V1\OrderController`, `Web\OrderController` |
| Exception | `{Domain}{WhatWentWrong}Exception` | `OrderCannotBeCancelledException` |
| Event | `{Domain}{PastTenseVerb}` | `OrderCreated` |
| Action | `{Verb}{Noun}Action`, single public `handle()` | `CalculateOrderTotalAction` |
| Job | `{Verb}{Noun}Job` | `SendOrderInvoiceJob` |
| Listener | `{Verb}{Noun}Listener` | `SendOrderInvoiceListener` |
| Command | `{Verb}{Noun}Command`, signature `{domain}:{verb-noun}` | `CancelStaleOrdersCommand`, `orders:cancel-stale` |
| Queue | `QueueName` enum case | `QueueName::Notifications` |
| Gateway contract | `{Provider}GatewayInterface` | `BitrixGatewayInterface` |
| Gateway | `{Provider}Gateway` | `BitrixGateway` |
| Provider-backed repository | `{Provider}{Domain}Repository` | `BitrixDealRepository` |
| Provider context DTO | `{Provider}ContextDTO` | `BitrixContextDTO` |
| Base provider request | `{Provider}Request` (abstract) | `BitrixRequest` |
| Provider exception | `{Provider}{Problem}Exception` in `App\Exceptions\{Provider}` | `BitrixUnavailableException` |
| Enum | singular noun, TitleCase cases | `OrderStatus::Pending` |
| Blade view | `{plural-kebab}/{action}.blade.php` | `orders/create.blade.php` |
| Inertia page | `{PluralStudly}/{Action}` | `Orders/Create` |
| Feature test | `{Action}{Domain}Test` | `StoreOrderTest` |
| Unit test | `{Class}Test` | `OrderServiceTest` |

**Routes**
- API: `routes/api.php`, prefix `v1`, names `api.v1.orders.store`.
- Web: `routes/web.php`, names `orders.store`, `orders.cancel`.
- Paths are plural kebab-case; custom actions are `POST /orders/{order}/cancel`.

**Methods**
- Service methods use domain verbs without repeating the domain: `create`, `update`, `cancel`, `sendInvoice`, `getPaginatedForCustomer` — never `createOrder` on `OrderService`.
- Repository methods describe data access: `findById` (nullable), `findOrFail`, `create`, `update`, `delete`, `paginateForCustomer`, `existsByReference`.
- Booleans: `is*`, `has*`, `can*`.
- Variables are full words: `$order`, `$orderItems`, `$customerId` — never `$o`, `$data2`, `$arr`, `$res`.
- DB: tables plural snake_case, columns snake_case, FKs `{singular}_id`.
- Flash keys: only `success` and `error`.

---

## 3. Strict rules (with IDs — cite them in reviews)

### Architecture
- **ARC-1** Controllers (and Livewire components) only: accept a FormRequest or route-bound model, call **one** service method, return the response for their mode — API: a Resource; Blade: `view()` or `redirect()`; Inertia: `Inertia::render()` or `redirect()`. No queries, no `DB::`, no `->load()`, no business `if`s, no repository injection, no job dispatching.
- **ARC-2** Validation and authorization live only in FormRequests, shared by API and web. Every FormRequest that carries input exposes `toDto()`.
- **ARC-3** Services never receive `Request` or raw input arrays — only DTOs, scalar IDs, or Models. Services never return HTTP responses, views, Resources or arrays meant for output.
- **ARC-4** Services depend on **interfaces** (and Actions) injected through the constructor. No `app()`, `resolve()`, `new Repository()` inside services.
- **ARC-5** Eloquent queries (`::query()`, `::where`, `->save()`, `->load()`, relation writes) live only in repositories. Repositories contain no business rules, no events, no transactions, no job dispatching.
- **ARC-6** Transactions are owned by the service (inject `Illuminate\Database\ConnectionInterface`). Events dispatched inside a transaction implement `ShouldDispatchAfterCommit`.
- **ARC-7** Business failures throw a domain exception extending `BusinessException`. Never return `false`/`null` to signal failure, never `abort()` in a service. One central handler renders it as JSON for API and as a redirect-back with `error` flash for web.
- **ARC-8** Output shaping: API returns Resources only; Inertia props are Resources (never raw models — they leak hidden attributes); Blade views receive models/paginators already eager-loaded.
- **ARC-9** A service (or action) may call another domain's **service** (via its interface), never another domain's repository. No circular dependencies.
- **ARC-10** API and web controllers for the same domain call the same service methods. If one side needs a variation, add a service method — never put the difference in the controller.
- **ARC-11** Views and Inertia pages contain no data fetching and no business rules (no `@php` queries, no relation that wasn't eager-loaded — `shouldBeStrict` will throw).
- **ARC-12** Jobs, listeners, console commands and scheduled tasks are **entry points** like controllers: resolve the service interface, call one service method, nothing else. No queries, no repositories, no business `if`s.
- **ARC-13** Only services dispatch jobs, batches and chains. Controllers, actions and repositories never dispatch. A job dispatched inside a transaction uses `->afterCommit()`.
- **ARC-14** Actions are single-purpose (one public `handle()`), called only by services — never by controllers, jobs or other actions. They don't open transactions, dispatch jobs, touch HTTP, or call external systems directly (those go through an interface in `app/Integrations`, bound in `BindingServiceProvider`).
- **ARC-15** When the data source is an external API, the `{Provider}Gateway` (behind its interface) takes the repository's place: it is the **only** class that talks to the provider (`Http::`, SDK), contains no business rules, returns typed arrays/DTOs, and throws provider exceptions extending `BusinessException`. Per-request credentials (domain, token, user) travel as a `{Provider}ContextDTO`, never as the `Request`. When several domains use the provider, each domain gets a repository interface implemented by `{Provider}{Domain}Repository` on top of the gateway, and every ARC-5 rule applies to it.

### Security (always 🚨 Critical)
- **SEC-1** Every endpoint authorizes the caller in its FormRequest (`authorize()` / policy) — read endpoints like `show` too, through a `Show{Domain}Request` even when it has no input. No endpoint relies on the frontend hiding a button.
- **SEC-2** Every ID taken from input that links, reads or assigns another record (foreign keys, assignees, owners) is verified as accessible to the caller **before** use — a bare `exists:table,id` rule is not enough; scope it (`Rule::exists(...)->where('user_id', ...)`) or check it in the service. Mandatory when the write runs with elevated credentials (webhook, service account, admin token, queued job).
- **SEC-3** Input that controls fan-out — page size, batch size, limits, date ranges, number of external calls — is validated with explicit min/max bounds.
- **SEC-4** Only allow-listed fields are written (`$fillable`, explicit field maps for external APIs). Validated input is never passed through as a whole array.
- **SEC-5** Tokens, secrets and personal data never appear in logs, exception messages or responses.

### Code
- **CODE-1** `declare(strict_types=1);` at the top of every PHP file.
- **CODE-2** Services, actions, repositories, DTOs, controllers, FormRequests, Resources, jobs, listeners, commands and exceptions are `final` (abstract bases excepted).
- **CODE-3** DTOs are `final readonly class`, constructed with named arguments, with a `static fromArray()` factory and optional `toArray()`. No setters, no business logic. DTOs cast input types (web forms send strings).
- **CODE-4** Every parameter, return and property is typed. Arrays carry generic PHPDoc (`list<OrderItemDTO>`, `array<string, mixed>`, `LengthAwarePaginator<int, Order>`).
- **CODE-5** Dependencies use constructor property promotion. A class whose properties are all promoted and immutable (services, actions, gateways, DTOs) is declared `final readonly class` with plain `private` promoted properties. Classes that can't be readonly — because the parent or a trait has mutable properties (controllers, FormRequests, jobs, listeners, commands, models) — use `private readonly` on each promoted property.
- **CODE-6** Backed enums for statuses, types and queue names, cast on the model. No magic strings or numbers. Money is integer minor units (cents).
- **CODE-7** Early returns, no `else` after `return`/`throw`, methods ≤ ~20 lines, one level of abstraction per method.
- **CODE-8** Strict comparison (`===`), no `@` suppression, no `compact()`/`extract()`, no `env()` outside config files.
- **CODE-9** Every relation used by a Resource or a view is eager-loaded in the repository (no N+1).
- **CODE-10** User-facing messages (flash, exceptions shown in UI) go through `__()` translation keys in web projects.

### Queues
- **QUE-1** Every job and queued listener declares `$tries`, `$backoff` and `$timeout` explicitly, and implements `failed()`.
- **QUE-2** Jobs are idempotent: running the same job twice gives the same result (check state before acting, use unique keys).
- **QUE-3** Payloads are small and serializable: scalar IDs or DTOs. Never Eloquent collections, closures, requests or files. If a single model is passed, set `$deleteWhenMissingModels = true`.
- **QUE-4** Queue names come from the `QueueName` enum — no string literals.
- **QUE-5** `ShouldBeUnique` when a duplicate dispatch is harmful; `WithoutOverlapping` when two runs on the same record would conflict.
- **QUE-6** Slow or external work (mail, PDFs, third-party APIs, imports/exports) never runs inside an HTTP request — it goes to a job, with `RateLimited` / `ThrottlesExceptions` middleware for third-party APIs.
- **QUE-7** Commands that process many records never load everything into memory: the repository uses `lazyById()` / `chunkById()`, or the service dispatches one job per record.

### Configuration (must exist and pass)
- **CFG-1** `AppServiceProvider::boot()` always enables `Date::use(CarbonImmutable::class)`, and — when the project uses a database — `Model::shouldBeStrict(! $this->app->isProduction())` and `DB::prohibitDestructiveCommands($this->app->isProduction())`.
- **CFG-2** Bindings are registered only in `BindingServiceProvider::$bindings`.
- **CFG-3** `composer check` (Pint + Rector dry-run + Larastan + tests incl. arch tests) is green before any task is reported done.
- **CFG-4** When the project has jobs or queued listeners: the production queue connection is not `sync`; the `failed_jobs` table exists; with Redis queues, Horizon has supervisors matching the `QueueName` priorities.
- **CFG-5** Every rule applies whenever its subject exists in the project. A rule whose subject doesn't exist (no DB, no queues, no web views, no external API) is skipped and not reported. The moment the subject is added, the rule becomes mandatory.

---

## 4. Reference files — read before writing that kind of code

The templates in `references/` are the canonical shape of every file this skill produces. **Read the matching file before generating, refactoring or fixing that kind of code — never write it from memory.** Read only what the task needs.

| File | Read when | Contains |
|---|---|---|
| `references/shared-layers.md` | any Eloquent-backed domain work | DTO, FormRequest, model + migration + factory, repository, service, enums, exceptions + central renderer, supporting classes (event, policy, show/cancel requests, item resource, notification, other-domain contracts, translations), bindings, `AppServiceProvider` |
| `references/external-api-gateway.md` | the data source is an external API (ARC-15) | context DTO, base provider request, auth middleware, gateway contract + implementation, provider exceptions, `config/services.php`, a full domain on the gateway (DTO, contracts, services, request, controller, resource), bounded list request, routes, gateway & security tests, `withBitrixCaller()` |
| `references/http-entry-points.md` | controllers, Resources, routes, Livewire | API controller + Resource + routes, Blade controller, Inertia controller + flash sharing, web routes, Livewire rule |
| `references/background-work.md` | Actions, jobs, queues, listeners, commands, scheduling | Action, job (retries, middleware, `failed()`), queue priorities, listener, command, schedule |
| `references/testing.md` | writing or reviewing tests | `phpunit.xml` testsuites, `tests/Pest.php`, service unit test, complete API feature file, security tests, Blade + Inertia tests, job / action / command tests, test rules |
| `references/arch-tests.md` | first setup in a project, adding a domain or layer, reviews | the full `tests/Arch/ArchitectureTest.php`, grouped by mode |
| `references/tooling.md` | first setup, migration-plan step 3, or `composer check` failing on config | `phpstan.neon`, `pint.json`, `rector.php` (with its skips), baseline-first rule, composer scripts |

Typical combinations:
- **New Eloquent feature** → `shared-layers` + `http-entry-points` + `testing` (+ `background-work` if it has jobs, actions or commands).
- **External-API feature** → `external-api-gateway` + `http-entry-points` + `testing`, plus from `shared-layers`: `BusinessException`, the central renderer, `BindingServiceProvider`, `AppServiceProvider` (without the renderer, provider exceptions come back as 500 instead of their own status).
- **First time in a project** → `tooling` + `arch-tests` + `testing` (its `phpunit.xml` part).
- **Code review** → the rules in this file are enough to find violations; open the matching reference only to write the corrected code.

---

## 5. Workflows

### A. Generate a new feature
1. Run section 0 and state the detected stack, delivery mode(s) and queue setup.
   Then read the reference files the feature needs (section 4).
2. Create files in this order: migration → enum(s) → model (`$casts`, relations, `$fillable`, `@property` docs) → factory with every state the tests use → policy → DTO(s) → repository contract + Eloquent repository → action(s) if justified → service contract + service → domain exceptions → event(s) → job(s) / listener(s) → bindings → FormRequest(s) → entry points for each requested mode (Resource + API controller and/or Web controller + views/pages, commands) → routes + schedule → translation keys → tests (`references/testing.md`).
3. Run `composer check`, fix everything, then report: files created, endpoints/pages/commands/jobs added, and any decision the user should know about.

### B. Refactor existing code
1. Run section 0, then read the reference files for the layers you will touch (section 4).
2. **Write characterization feature tests first** for current behavior (status codes, JSON shape or redirects/flash/view data, DB side effects, dispatched jobs). They must pass before any change.
3. Classify each line of the fat controller/model/job/command:
   - validation / authorization → FormRequest
   - input mapping → DTO
   - queries, eager loading & persistence → Repository
   - rules, conditions, transactions, events, cross-domain calls → Service
   - a step duplicated across services → Action (ARC-14)
   - slow or external work inside the request → Job dispatched by the service (QUE-6)
   - logic inside existing jobs, listeners or commands → Service; the entry point becomes a one-line call (ARC-12)
   - response shaping → Resource (API/Inertia) or view (Blade)
4. If API and web controllers duplicate logic, merge it into one service method (ARC-10).
5. Move in small steps; run tests after each. External behavior stays identical unless the user approves a change.
6. Add bindings, run `composer check`, report a before/after summary per file with the rule IDs that were violated.

### C. Code review
Run section 0, then check every file against section 3. Report only rules whose subject exists in the project (CFG-5). Output a table, most severe first:

| Severity | File:line | Rule | Problem | Fix |
|---|---|---|---|---|

- 🚨 **Critical** — security & data integrity: SEC-1…SEC-5, SQL/commands built from input, data loss or corruption, wrong results returned to users, non-idempotent jobs with external side effects (charges, creates, sends).
- 🔴 **Blocker** — architecture: ARC-1, ARC-3, ARC-5, ARC-6, ARC-7, ARC-10, ARC-12, ARC-13, ARC-15, QUE-2, QUE-3, QUE-6, N+1, raw models in Inertia props.
- 🟠 **Major** — strictness: CODE-1…CODE-6, ARC-2, ARC-4, ARC-8, ARC-9, ARC-11, ARC-14, QUE-1, QUE-4, QUE-5, QUE-7, missing tests for a business rule.
- 🟡 **Minor** — naming, readability, CODE-7, CODE-8, CODE-10, PHPDoc gaps.

After the table, show corrected code for every Critical and Blocker, written from the matching reference template. End with a verdict: *Approve*, *Approve with changes* or *Request changes*. Any Critical means *Request changes*.

**Migration plan** — add it when the review has more than ~10 findings or the project predates this skill. It is an ordered list of small PRs:
1. Each Critical, alone, with a regression test.
2. Remaining authorization gaps and input bounds.
3. Tooling: Pint, Rector, Larastan with a baseline, `strict_types`, `final`. Start with a commit that only runs Pint + Rector on the untouched code (`references/tooling.md`).
4. Context DTOs + FormRequests with `toDto()`.
5. Domain exceptions + central renderer (keep the response shape clients already read).
6. Interfaces, repositories/gateway, `BindingServiceProvider`.
7. Unit tests for every rule branch.
8. Raise the Larastan level and shrink the baseline.

Each PR names the rule IDs it closes and keeps the existing feature tests green. The plan never relaxes a rule: new or touched code is fully compliant from the first PR; only untouched legacy code waits for its phase.

### D. Write tests
Follow `references/testing.md`. Every service method gets unit tests for each rule branch; every endpoint/page gets feature tests for success, validation, authorization and each business exception in each delivery mode; every job, action and command gets the tests listed there.

---

## 6. Adapting to older versions

| Situation | Adaptation |
|---|---|
| Laravel 10 (no `bootstrap/app.php` exceptions) | Register the `BusinessException` renderer in `App\Exceptions\Handler::register()` with `$this->renderable(...)`; register `BindingServiceProvider` in `config/app.php`. |
| Laravel 10 jobs & scheduling | Use the `Dispatchable, InteractsWithQueue, Queueable, SerializesModels` traits instead of `Illuminate\Foundation\Queue\Queueable`; schedule in `App\Console\Kernel::schedule()` instead of `routes/console.php`. |
| Laravel 11+ | Register `BindingServiceProvider` in `bootstrap/providers.php`. |
| PHP 8.2 | `readonly` classes are available; avoid typed class constants (8.3). |
| PHP 8.1 | Use `readonly` **properties** instead of `readonly class`; drop `toBeReadonly()` from the DTO arch test. |
| Pest 2 | Remove `arch()->preset()` lines; the rest works. |
| No Sanctum (API) | Use the project's existing API guard. |

Never upgrade framework or major package versions as a side effect of a task — propose it separately.

---

## 7. Definition of done

- [ ] Section 0 detection stated; code matches the project's delivery mode(s) and queue setup.
- [ ] Every new/changed file follows sections 2–3 and the matching template in `references/`.
- [ ] Bindings added to `BindingServiceProvider`.
- [ ] SEC-1…SEC-5 checked for every endpoint touched; each SEC-2 / SEC-3 has a test.
- [ ] Jobs/listeners declare tries, backoff, timeout and `failed()`, use a `QueueName` queue, and are idempotent; recurring commands are scheduled.
- [ ] Unit tests for every service rule branch, job and action; feature tests per delivery mode for success, validation, auth and each business exception; command feature tests.
- [ ] No dangling references: every class, method, route, policy ability, factory state and translation key used by the new code exists.
- [ ] Arch tests present, matching what the project has, and registered in `phpunit.xml` (they actually run).
- [ ] `composer check` is green (if you cannot run it, say so and list the commands to run).
- [ ] Final summary lists files touched, rule IDs addressed, and any deviation with its reason.
