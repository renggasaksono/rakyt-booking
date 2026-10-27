---
id: F-013
title: Admin cross-seller booking search & details
status: done
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0003", "0004", "0011", "0026", "0039", "0041"]
updated: 2026-10-27
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

- [x] AC-1 Exact match on booking reference returns that booking regardless of organization.
- [x] AC-2 Phone search normalises input to E.164 like F-005.
- [x] AC-3 Filters combine and persist in the URL; results paginated.
- [x] AC-4 Detail page is read-only and shows status history from `audit_events` where recorded.
- [x] AC-5 Customer phone numbers are shown only on detail pages, not in list exports or logs.
- [x] AC-6 A seller session cannot reach these pages or endpoints (test); a seller still cannot read another seller's bookings (existing cross-tenant tests stay green).
- [x] AC-7 Usable at phone width. (Built phone-first and checked statically; real-device review with the other admin screens.)

Tests: `tests/Feature/Admin/AdminBookingSearchTest.php` (AC-1–AC-3, AC-5–AC-7, indexes, fixed query count, nav),
`tests/Feature/Admin/AdminBookingDetailTest.php` (AC-4–AC-7, seats = "Hari ini", hold expiry, payment and evidence,
fixed query count, organization-named reads), `tests/js/adminBookings.test.js` (URL filters). Existing seller
cross-tenant suites (`tests/Feature/Tenancy`, `tests/Feature/Bookings`) stay green.

## Data & rules (as built — [ADR-0041](../decisions/0041-admin-booking-search.md))

- **Routes** (GET only, `auth` + `platform-admin`): `/admin/pesanan` (`admin.bookings.index`) and
  `/admin/pesanan/{public_id}` (`admin.bookings.show`). "Pesanan" in the admin nav. No export, no write route.
- **Search** `cari` is read as one kind: a reference (`RKB-…`, any case) → exact; a whole mobile number in any F-005
  format → E.164 → exact; otherwise part of the customer's name or the seller's name / slug. Partial references and
  partial numbers are not matched (the hint under the box says so).
- **Filters** `status` (the one ADR-0026 status set — booking and payment state share it, so "payment status" is the
  same select), `organisasi` (F-015's searchable picker over the F-012 seller lookup), `dari` / `sampai` (departure
  start date; a reversed range is dropped); invalid values are dropped. 25 per page, newest booking first. Detail
  links carry the filters so "←" restores the list.
- **List row:** reference, customer name, participants, total, status, trip, departure dates (seller-local), created
  (WIB), seller (name, account status, link to F-012's detail). Never the phone, notes, tokens or links.
- **Detail:** seller (account status, link), customer name **and phone**, participants, trip, departure dates /
  status / seats (`SeatBreakdown`, as "Hari ini"), unit price and total, created time, hold expiry while
  `awaiting_payment` (flagged once past), cancel reason, payment record (amount due, evidence via F-012's signed route
  with upload time, the seller's decision, verification time, reject reason) and the booking's audit events
  (`AuditEventQueries::forSubject()`, newest 20). Notes are not shown (not in scope).
- **Indexes** (migration `2026_10_27_000005`): `bookings (reference)`, `(customer_phone)`, `(created_at, id)`,
  `trip_departures (start_date)`.

## Open questions

None. (The spec's "payment status" filter is answered by ADR-0026 §1 — one status column for booking and payment
state; see ADR-0041. Name search has no trigram index yet — follow-up in ADR-0041.)

## Changelog

- 2026-10-17 — created
- 2026-10-23 — **ready.** No open questions; dependencies (F-005/F-006 statuses, F-009 audit events) are shipped. Booking statuses and the `BookingStatus` enum, E.164 phone normalisation and the signed-URL payment evidence it references all exist.
- 2026-10-27 — **built; status → done** (`feat/F-013-admin-booking-search`). `/admin/pesanan` search (exact
  reference, whole phone → E.164, or customer / seller name or slug) with status, seller and departure-date filters in
  the URL, 25 per page; one query joins each booking to its own departure, trip and organization on the composite
  keys. `/admin/pesanan/{id}` read-only detail with seller (+ suspended status), customer incl. phone, departure seats
  from `SeatBreakdown`, amounts, payment record and evidence through F-012's signed route, hold expiry and audit
  history. Four indexes for the cross-organization reads. The audit view's organization picker became a shared
  component. ADR-0041 proposed. AC-7 real-device review pending with the other admin screens.
