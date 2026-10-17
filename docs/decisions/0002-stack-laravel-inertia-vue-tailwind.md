---
id: 0002
title: Laravel + Inertia + Vue 3 + Tailwind, modular monolith
status: accepted
date: 2026-10-15
deciders: [Rengga]
related: [ADR-0004]
---

# 0002. Laravel + Inertia + Vue 3 + Tailwind, modular monolith

## Context

Blueprint §2 and §4. We need a modern, mobile-first UI and app-like seller dashboard, built fast by a small team, with simple operations. Local dev runs on Laravel Herd.

## Decision

- **Backend:** Laravel, one deployable app with clear internal modules (auth, organizations, trips, departures, bookings, payment records, notifications, subscriptions).
- **Frontend:** Inertia.js + Vue 3 + Tailwind CSS. No separate API for app screens.
- **Infra:** Laravel queue + scheduler (Redis only when workload justifies it); S3-compatible storage for media; managed app hosting or a simple VPS/container.
- **Not now:** microservices, Kubernetes, event-driven infra, separate SPA, native apps.

## Open question

**Database engine — PostgreSQL or MySQL.** The blueprint says both are adequate. Decide before the first migration and record it here (or in a superseding ADR). Consider what Herd/your host provides and team familiarity.

**Resolved 2026-10-17:** PostgreSQL — see [ADR-0012](0012-database-postgresql.md).

## Consequences

- Good: one codebase, shared auth/routing, fast iteration.
- Costs: frontend coupled to backend; a public API or mobile app later needs deliberate work.
- Public booking pages may later need SSR/caching for SEO — handle then, without splitting the system.

## Alternatives considered

- Laravel API + Next.js — more moving parts, duplicated auth.
- Blade + Alpine — simpler, but awkward for the interactive dashboard and multi-step booking.
- API + native app — defer until users demand it.
