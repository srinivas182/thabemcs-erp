# ADR-0003: Two React front-ends, one design system

**Status:** Accepted — 22 Sep 2026

## Context
The client prefers React. Site teams need to work without signal; Inertia pages fetch data from the server on every navigation, so they cannot work offline.

## Decision
- **Management app:** React + Inertia (fast to build, server-side routing and auth).
- **Site app:** React SPA installed as a PWA, offline-first with IndexedDB (Dexie) and an outbox that syncs to `/api/v1`.
- Shared `packages/ui` and `packages/shared` (Zod schemas) keep both consistent. React Native can reuse the shared package later.
