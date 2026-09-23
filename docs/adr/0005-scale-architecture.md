# ADR-0005: How the platform scales

Date: 2027-03-29
Status: Accepted

## Context

Thabekhulu's target is 25,000 projects and 100,000 concurrent users. The first build computed figures
on demand, so dashboards and the command centre grew more expensive as the portfolio grew, and all
files, sessions and scheduled work assumed a single server.

## Decision

**Read paths never recompute.** Project figures and critical-path results are precomputed into
`project_metrics` and onto the activities by queued jobs when data changes, and nightly for
date-dependent values. Dashboards, the command centre and cross-project reports read stored figures.
Automated tests fail if a page's query count grows with the number of projects.

**Nothing sends whole tables to the browser.** Dropdowns use a lookup endpoint that returns at most 20
matches; lists paginate; reports state when they are cut short.

**Caching is company-scoped by construction.** All caching goes through `CompanyCache`, which puts the
company in every key; an architecture test forbids using the cache facade directly. Invalidation uses
versioned groups, so it works on any cache store.

**The application is stateless.** Sessions, cache and queues live in Redis, in separate databases, so a
full cache cannot evict sessions and clearing the cache cannot drop jobs. Uploaded files move to
S3-compatible object storage, because a local disk is not shared between servers. Company context is a
scoped binding, reset per request, which is also what Octane requires.

**Work is queued and split.** Scheduled work is queued per company (and per project where the work is
per project), on named queues: `metrics`, `reports`, `maintenance`, `integrations`, `mail`, `default`.
Workers per queue scale independently, so a long report never delays user-facing work.

**Reads can go to a replica.** Setting `DB_READ_HOST` sends reads to a replica, with sticky sessions so
a user never reads back stale data they just saved.

## Consequences

- Figures on dashboards can be up to a few minutes old after a change, which is acceptable for
  portfolio views and is stated on the page. Project pages themselves read live data.
- Every new cross-project figure belongs in the metrics refresh, not in a page.
- Octane, Horizon, Scout/Meilisearch and the S3 driver are additional packages installed at deployment
  (see docs/deployment.md); the code and configuration are already written for them.
- Capacity is proven in Sprint 23 on a full-size database under load, not inferred from this design.
