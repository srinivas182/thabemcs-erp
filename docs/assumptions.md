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

## To be added as each module is built
Projects, feasibility, funding, land, approvals, procurement and finance assumptions are appended
here in the sprint that builds them.
