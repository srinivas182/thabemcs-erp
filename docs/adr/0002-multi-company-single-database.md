# ADR-0002: Multi-company tenancy in a single database

**Status:** Accepted — 22 Sep 2026

## Context
One internal platform serves many group companies. The Super Admin creates companies and sets quotas; each Company Admin manages their own company. Target scale: 25,000 projects.

## Options
1. Database per company — strongest isolation, heavy operations and cross-company reporting is hard.
2. **Single database, `company_id` on every company-owned table** — simple operations, group reporting is easy.

## Decision
Option 2, with isolation enforced in code:
- `CurrentCompany` (scoped binding, Octane-safe) holds the acting company.
- `CompanyScope` filters every query and **fails closed** (no rows) when no company is set.
- Cross-company reads require explicit platform access (Super Admin only).
- `company_id` is stamped automatically and can never change.
- Composite indexes start with `company_id`.
- A dedicated test suite checks isolation on every build.

## Consequences
Every new company-owned model must use `BelongsToCompany`. Raw queries must filter `company_id` explicitly.
