# Changelog

## [0.4.0] — Projects and stage gates (Sprint 4)

### Added
- Project register: create and edit projects (type, province, town, region, value, dates, project manager), automatic PRJ codes, stage filters and search.
- Project page with the development-cycle stage track and four sections: stage gate, programme, tasks, risks and issues.
- Stage gates: SA-based checklist per stage; approvers sign off to move a project on; approval history with comments; earlier stages locked; project manager notified.
- Programme milestones with planned and actual dates and days-late tracking.
- Tasks with assignee, due date, priority and status; assignees notified and can complete their own tasks.
- Risk and issue register on a 5 x 5 matrix with owners and status.
- My Day now shows your open tasks, gates waiting for your approval, overdue-task and high-risk alerts; development line links to filtered projects.
- Search results open the project; Projects appears in the sidebar.

## [0.3.0] — Platform foundation complete (Sprint 3)

### Added
- Profile page: update name, email and SA mobile number; change password.
- Two-factor authentication: set up with an authenticator app (QR code), confirm, recovery codes, turn off. Password re-confirmation before enabling.
- Notification centre with unread badge in the header; mark one or all as read.
- Global search (Ctrl+K) across projects, people (Company Admins) and companies (Super Admins), respecting company isolation.
- Audit log screen for Company Admins and Directors; every audit entry is stamped with its company.
- `docs/assumptions.md` — assumptions to confirm in discovery.

### Changed
- Site app split into cached chunks (largest chunk 219 KB, was 547 KB).

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
