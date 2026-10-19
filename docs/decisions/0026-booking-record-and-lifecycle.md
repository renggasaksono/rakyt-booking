---
id: 0026
title: Booking record, status lifecycle, reference and seat accounting
status: proposed
date: 2026-10-19
deciders: [Rengga]
related: [F-005, F-006, F-007, F-013, ADR-0005, ADR-0006, ADR-0017, ADR-0021]
---

# 0026. Booking record, status lifecycle, reference and seat accounting

*Proposed by the planner agent on 2026-10-19 while making F-005 `ready`.*

## Context

F-005 creates the first `bookings` rows, so it has to fix their shape before F-006 (payments) and F-007 (seller
list) build on them. Four things are open or unwritten:

- **Status model.** The blueprint names booking statuses *pending, confirmed, cancelled, completed*; the mockups and
  the glossary use one payment-centred set (`awaiting_payment`, `needs_review`, `paid`, `rejected`, `cancelled`,
  `completed`). F-006 asks whether these are one field or two.
- **Reference format** `RKB-MMDD-NNNN`: does `MMDD` mean the departure date or the booking date, and is `NNNN` unique per
  platform or per seller? The mockups show `RKB-1024-0142` and `RKB-1031-0139` for departures on 24 and 31 Okt, and
  `RKB-1114-0144` for a 14 Nov departure "dibuat 09.12" on 16 Okt; the numbers rise across departures in creation order.
- **Seat accounting.** `trip_departures.seats_taken` exists (F-003) with `CHECK (seats_taken BETWEEN 0 AND capacity)`
  and is "maintained by F-005". ADR-0006 fixes the lock and the 2-hour hold; it does not say which statuses hold seats or
  in what order rows are locked.
- **Lost or repeated submits** on mobile connections (double tap, retry after a timeout).

## Decision

1. **One status column on `bookings`**, using the glossary set: `awaiting_payment` → (`needs_review` → `paid` |
   `rejected`) | `cancelled`; `paid` → `completed`. A `cancel_reason` (`hold_expired`, later `seller`, …) is set exactly
   when the status is `cancelled` (`CHECK`). Amounts due/paid, evidence and verification live in `payment_records`
   (F-006), not in a second status. The blueprint's *pending / confirmed* are UI groupings: pending = `awaiting_payment`
   or `needs_review`; confirmed = `paid`.
2. **Seat-holding statuses** are `awaiting_payment`, `needs_review` and `paid`. Invariant: for every departure,
   `seats_taken` = the sum of `participants` over its bookings in those statuses. Whether `rejected` keeps holding seats
   is F-006's to decide (ADR-0006 "still open"); until then a `rejected` booking does not exist in the app.
3. **Every seat change happens in the transaction that changes the booking status**, through one service
   (`BookingService` / `SeatHoldService`), never by editing `seats_taken` directly. **Lock order** is fixed:
   organization row (only when a reference number is taken) → departure row (`FOR UPDATE`) → booking rows. It matches
   the existing order of the time-zone change (organization, then departures), so the two cannot deadlock.
4. **Price snapshot.** A booking stores `unit_price` (the departure's effective price at creation) and
   `total_amount = participants × unit_price` (`CHECK`). The form sends the unit price it showed; if the server's price
   differs, the booking is refused and the new price shown, so a customer never owes a total they did not see.
5. **Reference** `RKB-MMDD-NNNN`: `MMDD` is the **departure start date**; `NNNN` is a **per-organization** counter
   (`organizations.last_booking_number`, incremented atomically inside the booking transaction), zero-padded to at least
   four digits (`RKB-1024-10023` after 9 999). Unique per organization (`UNIQUE (organization_id, reference)`), not per
   platform; admin search (F-013) always shows the seller next to it. The reference is display-only (ADR-0017).
6. **Idempotent submit.** The form carries a random submission key; `UNIQUE (organization_id, submission_key)` makes a
   repeated submit return the booking it already created instead of a second hold.
7. **Expiry.** A scheduled command releases `awaiting_payment` bookings whose `hold_expires_at ≤ now` (status
   `cancelled`, reason `hold_expired`, seats returned, audited as the system). The booking transaction also releases
   expired holds **on the departure it is booking** before counting seats, so a stalled scheduler never turns away a
   customer for seats that are free. "Hold active" is one rule, `status = awaiting_payment ∧ now < hold_expires_at`,
   which F-006's evidence upload also uses.

## Consequences

- Good: one place answers "does this booking hold seats?"; the seat counter cannot drift silently (invariant test and
  `CHECK`s); no deadlock between bookings and time-zone changes; references stay short and readable per seller.
- Bad / costs: a seller's bookings serialise on the organization row for the length of a booking transaction (fine at
  pilot volume). Two sellers can show the same reference; support must always ask which seller.
- Follow-ups: F-006 decides `rejected` (re-upload, clock) and adds `payment_records`; F-007 adds seller cancellation
  (`cancel_reason = seller`) through the same service; F-004 decides whether accepted date requests create bookings
  through it.

## Alternatives considered

- **Two status fields** (booking: pending/confirmed/cancelled/completed; payment: awaiting/needs_review/paid/rejected)
  — every list, pill and rule would have to combine them; the mockups already show one pill per booking.
- **Platform-wide `NNNN`** — reaches 9 999 early and makes every seller's numbers jump; buys nothing, since the
  reference grants no access.
- **`MMDD` = booking date** — the mockup's 14 Nov booking made on 16 Okt reads `1114`; the departure date is what the
  seller and customer talk about.
- **Counting seats from bookings on every read** (no counter) — simpler writes, but every public page and list would
  aggregate bookings, and F-003 already built the counter and its `CHECK`.
- **Releasing expired holds only from the scheduler** — correct but under-books whenever the scheduler is late.
