# Changelog

## [0.2.0] — Platform administration (unreleased)

### Added
- Super Admin company management: create companies with CIPC/VAT validation, set project/user/storage limits, enable modules, suspend and reactivate.
- New companies automatically invite their first Company Admin by email.
- "Work in company" for Super Admins, with a banner and one-click return to the platform view.
- Company Admin "People" screen: invite users with a role, change roles, deactivate and reactivate; user limit enforced.
- Invitation emails with a secure set-password link.
- Navigation shows Companies (Super Admin) and People (Company Admin).
- CI posts failure reports on pull requests.

### Security
- Platform routes limited to Super Admins; people management limited to Company Admins.
- Users in other companies return 404; admins cannot deactivate themselves.

## [0.1.0] — Phase 1 scaffold (unreleased)

### Added
- Laravel 13 / PHP 8.4 application with domain folder structure.
- Multi-company tenancy: `CurrentCompany` context, fail-closed `CompanyScope`, `BelongsToCompany` trait.
- Companies with quotas and per-company module toggles; regions with SA provinces.
- Super Admin "act as company", with audit logging.
- Authentication with Fortify (sign in, password reset, 2FA); inactive users and suspended companies blocked.
- 11 default company roles (spatie/laravel-permission, teams on `company_id`).
- Projects domain reference implementation with the 7-stage development cycle and quota enforcement.
- Management app (React + Inertia): sign-in screens, app shell, My Day home page.
- Site app (PWA): offline daily diary with sync outbox.
- Shared design system and Zod schemas.
- Docker Compose environment; GitHub Actions CI (Pint, Larastan L8, Pest, TypeScript, builds).
