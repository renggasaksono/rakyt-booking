---
id: F-009
title: Tenancy, security & operations baseline
status: draft
priority: P0
roadmap: mvp-weeks-2-6
mockups: []
decisions: ["0002", "0004", "0006"]
updated: 2026-10-17
---

# F-009 · Tenancy, security & operations baseline

## Problem

Cross-seller data exposure or overbooking would end trust in the product. Blueprint lists these as launch blockers.

## Scope

**In:** organization scoping + policies, cross-tenant test suite, audit events, account recovery, automated backups + **tested restore**, error reporting, structured logs, uptime checks, secrets management, rate limiting, private media.

## Acceptance criteria

- [ ] Cross-tenant tests for trips, departures, bookings, payment records, media.
- [ ] Audit events for booking status, price, capacity and payment verification changes (who, what, before/after, when).
- [ ] Daily automated DB backups; restore rehearsed and documented in `docs/` before pilot.
- [ ] Error tracking and uptime monitoring live before pilot.
- [ ] No secrets in git; `.env.example` complete.
- [ ] Scheduler (hold expiry, deadline closing) monitored — alert if it stops.

## Open questions

- [ ] Hosting target (managed vs VPS) and backup tooling.
- [ ] Error tracking service.

## Changelog

- 2026-10-17 — created
