---
id: F-015
title: Audit logging for admin actions
status: ready
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0011"]
updated: 2026-10-23
---

# F-015 · Audit logging for admin actions

## Problem

An admin who can see and suspend every seller must leave a trail: what was done, to whom, when and why.

## Scope

**In:** reuse F-009's `audit_events` (no second log) for admin actions; record actor (platform admin user), action,
target type/id, organization (if any), timestamp and relevant before/after or reason; admin view of audit events
filterable by organization, actor and action; organization detail (F-012) and booking detail (F-013) show related events.

Admin actions audited in the MVP: seller suspension, seller reactivation, platform-admin flag grant/revoke. Any
sensitive admin change added later must be audited too.

**Out (explicitly):** logging page views or searches; editing or deleting audit events; SIEM/export integrations;
**surfacing admin actions in the seller's own audit history** — admin events are platform-internal and never shown in
the seller app in P0 (resolved 2026-10-23). A suspended seller is informed out-of-band, not via an in-app notice.

## Dependencies

F-009 (`audit_events` table and writer) · F-010.

## Acceptance criteria

- [ ] Each audited action writes exactly one event in the same transaction as the change.
- [ ] Events record actor, action, target, timestamp and reason/change details.
- [ ] Events never contain passwords, OTP codes, 2FA secrets, tokens or payment evidence contents; customer data limited to IDs.
- [ ] Audit events are append-only through the app (no update/delete routes); tested.
- [ ] Admin audit view is behind the F-010 gate; sellers cannot read admin events (test).
- [ ] Admin events are not exposed in any seller-facing audit view or page in P0 (test, if a seller audit view exists).

## Open questions

None — seller visibility resolved 2026-10-23 (see changelog).

## Changelog

- 2026-10-17 — created
- 2026-10-23 — **ready.** Sellers do **not** see admin actions on their org in P0 (Rengga); admin audit events stay platform-internal. Moved to scope "Out" with an AC.
