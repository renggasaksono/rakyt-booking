---
id: F-014
title: Seller account suspension & reactivation
status: draft
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0003", "0004", "0011"]
updated: 2026-10-17
---

# F-014 · Seller account suspension & reactivation

## Problem

If a pilot seller abuses the platform (fraudulent trips, spam) or asks to pause, the product owner must be able to
stop new activity without destroying data or customers' booking records.

## Scope

**In:** admin action to suspend and reactivate an organization, with confirmation and required reason;
organization `status` (`active` / `suspended`) plus who/when/why; suspension blocks new bookings on that seller's
public page; audit events for both actions.

**Out (explicitly):** deleting organizations, trips or bookings; automatic refunds or customer notifications;
self-service account closure.

## Dependencies

F-010 · F-012 · F-015 · F-002 (public page) · F-005 (booking creation).

## Acceptance criteria

- [ ] Suspend and reactivate each require a confirmation step and a reason; both write an audit event (actor, action, organization, reason, timestamp).
- [ ] Suspending never deletes or alters trips, departures, bookings or payment records (test compares before/after).
- [ ] While suspended, new bookings and date requests for that organization are rejected server-side.
- [ ] Reactivating restores normal behaviour without data changes.
- [ ] Only the platform admin can suspend/reactivate (seller and guest attempts → denied; test).

## Open questions

Effects of suspension not yet decided — do not invent behaviour; resolve before `ready`:

- [ ] Public page: hidden (404), or shown with a neutral "tidak tersedia" notice? — Rengga
- [ ] Can the seller still sign in (read-only) to handle existing customers? — Rengga
- [ ] Existing unpaid bookings: do holds keep expiring normally? Can customers still upload payment evidence? — Rengga
- [ ] Existing paid/upcoming bookings: who tells customers, and is the seller still expected to run the trip? — Rengga

## Changelog

- 2026-10-17 — created
