---
id: F-005
title: Customer booking & seat hold
status: done
priority: P0
roadmap: mvp-weeks-2-3
mockups: [1b, 1c, 1d, 2e, 3e]
decisions: ["0003", "0004", "0005", "0006", "0007", "0008", "0013", "0014", "0017", "0021", "0025", "0026", "0027", "0028"]
updated: 2026-10-19
---

# F-005 · Customer booking & seat hold

## Problem

Customers book by chatting the operator; bookings get lost in WhatsApp threads and the same seat is promised to two
people. After F-002 a customer can see the dates, seats and prices, but "Pesan →" leads nowhere. They need a short
form on their phone that books seats for a specific departure, tells them plainly how long the seats are held, and
gives them a booking reference and a link to come back to — without an account. The operator needs bookings that can
never overbook a departure, and unpaid holds that release themselves.

## Scope

**In:**
- **Booking form** (mockup 1c) at `/{slug}/{trip}/pesan?keberangkatan=…`: trip and departure summary, Nama lengkap,
  Nomor WhatsApp, Jumlah peserta (stepper capped by seats left), Catatan (opsional), live total, the seat-hold policy
  and a privacy line before the gold "Kirim pesanan".
- **Booking creation**, concurrency-safe: bookability re-checked and seats taken under the departure row lock
  (ADR-0006), price snapshot, booking reference, access token, audit event; idempotent against double submits.
- **Seat hold**: 2 hours from creation (ADR-0006); automatic release by a scheduled job and inline when the same
  departure is booked; the single "hold active" rule that F-006's evidence upload uses.
- **Customer booking page** at `/{slug}/pesanan/{token}` (top half of mockups 1d/2e): "Pesanan terkirim", reference,
  status pill, hold countdown and release time, booking summary, "Kabari operator via WhatsApp"; expired state.
- **Abuse protection**: `throttle:public-booking` (per phone and per IP), `throttle:public-page` on GETs.
- The real `DepartureBookings` binding for F-003 (delete guards, "n pesanan yang ada").
- The two-connection concurrency test helper and the last-seat tests (handed over from F-009 slice A).
- F-009 hand-offs: **R1** (empty-phone bucket), **R20** (token redaction in logs), **A5** booking case, **A10/A11**
  for `booking.created` and `booking.hold_expired`; **R17** only if this feature queues a job (it does not need to).

**Out (explicitly):**
- Payment instructions, the 3-step progress, evidence upload, the `needs_review` transition and seller verification
  (F-006 — they complete mockups 1d/2e/3e on the page this feature builds).
- Seller booking list, booking detail, seller cancellation, "Ingatkan via WA" (F-007, F-008).
- Request-a-date bookings (F-004); booking a `request_date` trip here is a 404.
- Customer accounts, per-participant details (names, ID numbers), child/group prices, add-ons, promo codes (P1).
- Deposits / DP 50% (P1, F-006 Q3).
- Automated WhatsApp/SMS/email messages to the customer or the seller (P1 automated notifications; Q8).
- Seller-configurable hold length, manual hold extension, a review deadline (ADR-0006).
- International (non-+62) WhatsApp numbers (Q6).
- CAPTCHA, honeypots, device fingerprinting — rate limits only in P0.

## User stories

- As a customer, I want to book seats for the date I picked with just my name, WhatsApp number and how many people,
  so that I don't have to negotiate in chat.
- As a customer, I want to see the total and how long my seats are held before I send, so that I know what I'm
  committing to.
- As a customer, I want a booking reference and a link I can reopen from my WhatsApp history, so that I can pay and
  check my booking later.
- As a customer who loses the race for the last seat, I want a clear message straight away, so that I can pick another
  date.
- As a seller, I want every booking to take seats atomically, so that a departure is never overbooked.
- As a seller, I want unpaid holds to release by themselves after 2 hours, so that seats come back without me chasing.
- As a seller, I want my customers' details visible only to me, so that I can trust Rakyt with them.

## Acceptance criteria

UI copy is Bahasa Indonesia from a new `lang/id/booking.php` (ADR-0007). Dates and times are in the organization's
time zone (ADR-0014). *Bookable* is F-003's single rule (`TripDeparture::isBookable()` / `scopeBookable()`); this
feature never re-implements it. "Holding statuses" are `awaiting_payment`, `needs_review`, `paid` (ADR-0026 §2).
Test names: `test_ac_<n>_…`.

### A. Booking form (1c)

- [x] **AC-1 Form for a bookable departure.** Given a published `scheduled` trip `karimunjawa-3h2m` of `kencana` and a
  bookable departure with public id `X`, when anyone opens `/kencana/karimunjawa-3h2m/pesan?keberangkatan=X`, then the
  form renders (200) with: a back link "←" to the trip page, the kicker "Langkah 2 dari 3", the title "Data pemesan",
  the trip name, and the date line "Kam 24 – Sab 26 Okt" (one date for 1-day trips; year added when not the current
  year) followed by "· Pelabuhan Jepara 06.00" when the trip has a meeting point (and time).
- [x] **AC-2 Not found.** Given any of: unknown, reserved or malformed slug; a draft or unpublished trip; a
  `request_date` trip; a missing, malformed or unknown `keberangkatan`; a departure of another trip of the same seller;
  a departure of another seller — then the response is the same Bahasa 404 page as F-002 AC-3, for GET and POST, and
  nothing is written.
- [x] **AC-3 Departure no longer bookable.** Given a departure of this trip that exists but is not bookable now (full,
  closed, past its deadline, or started), when the form URL is opened, then the page (200) shows the trip and date line,
  "Tanggal ini sudah tidak bisa dipesan." with the reason word ("Penuh" or "Ditutup"), no form, and the gold
  "Pilih tanggal lain →" linking to the trip page.
- [x] **AC-4 Fields.** The form has, in this order: **Nama lengkap** (`autocomplete="name"`); **Nomor WhatsApp** with a
  fixed "+62" prefix, `inputmode="tel"`, `autocomplete="tel-national"` and the helper "Konfirmasi & info keberangkatan
  dikirim ke sini."; **Jumlah peserta** stepper with the helper "Sisa kuota n"; **Catatan (opsional)** (multi-line,
  counter "n/500" once 400 characters are typed). Labels are visible text, not placeholders.
- [x] **AC-5 Participant stepper.** Starts at 1; "−" is disabled at 1 and "+" at the seats left when the page loaded;
  a typed value is clamped to 1…seats left; both buttons are ≥ 44 × 44 px and have Bahasa `aria-label`s.
- [x] **AC-6 Live total.** Below the fields the form shows "2 × Rp1.250.000" and the total "Rp2.500.000"
  (participants × the departure's effective price, integer rupiah, the F-003 formatter), updated on every stepper
  change without a request.
- [x] **AC-7 Hold policy before submit.** Directly above "Kirim pesanan" the form says "Kuota ditahan 2 jam sampai
  dibayar, lalu dilepas." (shortened to one line on mobile, Q11). The number of hours comes from the same constant the
  server uses (`config('rakyt.booking.hold_minutes')` = 120, ADR-0006; not settable per seller).
- [x] **AC-8 Privacy line.** Under the button: "Data Anda hanya diteruskan ke :name untuk mengurus pesanan ini." with a
  "Privasi" link to `/privasi` (Q13).
- [x] **AC-9 Submit button.** "Kirim pesanan" is the one gold button on the page; while sending it shows "Mengirim…",
  is disabled, and a second tap sends nothing. On a network failure the form keeps every value and shows "Belum
  terkirim. Periksa koneksi lalu coba lagi."; retrying reuses the same submission key (AC-16).

### B. Validation & creation

- [x] **AC-10 Validation.** Given a submit, then: Nama lengkap is required, trimmed, inner whitespace collapsed,
  2–80 characters, no control characters ("Isi nama lengkap (2–80 huruf)."); Nomor WhatsApp is required and must
  normalise to an Indonesian mobile number `+628…` with 8–11 digits after "+628" ("Nomor WhatsApp tidak valid. Contoh:
  0812 3456 7890."); Jumlah peserta is an integer ≥ 1 ("Minimal 1 peserta."); Catatan is at most 500 characters. Errors
  show inline under their field, the page scrolls to the first one, and all typed values are kept.
- [x] **AC-11 Phone normalisation.** Given "0812 3456 7890", "812-3456-7890", "+62 812 3456 7890", "62812345678 90"
  or "0062 812 3456 7890", then the stored `customer_phone` is `+6281234567890` (`App\Security\PhoneNumber`, the same
  rule as the seller's number, F-001). The rate-limit key uses the same value (F-009 A15).
- [x] **AC-12 Create.** Given a valid submit for a bookable departure with enough seats, then in **one** transaction:
  a booking is created with status `awaiting_payment`, `unit_price` = the departure's effective price now,
  `total_amount` = participants × `unit_price`, `hold_expires_at` = `created_at` + 120 minutes, a reference and an
  access token; the departure's `seats_taken` grows by the participants; and one `booking.created` audit event is
  written (actor `customer`, the booking as actor id, IP). The response redirects (303) to the booking page
  `/{slug}/pesanan/{token}`.
- [x] **AC-13 Price changed since the form loaded.** Given the form showed Rp1.250.000 and the seller changed the
  effective price to Rp1.300.000 before the submit, then no booking is created and the form shows "Harga untuk tanggal
  ini berubah menjadi Rp1.300.000 per orang. Periksa total lalu kirim lagi." with the total recomputed; the next
  submit at the new price succeeds (ADR-0026 §4, Q9).
- [x] **AC-14 Seats changed since the form loaded.** Given participants > seats left at submit time, then no booking is
  created; when seats left > 0 the form shows "Sisa kuota tinggal n. Kurangi jumlah peserta." and the stepper maximum
  becomes n; when seats left = 0 it shows AC-3's state with "Penuh".
- [x] **AC-15 Bookability re-checked under the lock.** Given the departure was closed, its deadline passed, its trip
  was unpublished or switched to `request_date`, or it started, after the form loaded, then the submit creates
  nothing and shows AC-3's state — except a trip unpublished or switched to `request_date` before the submit reaches
  the lock, which is AC-2's 404 (the form URL is a 404 then too; ADR-0028 §5). The deadline is exact: with a deadline of 23.59.59, a submit at 23.59.30 succeeds and
  one at 00.00.00 is refused — also with the scheduler stopped and the departure still `open` (F-003 AC-36, F-009 B5).
- [x] **AC-16 Idempotent submit.** Given the same submission key is posted twice (double tap, retry after a timeout),
  then exactly one booking exists, seats are taken once, one audit event is written, and both responses redirect to the
  same booking page. A new form load gets a new key.
- [x] **AC-17 Booking reference.** Given a seller with booking code `BRM` and a booking on a departure starting
  24 Okt, then its reference is `RKB-BRM-1024-NNNN`, where NNNN is the organization's next booking number zero-padded to
  4 digits (more digits after 9 999). Two simultaneous bookings of one seller never get the same number, and two
  sellers whose counters are equal still get different references (`RKB-BRM-1024-0144` vs `RKB-KMD-1024-0144`): a
  reference is unique across the platform (ADR-0026 §5, Q2).
- [x] **AC-17b Seller booking code.** Given an organization is created (F-001 sign-up) or exists before this feature
  (migration backfill), then it has a `booking_code` of 3–4 upper-case characters derived from its slug
  (`bromo-adventure` → `BRM`, `komodo-trip` → `KMD`), unique across all organizations (two sellers racing for the same
  code: the unique index decides and the loser gets the next candidate), never `RKB`, and unchanged when the seller
  later changes their slug or name.
- [x] **AC-18 Access token.** Given a booking, then its link carries a token of 32 random URL-safe characters; the
  database stores its SHA-256 hash (unique, used for lookup) and an encrypted copy, never the plain token in a readable
  column (ADR-0027 §2). The token is not in any audit event, log line or flashed session data. `/{slug}/pesanan/<reference>`
  is a 404: the reference grants nothing (ADR-0017).

### C. Concurrency & seat accounting (ADR-0006)

- [x] **AC-19 Last seat.** Given a departure with 1 seat left, when two customers submit for 1 participant at the same
  moment from separate database connections, then exactly one booking is created, the other customer gets AC-14's
  "Penuh" state, `seats_taken` = capacity, and there is one `booking.created` event.
- [x] **AC-20 Last seats, larger groups.** Given 3 seats left, when two customers submit for 2 participants each at the
  same moment, then exactly one succeeds and the other gets "Sisa kuota tinggal 1. Kurangi jumlah peserta."
- [x] **AC-21 Booking against a capacity cut.** F-003 AC-39's two tests run against the real booking path instead of
  the `take_seats.php` simulation: customer first → the seller's cut is refused; seller first → the booking is refused;
  never `seats_taken > capacity`.
- [x] **AC-22 Booking against a hold release.** Given a departure whose only free seats belong to a hold that is being
  released by the expiry job, when a customer books those seats at the same moment, then both finish without a lost
  update: afterwards `seats_taken` equals the sum of participants of the departure's holding bookings.
- [x] **AC-23 Seat invariant.** After every test in this feature that changes bookings, for each departure
  `seats_taken` = Σ participants of its bookings in holding statuses. Database `CHECK`s reject: `participants < 1`;
  `unit_price` outside Rp1.000–Rp100.000.000; `total_amount ≠ participants × unit_price`; a status outside the
  glossary set; `cancel_reason` set without `cancelled` or missing with it (F-009 Data & rules).

### D. Hold & expiry

- [x] **AC-24 Expiry job.** Given an `awaiting_payment` booking with `hold_expires_at ≤ now`, when
  `bookings:release-expired-holds` runs (scheduler: every minute, `withoutOverlapping`, `onOneServer`), then, under the
  departure lock, the booking becomes `cancelled` with `cancel_reason = hold_expired`, `seats_taken` drops by its
  participants, and one `booking.hold_expired` event is written with actor `system` naming the command (F-009 A11).
  Running it twice, or two runs at once, gives the same end state and one event (F-009 B4). The command is the only new
  entry in `cross_organization_allowlist`; it lists ids across organizations and releases each as its organization,
  like `departures:close-past-deadline`.
- [x] **AC-25 What the job never releases.** A booking in `needs_review` (whatever its `hold_expires_at`), `paid`,
  `cancelled`, or `awaiting_payment` with `hold_expires_at > now` is untouched. Boundary: at `created_at` + 1:59:59 the
  hold is active; at + 2:00:00 it has expired.
- [x] **AC-26 Inline release.** Given a full departure whose seats are partly held by an expired, not-yet-released
  hold, when a customer books it with the scheduler stopped, then the booking transaction first releases the expired
  holds of **that** departure (same status change, seat return and `booking.hold_expired` event as AC-24), then takes
  the seats; the customer is not turned away (ADR-0026 §7).
- [x] **AC-27 One "hold active" rule.** `Booking::holdIsActive(now)` is true exactly when status is `awaiting_payment`
  and `now < hold_expires_at`; an expired hold is inactive even before any job runs. F-006's evidence upload must use
  it (ADR-0006: upload after expiry is refused; upload before expiry stops the clock).
- [x] **AC-28 No extension.** `hold_expires_at` is set once at creation: it is not mass-assignable, no route changes
  it, and the booking service refuses to change it on an existing booking (ADR-0006: sellers cannot extend a hold).

### E. Customer booking page (1d/2e, top half)

- [x] **AC-29 Content.** Given a booking in `awaiting_payment`, when its link is opened, then the page shows "Pesanan
  terkirim", the reference in large type, the status pill "◷ Menunggu pembayaran" (tint + glyph + word), the countdown
  ("1 j 52 m"; "52 m" under an hour; "< 1 m" under a minute) and "Kuota ditahan s.d. 16.40. Setelah itu kuota dilepas
  otomatis." ("s.d. besok 01.10" when the release is on the next local day); and a summary: trip name, date line,
  meeting point and time, Nama, Nomor WhatsApp, "2 peserta × Rp1.250.000" and "Total Rp2.500.000".
- [x] **AC-30 Countdown without a trusted phone clock.** The page receives the server's current time and
  `hold_expires_at`; the countdown runs on the offset between them, so a wrong phone clock does not change it. At zero
  the page switches to the expired state (AC-31) without a reload.
- [x] **AC-31 Expired.** Given a booking cancelled with `hold_expired` — or `awaiting_payment` past `hold_expires_at`
  before the job has run — then the page shows "– Dibatalkan", "Waktu pembayaran habis. Kuota sudah dilepas." and,
  while the trip is still published, an ink link "Pesan lagi" to the trip page. No countdown.
- [x] **AC-32 Other statuses.** Given any other status, the page shows its pill from the glossary status set (glyph +
  word, never colour alone) and no countdown; F-006 adds its own content for `needs_review`, `paid` and `rejected`.
- [x] **AC-33 Kabari operator via WhatsApp.** The page has an ink-outline "Kabari operator via WhatsApp" linking to
  `https://wa.me/<seller digits>?text=…` with `lang/id/booking.php → whatsapp.customer`, URL-encoded: "Halo :seller,
  saya sudah pesan :trip tanggal :date untuk :n orang. Kode pesanan :reference." It opens in a new tab/app
  (`rel="noopener noreferrer"`). There is no gold button on the page until F-006 adds "Unggah bukti transfer".
- [x] **AC-34 Page outlives the trip's state.** Given the trip was unpublished, or the departure closed, filled or
  started after booking, then the booking page still renders (F-003 AC-15).
- [x] **AC-35 Token checks.** Given a wrong token, another booking's token of another seller under this slug, a token
  with one character changed, or the reference instead of a token, then the response is the same 404 page as AC-2.
- [x] **AC-36 Headers and head tags.** The booking page sends `Referrer-Policy: no-referrer`, `Cache-Control:
  no-store, private` and `X-Robots-Tag: noindex`; the form sends `Cache-Control: no-store, private`. The booking page
  `<title>` is "Pesanan Anda · :seller"; neither page renders Open Graph tags or any customer data in `<head>`.
- [x] **AC-37 Only listed fields leave the server.** The form and booking page props are built by an explicit presenter
  (no model `toArray()`); a test asserts the exact keys in Data & rules → *Public fields* and that no numeric id,
  `organization_id`, `access_token_hash`, other bookings, `seats_taken` or seller-private data is present.

### F. Tenancy, abuse, logs, integration

- [x] **AC-38 Cross-tenant.** Given sellers A (`kencana`) and B (`bahari`): (a) `GET` and `POST /kencana/<trip of
  A>/pesan` with B's departure public id → 404, no booking, B's `seats_taken` unchanged; (b) `/kencana/<trip of B's
  slug>/pesan` → 404; (c) B's booking token under `/kencana/pesanan/…` → 404, and under `/bahari/pesanan/…` → 200;
  (d) a seller of A signed in who books on B's page creates a booking that belongs to B, and neither page shows any of
  A's data (F-009 A5 booking case; ADR-0021).
- [x] **AC-39 Rate limits.** `POST …/pesan` uses `throttle:public-booking`: 5 per hour per normalised phone and 30 per
  10 minutes per IP (`config/rakyt.php`). An empty or unparseable phone adds no phone bucket — only the IP limit applies
  (F-009 R1). Over a limit, the form shows "Terlalu banyak percobaan. Coba lagi dalam n menit." and keeps the typed
  values (F-009 A14). The form and booking page GETs use `throttle:public-page`.
- [x] **AC-40 Logs.** Given a request to a booking page, then no log line, error-tracker payload or exception context
  contains the token (the path segment after `/pesanan/` is redacted, F-009 R20), and phone numbers are masked
  (F-009 A24).
- [x] **AC-41 F-003 integration.** The real `DepartureBookings` binding replaces `SeatsTakenDepartureBookings`:
  `hasAnyBooking` is true for a departure with any booking, cancelled ones included (F-003 AC-16, AC-29 — delete is not
  offered, and the `bookings` foreign key also restricts deleting the departure); `activeBookingCount` counts bookings
  in holding statuses (F-003 AC-32's "n pesanan yang ada").
- [x] **AC-42 Phone width and design rules.** Both pages work at 360 px without horizontal scroll; tap targets ≥ 44 px;
  text ≥ 12 px (11 px only for uppercase kickers); the form's total and "Kirim pesanan" sit in a sticky bottom bar that
  never covers the last field; exactly one gold button per screen; status words always carry a glyph (ADR-0008); all
  copy comes from `lang/id/booking.php`. *Device review with F-002/F-003's.*

## Data & rules

### Routes (ADR-0027)

| Method & path | Name | Middleware |
|---|---|---|
| `GET /{organization}/{trip}/pesan?keberangkatan=` | `public.booking.create` | `web` with session + CSRF, **without** `ResolveCurrentOrganization` and `AuthenticateSession`; `public-organization`; `throttle:public-page` |
| `POST /{organization}/{trip}/pesan` | `public.booking.store` | same, `throttle:public-booking` instead of `public-page` |
| `GET /{organization}/pesanan/{token}` | `public.booking.show` | same as the form, plus `no-referrer`; session payloads encrypted (`SESSION_ENCRYPT`, ADR-0028 §1) |

- Registered so they never shadow an app route (fallback routes, ADR-0025 §1). The organization comes from the slug
  (`ResolvePublicOrganization`, which also enforces `PublicVisibility::organizationIsPublic` — F-014's hook); the trip
  through `PublicVisibility::trips()` (published) and `booking_mode = scheduled`; the departure by `public_id` within
  that trip; the booking by `access_token_hash` within the organization. `isPublicPage()` covers the three routes, so
  no seller shared props are sent.
- F-002's trip page already links "Pesan →" to `…/pesan?keberangkatan=<public_id>` (AC-22 there); no change needed.

### `bookings` (new, seller-owned; `BelongsToOrganization`, `BookingPolicy extends OrganizationOwnedPolicy`)

| Column | Type | Rules |
|---|---|---|
| `id` | bigint | Never exposed publicly |
| `organization_id` | FK, not null | Tenant scope (ADR-0004) |
| `public_id` | ULID, unique | For seller URLs (F-007) |
| `trip_departure_id` | bigint | Composite FK `(organization_id, trip_departure_id)` → `trip_departures (organization_id, id)`, **restrict on delete** (add `UNIQUE (organization_id, id)` on `trip_departures`) |
| `reference` | string(24) | `RKB-CODE-MMDD-NNNN`; `UNIQUE (organization_id, reference)`; unique platform-wide by construction (unique `booking_code` + per-organization counter, AC-17) |
| `access_token_hash` | char(64) | SHA-256 of the token; unique |
| `access_token` | text | Laravel `encrypted` cast; hidden from serialisation |
| `submission_key` | uuid | `UNIQUE (organization_id, submission_key)` (AC-16) |
| `customer_name` | string(80) | AC-10 |
| `customer_phone` | string(16) | E.164 `+628…` |
| `participants` | smallint | `CHECK (participants >= 1)` |
| `notes` | text, nullable | ≤ 500 chars (app) |
| `unit_price` | integer | Snapshot; `CHECK BETWEEN 1000 AND 100000000` |
| `total_amount` | bigint | `CHECK (total_amount = participants::bigint * unit_price)` (999 × Rp100.000.000 exceeds `integer`) |
| `status` | string(20) | `CHECK IN ('awaiting_payment','needs_review','paid','rejected','cancelled','completed')` |
| `cancel_reason` | string(20), nullable | `CHECK ((status = 'cancelled') = (cancel_reason IS NOT NULL))`; values `hold_expired` now, `seller` later (F-007) |
| `hold_expires_at` | timestamptz | `created_at + 120 min`, set once (AC-28) |
| `created_at`, `updated_at` | timestamptz | UTC |

Indexes: `(status, hold_expires_at)` for the expiry job; `(organization_id, trip_departure_id, status)`;
`(organization_id, created_at)` for F-007. Schema and model guards (F-009 A6/A7) must pass.

**`organizations.last_booking_number`** (new, integer, default 0): the per-organization reference counter (ADR-0026 §5).

**`organizations.booking_code`** (new, string(4), not null after backfill): `CHECK (booking_code ~ '^[A-Z]{3}[A-Z0-9]?$'
AND booking_code <> 'RKB')`, `UNIQUE`. Set once when the organization is created; never edited in P0 (no UI, and
`booking_code` joins `organization_id` among the attributes that cannot be updated once a booking exists). Derivation
from the slug: first letter, then the next consonants (`bromo-adventure` → `BRM`); on collision try other letter
combinations from the slug, then the 3-letter stem plus a digit (`BRM2`…`BRM9`), then four random letters. The insert
relies on the unique index, not a prior check (AC-17b). (ADR-0026 §5.)

### Statuses and transitions (ADR-0026)

| From | To | By | Seats |
|---|---|---|---|
| — | `awaiting_payment` | customer submit (F-005) | + participants |
| `awaiting_payment` | `cancelled` (`hold_expired`) | expiry job or inline release (F-005) | − participants |
| `awaiting_payment` | `needs_review` | evidence upload while `holdIsActive` (F-006) | unchanged; clock stops |
| `needs_review` | `paid` / `rejected` | seller (F-006) | unchanged / F-006 decides |
| holding status | `cancelled` (`seller`) | seller (F-007, later) | − participants |

Holding statuses: `awaiting_payment`, `needs_review`, `paid`. Invariant: `seats_taken` = Σ participants of holding
bookings (AC-23). Every transition that changes seats goes through one service (e.g. `App\Bookings\BookingService`,
`SeatHolds`) — never a direct `seats_taken` update and never `DepartureService` internals.

### Booking transaction (ADR-0006, ADR-0026 §3)

Inside `DB::transaction`, in this lock order (the same order as the F-001 time-zone change, so no deadlock):

1. Return the existing booking if `submission_key` is already used (AC-16).
2. `organizations.last_booking_number = last_booking_number + 1` (atomic, row-locked) → `NNNN`.
3. Lock the departure (`lockForUpdate()`), reload it with its trip; release expired holds of this departure
   (lock those booking rows, AC-26).
4. Re-check `isBookable(now)` (AC-15), `unit_price` = effective price vs the posted price (AC-13), seats left vs
   participants (AC-14). Any failure → throw a validation error; the whole transaction (counter included) rolls back.
5. Insert the booking; `seats_taken += participants` (conditional `seats_taken + n <= capacity` as a second guard; the
   `CHECK` is the last one); write `booking.created` through `AuditLog`.

### Expiry job

`bookings:release-expired-holds`, every minute, `withoutOverlapping()->onOneServer()`, heartbeat monitored with the
scheduler (F-009 B3). Lists `(id, organization_id)` of `awaiting_payment` bookings with `hold_expires_at <= now (UTC)`
across organizations (allowlisted), then per organization under `CurrentOrganization::runAs`: lock departure → lock
booking → re-check → release → audit. Runs synchronously (no queued job, so F-009 R17 does not apply; if a builder
queues it, pass ids and load inside `handle()` under `ScopedToOrganization`).

### Audit (F-009 A10/A11)

- `booking.created` — actor `customer` (actor id = booking id), IP; `after`: `reference`, `trip_departure_id`,
  `participants`, `unit_price`, `total_amount`, `status`, `hold_expires_at`. No name, phone, notes or token.
- `booking.hold_expired` — actor `system` (`bookings:release-expired-holds`, also for inline release); `before`/`after`:
  `status`, `cancel_reason`.
- `seats_taken` changes are recorded by these booking events, not as separate departure events.

### Public fields (AC-37)

- **Form props:** `title`, `organization` (`name`, `slug`), `trip` (`slug`, `name`), `departure` (`public_id`,
  `start_date`, `end_date`, `state` `bookable|full|closed`, `seats_left`, `price`), `meeting` (`point`, `time`),
  `submission_key`, `hold_minutes`, `context` (`today`), `copy`, `calendar`, `urls` (`trip`, `store`, `privacy`,
  `home`), `errors`. `seats_left`/`price` are null unless bookable.
- **Booking page props:** `title`, `organization` (`name`, `slug`, `whatsapp_url`), `booking` (`reference`, `status`,
  `cancel_reason`, `participants`, `unit_price`, `total_amount`, `customer_name`, `customer_phone`, `hold_expires_at`
  as ISO UTC), `trip` (`name`, `url` or null when unpublished), `departure` (`start_date`, `end_date`), `meeting`
  (`point`, `time`), `context` (`now` ISO UTC, `timezone`), `copy`, `calendar`, `urls` (`home`), `errors`.

### Rate limits

| Endpoint | Key | Default |
|---|---|---|
| `POST …/pesan` | normalised phone (none when empty — R1) | 5 per 60 min |
| `POST …/pesan` | IP | 30 per 10 min |
| `GET …/pesan`, `GET …/pesanan/{token}` | IP (`public-page`) | 120 per min |

### Config

`config/rakyt.php → booking`: `hold_minutes` 120 (ADR-0006 — a constant, not a seller setting), `notes_max` 500,
`name_max` 80.

### Edge cases covered

- Last seat / last seats raced (AC-19, AC-20); booking vs capacity cut (AC-21); booking vs hold release (AC-22).
- Double tap, retry after timeout, back-button resubmit (AC-9, AC-16).
- Price or seats changed between page load and submit (AC-13, AC-14); departure closed, deadline passed, trip
  unpublished or mode changed meanwhile (AC-15); deadline second boundary and scheduler stopped (AC-15, AC-26).
- Hold expired but the job late (AC-26, AC-27, AC-31); job run twice or on two servers (AC-24).
- Hold that outlasts the booking deadline or even the meeting time (a booking just before a same-day deadline): kept
  exactly 2 h per ADR-0006; the seller sorts it out (Q12).
- Same phone booking the same departure twice: allowed (families, corrections), capped by the rate limit.
- Reference counter past 9 999 (AC-17); two sellers with the same counter value (different references through the seller code, AC-17).
- Wrong/forged/other-seller token, reference as token (AC-35, AC-38); token in logs (AC-40) or referrer (AC-36).
- Signed-in seller booking another seller's trip, or their own (AC-38 d — a booking is never tied to a user).
- Trip unpublished or departure closed after booking (AC-34). Seller slug changed by support: old links 404 (ADR-0027).
- Wrong phone clock (AC-30); slow or dropped connection (AC-9); 360 px screens (AC-42).
- HTML/script in name or notes: stored as text, rendered as text (no `v-html`), as F-002 AC-27.

### Dependencies

- **F-003** (built): `TripDeparture::isBookable()`, effective price, `seats_taken` + `CHECK`, `DepartureBookings`
  contract, money/date formatters. F-005 binds the real `DepartureBookings` (AC-41).
- **F-002** (built): `ResolvePublicOrganization`, `PublicVisibility`, the "Pesan →" link, 404/429 pages, date helpers
  in `resources/js/lib/publicPage.js`.
- **F-001** (built): organization `name`, `slug`, `whatsapp`, `timezone`; `PhoneNumber` and the `+628…` rule.
- **F-009**: tenancy primitives, `AuditLog`, limiters (fix **R1** first), log redaction (**R20**), heartbeat (B3),
  A5/A9/A10/A11. The two-process helper `tests/Support/Concurrency/take_seats.php` is generalised here to call the
  real booking service (AC-19–AC-22).
- **F-006** (next): adds payment instructions, the progress steps, evidence upload (must use `holdIsActive`, AC-27)
  and the `needs_review`/`paid`/`rejected` transitions on the booking page this feature builds.
- **F-007**: seller list and detail read `bookings`; seller cancellation uses the same service.
- **F-008**: moves `whatsapp.customer` into its template set.
- **F-014**: suspension hooks into `PublicVisibility` and so covers these routes; what a suspended seller's pending
  holds do is F-014's call.
- **ADR-0026, ADR-0027** (both accepted 2026-10-19).

## Test plan

| Area | Tests |
|---|---|
| Form & validation (A, B) | Feature tests per AC-1–AC-18 (`tests/Feature/Bookings/BookingFormTest`, `CreateBookingTest`); phone normalisation data set; deadline second boundary with `travelTo`; scheduler not run |
| Concurrency (C) | `BookingConcurrencyTest` on PostgreSQL with committed rows (no wrapping transaction, schema rebuilt after, like F-003's `CapacityConcurrencyTest`): last seat, last seats, capacity cut (both orders), hold release vs booking; a second-process helper calling the real booking service; invariant assertion after each |
| Hold & expiry (D) | `ReleaseExpiredHoldsTest`: due/not due/boundaries, `needs_review` untouched, idempotent double run, allowlist exact, audit actor; inline release with the scheduler stopped |
| Booking page (E) | Content per status, expired-before-job, headers, `<head>`, exact prop keys, token negatives |
| Tenancy (F) | `BookingTenancyTest` with `InteractsWithTenants`: AC-38 a–d; schema/model guards; `BookingPolicy` 404 |
| Abuse & logs | Limiter buckets (same phone in 3 formats → one bucket; empty phone → IP only); 6th booking in an hour refused with input kept; log capture asserts no token and masked phone |
| F-003 integration | `DepartureBookings` binding: delete not offered with a cancelled booking; "n pesanan yang ada" count |
| JS (Vitest) | `tests/js/booking.test.js`: stepper clamp, total, countdown offset and formatting ("1 j 52 m", "< 1 m"), release-time wording (today / besok) |
| Manual | 360 px browser check of both pages; real-device review with F-002/F-003 |

## Build notes (2026-10-19)

Built test-first on `claude/f-005-customer-booking-9edbd4`; every AC above has a passing test (AC-42's real-device
review is still to do together with F-002/F-003's, as the AC says). Choices the spec left open are in
[ADR-0028](../decisions/0028-customer-booking-implementation-choices.md) (accepted 2026-10-19). Differences from the text above:

- **Session payloads are encrypted** (ADR-0028 §1): a session stores each visited URL — the booking page's token
  included — so `SESSION_ENCRYPT` defaults to `true`. The booking page runs the session like the form (ADR-0027 §3),
  which gives F-006's upload its CSRF cookie and flashed messages.
- **`total_amount` is `bigint`** (review fix): 22 × Rp100.000.000 already exceeds `integer`.
- **Submission keys** replay only for their own departure; the clock is read under the departure lock (ADR-0028 §3).
- **AC-15, trip unpublished or switched to `request_date` meanwhile:** the submit is AC-2's 404 (AC-15 reworded); a
  change landing between that check and the lock is caught by `isBookable()` under the lock and shows the AC-3 state.
- **Opening the form also releases the departure's expired holds** (ADR-0028 §4), so a late scheduler does not show
  "Penuh" for free seats (AC-26).
- **Lock order:** the organization row is locked *before* the submission-key check (ADR-0028 §3), so two same-key
  submits from two connections serialise.
- **Expiry job heartbeat** (Data & rules → Expiry job): F-009 B3's heartbeat does not exist yet; the command is
  scheduled `everyMinute()->withoutOverlapping()->onOneServer()` and joins the heartbeat when B3 lands.
- **Q6–Q8, Q10–Q13** are still open; their recommended defaults are what is built.
- **Status glyph for `completed`:** "☑" (the glossary has none; ADR-0028 §10).

Code: `app/Bookings` (`BookingService`, `BookingPresenter`, `BookingRefused`, `BookingDepartureBookings`,
`Jobs/ReleaseExpiredHolds`), `app/Organizations/BookingCode.php`, `app/Http/Controllers/PublicPages/BookingController.php`,
`resources/js/pages/Public/BookingForm.vue`, `Booking.vue`, `resources/js/lib/booking.js`, `lang/id/booking.php`.
Tests: `tests/Feature/Bookings/*` (form, creation, booking code, expiry, page, tenancy/abuse/logs/integration,
concurrency), F-003's `CapacityConcurrencyTest` on the real booking path, `tests/js/booking.test.js`.

## Open questions

None blocks building: each has a recommended default that the builder implements unless Rengga overrides it. All can
change before the pilot at the cost of test data only.

- [x] **Q1 Hold length and evidence.** Decided 2026-10-17: 2 h fixed, upload stops the clock, no manual extension
  (ADR-0006 accepted). — Rengga
- [x] **Q2 Reference format.** Decided 2026-10-19: `RKB-<seller code>-<MMDD>-<NNNN>`, e.g. `RKB-BRM-1114-0144`.
  `MMDD` = departure start date; `NNNN` = per-organization counter (≥ 4 digits); the seller code makes every reference
  unique across the platform, so support can identify the seller from the reference alone. The mockups'
  `RKB-1114-0144` gains the code segment. ADR-0026 §5. — Rengga
- [x] **Q3 Booking status model** (also F-006 Q1). Decided 2026-10-19: one `bookings.status` with the glossary set +
  `cancel_reason`; amounts and evidence in F-006's `payment_records`; blueprint "pending/confirmed" are UI groupings
  (pending = `awaiting_payment`/`needs_review`, confirmed = `paid`). Holding statuses: `awaiting_payment`,
  `needs_review`, `paid`. ADR-0026 §1–2. — Rengga
- [x] **Q4 Booking page URL.** Decided 2026-10-19: `/{slug}/pesanan/{token}` (not `/b/{token}`); the organization
  comes from the slug, so no cross-organization lookup. ADR-0027 §1 (accepted). — Rengga
- [x] **Q5 Token storage.** Decided 2026-10-19: SHA-256 hash for lookup plus an encrypted copy, so the seller can copy or
  resend the customer's link (F-007/F-008). ADR-0027 §2 (accepted). — Rengga
- [ ] **Q6 Non-Indonesian WhatsApp numbers.** *Recommended:* P0 accepts Indonesian mobiles only (`+628…`), the same
  rule as sellers; foreign guests book through the seller by chat. Revisit if Bali/Labuan Bajo pilots ask. — Rengga ·
  *not blocking*
- [ ] **Q7 Maximum participants per booking.** *Recommended:* no cap beyond seats left (groups and families book
  together); the per-phone rate limit and the 2-hour hold bound abuse. — Rengga · *not blocking*
- [ ] **Q8 Telling the seller about a new booking.** *Recommended:* no email or push in F-005; the booking page asks the
  customer to "Kabari operator via WhatsApp" with the reference, and F-007's dashboard lists new bookings. F-006 decides
  how the seller is told about uploaded evidence. — Rengga · *not blocking*
- [x] **Q9 Price changed between form and submit.** Decided 2026-10-19: refuse and show the new price (AC-13;
  ADR-0026 §4 accepted). — Rengga
- [ ] **Q10 Booking page before F-006.** *Recommended:* F-005 ships the page without payment instructions or upload;
  F-006 (week 4) adds them before any pilot customer books. Alternative: show the seller's accounts read-only now.
  — Rengga · *not blocking*
- [x] **Q11 Seat-hold wording on the form.** Decided 2026-10-20: shortened to one line on mobile — "Kuota ditahan 2 jam
  sampai dibayar, lalu dilepas." (was the longer mockup text). — Rengga.
  Reworded 2026-10-23: "Kursi ditahan…" became "Kuota ditahan…" (ADR-0037). — Rengga
- [ ] **Q12 Holds that outlast the departure.** A booking just before a same-day deadline is held 2 h, possibly past the
  meeting time. *Recommended:* keep ADR-0006's exact 2 h; the seller confirms or cancels by hand. Alternative (needs a
  new ADR): cap the hold at the meeting time. — Rengga · *not blocking*
- [ ] **Q13 Privacy line.** Depends on the Syarat & Privasi text (F-009 Q9, pilot gate). *Recommended:* show the
  one-line notice and link the existing `/privasi` page now. — Rengga · *not blocking*

**F-009 hand-offs:** the two-connection concurrency helper (slice A) is generalised here; fix **R1** before adding the
booking route; **R20** is AC-40; **R17** applies only if a queued job is added. `E.164` normalisation is
`App\Security\PhoneNumber`.

## Changelog

- 2026-10-17 — created
- 2026-10-17 — hold rules from ADR-0006 (accepted) added
- 2026-10-17 — concurrency test helper moved here from F-009 slice A
- 2026-10-18 — F-009 hand-offs R17, R20 noted
- 2026-10-19 — Planner: full acceptance criteria (AC-1…AC-42: form, validation & creation, concurrency, hold &
  expiry, customer booking page, tenancy/abuse/logs/integration), `bookings` table, transitions, lock order, expiry
  job, audit, public fields, rate limits, edge cases, test plan. Evidence upload and the `needs_review` transition moved
  to F-006 (F-005 keeps the rule they use, AC-27). Reference format given a default (Q2); Q3–Q13 added with defaults.
  ADR-0026 and ADR-0027 proposed. Status → `ready`
- 2026-10-19 — Q2 decided: reference `RKB-<seller code>-MMDD-NNNN`, unique platform-wide; AC-17 updated, AC-17b and `organizations.booking_code` added
- 2026-10-19 — Q3 decided: one status column (ADR-0026 §1–2)
- 2026-10-19 — ADR-0026 accepted as a whole; Q9 decided
- 2026-10-19 — ADR-0027 accepted as a whole; Q4, Q5 decided
- 2026-10-19 — Built (builder): AC-1…AC-42 and AC-17b covered by tests; F-009 R1 and R20 fixed; real `DepartureBookings` bound; F-003 AC-39 on the real booking path; ADR-0028 proposed (booking page without a session, booking-code insert, form-time hold release). Status → `done`
- 2026-10-19 — Review fixes: `total_amount` bigint (overflow above Rp2,1 miliar), booking page keeps the session with encrypted session payloads (Rengga's decision; ADR-0028 §1 revised), submission keys bound to their departure, clock read under the lock, two-connection same-key test, AC-15 reworded
- 2026-10-19 — ADR-0028 accepted by Rengga
- 2026-10-23 — UI copy says "kuota" instead of "kursi" (ADR-0037): quoted strings updated to the new `lang/id` copy and the tests that assert them. Behaviour unchanged; status unchanged
