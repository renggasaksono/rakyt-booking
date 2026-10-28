---
id: F-011
title: Admin overview dashboard
status: done
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0011", "0042"]
updated: 2026-10-28
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

- [x] Shows the five counts, computed live (or briefly cached) from `organizations`, `trips`, `bookings`; no duplicated counters.
- [x] "Pending bookings" counts bookings in `awaiting_payment`; "payment pending verification" counts bookings in `needs_review` — the single `BookingStatus` enum the seller app uses (F-006 kept the lifecycle on `booking.status`; there is no separate payment-status driver).
- [x] Each count links to the matching filtered list in F-012 or F-013.
- [x] Suspended organizations are counted and shown separately from active ones.
- [x] Only reachable through the F-010 gate; tested.
- [x] Usable at phone width.

Tests: `tests/Feature/Admin/AdminOverviewDashboardTest.php` (AC-1…AC-6: counts across organizations, zero state,
live after a change, three count queries naming no organization, indexes leading with `status`, links and the lists
they open, copy, phone-first layout), `AdminAccessControlTest` (F-010 gate on every admin route),
`AdminSellerDirectoryTest` (the `trip=published` filter), `tests/js/adminOverview.test.js`.

Built: `App\Admin\Queries\AdminOverviewQueries` (one grouped query each on `organizations`, `trips`, `bookings`),
`AdminDashboardController`, `resources/js/pages/Admin/Dashboard.vue`, copy in `lang/id/admin.php` (`dashboard`).
Links: sellers → `/admin/penjual` (+ `?status=active` / `?status=suspended`); published trips →
`/admin/penjual?trip=published` (sellers with at least one published trip, a new F-012 filter); bookings →
`/admin/pesanan` (+ `?status=awaiting_payment` / `?status=needs_review`). Index `trips (status)` added. See
[ADR-0042](../decisions/0042-admin-overview-counts.md).

## Open questions

None — status mapping resolved 2026-10-23 (see changelog).

## Changelog

- 2026-10-17 — created
- 2026-10-23 — **ready.** Status mapping pinned from the shipped F-006 code: pending = `awaiting_payment`, pending verification = `needs_review` on the single `App\Bookings\BookingStatus` enum (no `payment_records.status` driver). Counts read `organizations`/`trips`/`bookings` only.
- 2026-10-28 — **done** (`feat/F-011-admin-overview-dashboard`). Live counts, one grouped query per table in
  `AdminOverviewQueries`; suspended sellers apart from active; every count links to its filtered list and a test
  follows each link and compares. Published trips link to the seller directory's new `trip=published` filter (there
  is no admin trip list; trips are read per seller) and count trips of suspended sellers too. `trips (status)` index
  added. ADR-0042 proposed. AC-6 checked by layout test only; real-device review pending with the other admin screens.
- 2026-10-28 — ADR-0042 accepted (Rengga): the published-trips count links to the seller directory filtered to sellers with a published trip.
