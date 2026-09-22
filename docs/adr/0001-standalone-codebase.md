# ADR-0001: Standalone codebase

**Status:** Accepted — 22 Sep 2026

## Context
MCS maintains the GMLM platform. Thabekhulu is a different domain (property development and construction ERP).

## Decision
Build Thabekhulu as a new, independent repository. No GMLM code, schema rules, namespaces or seeders are reused. The same engineering standards apply.

## Consequences
- Clean domain model with no MLM concepts to work around.
- Platform foundation (auth, roles, audit, settings) is built fresh, using first-party Laravel and spatie packages to keep the effort small.
