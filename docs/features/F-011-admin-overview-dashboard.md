---
id: F-011
title: Admin overview dashboard
status: ready
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0011"]
updated: 2026-10-23
---

# F-011 · Admin overview dashboard

## Problem

During the pilot the product owner needs to see at a glance whether sellers are active and whether bookings are stuck.

## Scope

**In:** `/admin` home with five counts, each linking to a filtered list:
total seller organizations · published trips · total bookings · pending bookings · bookings with payment pending verification.

**Out (explicitly):** charts, revenue/GMV figures, growth or conversion metrics, new aggregate tables. Counts are
computed from existing tables and statuses.

## Dependencies

F-010 · F-001 (organizations) · F-003 (trip published state) · F-005/F-006 (booking & payment statuses) · F-012, F-013 (link targets).

## Acceptance criteria

- [ ] Shows the five counts, computed live (or briefly cached) from `organizations`, `trips`, `bookings`; no duplicated counters.
- [ ] "Pending bookings" counts bookings in `awaiting_payment`; "payment pending verification" counts bookings in `needs_review` — the single `BookingStatus` enum the seller app uses (F-006 kept the lifecycle on `booking.status`; there is no separate payment-status driver).
- [ ] Each count links to the matching filtered list in F-012 or F-013.
- [ ] Suspended organizations are counted and shown separately from active ones.
- [ ] Only reachable through the F-010 gate; tested.
- [ ] Usable at phone width.

## Open questions

None — status mapping resolved 2026-10-23 (see changelog).

## Changelog

- 2026-10-17 — created
- 2026-10-23 — **ready.** Status mapping pinned from the shipped F-006 code: pending = `awaiting_payment`, pending verification = `needs_review` on the single `App\Bookings\BookingStatus` enum (no `payment_records.status` driver). Counts read `organizations`/`trips`/`bookings` only.
