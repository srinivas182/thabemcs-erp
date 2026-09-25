# Security

What is in place, what is deliberately not, and how the system is tested by somebody other than the people
who built it.

**No system connected to the internet is provably safe.** Laravel, PHP, the database, the browser and every
library will have vulnerabilities found after we ship. What follows is what we control, plus the
arrangements for finding what we missed.

## What is in place

### Getting in
| Control | How |
|---|---|
| Two-factor authentication | Required for everyone who signs in. A person without it can reach only their own profile until they set it up. |
| Passwords | At least 12 characters, letters and numbers, and checked against the public breach list. Only the first five characters of the hash leave the machine. |
| Sign-in attempts | Five a minute per account, twenty a minute per address. |
| Sessions | One hour idle, secure and strict cookies outside development, and a "sign out everywhere else" control. |
| Roles | Eleven roles, held per company, 40 gates checked in controllers. The browser never decides permission. |

### The application
| Control | How |
|---|---|
| SQL injection | Every query goes through Eloquent or the query builder, which send values as parameters. There is no string-built SQL in the codebase. |
| Cross-site scripting | Blade and React escape output by default; the content security policy allows scripts only from this site. |
| Cross-site request forgery | Laravel's token on every form and non-GET request. |
| Clickjacking | Framing refused outright. |
| Mass assignment | Explicit fillable lists; secrets and system-set fields are written with forceFill only. |
| Personal information | ID numbers encrypted at rest; documents private and permission-checked; POPIA retention runs monthly. |
| Audit | Every meaningful action recorded with who, what and when. Request ids tie log lines to one request. |

### The public website
| Control | How |
|---|---|
| Separation | The website reads published content and stock availability. It cannot reach employee, investor or financial data. |
| Forms | POPIA consent required, five submissions a minute and forty a day per address, plus a hidden field robots fill in and people do not. |
| Uploads | Images and PDFs only, checked by what the file is rather than what it is called. Images are re-encoded, so anything hidden in the file does not survive. SVG refused, because browsers run it as code. Virus scanning where a scanner is installed. |
| Reading | 120 requests a minute per address, and pages are cached, so the website cannot slow the back office down. |
| Search engines | The back office is excluded in robots.txt and by meta tags. |

### Keeping it that way
- Every push runs `composer audit` and `npm audit`; findings are reported, not silently passed.
- A test walks every route and fails the build if one is reachable without signing in.
- Static analysis at level 8 and 218 automated tests run on every push.

## What is deliberately not in place

- **A web application firewall** — belongs at the hosting layer, configured when hosting is decided.
- **Distributed denial of service protection** — the CDN's job, not the application's.
- **Certificate and secret rotation** — the hosting provider's secret store, part of go-live setup.
- **Log aggregation and alerting** — needs the hosting account; the logs are structured and ready for it.

## The independent penetration test

**We cannot test our own work and call it assurance.** An outside firm must try to break in, and Thabekhulu
should engage them directly so their loyalty is clearly to the client.

**Scope to give the testers**
- The public website and its forms, including file uploads.
- Authentication: sign-in, two-factor, password reset, session handling.
- The back office as a signed-in user of a low-privilege role, looking for ways to reach data or actions
  belonging to another role or another company.
- The two token links (supplier quotes and tenant statements).
- The site app API.
- Company separation specifically: can a user of one company reach another company's data by any route?

**Rules of engagement**
- Test against staging with full-size generated data, never production.
- Give them two accounts per role and the source code. A test where the tester can read the code finds more
  than one where they cannot.
- Agree a window, a contact who can stop the test, and how findings are reported.

**What happens with the findings**
1. Everything critical or high is fixed before go-live. No exceptions.
2. Medium findings are fixed or accepted in writing by Thabekhulu, with a reason.
3. The firm retests and issues a clean report.
4. The report and the retest go in `docs/` with the date.

**Cost and timing:** roughly R25 000 to R60 000 in South Africa for a test of this size, and the firm's
calendar usually sets the date. Book it as soon as hosting is decided.

| Test | Firm | Date | Findings | Retested |
|---|---|---|---|---|
| _(to be booked)_ | | | | |

## If something goes wrong

1. **Contain.** Suspend the account or take the affected surface offline. `php artisan down` if it is the whole application.
2. **Preserve.** Keep the logs, the database snapshot and the audit trail before changing anything.
3. **Assess.** What was reached, whose data, and over what period. The audit trail and request ids are the record.
4. **Fix**, then check whether the same weakness exists anywhere else.
5. **Report.** If personal information was involved, POPIA section 22 requires notifying the Information
   Regulator and the people affected, as soon as reasonably possible. The Information Officer decides and reports.
6. **Write it up.** What happened, how, what changed so it cannot happen again.

Contacts are in `docs/disaster-recovery.md`.

## Ongoing, after go-live

Security is a subscription, not a purchase. The retainer should carry:

- monthly dependency patching, with anything critical done inside a week;
- a quarterly review of roles, permissions and dormant accounts;
- an annual penetration test;
- an agreed response time for a suspected breach.
