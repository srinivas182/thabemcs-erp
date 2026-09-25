# Changelog

## [0.24.0] — Website content management (Sprint 24)

### Added
- Pages built from 13 block types (hero, services, developments, track record, process, team, testimonials, gallery, questions, text and image, forms, call to action, introduction), edited in the back office.
- Draft, publish, schedule and take down, with a version history of the last 20 saves and one-click restore.
- Media library for website images and PDFs, with alt text for screen readers and search engines.
- Navigation and footer menus built from published pages.
- Articles with categories and authors.
- Form builder whose entries become buyer or tenant records with source "Website", with POPIA consent recorded and notifications to chosen people.
- A Marketing role that can manage content and nothing else.
- Unknown blocks and fields are stripped on save, and application addresses cannot be taken by a page.

## [0.23.0] — Scale certification tooling and acceptance pack (Sprint 23)

### Added
- `scale:seed`: builds a full-size test database (25,000 projects, 100,000 users and the records that hang off them) with bulk inserts, resumable and refused in production.
- `perf:measure`: times the work behind the dashboard, command centre, heaviest reports, profitability and critical path, with the query count for each.
- k6 load tests for people working (`load-tests/browsing.js`) and phones syncing in bursts (`load-tests/site-app.js`), with the agreed thresholds built in.
- `docs/performance.md`: targets, method, and tables to record server-side timings, load runs and the full-size restore rehearsal.
- `docs/uat-plan.md`: acceptance scripts for all nine roles, the assumption-confirmation sessions to book, and the sign-off table.

## [0.22.0] — Security and resilience (Sprint 22)

### Added
- Security headers on every response, including a content security policy that only allows scripts from this site, and a request id carried through the logs.
- Two-factor authentication required for Company Admins, Directors, Finance and Development Managers; they are sent to set it up before they can work.
- Sign out of every other device, with password confirmation.
- Nightly encrypted database backup with 30-day retention, and `backup:verify`, which restores the latest backup into a scratch database, counts key tables and times it.
- `docs/disaster-recovery.md`: what is backed up, how to restore, proposed recovery targets and a log for restore rehearsals.
- Dependency vulnerability scanning (composer audit and npm audit) on every push.
- In-app help for every page, opened with the button or the ? key.
- Accessibility: skip-to-content link, live region for messages, focusable main content.
- Rental invoices now go to Sage as customer invoices, alongside supplier invoices.

## [0.21.0] — Professional platform (Sprint 21)

### Added
- Opening data import from CSV for suppliers, employees, units, budgets and tenants, with downloadable templates, a check-first pass and a full rollback if any row is wrong.
- Company letterhead on every report, on screen and in print, and saved report layouts choosing which columns show and in what order, with one default per report.
- Notification preferences: email as things happen, or one daily summary at 07:15; everything still appears in the inbox.
- Read-only API tokens for other systems, and webhooks for six events (invoice approved, order issued, sale registered, lease started, incident reported, project completed), signed with HMAC-SHA256 and retried with a growing delay.

## [0.20.0] — Close-out, investor returns and reinvestment (Sprint 20)

### Added
- Close-out checklist per project (construction, statutory, handover, financial and records), with optional items, and a project can only be marked complete once every required item is done.
- Final account on the close-out page: approved feasibility against the final position, paid to date, distributed and undistributed profit.
- Investor positions per project: capital contributed, capital still owed, preferred return earned and total paid.
- Distribution waterfall — capital, then preferred return, then profit share — with a preview before anything is saved, approval by a Director, and payment recorded against each investor's funding.
- Reinvestment of returns into another project's funding.
- Investor returns report (fourteenth standard report).

## [0.19.0] — Rentals (Sprint 19)

### Added
- Leases over the same unit records used for sales, residential or commercial, fixed-term or month to month, with notice periods, escalations and deposits.
- Tenants with encrypted ID numbers, FICA and credit-check screening before approval.
- Monthly rental invoicing with escalations applied on each anniversary, extra recurring charges (utilities, parking, levies), and no double billing.
- Receipts allocated to the oldest unpaid invoice, and arrears aged current, 30, 60 and 60+ days.
- Deposits held in a named interest-bearing account with interest accrued monthly, and the refund due worked out on ending the lease.
- Incoming and outgoing inspections recording the condition of each area.
- Maintenance requests, with a private tenant link where tenants see their lease and account and report problems without signing in; urgent requests notify the letting team.
- Rent roll and tenant arrears reports (twelve and thirteen standard reports), and weekly arrears and lease-expiry reminders.

## [0.18.0] — Sales (Sprint 18)

### Added
- Stock schedule per project: erven, houses, sectional units and commercial units with prices (incl. VAT), price history, NHBRC enrolment and availability.
- Buyers from enquiry to registration, with encrypted ID numbers and FICA verification.
- Reservations with deposits that expire automatically and release the unit.
- Sale agreements: price, deposit held in a named trust account, bond details, suspensive conditions with due dates, and automatic move to unconditional (or lapse on failure).
- Transfer pipeline of nine steps through to registration in the Deeds Office, which transfers the unit.
- Agency commission worked out on the price excluding VAT, payable after registration and blocked without a valid Fidelity Fund Certificate.
- Sales feed profitability and cash flow: sold units at agreed prices plus unsold stock at list price replace the feasibility's revenue line.
- Sales schedule report (eleventh standard report) and an estate agency supplier type with FFC compliance.

## [0.17.0] — Scale II: infrastructure (Sprint 17)

### Added
- Read replica support for reports and dashboards (`DB_READ_HOST`), with sticky reads after a write.
- Separate Redis databases for cache, sessions and queues, so a full cache cannot evict sessions and clearing the cache cannot drop queued jobs; permissions cached in Redis.
- Named queues (`metrics`, `reports`, `maintenance`, `integrations`, `mail`, `default`) with workers split per queue, and scheduled work queued per company and per project instead of looping in one process.
- S3-compatible object storage for private files and a CDN host for built assets.
- `/health` readiness endpoint checking database, cache, queue and storage, for the load balancer and monitoring.
- OPcache preloading and production PHP settings; `deploy.sh` that migrates, caches config/routes/views/events, restarts workers and verifies health.
- Web server compression, upload limits and year-long caching of content-hashed assets.
- `docs/deployment.md` (hosting topology, queue layout, packages to install at deployment) and ADR-0005.

## [0.16.0] — Scale I: application (Sprint 16)

### Added
- Precomputed project metrics and stored critical path, refreshed by queued jobs on change and nightly.
- Search-as-you-type lookups replacing 25 whole-table dropdowns.
- Report designer filters and sorting pushed into the database, with an explicit note when a report is cut short.
- Company-scoped caching with an architecture test, ETags on the site app API, and indexes for company-wide queries.

## [0.15.0] — Reporting and integrations (Sprint 15)

### Added
- Report designer: choose a dataset, columns and their order, conditions, sort and totals; saved reports can be viewed, printed, exported and scheduled like standard reports.
- Three more standard reports (ten in total): variation register, programme status, supplier compliance.
- Command centre: live projects on a map coloured by health, with the reasons listed; group view for Super Admin.
- Form builder with drag-and-drop ordering and seven question types; forms are versioned; filled in offline on the site app (with photos) and listed per project with pass/fail.
- Sage Business Cloud Accounting (South Africa) connection: sends approved supplier invoices with the mapped account and VAT type; SimplePay connection: sends overtime, allowances and approved leave. Encrypted credentials, connection test, send log, and nothing sent twice.

## [0.14.0] — Commercial and compliance completion (Sprint 14)

### Added
- Requests for quotation by email: suppliers get a private link to view the items (never the estimates), submit or revise their quote with a document until closing, or decline; buyers see who has opened, quoted or declined, and are notified when quotes arrive.
- Contract form defaults for JBCC PBA and MWA, NEC4 ECC, GCC 2015, FIDIC and own forms (retention, payment days, defects period), editable per contract.
- Project Performance page: earned value (SPI, CPI, forecast final cost, variance at completion) with a planned-value S-curve and weekly history; profitability against the approved feasibility, with cost codes already over budget.
- Programme calendar skips the December building shutdown.
- Workforce allowances (per day worked, per month, once-off) included in the payroll inputs export; private employment documents with expiry dates.
- POPIA page: register of personal information, retention rules with a monthly clean-up (and a run-now button), data subject requests with due dates and an export of what is held.

## [0.13.0] — Programme and site completion (Sprint 13)

### Added
- Programme per project: activities with WBS, durations, earliest start, responsible person and contractor; finish-to-start links with lag; critical path on working days excluding SA public holidays; Gantt chart with progress, links and a today line; loops refused; forecast completion against planned.
- Progress updates stamp actual start and finish automatically.
- Daily escalation of tasks more than two working days overdue to the project manager.
- Company master data: cost code library and units, with a standard South African starting set; budgets pick cost codes from the library and requisitions pick units.
- Meetings and minutes (site, progress, design, safety, client): numbered, printable, locked when issued; action items become tasks and owners are notified.
- H&S legal appointments register (OHS Act and Construction Regulations 2014) with gaps and competency expiry; safety file checklist.
- Site app: automatic weather in the diary; crew register for workers without logins; goods received against purchase orders with QR scanning and a delivery-note photo; snags with photos; quality and safety inspections; site instructions. All work offline.
- QR code on purchase orders for receiving on site; crew register summary on the project Site page.

## [0.12.1] — Sign-in page polish

### Added
- Branding from `.env` (config/branding.php): name, short name, owner, tagline, optional logo file, support email, phone and hours, privacy notice link, "built by" line.
- Brand mark (or the client's logo) on all sign-in, password and two-factor screens, including on phones.
- Authorised-use and POPIA notice, support contact, and a link to the site app on the sign-in screens; notice added to the site app sign-in.
- Show or hide password, Caps Lock warning, and status messages (for example after a password reset) on the sign-in page.
- Search engines asked not to index the system (robots.txt and a noindex tag).

## [0.12.0] — Reports and dashboards (Sprint 12)

### Added
- Portfolio dashboard: live projects with stage, budget used, payments, high risks, open incidents and snags; company totals incl. amounts owed to suppliers, approvals waiting and days since a lost-time injury.
- Group portfolio for the Super Admin across all companies.
- Standard reports: cost report, commitments, supplier age analysis, retention schedule, safety statistics, leave register, plant utilisation.
- Report filters (project, period, as at), print-ready layout for PDF, Excel (.xlsx with rand, percent and date formats) and CSV downloads; downloads are audit-logged.
- Scheduled reports emailed weekly or monthly as Excel or CSV, only to recipients allowed to see them.

## [0.11.0] — Certificates, cash flow, workforce, plant and exports: Release 2 complete (Sprint 11)

### Added
- Construction contracts per project (JBCC PBA/MWA, NEC4, GCC 2015, FIDIC, own form) with contract sum, retention rate, retention limit and release at practical completion; contractor compliance checked on appointment.
- Interim payment certificates (PC-001 ...): value to date, retention (with limit and release at practical and final completion), previous certificates, amount due and VAT; approved through the approval engine.
- Contractor invoices matched to certified certificates.
- Cash flow per project: forecast from the approved feasibility against actual payments, with a cumulative S-curve.
- Workforce: employees (encrypted ID numbers), site allocation history, leave with BCEA balances and SA public holidays, overtime with BCEA daily and weekly limits and 1.5x/2x rates.
- Plant and equipment register: owned and hired plant, moves between sites, services with next-service dates, breakdowns and off-hire.
- Accounting and payroll CSV exports: supplier invoices (Sage layout), supplier payments, payroll inputs (overtime and leave).

## [0.10.0] — Finance: budgets, invoices, payments, variations (Sprint 10)

### Added
- Project budget by cost code: created from the approved feasibility or imported from a CSV bill of quantities (SA number formats understood); add codes manually.
- Budget view: original, variations, revised, committed (approved POs), direct invoices, available and percentage used.
- Purchase orders are charged to a cost code; approval of a PO checks the budget and sends warnings at 80%, 90% and 100%.
- Variation orders (VO-001 ...) with reason, amount (or saving), time impact and link to a site instruction, approved through the approval engine; approved variations revise the budget.
- Supplier invoices: capture with copy, duplicate detection, three-way match (order, goods received, invoice) and VAT checks.
- Invoice approval with segregation of duties; Director override with reason for match failures; rejection with reason.
- Payment runs: batch approved invoices due by a date, leave out non-compliant suppliers, Director approval, payment schedule CSV, mark paid with a final compliance check.

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
