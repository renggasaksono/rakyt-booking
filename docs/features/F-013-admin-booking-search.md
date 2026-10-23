---
id: F-013
title: Admin cross-seller booking search & details
status: ready
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0003", "0004", "0011"]
updated: 2026-10-23
---

# F-013 · Admin cross-seller booking search & details

## Problem

When a customer or seller reports a problem ("I paid but it says unpaid"), the product owner must find the booking
across all sellers and see what happened, without opening the database.

## Scope

**In:** booking search across organizations by booking reference, customer name, customer phone, organization name/slug;
filters by booking status, payment status, organization, departure date; booking detail: seller, trip, departure
(dates, seats), participants, amounts (integer rupiah), payment status and evidence, hold expiry, and audit history.

**Out (explicitly):** editing bookings, changing payment status, refunds, cancellations on the seller's behalf,
customer messaging. Any of these needs a product requirement first.

## Dependencies

F-010 · F-005 · F-006 · F-009 (audit events for history) · F-012 (links to seller).

## Acceptance criteria

- [ ] Exact match on booking reference returns that booking regardless of organization.
- [ ] Phone search normalises input to E.164 like F-005.
- [ ] Filters combine and persist in the URL; results paginated.
- [ ] Detail page is read-only and shows status history from `audit_events` where recorded.
- [ ] Customer phone numbers are shown only on detail pages, not in list exports or logs.
- [ ] A seller session cannot reach these pages or endpoints (test); a seller still cannot read another seller's bookings (existing cross-tenant tests stay green).
- [ ] Usable at phone width.

## Open questions

None.

## Changelog

- 2026-10-17 — created
- 2026-10-23 — **ready.** No open questions; dependencies (F-005/F-006 statuses, F-009 audit events) are shipped. Booking statuses and the `BookingStatus` enum, E.164 phone normalisation and the signed-URL payment evidence it references all exist.
