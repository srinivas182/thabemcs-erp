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

## To be added as each module is built
Projects, feasibility, funding, land, approvals, procurement and finance assumptions are appended
here in the sprint that builds them.
