# Thabekhulu Development Software

Multi-company property development and construction ERP for Steve Maqueens and
Thabekhulu Development Group, built by Mayura Consultancy Services.

It manages the full development cycle — **plan, fund, secure land, approve, build,
sell/rent, close out** — across companies, regions and projects nationwide.

## Documentation

| Document | What it covers |
|---|---|
| [docs/installation.md](docs/installation.md) | Step-by-step install on a developer's machine and on cPanel, with checks and troubleshooting |
| [docs/developer-guide.md](docs/developer-guide.md) | How the codebase is organised, the shared machinery, conventions and traps |
| [docs/modules.md](docs/modules.md) | Every module: purpose, tables, business rules and permissions |
| [docs/deployment.md](docs/deployment.md) | Hosting, queues, and packages installed at deployment |
| [docs/adr/](docs/adr/) | The decisions behind the architecture |
| [docs/assumptions.md](docs/assumptions.md) | Business assumptions awaiting the client's confirmation |

## Applications

| App | Path | Users | Stack |
|---|---|---|---|
| Management web app | `resources/js` | Directors, PMs, QS, finance, procurement, admins | React 19 + Inertia v3 + Tailwind 4 |
| Site app (PWA) | `site-app` | Site managers, H&S officers, contractors | React 19 SPA, TanStack Router/Query, Dexie (offline) |
| API | `routes/api.php` | Site app, integrations, future native apps | Laravel 13, Sanctum, `/api/v1` |

Shared code: `packages/ui` (design system) and `packages/shared` (types + Zod schemas).

## Getting started (Docker)

```bash
cp .env.example .env
docker compose up -d
docker compose exec app composer setup      # install, key, migrate + seed, build assets
```

- App: http://localhost:8080 — Super Admin credentials come from `SUPER_ADMIN_*` in `.env`
- Demo company admin (local only): `companyadmin@thabekhulu.local` / `password`
- Mailpit: http://localhost:8025

Site app during development:

```bash
npm run dev --workspace site-app   # http://localhost:5174 (proxies /api to :8080)
```

## Quality checks

```bash
composer lint       # Pint code style
composer analyse    # Larastan level 8
composer test       # Pest
npm run typecheck   # TypeScript (both apps)
```

CI runs all of the above on every push and pull request.

## Architecture in one page

- **Standalone codebase** — not built on the GMLM core (see ADR-0001).
- **Multi-company, single database** — every company-owned model uses the
  `BelongsToCompany` trait; queries are scoped to the current company and **fail
  closed** when no company is set (ADR-0002).
- **Super Admin** creates companies, sets quotas (projects, users, storage) and
  enables modules per company. Company Admins manage everything inside those limits.
- **Domain folders** — `app/Domains/<Domain>/{Models,Enums,Services,Http,...}`.
- **Roles** — spatie/laravel-permission with teams keyed on `company_id`.
- **Audit** — spatie/laravel-activitylog on business models.
- **Timestamps** stored in UTC, displayed in SAST (`APP_DISPLAY_TIMEZONE`).

See `docs/adr/` for the decisions and their reasoning.
