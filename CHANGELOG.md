# Changelog

## [0.9.0] — Approval engine and procurement (Sprint 9)

### Added
- Approval engine with delegation-of-authority bands, sequential steps by role, no self-approval, one step per person, reasons required for rejection.
- Approvals inbox (sidebar) for everything waiting for you, your own requests and their progress; My Day lists pending approvals.
- Delegation while away (up to 90 days), recorded as "approved by X for Y".
- Hourly escalation of approvals waiting more than 48 hours.
- Requisitions with line items and estimates, submitted for approval.
- Quotes per requisition with uploaded quotation documents; lowest-quote marking; supplier compliance shown per quote.
- Award rules: three quotes above R30 000 or a single-source reason; reason required when not choosing the cheapest.
- Purchase orders: drafted from the award with line prices, VAT at 15% for VAT vendors, approval trail, issue to supplier, cancellation.
- Compliance and CIDB checks at award, submission and issue.
- Goods received notes (GRN) with partial receipts, over-receipt prevention and links to site-app deliveries.

## [0.8.0] — Site app and health & safety (Sprint 8)

### Added
- Site app (PWA at `/site`): sign-in (with two-factor), project picker, works offline with an automatic sync queue and a "not sent yet" list.
- Capture on the phone: daily diary, sign in/out with GPS and selfie, geotagged progress photos, deliveries (condition, delivery note), incident reports.
- Photos compressed on the phone before upload; retries never duplicate records (client IDs).
- Site API (`/api/v1/site/...`) for projects, suppliers, diary, attendance, photos, deliveries, incidents.
- Geofenced attendance: distance from the site point recorded; sign-ins outside the radius flagged.
- Project site location and sign-in radius on the project form.
- Site page per project: diary, attendance (7 days), photo gallery, deliveries, numbered site instructions (SI-1, SI-2 ...), quality inspections and snag list (open, fixed, verified).
- Health and safety page: key figures, incident register with investigation, reportability to the Department of Employment and Labour, toolbox talks, safety inspections.
- Notifications for serious incidents and problem deliveries.

## [0.7.0] — Suppliers and documents: Release 1 complete (Sprint 7)

### Added
- Contractor and supplier registry: type, CIPC, VAT, CIDB CRS number, grade and class, B-BBEE level, contacts; suspend with reason.
- Compliance documents per supplier type (tax compliance PIN, CIDB, COIDA, B-BBEE, bank confirmation, insurance, H&S file and more) with expiry dates, uploaded copies and verification.
- Suppliers with missing or expired blocking documents are shown as blocked; `ComplianceService::ensureCanTransact()` is ready for procurement and payments.
- CIDB grade check against contract value.
- Daily expiry notifications at 30, 14 and 7 days, and on expiry; My Day alert for expired and expiring documents.
- Supplier performance ratings (quality, time, safety) per project.
- Document management: folders per project, upload with progress, version history, drawing register with numbers, disciplines and revisions.
- Role-restricted documents; private storage with permission-checked, audited downloads.
- Company storage limit enforced on uploads.

## [0.6.0] — Land, statutory approvals and professional team (Sprint 6)

### Added
- Land pipeline: sites from identified through due diligence, offer, acceptance and transfer, with key dates; link land to a project.
- SA land due-diligence checklist (13 checks) with clear / issue / not applicable results, notes and who checked; offers can't be accepted with open issues.
- Statutory approvals register across projects: town planning, building plans, engineering services, environmental, water use, heritage, fire, NHBRC, construction work permit, occupancy.
- Decision tracking, conditions, validity dates; My Day alerts for approvals lapsing within 60 days and overdue decisions.
- Professional team per project with the correct SA council per discipline, registration verification, fee basis and agreed fee.
- Fee claims: recorded by the project team, approved by the QS, paid by Finance; claims over the agreed fee are flagged.
- Land and Approvals in the sidebar; Approvals and Team on the project page.

## [0.5.0] — Feasibility, funding and investors (Sprint 5)

### Added
- Development feasibility per project with multiple scenarios (base case, slower sales and so on), created from a standard SA appraisal template or copied from another scenario.
- Timed cost and revenue lines by heading (land, acquisition, professional fees, municipal, construction, contingency, marketing, finance, other, revenue); amounts or percentages of construction or revenue.
- Results: revenue, cost, profit, margin on revenue, profit on cost, peak funding requirement and month, annualised IRR, and a monthly cash-flow chart.
- Baseline approval: one approved, locked scenario per project.
- Funding page per project: funding requirement from the baseline vs committed sources, gap to raise, money received and paid out per source.
- Funding sources: developer equity, investor capital, loans (with interest rate), grants; agreement date and status.
- Project bank account (last four digits only).
- Investor register with encrypted ID/registration numbers and FICA verification.
- Feasibility and Funding links on the project page; Investors in the sidebar.

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
