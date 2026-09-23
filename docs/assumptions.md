# Build assumptions (to confirm in discovery)

Modules are being built before the discovery workshops, on South African industry-standard
assumptions. Each item below should be confirmed or corrected with Senzo Shange.
Everything marked **configurable** can be changed in settings without code changes.

## Platform
| # | Assumption | Configurable |
|---|---|---|
| P1 | One login per person; a person belongs to exactly one company | No |
| P2 | 11 default roles: Company Admin, Director, Development Manager, Project Manager, QS, Site Manager, H&S Officer, Procurement, Finance, Sales & Leasing, Contractor | Roles yes (later sprint) |
| P3 | Company Admins manage people; Company Admins and Directors see the audit log | Yes (later sprint) |
| P4 | Invitation links expire after 60 minutes | Yes (`auth.passwords.users.expire`) |
| P5 | Two-factor authentication is optional for everyone | Can be made mandatory per role |
| P6 | Times are displayed in SAST; stored in UTC | Yes |

## Projects (Sprint 4)
| # | Assumption | Configurable |
|---|---|---|
| PR1 | Every project follows the 7-stage cycle: Plan, Fund, Secure land, Approvals, Build, Sell / rent, Close out | No |
| PR2 | Each stage has a default checklist based on SA practice (`config/stage_gates.php`); "where applicable" items don't block | Yes (per-company templates planned) |
| PR3 | Company Admins, Directors and Development Managers approve stage gates; Project Managers complete checklist items | Yes (later sprint) |
| PR4 | Earlier stages' checklists are locked once a gate is approved | No |
| PR5 | Project codes run PRJ-0001, PRJ-0002 per company unless a code is entered | Yes |
| PR6 | Risks are scored 1 to 5 for likelihood and impact: low under 5, medium 5 to 9, high 10 to 19, critical 20 and above | Yes (later sprint) |
| PR7 | Anyone can be assigned a task; assignees can mark their own tasks done | No |

## Feasibility and funding (Sprint 5)
| # | Assumption | Configurable |
|---|---|---|
| FE1 | All feasibility figures are in rand excluding VAT; VAT is handled in the finance module | No |
| FE2 | Costs and revenue are spread evenly across the months they apply to | No |
| FE3 | Template starting rates: professional fees 12% of construction, contingency 5% of construction, marketing and agents' commission 5% of revenue | Yes, per scenario |
| FE4 | Peak funding requirement = lowest cumulative cash position; IRR is monthly, annualised | No |
| FE5 | One approved baseline per project; approved scenarios are locked and changes are made by copying | No |
| FE6 | Directors, Development Managers and Company Admins approve feasibilities; Finance, PMs and Development Managers edit them | Yes (later sprint) |
| FU1 | Funding types: developer equity, investor capital, debt, grant | No |
| FU2 | "Committed" and "Active" sources count towards funding secured | No |
| FU3 | Investor ID/registration numbers are encrypted; FICA verification is recorded as a manual check | No |
| FU4 | Only the last four digits of the project bank account are stored | No |
| FU5 | Investors and funding are visible to Company Admins, Directors, Development Managers and Finance only | Yes (later sprint) |

## Land, approvals and professional team (Sprint 6)
| # | Assumption | Configurable |
|---|---|---|
| LA1 | Land due-diligence checklist per `config/land_checks.php` (title, encumbrances, zoning, servitudes, services, access, geotech, environmental, flood lines, heritage, land claims, rates, valuation) | Yes |
| LA2 | An offer can only be accepted or land transferred once every required check is clear or not applicable | No |
| LA3 | Land can exist before a project and be linked later | No |
| LA4 | Company Admins, Directors and Development Managers manage land | Yes (later sprint) |
| AP1 | Applications needing attention: approvals lapsing within 60 days, and decisions past their expected date | Yes |
| AP2 | Approval expiry must be entered from the decision letter (validity differs by municipality and approval type) | No |
| PT1 | Statutory councils: SACAP (architects), SACQSP (QS), ECSA (engineers), SACPLAN (town planners), SAGC (land surveyors), SACPCMP (construction PMs, H&S agents), EAPASA (environmental), LPC (attorneys, conveyancers) | No |
| PT2 | Registration is verified manually against each council's public register and recorded with who checked it | No |
| PT3 | Fee claims are approved by the QS, Development Manager, Director or Company Admin, and marked paid by Finance | Yes (later sprint) |
| PT4 | Approved claims above the agreed fee are flagged, not blocked | Yes |

## Suppliers and documents (Sprint 7)
| # | Assumption | Configurable |
|---|---|---|
| SU1 | Required compliance documents per supplier type, and which ones block appointment and payment, per `config/supplier_compliance.php` | Yes |
| SU2 | CIDB grade limits (R500k for grade 1 up to no limit for grade 9) must be confirmed against the current CIDB Regulations before go-live | Yes |
| SU3 | Expiry warnings at 30, 14 and 7 days go to Procurement and Company Admins at 07:00 SAST; My Day shows expired and expiring counts | Yes |
| SU4 | B-BBEE is recorded but does not block (it affects preferential procurement scoring, not eligibility) | Yes |
| SU5 | Suppliers are managed by Company Admins, Directors, Development Managers and Procurement; Project roles can rate performance | Yes (later sprint) |
| DO1 | Documents are private; every download goes through a permission check and is recorded in the audit log | No |
| DO2 | Every upload creates a new version; older versions are kept and can still be downloaded | No |
| DO3 | Allowed types: PDF, Office, CSV, images (incl. HEIC), DWG/DXF, ZIP, TXT; maximum 50 MB per file | Yes |
| DO4 | Restricted documents are visible to the chosen roles plus Company Admins and Directors | No |
| DO5 | Virus scanning of uploads is recommended in production (e.g. ClamAV) and will be added with the hosting set-up | n/a |

## Site app and health & safety (Sprint 8)
| # | Assumption | Configurable |
|---|---|---|
| SI1 | The site app is served from the same domain at `/site` and signs in with the same account (Sanctum cookie) | No |
| SI2 | One site diary per project per day; a later submission for the same day replaces it | No |
| SI3 | Attendance is checked against the project's site point and sign-in radius (default 300 m), allowing up to 100 m for GPS accuracy; outside-radius sign-ins are flagged, not blocked | Yes (radius per project) |
| SI4 | Photos are shrunk on the phone to 1600 px (selfies 800 px) before upload | Yes |
| SI5 | Records captured offline sync automatically; validation failures stay on the phone for the user to review | No |
| SI6 | Damaged or short deliveries notify Procurement and Project Managers | Yes |
| HS1 | Fatalities and dangerous occurrences are marked reportable to the Department of Employment and Labour (OHS Act s24) automatically; lost-time and medical cases prompt a reportability check | Yes |
| HS2 | Incidents (other than near misses) need a root cause and corrective action before closing; reportable incidents also need the date reported | No |
| HS3 | Incidents notify Safety Officers, Project Managers and Directors immediately | Yes |

## Approvals and procurement (Sprint 9)
| # | Assumption | Configurable |
|---|---|---|
| DA1 | Purchase order approvals (excl. VAT): up to R25k Project Manager; to R250k PM, Finance, Development Manager; to R1m adds a Director; above R1m PM, Finance and two different Directors | Yes (`config/delegation_of_authority.php`) |
| DA2 | Requisitions are approved by a Project Manager | Yes |
| DA3 | Nobody approves their own request, and one person cannot approve two steps of the same request | No |
| DA4 | Steps waiting more than 48 hours are escalated to Directors and Company Admins (checked hourly) | Yes |
| DA5 | Approvers can delegate to a colleague for up to 90 days; delegated approvals record who acted and for whom | Yes |
| PR1 | Three quotes are required above R30 000 excl. VAT unless a single-source reason is recorded | Yes |
| PR2 | Choosing a quote other than the cheapest requires a written reason | No |
| PR3 | Supplier compliance and CIDB grading are checked at award, at submission for approval and again at issue | No |
| PR4 | VAT at 15% is added when the supplier has a VAT number | Yes |
| PR5 | PO line prices are spread from the awarded quote in proportion to the requisition estimate; buyers adjust them to the quotation | No |
| PR6 | Goods cannot be received beyond the ordered quantity; partial receipts are allowed | No |

## Finance (Sprint 10)
| # | Assumption | Configurable |
|---|---|---|
| FI1 | Budgets are excl. VAT, by cost code; codes from the feasibility use headings 01 Land to 09 Other | No |
| FI2 | Committed spend = purchase orders approved or later; plus approved invoices without an order | No |
| FI3 | Budget warnings at 80%, 90% and 100% of the revised budget, once each, to the project manager and Finance | Yes |
| FI4 | BOQ import is CSV (save from Excel); direct Excel import can be added | Yes |
| FI5 | Three-way match: invoice total = amount + VAT; VAT 15% (±R1) for VAT vendors and none for non-vendors; invoiced to date ≤ order value and ≤ value received (R5 tolerance) | Yes |
| FI6 | Invoices without a purchase order always fail the match and need a Director or Company Admin override with a reason | No |
| FI7 | The person who captures an invoice cannot approve it | No |
| FI8 | Payment runs are approved by a Director (two Directors above R1m); non-compliant suppliers are left out and re-checked when marking paid | Yes |
| FI9 | The payment schedule CSV lists beneficiary, registration number, amount and references; bank account numbers are not stored (beneficiaries are held in online banking) | No |
| FI10 | Variations: QS then PM up to R100k; adds Development Manager to R1m; adds a Director above | Yes |

## Contracts, workforce and plant (Sprint 11)
| # | Assumption | Configurable |
|---|---|---|
| CO1 | Retention defaults: 10% of value to date, limited to 5% of the contract sum, half released at practical completion and the rest at final completion (JBCC-style); set per contract | Yes (per contract) |
| CO2 | Certificates: value to date (incl. materials on site and variations) less net retention less previous certificates; one certificate in progress at a time; the value to date cannot go down | No |
| CO3 | Payment certificates approved by PM then Development Manager, adding a Director above R1m | Yes |
| CO4 | Contractor invoices are matched against the certified amount instead of a purchase order | No |
| CF1 | Cash-flow forecast month 1 = the project's planned start (or the baseline approval month); actual = supplier invoices paid, excl. VAT | No |
| WF1 | Annual leave 15 working days a year (21 consecutive days), sick leave 30 days per 3-year cycle, family responsibility 3 days a year, scaled for 6-day weeks; cycles run from the start date | Yes |
| WF2 | Weekends and SA public holidays (incl. Good Friday, Family Day and the Sunday rule) are not counted as leave days; once-off declared holidays must be added | Partly |
| WF3 | Overtime at most 3 hours a day and 10 a week (BCEA s10); 1.5x normally, 2x on Sundays and public holidays | Yes |
| WF4 | Employee ID numbers are encrypted and shown masked (POPIA); pay rates stay in the payroll system | No |
| EX1 | Export column layouts target Sage Business Cloud (invoices) and a generic payroll input layout (SimplePay maps columns); confirm with the client's accountant | Yes |

## Reports and dashboards (Sprint 12)
| # | Assumption | Configurable |
|---|---|---|
| RE1 | Portfolio dashboard for Company Admins, Directors, Development Managers, Finance, QS and PMs; the Super Admin sees a group view across companies | Yes |
| RE2 | "High risk" means likelihood × impact of 10 or more on the 5×5 matrix | Yes |
| RE3 | Financial reports (cost, commitments, age analysis, retention) for the same roles as RE1; safety statistics also for Site Managers and Safety Officers; leave register for workforce managers; plant utilisation for plant managers | Yes |
| RE4 | PDF is produced from the browser's print (clean print layout); Excel and CSV are downloaded directly | No |
| RE5 | Plant utilisation = days on site and working ÷ days in the period, from the movement history | No |
| RE6 | Scheduled reports go out at 06:00 SAST; weekly cover the previous Monday to Sunday, monthly the previous month; recipients who can no longer see a report are skipped | Yes |

## Programme, meetings, master data, site operations and H&S compliance (Sprint 13)
| # | Assumption | Configurable |
|---|---|---|
| PG1 | Programme uses working days (Monday to Friday) excluding SA public holidays; the builders' December shutdown is not yet excluded | Yes (later) |
| PG2 | Links are finish-to-start with an optional lag; an activity starts no earlier than its "earliest start" date; zero-float activities are critical | No |
| PG3 | Tasks more than two working days overdue are escalated once to the project manager at 07:30 on weekdays | Yes |
| MD1 | Each company starts with a standard cost code library (01 Land to 09 Other) and a list of units, which Company Admins, Finance and QSs can change | Yes |
| MT1 | Meeting minutes are locked once issued; action items become tasks and their owners are notified on issue | No |
| SO1 | Weather in the site diary is suggested from Open-Meteo using the site location; staff can change it | No |
| SO2 | Crew register: one record per worker per day, marked by the supervisor; workers need no login | No |
| SO3 | Goods received on the phone against a purchase order (QR code on the order), with a photo of the signed delivery note | No |
| HS4 | Legal appointment types and references follow the OHS Act and Construction Regulations 2014; confirm the list with the client's registered H&S practitioner | Yes |
| HS5 | Safety file checklist has 16 standard items; confirm with the client's H&S practitioner | Yes |

## RFQs, contracts, performance, workforce and POPIA (Sprint 14)
| # | Assumption | Configurable |
|---|---|---|
| RQ1 | RFQs go by email with a private link (48-character random token, only its hash stored); suppliers see items and quantities but never the company's estimates; they can revise until the closing date | No |
| RQ2 | Quote documents uploaded by suppliers are stored as if uploaded by the buyer who sent the RFQ; the audit log records the supplier link | No |
| CT1 | Contract form defaults (retention, payment days, defects period) for JBCC PBA/MWA, NEC4, GCC 2015 and FIDIC are typical starting values only; the signed contract data governs. Confirm with the client's QS | Yes |
| PG4 | Programme excludes the building industry's December shutdown (16 December to 9 January by default) | Yes |
| EV1 | Earned value: budget at completion = revised project budget, spread over activities by entered activity budgets or else by duration; actual cost = approved supplier invoices excl. VAT; a reading is saved every Monday | No |
| PF1 | Forecast cost per cost code = the larger of revised budget and committed-plus-direct spend; revenue from the approved feasibility until the Sales module exists | No |
| WF5 | Allowances: daily allowances count days present on the crew register; monthly once per month; once-off in the month paid. Employment documents are visible only to Company Admins and Directors | Yes |
| PO1 | Record of processing (register) lists six categories of personal information with purpose, lawful basis and access; confirm with the client's Information Officer | Yes |
| PO2 | Retention defaults: selfies 12 months, crew register 36 months (BCEA minimum), former employees anonymised after 36 months, read notifications 12 months, RFQ links 12 months; clean-up on the 1st of each month | Yes |
| PO3 | Data subject requests are due 30 days after receipt; exports cover employees and system users | Yes |
| PO4 | The Information Officer's name and email are set per deployment (POPIA_INFORMATION_OFFICER) | Yes |

## Reporting, forms and integrations (Sprint 15)
| # | Assumption | Configurable |
|---|---|---|
| RD1 | Report designer datasets: purchase orders, supplier invoices, budget by cost code, suppliers, incidents, employees, programme activities; up to 5 000 rows; filters is / is not / contains / at least / at most | No |
| RD2 | Custom reports are visible to everyone in the company who has permission for the dataset; only the author or a Company Admin/Director can edit or delete | No |
| RP8 | Ten standard reports: the seven from Sprint 12 plus variation register, programme status and supplier compliance | No |
| MP1 | Command centre health: red = over budget, open incident or forecast late; amber = 80%+ budget used, high risks or activities behind; map tiles from OpenStreetMap (fine for this volume; a commercial tile service can be swapped in) | Yes |
| FB1 | Form builder question types: pass/fail/N/A, yes/no, choice, number, text, date, photo. A form fails if any pass/fail answer is "fail". Editing questions creates a new version; completed forms keep their questions | No |
| IN1 | Sage Business Cloud Accounting ZA uses API v2.0.0 (API key + Basic auth + company ID; 5 000 calls/day). Approved, scheduled and paid supplier invoices are sent as supplier invoices with one account line per invoice; accounts mapped per cost code with a default; field names to be confirmed in the client's Sage sandbox | Yes |
| IN2 | SimplePay: overtime hours (1.5x and 2x) and allowances go to the payslip for the period end through Bulk Inputs; the payslip item for each is set per company; approved leave is sent as leave days (working days, public holidays excluded) | Yes |
| IN3 | Each supplier and employee needs its ID from Sage/SimplePay entered once; records without it are listed as not sent. Nothing is sent twice | No |

## Scale and infrastructure (Sprints 16-17)
| # | Assumption | Configurable |
|---|---|---|
| SC1 | Dashboard and command-centre figures come from precomputed metrics, refreshed when data changes and nightly; they can be a few minutes behind. Project pages read live data | No |
| SC2 | The dashboard and command centre list the 100 projects needing attention most; the full list is the projects register | Yes |
| SC3 | Dropdowns return at most 20 matches as you type; reports return 5 000 rows and say when they are cut short | Yes |
| SC4 | Hosting recommended in a South African region (AWS Cape Town) for POPIA; read replica for reports, Redis for cache/sessions/queues, S3-compatible storage for files, CDN for assets | Yes |
| SC5 | Scheduled work is queued per company (per project for metrics and snapshots) across named queues; exactly one scheduler runs | No |
| SC6 | Octane, Horizon, Scout/Meilisearch and the S3 driver are installed at deployment (docs/deployment.md); until then the app runs under PHP-FPM with OPcache preloading and database search | Yes |

## Sales (Sprint 18)
| # | Assumption | Configurable |
|---|---|---|
| SA1 | Unit prices are captured including VAT where the seller is a VAT vendor; revenue, commission and profitability use the price excluding VAT (VAT at 15%) | Yes |
| SA2 | Reservations hold a unit for 14 days by default; when they run out the unit returns to available automatically and the person who reserved it is told | Yes |
| SA3 | Standard suspensive condition periods: bond approval 30 days, sale of the buyer's property 60 days, deposit 14 days. The sale becomes unconditional when all are met or waived; one failure lapses the sale | Yes |
| SA4 | Transfer pipeline: instruction, FICA, documents signed, bond granted, guarantees, rates clearance, transfer duty or VAT, lodgement, registration. Registration completes the sale and transfers the unit | Yes |
| SA5 | Estate agencies are suppliers of type "estate agency"; commission is payable only after registration and only with a valid Fidelity Fund Certificate and tax compliance (Property Practitioners Act) | Yes |
| SA6 | Agency commission defaults to 5% of the price excluding VAT; the signed mandate governs. Deposits are recorded as held in a named trust account | Yes |
| SA7 | Once a project has a stock schedule, sales replace the feasibility's revenue line in profitability: sold units at agreed prices plus unsold units at list price | No |
| SA8 | Transfer duty versus VAT on the sale is decided by the conveyancer; the system records that the step is done, not which applied. Confirm the whole pipeline with the client's conveyancer | No |

## Rentals (Sprint 19)
| # | Assumption | Configurable |
|---|---|---|
| RN1 | Rented units are the same stock records as sales; a unit can be for sale, to let, or both, and cannot be let while an active lease exists | No |
| RN2 | Residential letting is exempt from VAT; commercial leases carry VAT at 15% | No |
| RN3 | Rent for the coming month is invoiced 7 days ahead, due on the lease's payment day; escalations apply on each anniversary and are shown on the invoice line | Yes |
| RN4 | Notice to end a lease defaults to 20 days for residential (Consumer Protection Act cooling-off for fixed terms) and 60 for commercial; the lease itself governs | Yes |
| RN5 | Deposits are held in a named interest-bearing account and earn interest at a configured rate (default 5% a year, simple, accrued monthly). Confirm the rate basis with the client's bank; the Rental Housing Act requires the rate the bank actually pays | Yes |
| RN6 | Receipts are allocated to the oldest unpaid invoice first; arrears are aged current, 1-30, 31-60 and 60+ days | No |
| RN7 | Each lease has a private tenant link (48-character token, only its hash stored) showing the lease, invoices and outstanding amount, and allowing maintenance requests | No |
| RN8 | Inspection areas and conditions come from configuration; deductions from a deposit must be supported by the outgoing inspection | Yes |

## Close-out, investor returns and reinvestment (Sprint 20)
| # | Assumption | Configurable |
|---|---|---|
| CL1 | Close-out checklist: 24 items across construction, statutory, handover, financial and records. Gas certificate, fire sign-off and body corporate handover are optional; the rest must be done before a project can be marked complete. Confirm with the client's QS and attorney | Yes |
| CL2 | The final account compares the approved feasibility with the final position, using sales revenue where units were sold | No |
| DI1 | Distribution waterfall: capital back first (pro rata where there is not enough), then the preferred return, then the remaining profit | Yes |
| DI2 | The preferred return runs on each contribution from the day it was received, simple interest at the rate in that investor's funding record | Yes |
| DI3 | Profit is shared by the stated profit-share percentages; where none are stated it follows capital contributed. Rounding differences go to the largest share | No |
| DI4 | A distribution is prepared, then approved by a Director or Company Admin, then marked paid; paying records money out against each investor's funding | No |
| DI5 | Reinvestment records money left in the business as funding received on another project, so both projects' funding stays correct | No |

## Platform polish (Sprint 21)
| # | Assumption | Configurable |
|---|---|---|
| PP1 | Imports are CSV files (save an Excel sheet as CSV) with a header row, up to 5 000 rows; every row is checked first and nothing is written unless the whole file is good | Yes |
| PP2 | Imports refuse names or codes already in the system rather than updating them, so an import can never overwrite existing records | No |
| PP3 | Report letterhead comes from the company record plus a letterhead block in company settings (address, contact, logo URL, footer); it prints on every report | Yes |
| PP4 | Saved report layouts choose which standard columns show and in what order; one can be the default for everyone in the company | No |
| PP5 | People choose to be emailed as things happen or to get one daily summary at 07:15; everything always appears in the inbox | Yes |
| PP6 | API tokens are read-only. Webhooks are signed with HMAC-SHA256 in X-Thabekhulu-Signature and retried up to five times with a growing delay | Yes |

## Security and resilience (Sprint 22)
| # | Assumption | Configurable |
|---|---|---|
| SE1 | Two-factor authentication is required for Company Admins, Directors, Finance and Development Managers, and for Super Admins | Yes |
| SE2 | Content security policy allows scripts only from this site, map tiles from OpenStreetMap and weather from Open-Meteo; anything else must be added deliberately | No |
| SE3 | Database backed up nightly at 01:00 and kept 30 days, plus point-in-time recovery from the managed database; uploaded files covered by object storage versioning | Yes |
| SE4 | Restores are rehearsed monthly and before go-live, and the result recorded in docs/disaster-recovery.md | No |
| SE5 | Proposed targets: at most 5 minutes of data lost (RPO) and 4 hours to be working again (RTO). Thabekhulu to agree | Yes |
| SE6 | Dependency vulnerability scanning runs on every push; findings are reported, not silently ignored | No |

## To be added as each module is built
Projects, feasibility, funding, land, approvals, procurement and finance assumptions are appended
here in the sprint that builds them.
