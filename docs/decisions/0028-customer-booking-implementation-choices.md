---
id: 0028
title: Implementation choices for customer booking and seat hold (F-005)
status: accepted
date: 2026-10-19
deciders: [Rengga]
related: [F-005, F-003, F-006, F-009, ADR-0006, ADR-0021, ADR-0025, ADR-0026, ADR-0027]
---

# 0028. Implementation choices for customer booking and seat hold (F-005)

*Proposed by the builder agent on 2026-10-19 while building F-005; §1 revised the same day after review (Rengga: keep
the session, encrypt it). Accepted by Rengga on 2026-10-19 as a whole; the points are technical calls within ADR-0006,
ADR-0026 and ADR-0027.*

## Context

F-005 adds the first seller-owned rows written by customers: the booking form, its transactional submit, the
customer's booking page, the hold-expiry job and a booking code on every organization. ADR-0026 and ADR-0027 fix the
model; building it meant several smaller choices that the spec leaves open or words differently.

## Decision

1. **Session payloads are encrypted, and the customer booking page runs the session** as ADR-0027 §3 says (confirmed,
   not amended). Laravel's `StartSession` stores every GET URL in the session (`_previous.url`), so a booking-page
   visit puts the access token in the `sessions` row; `SESSION_ENCRYPT` now defaults to `true` (config and
   `.env.example`), so that row is ciphertext under `APP_KEY` — the same exposure as the `encrypted` `access_token`
   column. Running the session matters for F-006: it gives the page the `XSRF-TOKEN` cookie the evidence-upload POST
   needs when the link is opened fresh (e.g. in WhatsApp's in-app browser), and flashed upload errors and toasts reach
   the page. *(Changed 2026-10-19 after review, Rengga's decision: the first draft ran this page without a session.)*
2. **Booking codes are assigned in `Organization::performInsert()`.** Candidates come from the slug in a fixed order
   (stem `BRM`, other letter combinations, `BRM2`…`BRM9`, then random four-letter codes; letters padded with vowels
   then `X` for short slugs; never `RKB`). Each insert runs in a savepoint; a unique violation on
   `organizations_booking_code_unique` retries with the next candidate, any other violation (the slug) is rethrown —
   so sign-up, factories and the backfill share one rule and no prior "is it free?" check exists to race (AC-17b).
   `booking_code` and `last_booking_number` are guarded; a set code can never change.
3. **The organization row is locked first and the submission key checked under that lock.** Same-key double submits
   from two connections therefore serialise and the second returns the first booking; the
   `UNIQUE (organization_id, submission_key)` index stays as a backstop (AC-16). A key is replayed only for the
   departure it was used on (otherwise the submit is refused), since replaying it hands out that booking's link. The
   clock is read after the departure lock, so the deadline check (AC-15) and `hold_expires_at` use the moment the
   submit holds the locks. Lock order is unchanged (organization → departure → bookings, ADR-0026 §3).
4. **Opening the booking form also releases the departure's expired holds** (same service, status change, seat return
   and `booking.hold_expired` event as the job), before it counts seats. ADR-0026 §7 releases inline on submit; without
   this a departure filled by expired holds would show "Penuh" and offer no form while the scheduler is late. F-002's
   trip page still reads `seats_taken` (at most a minute behind with the job running).
5. **Refusals are validation errors back to the form URL** (`departure` → the "tidak bisa dipesan" state, `price`,
   `participants`), and the form re-reads the departure; nothing about the refusal is cached client-side. A trip that
   is unpublished or switched to `request_date` between form and submit is a 404 at submit (AC-2's rule, since the form
   URL would also 404); a change that lands between the 404 check and the lock is caught by `isBookable()` under the
   lock and shows the state (AC-15).
6. **`DepartureBookings::hasAnyBooking()` is `seats_taken > 0` or any booking row.** By the seat invariant the first
   implies the second; keeping it makes the check independent of how a departure's seats were set.
7. **The token route only matches `[A-Za-z0-9]{32}`**, so `/<slug>/pesanan/pesan` (a trip whose slug is `pesanan`)
   still reaches that trip's booking form. Tokens of any other shape fall through to the same Bahasa 404 page.
8. **Upper-case slugs redirect (GET only) with the rest of the path kept**; F-002's redirect rebuilt only
   `/<slug>[/<trip>]`, which would have dropped `/pesan` or the token. A non-GET request with an upper-case slug is a 404.
9. **The JSON log processor redacts the path segment after `/pesanan/`** in every string, JSON-escaped slashes
   included (AC-40, F-009 R20).
10. **The `completed` pill uses "☑"**: the glossary gives no glyph, and ADR-0008 needs one; "✓" is Lunas.
11. **Concurrency helpers** `tests/Support/Concurrency/book.php` (the real `BookingService`) and `release_holds.php`
    (the real job) replace F-003's `take_seats.php` simulation; F-003 AC-39 now runs against the real path.

## Consequences

- Good: no readable token in session storage or logs; F-006's upload works from a freshly opened link; one booking-code rule for every way an organization is created; a late
  scheduler never turns a customer away on the form or on submit.
- Bad / costs: switching on session encryption invalidates existing unencrypted sessions once (everyone signs in again
  — fine before launch; set `SESSION_ENCRYPT=true` in each local `.env`); a session row per customer who opens a
  booking page; a GET of the form can write (only when expired holds exist) and so takes the departure lock briefly; a
  failed booking-code insert burns an organization id (sequence gap). `bookings.total_amount` is `bigint` (999 seats ×
  Rp100.000.000 exceeds `integer`); F-006's payment amounts need the same.
- Follow-ups: F-009 B3 adds the scheduler heartbeat that should also cover `bookings:release-expired-holds`; F-006 uses
  `Booking::holdIsActive()` for uploads.

## Alternatives considered

- **No session on the booking page** (the first draft) — keeps the token out of `sessions`, but leaves F-006's upload
  without a CSRF cookie (419 on a fresh link) and without flashed messages.
- **Session on the booking page, URL not stored** — Laravel has no switch for `_previous.url`; a custom `StartSession`
  subclass would fork framework code for one route.
- **Prior "is the code free?" query before insert** — still needs the retry for races; one path is simpler.
- **Leaving the form read-only and showing "Penuh"** until the job runs — contradicts ADR-0026 §7's intent.
- **Resolving unpublished trips on submit to show the "tidak bisa dipesan" state** — the redirect back to the form would
  404 anyway, so the customer would see the 404 one step later.
