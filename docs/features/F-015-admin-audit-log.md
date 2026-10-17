---
id: F-015
title: Audit logging for admin actions
status: draft
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0011"]
updated: 2026-10-17
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

**Out (explicitly):** logging page views or searches; editing or deleting audit events; SIEM/export integrations.

## Dependencies

F-009 (`audit_events` table and writer) · F-010.

## Acceptance criteria

- [ ] Each audited action writes exactly one event in the same transaction as the change.
- [ ] Events record actor, action, target, timestamp and reason/change details.
- [ ] Events never contain passwords, OTP codes, 2FA secrets, tokens or payment evidence contents; customer data limited to IDs.
- [ ] Audit events are append-only through the app (no update/delete routes); tested.
- [ ] Admin audit view is behind the F-010 gate; sellers cannot read admin events (test).

## Open questions

- [ ] Do sellers ever see admin actions on their own organization (e.g. suspension notice in their audit history)? — Rengga

## Changelog

- 2026-10-17 — created
