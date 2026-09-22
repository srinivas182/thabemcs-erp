# ADR-0004: Technology baseline

**Status:** Accepted — 22 Sep 2026

- **Laravel 13 / PHP 8.4.** Laravel 11 left security support in March 2026; 13 is supported to Q1 2028.
- **MySQL 8**, **Redis** (cache, queue, sessions), **Meilisearch** (search).
- **Sanctum** instead of JWT: first-party, supports both SPA cookies and API tokens.
- **Pest, Larastan level 8, Pint** as quality gates in CI.
- Timestamps in UTC; UI displays South African Standard Time.
