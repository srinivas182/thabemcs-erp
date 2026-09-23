# Developer guide

How this codebase is put together and how to work in it. Read this once before your first change;
`docs/modules.md` then describes what each module does.

As at Sprint 21: 110 tables, ~24,000 lines of PHP across 27 domains, 71 management screens, a site PWA,
and 199 automated tests.

---

## 1. What the system is

A property-development ERP for Thabekhulu Development Group (South Africa). One system follows a
development from land and feasibility, through funding, approvals, procurement and construction, to
selling or letting the finished units, and finally closing out and paying investors.

It is **multi-company**: one Thabekhulu group with several operating companies in a single database.
Every company's data is separated in the application, not by having separate databases.

## 2. Stack

| Part | Choice |
|---|---|
| Backend | Laravel 13, PHP 8.4 |
| Database | MySQL 8 (SQLite in tests) |
| Cache, sessions, queues | Redis (separate databases) |
| Management app | React 19 + TypeScript + Inertia v3 + Tailwind 4 + shadcn/ui |
| Site app | React 19 SPA (own PWA), TanStack Router/Query, Dexie (IndexedDB) |
| Testing | Pest v5, Larastan level 8, Pint |

`docs/deployment.md` covers hosting, queues and what to install at deployment.

## 3. Running it

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # seeds roles, then a super admin from config/platform.php
npm run dev                         # management app
npm run dev --workspace site-app    # site PWA at /site
php artisan queue:work --queue=metrics,default
```

Before pushing, run what CI runs:

```bash
vendor/bin/pint            # formatting
vendor/bin/phpstan analyse # level 8
vendor/bin/pest            # tests
npm run typecheck && npm run build
```

## 4. Repository layout

```
app/Domains/<Domain>/      One business area: Models, Services, Http/Controllers, Jobs, Reports
app/Support/               Cross-cutting building blocks (tenancy, cache, ULIDs)
app/Http/Middleware/       Company context, module gating, Inertia sharing, ETags
app/Providers/             Permissions (gates) and model listeners
config/                    Business rules that must be changeable without code
database/migrations/       26 migrations, roughly one per sprint group
resources/js/pages/        Management screens, one folder per area
resources/js/components/   Shared UI (data helpers, lookup field, brand mark)
packages/ui, packages/shared  Design tokens and types shared with the site app
site-app/                  Offline site PWA, built into public/site
tests/Feature/             Feature tests, one folder per area
docs/                      This guide, modules, deployment, ADRs, assumptions
```

**Domains are the unit of organisation.** A domain owns its tables, business rules and screens.
Cross-domain work goes through the other domain's service, never by reaching into its tables.

## 5. The five things you must understand

### 5.1 Company separation (multi-tenancy)

Everything company-owned uses `App\Support\Tenancy`:

- `BelongsToCompany` trait on the model: stamps `company_id` on create and applies `CompanyScope`.
- `CompanyScope` **fails closed**: with no company context, company-owned queries throw rather than
  returning everything.
- `CurrentCompany` is a scoped binding (reset per request, safe under Octane).
- `SetCurrentCompany` middleware sets it from the signed-in user, **and must run before
  `SubstituteBindings`** (see `bootstrap/app.php`) or route model binding would look outside the company.

Working outside a request — a queued job, an artisan command — you must set the context yourself:

```php
$context->runFor($company, fn () => /* ... */);
```

Deliberately crossing companies (platform reports, a tenant's public link) needs
`withoutGlobalScope(CompanyScope::class)` and should be obvious in the code.

### 5.2 Permissions

Roles come from `App\Domains\Platform\Enums\Role` (11 roles) with spatie/laravel-permission, **keyed on
`company_id`** so the same person can hold different roles in different companies.
`SetCurrentCompany` calls `setPermissionsTeamId()`.

Authorisation is by **gate**, defined in `AppServiceProvider` (38 of them: `manage-procurement`,
`view-sales`, `close-projects`…). Controllers call `Gate::authorize('…')`; the front end receives the
same answers in the shared `can` object for showing or hiding controls. **Never rely on the front end
for permission** — the gate in the controller is the real check.

### 5.3 Precomputed figures and caching

Anything shown across many projects reads `project_metrics`, refreshed by the queued
`RefreshProjectMetrics` job when data changes and nightly for date-dependent values. The critical path
is stored on `programme_activities` the same way.

**If you add a figure to a dashboard, add it to the metrics refresh — not to the page.** Tests in
`tests/Feature/Scale` fail if a page's query count grows with the number of projects.

All caching goes through `App\Support\Cache\CompanyCache`, which puts the company in every key. An
architecture test forbids using the `Cache` facade inside `app/Domains`.

### 5.4 Queues

Named queues: `metrics`, `reports`, `maintenance`, `integrations`, `mail`, `default`. Scheduled work
is queued **per company** (`RunCompanyMaintenance::fanOut()`), never looped in one process.

Eleven scheduled commands, all in `routes/console.php`:

| Command | When |
|---|---|
| `metrics:refresh` | 04:30 daily |
| `reports:send-scheduled` | 06:00 daily |
| `suppliers:compliance-alerts`, `sales:release-reservations` | 07:00 daily |
| `notifications:digest` | 07:15 daily |
| `tasks:escalate`, `rentals:reminders` | weekdays |
| `approvals:escalate` | hourly |
| `programme:snapshot` | Mondays |
| `rentals:bill` | 24th monthly |
| `popia:retention` | 1st monthly |

### 5.5 Nothing sends whole tables

Dropdowns use `GET /lookup/{type}` (max 20 matches) with the `LookupField` component. Lists paginate.
Reports state when they are cut short. If you find yourself passing a full table to a page, use a lookup.

## 6. Conventions

**Models** carry `@property` docblocks, an explicit `$fillable`, `casts()`, and `BelongsToCompany`.
Public identifiers are ULIDs (`HasPublicUlid`) so database ids never appear in URLs. Secrets
(webhook secrets, tenant link tokens) are set with `forceFill`, never mass-assigned.

**Controllers stay thin**: authorise, validate, call a service, redirect with a message. Business rules
live in services and are tested through them.

**Money** is stored as `decimal` and cast; VAT is 15% and configurable per document type. Amounts in
reports are excluding VAT unless a column says otherwise.

**Configuration over code** for anything the client may change: `config/` holds 34 files, including the
stage-gate checklists, supplier compliance matrix, delegation-of-authority bands, contract forms, POPIA
retention, sales and rental rules. Changing a business rule should rarely need a code change.

**Front end**: one page component per screen, `AppLayout` for the shell, shared helpers in
`components/data.tsx` (`formatRand`, `formatDate`, `PageHeader`, `SelectField`, `Pager`). Design tokens
live in `packages/ui`.

## 7. Adding a feature — the usual path

1. Migration for new tables (company-owned tables get `company_id` and the right indexes).
2. Model with docblocks, fillable, casts, `BelongsToCompany`.
3. Service holding the rules, with clear exceptions for refusals.
4. Gate in `AppServiceProvider`, controller, route in `routes/web.php`.
5. Page under `resources/js/pages/`, wired to the shared `can` flags.
6. Tests: the happy path, each refusal, and the permission boundary.
7. If it changes project figures, extend `ProjectMetricsService`.
8. Record any business assumption in `docs/assumptions.md`.

## 8. Testing

Pest, one folder per area, SQLite in memory, queues run inline. Helpers in `tests/Pest.php`:

- `inCompany($company, fn () => …)` runs code in a company context;
- `userWithRole($company, Role::X)` makes a user;
- `compliantSupplier($company, 'Name')` makes a supplier with valid compliance documents.

Write tests in the client's language — "it refuses a purchase order when the supplier's tax clearance
has expired" — so the test list reads as a description of the system.

## 9. Traps that have already caught us

- **Middleware order**: `SetCurrentCompany` must precede `SubstituteBindings`.
- **Lazy loading is off.** Eager-load relations you use, including inside jobs and connectors.
- **Factories must fill every column** because models run in strict mode.
- **`setPermissionsTeamId()`** must be set before assigning or checking roles outside a request.
- **Test helpers must not shadow global functions** (`activity()` belongs to spatie).
- **`actingAs` persists** across requests within one test; call `auth()->logout()` to test a public page.
- **Dates in tests**: contributions, invoices and attendance in the future are ignored by services that
  work "as at today". Use relative dates.
- **Model events** drive metric refreshes; bulk `update()` on a query builder skips them deliberately.

## 10. Where to read next

| Document | What it covers |
|---|---|
| `docs/modules.md` | Every module: purpose, tables, rules, permissions |
| `docs/deployment.md` | Hosting, queues, packages to install at deployment |
| `docs/adr/` | Why the big decisions were made (ADR-0001 to 0005) |
| `docs/assumptions.md` | ~130 business assumptions awaiting the client's confirmation |
| `CHANGELOG.md` | What shipped in each sprint |
