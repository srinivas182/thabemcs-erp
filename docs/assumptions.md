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

## To be added as each module is built
Projects, feasibility, funding, land, approvals, procurement and finance assumptions are appended
here in the sprint that builds them.
