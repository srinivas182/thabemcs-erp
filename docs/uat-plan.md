# User acceptance testing

How Thabekhulu checks the system does what they need, before go-live. Roughly two weeks, on staging,
with their own people and their own data.

## Before it starts

- Staging loaded with **their** opening data (suppliers, employees, projects, budgets), imported through
  Settings → Import.
- Email sending switched on, so notifications and reports actually arrive.
- Each tester has an account with the role they hold in real life, and two-factor set up where required.
- A short walkthrough per role — half an hour each, not a training course.

## How to run a script

Each tester works through their scripts on staging and records **pass**, **pass with comment**, or
**fail** with what happened. Anything that fails gets fixed and retested. Nothing is signed off by
someone who did not use it.

## Scripts by role

### Project manager
1. Create a project, complete a stage-gate checklist and move it to the next stage.
2. Build a programme, record progress, and see the critical path and forecast finish.
3. Raise a requisition, compare quotes, and take it through approval.
4. Open the Performance tab and explain what SPI and CPI are saying.
5. Record a risk and an issue, and see them on the dashboard.

### Quantity surveyor
1. Capture a feasibility and approve it as the baseline.
2. Create the budget from the feasibility, then import a BOQ.
3. Raise a variation order and follow it through approval into the revised budget.
4. Issue an interim payment certificate and check the retention maths.
5. Run the cost report and the commitments report, and export both.

### Finance
1. Capture a supplier invoice against an order and a delivery, and see the three-way match.
2. Try to approve an invoice you captured yourself (it must refuse).
3. Override a failed match as a Director, with a reason, then find that reason in the audit trail.
4. Build a payment run and confirm a non-compliant supplier is left out.
5. Send approved invoices to Sage and check them in Sage.

### Procurement
1. Add a supplier and load their compliance documents.
2. Let a document expire and confirm the supplier is blocked from award and payment.
3. Send a request for quotation by email; have the supplier answer through their link.
4. Award, issue the order, and receive goods partially, then fully.

### Site manager and foreman (on a phone, on site)
1. Sign in on site with the phone offline; confirm it syncs when signal returns.
2. Capture a diary entry, photos, a delivery and an incident.
3. Receive goods by scanning the QR code on the order.
4. Complete a custom checklist, including a photo question.
5. Sign in the crew who have no logins of their own.

### Health and safety officer
1. Record an incident and its investigation; confirm a reportable one is flagged.
2. Record a toolbox talk and a safety inspection.
3. Check the legal appointments register shows gaps and expiring competencies.
4. Run the safety statistics report.

### Sales and letting
1. Load the stock schedule, reserve a unit, and let the reservation expire.
2. Record a sale with conditions; meet them and see the sale go unconditional.
3. Work the transfer through to registration and approve commission.
4. Create a lease, invoice a month, receipt part of it and check the arrears ageing.
5. Send a tenant their link and have them report a repair.

### Director and Ma Queen
1. Look at the portfolio dashboard and the command centre; explain the red and amber projects.
2. Approve something in the approvals inbox, and delegate while away.
3. Open the close-out checklist and the final account on a project.
4. Prepare, approve and pay an investor distribution; check the split against the agreement.
5. Receive a scheduled report by email.

### Company administrator
1. Invite a user, set their role, and remove someone.
2. Import opening data and watch a bad file be refused.
3. Change a company setting (delegation band, cost code) and see it take effect.
4. Run the POPIA retention clean-up and record a data subject request.
5. Read the audit trail for a change someone else made.

## Confirming the assumptions

`docs/assumptions.md` holds about 130 business assumptions made while building — thresholds, notice
periods, checklists, default rates. **Senzo works through these with the people who know**, and each is
confirmed or corrected. Corrections that are configuration are changed on the spot; anything needing code
is listed and estimated.

Sessions worth booking separately:
- delegation of authority and approval bands (with Ma Queen);
- the sales transfer pipeline and deposits (with their conveyancer);
- lease terms and the deposit interest rate (with their rental agent or attorney);
- health and safety appointments and the safety file (with their H&S practitioner);
- CIDB grade limits and supplier compliance (with procurement);
- the close-out checklist (with their QS).

## Sign-off

| Area | Tester | Date | Result | Notes |
|---|---|---|---|---|
| Projects and programme | | | | |
| Feasibility and budgets | | | | |
| Procurement | | | | |
| Finance and payments | | | | |
| Site app and safety | | | | |
| Sales | | | | |
| Rentals | | | | |
| Close-out and investors | | | | |
| Reports and dashboards | | | | |
| Administration and POPIA | | | | |

Go-live needs every area passed, all the assumptions confirmed, and the performance and restore results
in `docs/performance.md` filled in and accepted.
