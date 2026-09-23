# Modules

What each part of the system does, the tables it owns, the rules it enforces and who may use it.
Domains live in `app/Domains/<Name>/`. Read `docs/developer-guide.md` first for the shared machinery.

---

## Platform

Companies, users, roles, invitations, search, notifications, audit trail, quotas, branding, lookups,
imports, API tokens and webhooks.

- **Tables** `companies`, `regions`, `users`, `activity_log`, `notifications`, `personal_access_tokens`, `report_presets`, `import_runs`, `notification_preferences`, `webhooks`, `webhook_deliveries`
- **Key pieces** `LookupController` (search-as-you-type for every dropdown), `ImportService` (CSV opening data, checked first, all-or-nothing), `WebhookDispatcher` + `DeliverWebhook` (HMAC-signed, retried), `SendNotificationDigest`, `HealthController` (`/health`)
- **Rules** 11 roles, scoped per company. Notifications go to the inbox always, by email or daily digest by choice. API tokens are read-only. Imports never overwrite existing records.
- **Permissions** `manage-company-users`, `import-data`, `manage-integrations`, `view-audit-log`

## Projects

The spine: projects, seven stage gates, milestones, tasks and risks.

- **Tables** `projects`, `stage_gate_items`, `stage_transitions`, `milestones`, `tasks`, `risks`
- **Rules** A project moves to the next stage only when that gate's checklist is complete and someone authorised approves it (`config/stage_gates.php`). Tasks overdue by two working days escalate to the project manager.
- **Permissions** `manage-projects`, `approve-stage-gate`

## Feasibility

Development appraisals and the approved baseline every later comparison uses.

- **Tables** `feasibilities`, `feasibility_lines`
- **Rules** South African template (land, professional fees, construction, finance, marketing, contingency, revenue). Approving one makes it the baseline; profitability and the final account compare against it.
- **Permissions** `manage-feasibility`

## Funding

Where the money comes from: investors, funding sources, drawdowns and repayments, project bank accounts.

- **Tables** `investors`, `funding_sources`, `funding_movements`, `project_bank_accounts`
- **Rules** Investor ID numbers are encrypted; FICA verification is recorded. Funding sources carry preferred return and profit share used by the distribution waterfall.
- **Permissions** `manage-funding`

## Land

Land pipeline and due diligence before a purchase.

- **Tables** `land_parcels`, `land_checks`
- **Rules** 13 South African checks (title deed, zoning, servitudes, rates clearance, environmental, geotech, services, heritage…) from `config/land_checks.php`.
- **Permissions** `manage-land`

## Approvals (statutory)

Council and authority applications.

- **Tables** `statutory_applications`
- **Rules** 11 application types (rezoning, subdivision, building plans, water use, environmental…) with submission and decision dates.
- **Permissions** `manage-land`

## Team

The professional team and their fees.

- **Tables** `professional_appointments`, `fee_claims`
- **Rules** 14 disciplines with their South African councils (SACAP, ECSA, SACQSP, SACPCMP…). Fee claims are approved before payment.
- **Permissions** `manage-team`, `approve-fee-claims`

## Suppliers

Contractors, subcontractors, suppliers, plant hire, consultants and estate agencies.

- **Tables** `suppliers`, `supplier_documents`, `supplier_ratings`
- **Rules** Compliance matrix per supplier type (`config/supplier_compliance.php`): CIDB, COIDA (letter of good standing), tax clearance, B-BBEE, insurance, NHBRC, Fidelity Fund Certificate. **Blocking documents stop appointment and payment.** Daily alerts at 30, 14 and 7 days before expiry.
- **Permissions** `manage-suppliers`

## Documents

Private, versioned document store used by every module.

- **Tables** `documents`, `document_versions`
- **Rules** Files are never public; downloads check permission and can be restricted to named roles. Drawing register and version history included.
- **Permissions** `manage-documents`

## Site

What happens on site, captured on a phone, offline.

- **Tables** `site_diaries`, `site_attendance`, `site_photos`, `deliveries`, `site_instructions`, `inspections`, `snags`
- **Rules** Geofenced sign in/out with selfie and GPS; photos compressed on the phone; everything is idempotent by `clientId` so a repeated sync never duplicates. Weather comes from Open-Meteo. Goods receiving uses the QR code printed on the purchase order.
- **Permissions** `capture-site`, `manage-site`

## Safety

Health and safety under the OHS Act and Construction Regulations 2014.

- **Tables** `safety_incidents`, `toolbox_talks`, `safety_appointments`, `safety_file_items`
- **Rules** Incident register with investigations; incidents reportable under section 24 are flagged. Legal appointments register shows gaps and expired competencies; 16-item safety file checklist.
- **Permissions** `manage-safety`, `view-safety-reports`

## Workflow (approvals engine)

One approval engine used by requisitions, orders, variations, invoices and payments.

- **Tables** `approval_requests`, `approval_steps`, `approval_delegations`
- **Rules** Delegation-of-authority bands by value (`config/delegation_of_authority.php`); delegation while away up to 90 days; anything waiting longer than the configured time escalates hourly.
- **Permissions** every module's own gate, plus the bands

## Procurement

From requisition to goods received.

- **Tables** `requisitions`, `requisition_lines`, `requisition_quotes`, `rfq_invitations`, `purchase_orders`, `purchase_order_lines`, `goods_receipts`
- **Rules** Three quotes required above R30,000 with a reason when the cheapest is not taken. RFQs go out by email; suppliers quote through a private link without signing in. Supplier compliance and CIDB grade are checked at award, submit and issue. Goods receipts allow partial delivery and refuse over-receipt.
- **Permissions** `raise-requisitions`, `manage-procurement`

## Finance

Budgets, variations, invoices, payments and the money view of a project.

- **Tables** `budget_lines`, `variation_orders`, `supplier_invoices`, `payment_runs`
- **Rules** Budget from the feasibility or a BOQ import; warnings at 80, 90 and 100% used. **Three-way match** (order, goods received, invoice) with VAT and duplicate checks; segregation of duties means the person who captured an invoice cannot approve it; a Director can override a failed match with a reason. Payment runs exclude non-compliant suppliers. Profitability uses sales revenue where units are sold, otherwise the feasibility.
- **Permissions** `manage-budget`, `raise-variations`, `manage-finance`, `override-invoice-match`

## Contracts

Construction contracts and payment certificates.

- **Tables** `contracts`, `payment_certificates`
- **Rules** JBCC, NEC4, GCC, FIDIC or own form, each with suggested retention, payment days and defects period (`config/contract_forms.php`). Retention with cap and release at practical completion; contractor invoices match their certificate.
- **Permissions** `manage-contracts`

## Programme

The construction programme and earned value.

- **Tables** `programme_activities`, `activity_dependencies`, `progress_snapshots`
- **Rules** Critical path on working days, excluding South African public holidays and the December building shutdown; loops refused. Results are **stored on the activities** for cross-project reporting. Earned value (SPI, CPI, forecast final cost) with a weekly reading per project.
- **Permissions** `manage-projects`, `view-financial-reports`

## Workforce

Employees, time and pay inputs.

- **Tables** `employees`, `employee_allocations`, `leave_requests`, `overtime_entries`, `employee_allowances`, `employee_documents`, `crew_attendance`
- **Rules** BCEA leave balances and overtime limits (1.5x and 2x); South African public holidays; ID numbers encrypted (POPIA); employment documents visible only to Company Admins and Directors. Crew members need no login — they are signed in on the site app.
- **Permissions** `manage-workforce`, `approve-leave`

## Plant

Owned and hired plant.

- **Tables** `plant_items`, `plant_events`
- **Rules** Moves, services and breakdowns; utilisation reported per item.
- **Permissions** `manage-plant`

## Master data

The company's own lists.

- **Tables** `cost_codes`, `units`
- **Rules** 27 South African cost codes seeded; budgets and requisitions pick from these lists. Cached per company.
- **Permissions** `manage-master-data`

## Meetings

Meetings, minutes and the actions that come out of them.

- **Tables** `meetings`
- **Rules** Minutes numbered by type; action items become tasks and notify their owners; issued minutes are locked.
- **Permissions** `manage-projects`

## Forms (form builder)

Custom checklists for site teams.

- **Tables** `form_templates`, `form_submissions`
- **Rules** Seven question types including pass/fail and photo; a form fails if any pass/fail answer is "fail". Editing questions makes a new version; completed forms keep the questions they were answered against. Filled in offline on the site app.
- **Permissions** `manage-forms`

## Sales

Selling units, from stock schedule to registration in the Deeds Office.

- **Tables** `sale_units`, `unit_prices`, `buyers`, `reservations`, `sale_agreements`, `sale_conditions`, `transfer_steps`
- **Rules** Prices captured including VAT, reported excluding. Reservations expire and release the unit automatically. Suspensive conditions with standard periods; all met or waived makes the sale unconditional, one failure lapses it. Nine-step transfer pipeline; **registration transfers the unit**. Commission is payable only after registration and only to an agency with a valid Fidelity Fund Certificate.
- **Permissions** `view-sales`, `manage-sales`, `approve-commission`

## Rentals

Letting the same units.

- **Tables** `tenants`, `leases`, `lease_charges`, `lease_invoices`, `lease_receipts`, `lease_inspections`, `maintenance_requests`
- **Rules** Residential letting is VAT-exempt, commercial carries VAT. Rent invoiced a week ahead with escalations on each anniversary; the same month cannot be billed twice. Receipts pay the oldest invoice first; arrears aged current/30/60/60+. Deposits held in a named interest-bearing account with interest accrued monthly (Rental Housing Act). Incoming and outgoing inspections support deposit deductions. Each lease has a **private tenant link** for statements and maintenance requests.
- **Permissions** `view-rentals`, `manage-rentals`

## Close-out

Finishing a development and paying investors.

- **Tables** `closeout_items`, `distributions`, `distribution_lines`, `reinvestments`
- **Rules** 24-item checklist across construction, statutory, handover, financial and records; a project is marked complete only when every required item is done. Final account against the approved feasibility. **Distribution waterfall**: capital (pro rata if short), then preferred return accrued from each contribution date, then profit by agreed share. Prepared → approved by a Director → paid, which records money out against each investor. Reinvestment moves money into another project's funding.
- **Permissions** `manage-funding`, `close-projects`

## Compliance (POPIA)

The Protection of Personal Information Act in practice.

- **Tables** `retention_rules`, `data_subject_requests`
- **Rules** Register of what personal information is held and why. Retention clean-up monthly: old selfies and crew records deleted, former employees anonymised rather than deleted, minimum periods enforced (BCEA three years). Data subject requests due in 30 days, with an export of everything held about an employee or user.
- **Permissions** `manage-popia`

## Integrations

Accounting and payroll.

- **Tables** `integrations`, `integration_syncs`
- **Rules** Sage Business Cloud Accounting (South African API v2.0.0) receives approved supplier invoices, coded by the cost code mapping. SimplePay receives overtime, allowances and approved leave. Credentials encrypted; every send recorded so **nothing goes twice**. *Not yet verified against live sandbox accounts; rental income to Sage is still outstanding.*
- **Permissions** `manage-integrations`

## Reporting

Dashboards, the report framework and the report designer.

- **Tables** `report_schedules`, `custom_reports`, `project_metrics`
- **Fourteen standard reports** cost report, commitments, supplier age analysis, retention schedule, safety statistics, leave register, plant utilisation, variation register, programme status, supplier compliance, sales schedule, rent roll, tenant arrears, investor returns
- **Rules** Every report implements one `Report` interface, so view, print, Excel/CSV export, scheduling and letterhead work the same for all. The designer builds new reports from seven datasets with filters run in the database. Saved layouts choose columns. The portfolio dashboard and command centre read `project_metrics` only.
- **Permissions** `view-financial-reports`, `view-safety-reports`, `manage-report-schedules`

---

## The site app (`site-app/`)

A separate React PWA at `/site`, built for phones on site with no signal.

- **Captures** daily diary, sign in/out with GPS and selfie, geotagged photos, deliveries, goods receiving by QR, incidents, snags, inspections, site instructions, crew attendance and custom checklists.
- **Offline** everything queues in IndexedDB and syncs when signal returns; each item carries a `clientId` so re-sending is safe.
- **API** 16 endpoints under `/api/v1/site/`, with ETags so unchanged lists cost almost nothing.

## Public pages (no sign-in)

| Page | Who uses it |
|---|---|
| `/quote/{token}` | A supplier answering a request for quotation |
| `/tenant/{token}` | A tenant seeing their lease and reporting a repair |
| `/health` | The load balancer and monitoring |

Both links use a 48-character random token, stored only as a hash, rate-limited and excluded from search engines.
