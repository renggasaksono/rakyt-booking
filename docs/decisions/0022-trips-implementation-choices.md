---
id: 0022
title: Implementation choices for trips and departures (F-003)
status: proposed
date: 2026-10-18
deciders: [Rengga]
related: [F-003, F-009, ADR-0004, ADR-0005, ADR-0006, ADR-0014, ADR-0015, ADR-0020, ADR-0021]
---

# 0022. Implementation choices for trips and departures (F-003)

*Proposed by the builder agent on 2026-10-18 while building F-003.*

## Context

F-003 is the first feature built on the F-009 tenancy primitives, and it comes before F-001 (sign-in, payment methods)
and F-005 (bookings). Building it meant making several technical choices the spec leaves open, each of which someone
could later ask "why?" about.

## Decision

1. **PostgreSQL sessions run in UTC** (`config/database.php → pgsql.timezone`). Laravel writes timestamps without an
   offset, so a server in another zone shifted every `timestamptz` value (found with deadlines). Code that passes a
   Carbon to a query converts it to UTC first.
2. **Child tables reference `(organization_id, trip_id)`.** `trip_departures` and `trip_photos` have a composite FK to
   `trips(organization_id, id)`, so the database refuses a row that points at another organization's trip.
   `CHECK (seats_taken <= capacity)` backs up ADR-0006.
3. **Seams for later features, with safe defaults.** `PaymentReadiness` (F-003 Q9) defaults to "no payment method",
   so nobody can publish until F-001 binds the real check. `DepartureBookings` (has the departure ever had a booking?
   how many active ones?) defaults to `seats_taken > 0` and 0 active bookings until F-005 binds a query over bookings.
4. **URLs use a lower-case ULID `public_id`.** Route values that are not integer keys must be short, valid UTF-8
   without NUL bytes (`App\Tenancy\RouteKey`), so malformed input is a 404, never a database error. The same check
   covers `withTrashed()` and `scopeBindings()` routes (F-009 R15, R16).
5. **Photo memory budget: 320 MB; the megapixel cap is derived from it** (F-009 R3; corrected 2026-10-18, see the
   amendment below). `App\Media\PhotoBudget` uses 8 bytes per pixel and 96 MB of headroom, so the cap is
   (320 − 96) MB / 8 ≈ **29 MP**, plus 8 000 px per side. Both are read from the file header before GD decodes
   anything. `PublicImage::sanitize()` raises `memory_limit` to the budget (never lowers it), scales the image down
   to **2 560 px** on the longest side, then applies the EXIF orientation to the small copy. The stored original is
   that ≤ 2 560 px, EXIF-free image. The variants job (400/800/1 600 px, never upscaled) works from it under the same
   budget. Over-cap photos get a Bahasa message; files GD cannot decode get a 422. All numbers are in
   `config/rakyt.php`.
6. **An organization's default deadline is H-1, H-3 or H-7.** "Sampai hari keberangkatan" needs a meeting time,
   which a new trip (and every onboarding trip) may not have, so it cannot be a default.
7. **Auto-close is a scheduled Artisan command** (`departures:close-past-deadline`, every minute,
   `withoutOverlapping()->onOneServer()`), not a queued job: it does not depend on a queue worker. It lists due ids
   across organizations (allowlisted) and closes each one as its own organization, under the row lock, audited as
   the system.
8. **Changes to photos and departures of a published trip apply immediately.** "Simpan perubahan" (AC-4) covers the
   trip's own fields. To keep the checklist true, a published trip cannot lose its last photo or its base price. Moving another photo into first place or removing the cover asks for confirmation first (F-003 AC-7a, decided 2026-10-18).
9. **The concurrency test uses a second process.** AC-39 needs two database connections with committed rows, so that
   test class does not wrap tests in a transaction; it runs a PHP child process that books seats with the conditional
   update F-005 will use, and makes the next test class rebuild the schema (audit events are append-only).
10. **Vitest for the UI's pure helpers** (money, duration, deadline labels, list input, autosave). They run in Node,
    with no DOM, in CI (`npm test`). Calendar names for dates come from `lang/id/trips.php`, matching Carbon's
    Indonesian locale, so client and server dates read the same.

## Consequences

- Good: tenant and seat invariants hold in the database as well as in code. F-001 and F-005 plug in by binding one
  interface each. Malformed URLs never reach PostgreSQL.
- Bad / costs: a published trip's photos and departures change without "Simpan perubahan". One test class rebuilds the
  schema (about 1 s). There is one more JS dev dependency (Vitest).
- Follow-ups: F-001 binds `PaymentReadiness` and recomputes preset deadlines when the time zone changes (ADR-0014).
  F-005 binds `DepartureBookings` and takes seats with the same row lock.

## Alternatives considered

- Leave the PostgreSQL zone to the server: wrong deadlines on any non-UTC host.
- A plain `trip_id` FK only: correct today through the application, but one raw query could cross tenants.
- Create `payment_methods` in F-003: rejected in Q9 (ADR-0018's protection belongs with its form).
- `same_day` as an allowed default with a fallback: hides a silent change of meaning.
- A queued auto-close job: needs a worker to close anything; the scheduler alone is simpler.
- Component tests with jsdom and @vue/test-utils: heavier; the logic worth testing was pulled into plain modules.

## Amendment 2026-10-18 (review B1)

The first version of §5 assumed GD needs 4 bytes per pixel and allowed 40 MP. Measured on PHP 8.4 with GD,
`sanitize()` peaked at **8.3 bytes per pixel**: the decoded image plus a full-size clone made by auto-orientation.
Under the default `memory_limit=128M`, a 16 MP photo was a fatal "memory exhausted". Decoding alone takes about
4.6 B/px for JPEG and WebP and 7.6 B/px for PNG. Scaling first and orienting the small copy removes the clone.

The cap is now derived from an explicit budget instead of being chosen by hand. `tests/Feature/Media/PhotoMemoryBudgetTest`
asserts that cap × bytes per pixel + headroom ≤ limit. It measures GD's bytes per pixel for each format against the
configured 8, and processes cap-sized PNG, JPEG, sideways JPEG and WebP files in a child process running at exactly
the configured limit. Peaks at the cap were 233 MB (PNG) and 173 MB (JPEG/WebP). Photos above 29 MP (e.g. 48 MP
"high-res" camera modes) are refused with a message asking for a lower resolution. Resizing in the browser was
considered and rejected by Rengga: everything stays on the server.
