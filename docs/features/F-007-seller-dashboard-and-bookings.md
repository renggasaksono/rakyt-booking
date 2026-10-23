---
id: F-007
title: Seller dashboard ("Hari ini") & booking list
status: done
priority: P0
roadmap: mvp-weeks-2-4
mockups: [1e, 2f, 1g]
decisions: ["0003", "0004", "0006", "0007", "0008", "0014", "0017", "0021", "0026", "0027", "0029"]
updated: 2026-10-22
---

# F-007 · Seller dashboard ("Hari ini") & booking list

## Problem

Since F-005, customers can book, but the seller cannot see a single booking: a signed-in seller lands on their trip
list, and the only trace of a booking is a lower "sisa kursi". Operators work from a phone between trips; they need the
day's tasks — who still has to pay, which departures are filling up, the link to share — one screen away, not a shrunk
ecommerce admin. They also need to find any booking by name, phone or reference when a customer messages them, see its
details, remind an unpaid customer on WhatsApp, and cancel a booking that will not happen so its seats go back on sale.

## Scope

**In:**
- **Seller navigation** with counts: desktop side nav (Hari ini, Pesanan, Trip & keberangkatan, Pembayaran, Halaman
  publik, Pengaturan) and a four-item phone bottom nav (Hari ini, Pesanan, Trip, Pengaturan) (Q2).
- **"Hari ini" home** (mockups 1e/2f, phone-first): greeting + date, plan badge, "+ Trip baru", **Perlu tindakan**
  (unpaid bookings with an active hold and bookings to check), **Pesanan terbaru**, **Keberangkatan terdekat** with seat
  bars (lunas / ditahan / kosong) and "Edit cepat" (F-003's quick-edit sheet), and the booking-link card (Salin,
  Bagikan). Sellers land here after sign-in and onboarding.
- **Booking list** `/pesanan`: search by name, phone or reference; filters by status, trip, departure and departure
  date; filters in the URL; 20 per page.
- **Booking detail** `/pesanan/{public_id}`: status, customer, trip and departure, amounts, notes, hold time, the
  customer's booking link (copy), "Chat WhatsApp" and "Ingatkan via WA".
- **Seller cancellation** ("Batalkan pesanan"): any holding booking → `cancelled` (`cancel_reason = seller`), seats
  returned, audited (ADR-0026 follow-up).
- The **"Ingatkan via WA"** prefilled message (F-008 later moves it into its template set, as with F-005's).
- Tenancy, authorisation and cross-tenant tests for every new route (F-009 A9).

**Out (explicitly):**
- Konfirmasi / Tolak, payment evidence, the `needs_review` → `paid` / `rejected` transitions and seller notification
  about evidence (**F-006**): F-006 adds its buttons to the Perlu tindakan rows and the detail page that this feature
  builds (*Hand-offs*). Until F-006 no booking is ever `needs_review`.
- "◐ Permintaan tanggal" rows and "Balas" — **deferred to P1**: F-004 was re-scoped to a WhatsApp handoff on 2026-10-22
  ("Option B"), so no in-app date-request rows, pill or "Balas" are built in P0 (F-004 Out). Nothing is added here.
- The `completed` transition ("Selesai") — not built in P0 (Q5).
- Manual booking entry by the seller (Q4), editing a booking (name, phone, participants, departure), moving a booking
  to another departure.
- Cancelling a whole departure with its bookings ("Batalkan keberangkatan", F-003 Q4) — sellers cancel bookings one by
  one, then close the departure.
- Refunds of any kind (ADR-0003: money is settled between seller and customer outside Rakyt).
- Automated messages to the customer on cancellation or reminder (P1 automated notifications).
- Reports, CSV export (P1), staff accounts and roles (P1), Pro plan page or billing (ADR-0019).
- A seller-visible change history (F-009 Q7).
- Deposits / DP 50% (F-006 Q3).

## User stories

- As a seller, I want to open the app and see what needs doing today, so that I don't miss a payment or a hold about to
  expire.
- As a seller, I want each upcoming departure's seats split into paid, held and empty, so that I know where to push
  sales.
- As a seller, I want to change a departure's price, seats or open/closed straight from Hari ini, so that a routine edit
  takes under 30 seconds.
- As a seller, I want to copy or share my booking link from the home screen, so that I can post it on WA or IG at once.
- As a seller, I want to find a booking by the name, number or code a customer sends me in chat, so that I can answer
  quickly.
- As a seller, I want to remind an unpaid customer with one tap that opens WhatsApp with the details filled in, so that
  I don't have to type them.
- As a seller, I want to cancel a booking that won't happen, so that its seats go back on sale.
- As a seller, I want to be sure no other operator can see my customers, so that I can trust Rakyt with them.

## Acceptance criteria

UI copy is Bahasa Indonesia from a new `lang/id/dashboard.php` (Hari ini, nav) and `lang/id/bookings.php` (seller
list and detail) (ADR-0007); status words, glyphs and the WhatsApp customer message may reuse `lang/id/booking.php`.
Dates and times are in the organization's time zone (ADR-0014). "Holding statuses" are `awaiting_payment`,
`needs_review`, `paid` (ADR-0026 §2); "hold active" is `Booking::holdIsActive(now)` (F-005 AC-27). Every status is
shown with the status-set pill: tint + deep text + glyph + word (ADR-0008, `docs/design` status set). Test names:
`test_ac_<n>_…`. Tests that need a `needs_review` or `paid` booking before F-006 exists build it with
`BuildsBookings::bookingFor()` and then set the status in a test helper — seats are held in all three statuses, so the
F-005 AC-23 invariant still holds.

### A. Navigation & landing

- [x] **AC-1 Landing.** Given a signed-in seller past onboarding, when they sign in, finish or skip onboarding (F-001
  E3), or open `/dashboard`, then they land on Hari ini at `/hari-ini` (route name `dashboard`; `/dashboard` redirects
  there with 302). A seller still in onboarding is redirected to their resume step (F-001 E2); a guest goes to `/masuk`.
- [x] **AC-2 Desktop nav.** Given a screen ≥ 1024 px, then the side nav lists, in order: **Hari ini** (`/hari-ini`),
  **Pesanan** (`/pesanan`), **Trip & keberangkatan** (`/trip`), **Pembayaran** (`/pesanan?status=perlu_tindakan`),
  **Halaman publik** (the seller page `/<slug>`, new tab, `rel="noopener"`, marked "↗"), **Pengaturan**. The nav is
  the ink panel of 1e/2f under the **Rakyt | Booking** product mark (ADR-0036); the current section is marked (Sogan
  fill with gading text, `aria-current="page"`); booking detail pages mark Pesanan.
- [x] **AC-3 Phone nav.** Given a screen < 1024 px, then the bottom nav — the same ink panel as the side nav (ADR-0036)
  — has four items, each ≥ 44 px tall: **Hari ini**,
  **Pesanan**, **Trip**, **Pengaturan**. Halaman publik is reached from the link card (AC-14), Pembayaran from Perlu
  tindakan (AC-8).
- [x] **AC-4 Nav counts.** Given the seller's bookings, then **Pesanan** and **Pembayaran** show the **Perlu tindakan
  count** (AC-8's rows: `needs_review` + `awaiting_payment` with an active hold) as a number badge with an accessible
  label ("3 pesanan perlu tindakan"); no badge when 0. The phone Pesanan item shows the same badge. Counts are computed
  for the current organization only, in one query, only on seller pages (never on public pages, F-002 AC-7).

### B. Hari ini (1e, 2f)

- [x] **AC-5 Header.** Given seller Agus of "Kencana Trip" opens Hari ini at 08.15 local on Friday 16 Oktober, then the
  page shows the date "Jumat, 16 Oktober" (year added when not the current year — never needed for today), the greeting
  "Selamat pagi, Agus." using `users.name` (pagi 04.00–10.59, siang 11.00–14.59, sore 15.00–17.59, malam 18.00–03.59,
  local time), and "+ Trip baru" linking to `/trip/baru`. As in 1e/2f the date is an uppercase Sogan kicker over a
  30 px greeting (24 px on a phone). The business name and the plan "Paket Gratis" (Q3) sit at the foot of the desktop
  side nav on every seller page (shared `seller.organization`), not in the page header; the phone shows neither.
  "+ Trip baru" is the page's one gold button (mockup 1e/2f); every other action on the page is ink or ink outline.
- [x] **AC-6 Order on a phone.** At 360 px the sections stack in this order: header, Perlu tindakan, Keberangkatan
  terdekat, Pesanan terbaru, link card (Q1). On desktop they follow the mockup's two-column layout.
- [x] **AC-7 Perlu tindakan rows.** Each row shows: customer name · "n org", the status pill, reference · trip name ·
  departure start date ("Karimunjawa 3H2M Open Trip · 24 Okt"), the total ("Rp2.500.000"), and — for
  `awaiting_payment` — "ditahan s.d. 16.40" ("s.d. besok 01.10" when the release falls on the next local day). The row
  links to the booking detail. An `awaiting_payment` row has an ink-outline **"Ingatkan via WA"** (AC-25); a
  `needs_review` row has no action until F-006 adds Konfirmasi / Tolak.
- [x] **AC-8 Perlu tindakan selection.** The section lists `needs_review` bookings (oldest status change first), then
  `awaiting_payment` bookings with an **active** hold (soonest `hold_expires_at` first), at most 5. The header reads
  "Perlu tindakan · n pembayaran" with the full count and a "Semua" link to `/pesanan?status=perlu_tindakan`. An
  `awaiting_payment` booking whose hold has expired but has not been released yet (scheduler late) is **not** listed and
  not counted.
- [x] **AC-9 Perlu tindakan empty.** Given nothing needs action, then the section shows "Semua beres. Tidak ada
  pembayaran yang perlu ditindak." and no "Semua" link.
- [x] **AC-10 Pesanan terbaru.** The section lists the 5 most recently created bookings in any status: customer name ·
  trip name · departure start date · "n org", the status pill (cancelled bookings show "– Dibatalkan", never a bare
  grey word), and the creation time: "09.12" today, "Kemarin", or the date ("14 Okt"). Each row links to the detail. A
  "Semua pesanan" link goes to `/pesanan`. Empty: "Belum ada pesanan. Bagikan link booking Anda supaya pelanggan bisa
  memesan." (the link card is on the same page).
- [x] **AC-11 Keberangkatan terdekat.** The section lists the next 3 departures of the seller's trips (any trip
  status, any departure state) whose start date is today or later, nearest first (start date, then trip name). Each
  row: trip name · date ("Karimunjawa 3H2M Open Trip · 24 Okt"), the seat bar and its summary (AC-12), the departure
  state pill when it is not Buka (Tutup ○, Penuh ◼ — F-003 AC-27), and **"Edit cepat"**. A "Semua keberangkatan" link
  goes to `/keberangkatan` (F-003). Empty: "Belum ada keberangkatan terjadwal." with a link "+ Tambah keberangkatan"
  to `/trip`.
- [x] **AC-12 Seat bar.** Given a departure with capacity 16, `paid` bookings for 12 participants, `awaiting_payment`
  and `needs_review` bookings for 2, and an open deadline of 21 Okt 23.59, then the bar has three segments in
  proportion — **lunas** 12 (solid Lunas green), **ditahan** 2 (solid amber `status-waiting-bar`), **kosong** 2 (a
  faint ink track), square with 3 px gaps as in 2f (ADR-0036) — with a legend "Lunas · Ditahan · Kosong" (each with
  its glyph) once per section, and the text "12 lunas · 2 ditahan · 2 kosong · tutup 21 Okt" ("2 ditahan" in the
  waiting deep colour). Zero parts are left out ("5 lunas · 11 kosong"; "16 lunas" for a
  full one), "· tutup …" appears only while the departure is open and before its deadline, and lunas + ditahan always
  equals the departure's `seats_taken` (a test asserts it). The bar itself is `aria-hidden`; the text is what screen
  readers get. Colour is never the only signal (the text is always shown).
- [x] **AC-13 Edit cepat.** Given a row in Keberangkatan terdekat, when the seller taps "Edit cepat", then F-003's
  quick-edit sheet (AC-31–AC-35 there: Harga / orang, Total kursi, Buka / Tutup pemesanan, gold "Simpan") opens for
  that departure — the sheet's "Simpan" is the screen's gold button while the sheet is open — and after saving the
  seller stays on Hari ini with F-003's toast and the row updated. All F-003 rules apply unchanged (capacity never below
  seats taken, concurrency-safe).
- [x] **AC-14 Link card.** The card "Link booking Anda" shows `rakyt.id/<slug>` (the configured public host) with an
  ink-outline **"Salin link"** (copies the full URL, then "Link disalin") and an ink-outline **"↗ Bagikan ke WA / IG"**
  (native share sheet; without Web Share it copies and shows "Link disalin") — the same behaviour as F-001 D5, reusing
  its component, but not gold here (AC-5). A third link "Lihat halaman" opens the seller page in a new tab.
- [x] **AC-15 Bounded queries.** Hari ini runs a fixed number of queries whatever the number of bookings and
  departures (no N+1): a test with 3 and with 30 bookings asserts the same query count.

### C. Booking list `/pesanan`

- [x] **AC-16 Rows.** The list shows the seller's bookings, newest first (`created_at` desc, then id), 20 per page with
  "Berikutnya" / "Sebelumnya". Each row: customer name, the status pill, reference, trip name · departure date range
  ("24–26 Okt"), "n org", total, and creation time (AC-10 wording). The whole row (≥ 44 px) links to the detail.
- [x] **AC-17 Search.** One field "Cari nama, nomor WA, atau kode pesanan" (`?cari=`) matches, case-insensitively:
  part of the customer name ("rina" finds "Rina Wulandari"); a phone number in any format F-005 accepts, or at least 4
  of its trailing digits ("0812 3456 7890", "+62 812-3456-7890" and "7890" find `+6281234567890`); or a reference,
  whole or in part ("rkb-brm-1024-0142", "0142"). Leading/trailing spaces are ignored; `%` and `_` are matched as text,
  not as wildcards. Fewer than 2 characters → the search is ignored.
- [x] **AC-18 Filters.** Filters: **Status** — Semua, Perlu tindakan (`perlu_tindakan`, AC-8's set), Menunggu
  pembayaran, Perlu dicek, Lunas, Ditolak, Dibatalkan (`completed` is not offered, Q5); **Trip** — the seller's trips
  by name; **Tanggal berangkat** — from / to (inclusive, local dates, compared with the departure start date); and
  **Keberangkatan** (`?keberangkatan=<departure public_id>`, set by links from F-003's departure cards; shown as a
  removable chip "Karimunjawa · 24 Okt"). Filters combine with AND and with the search.
- [x] **AC-19 Filters in the URL.** Every search and filter lives in the query string (`cari`, `status`, `trip`, `dari`,
  `sampai`, `keberangkatan`, `halaman`), so a reload, the back button, a shared bookmark and the detail page's "←"
  restore the same list. Changing a filter resets to page 1. "Hapus filter" clears all of them.
- [x] **AC-20 Invalid filters.** An unknown status, a malformed date, `dari` after `sampai`, or a trip / departure id
  that is unknown or belongs to another seller is ignored (that filter is dropped and the list renders, 200) — never a
  500, and never a different response for another seller's id than for an unknown one.
- [x] **AC-21 Empty states.** No bookings at all: "Belum ada pesanan." with the link card's Salin / Bagikan. Filters or
  search with no result: "Tidak ada pesanan yang cocok." with "Hapus filter".
- [x] **AC-22 Phone layout.** At 360 px: the search field is on top, then a "Filter" button showing the number of
  active filters ("Filter (2)") that opens a bottom sheet with the filters and an ink "Terapkan"; active filters show as
  removable chips under the search. On desktop the filters are inline above the list.

### D. Booking detail `/pesanan/{public_id}`

- [x] **AC-23 Content.** Given a booking, the detail shows: "←" back to the list (keeping its filters, AC-19); the
  reference in large type and the status pill; "Dibuat Jum 16 Okt 09.12"; for `awaiting_payment` with an active hold
  "Kursi ditahan s.d. 16.40" (AC-7 wording); for `cancelled` the reason — "Waktu pembayaran habis, kursi dilepas
  otomatis." (`hold_expired`) or "Dibatalkan oleh Anda." (`seller`); **Pemesan**: Nama, Nomor WhatsApp
  (formatted "+62 812-3456-7890"), Catatan (as plain text, line breaks kept, or "—"); **Trip**: trip name (link to
  the trip editor), date line and meeting point/time as on the customer page; **Pembayaran**: "2 peserta ×
  Rp1.250.000" and "Total Rp2.500.000" from the booking's own `unit_price` snapshot (never the current price).
- [x] **AC-24 Customer link.** The detail has "Salin link pesanan pelanggan", which copies the customer's booking page
  URL `https://rakyt.id/<slug>/pesanan/<token>` (from the encrypted token copy, ADR-0027 §2) and shows "Link disalin".
  The token is in this page's props only — never in the list, Hari ini, logs or audit events — and the page sends
  `Cache-Control: no-store, private`.
- [x] **AC-25 WhatsApp actions.** "Chat WhatsApp" (every status) opens `https://wa.me/<customer digits>` with no text.
  "Ingatkan via WA" (only `awaiting_payment` with an active hold; also on Perlu tindakan rows) opens
  `https://wa.me/<customer digits>?text=…` with `lang/id/bookings.php → whatsapp.reminder`, URL-encoded: "Halo :name,
  pesanan :reference untuk :trip tanggal :date (:n orang) menunggu pembayaran :total. Kursi ditahan sampai :time.
  Detail pesanan: :url" — `:url` is the customer link (AC-24). Both open in a new tab/app with
  `rel="noopener noreferrer"`. Nothing is sent by Rakyt and nothing is recorded (F-008 later moves the template).
- [x] **AC-26 Not found.** An unknown or malformed `public_id`, the numeric id, the reference, or the access token in
  the URL → 404 (the seller app's 404 page).

### E. Seller cancellation

- [x] **AC-27 Offer.** Given a booking in a holding status (`awaiting_payment` — active hold or not —,
  `needs_review` or `paid`), the detail shows an ink-outline, destructive-styled (Ditolak deep text) **"Batalkan
  pesanan"**. For any other status it is not shown.
- [x] **AC-28 Confirm.** Tapping it opens a confirmation (bottom sheet on a phone, dialog on desktop) titled
  "Batalkan pesanan RKB-…?" with: "n kursi di keberangkatan 24 Okt akan dibuka lagi." and "Pelanggan tidak diberi tahu
  otomatis. Kabari lewat WhatsApp." — and for `paid` also "Pesanan ini sudah lunas. Pengembalian dana diurus langsung
  antara Anda dan pelanggan." (ADR-0003). Buttons: "Batalkan pesanan" (Ditolak-styled, not gold) and "Kembali".
- [x] **AC-29 Cancel.** Given the seller confirms, then in **one** transaction, locking the departure row then the
  booking row (ADR-0026 §3; no organization lock — no reference is taken), the booking is re-read; if it is still in a
  holding status it becomes `cancelled` with `cancel_reason = seller`, the departure's `seats_taken` drops by its
  participants, and one `booking.cancelled` audit event is written (actor: the seller user; `before`/`after`: `status`,
  `cancel_reason`). The response returns to the detail with the toast "Pesanan dibatalkan. n kursi dibuka lagi." and an
  ink-outline "Kabari pelanggan via WA" (opens `whatsapp.cancelled`: "Halo :name, pesanan :reference untuk :trip
  tanggal :date dibatalkan. Hubungi kami jika ada pertanyaan."). The change goes through `BookingService`, never a
  direct `seats_taken` update. `hold_expires_at` is unchanged.
- [x] **AC-30 Already changed.** Given the booking was released by the expiry job, or cancelled in another tab, between
  page load and confirm, then nothing changes, no event is written, seats are returned at most once, and the detail
  shows its current status with "Pesanan ini sudah tidak aktif." (200 after redirect, not an error page). Posting
  cancel twice gives the same end state.
- [x] **AC-31 Cancel vs expiry, concurrently.** Given an `awaiting_payment` booking whose hold has just expired, when
  the seller's cancel and `bookings:release-expired-holds` run at the same moment from two database connections, then
  exactly one of them changes the booking (either `seller` or `hold_expired`), one audit event is written, and
  `seats_taken` drops once (F-005 AC-23 invariant holds).
- [x] **AC-32 Seats back on sale.** Given a full departure, when the seller cancels a 2-participant booking on it, then
  the departure shows 2 seats left on Hari ini and its public trip page, and the next customer can book them (F-005
  AC-12). A departure that was closed stays closed (cancelling never reopens it).
- [x] **AC-33 Departure in the past.** Cancelling is allowed whatever the departure date (a seller cleaning up a
  no-show); seats are returned the same way.

### F. Tenancy, security, design

- [x] **AC-34 Cross-tenant.** Given sellers A and B (`InteractsWithTenants`, F-009 A9): A's Hari ini, nav counts,
  Perlu tindakan, Pesanan terbaru, Keberangkatan terdekat and booking list never include B's bookings or departures;
  searching A's list for B's customer name, phone or reference finds nothing; `GET /pesanan/<B's public_id>` → 404;
  `POST` cancel on B's booking → 404 with B's booking and `seats_taken` unchanged and no event; A's list with
  `trip=<B's trip id>` or `keberangkatan=<B's departure public_id>` behaves as an unknown id (AC-20). Policy:
  `BookingPolicy` (`view`, `cancel`) extends `OrganizationOwnedPolicy`; route binding is scoped to the current
  organization.
- [x] **AC-35 Guests and other roles.** A guest on any F-007 route is redirected to `/masuk`; a signed-in user without
  an organization goes to onboarding (F-001 E1); the platform admin has no seller session (ADR-0011) — nothing here is
  reachable from `/admin`.
- [x] **AC-36 Only listed fields leave the server.** Hari ini, list and detail props are built by explicit presenters
  (no model `toArray()`); tests assert the exact keys in Data & rules → *Props*, and that no numeric id,
  `organization_id`, `access_token_hash`, `submission_key` or another seller's data is present (the access token only on
  the detail, AC-24).
- [x] **AC-37 Rendering and logs.** Customer name and notes render as text (no `v-html`); HTML in them shows
  literally. List and detail send `Cache-Control: no-store, private`. Phone numbers in logs and error reports stay
  masked (F-009 A24); the search term `cari` (a name or phone) is redacted from logged URLs — add it to the URL
  parameters `App\Logging\RedactSensitiveData` redacts.
- [x] **AC-38 Phone width and design rules.** Hari ini, the list, its filter sheet and the detail work at 360 px without
  horizontal scroll; tap targets ≥ 44 px; text ≥ 12 px (11 px only for uppercase kickers); exactly one gold button per
  screen (AC-5, AC-13); every status carries its glyph; tints come from `--color-status-*-tint` tokens; all copy from
  `lang/id/*.php`. Loading states on Salin / Bagikan / cancel; a failed cancel keeps the sheet open with "Belum
  tersimpan. Periksa koneksi lalu coba lagi." *Device review with F-002/F-003/F-005's.*
  *Verified 2026-10-19:* source rules in `DashboardDesignTest`; a browser check at a true 360 px viewport found no
  horizontal overflow (`scrollWidth` = 360) on Hari ini, the list with its filter sheet, and the detail with its
  cancel sheet. Not in CI; the real-device review is still to do with the other P0 screens.

## Data & rules

### Routes (inside the existing `auth`, `organization`, `onboarded` group)

| Method & path | Name | Notes |
|---|---|---|
| `GET /hari-ini` | `dashboard` | Hari ini. `hari-ini` is already a reserved slug |
| `GET /dashboard` | — | 302 → `/hari-ini` (replaces today's redirect to `/trip`) |
| `GET /pesanan` | `bookings.index` | List; query `cari`, `status`, `trip`, `dari`, `sampai`, `keberangkatan`, `halaman` |
| `GET /pesanan/{booking:public_id}` | `bookings.show` | Detail; `no-store` |
| `POST /pesanan/{booking:public_id}/batalkan` | `bookings.cancel` | Seller cancellation |
| `GET /pesanan/{booking:public_id}/ingatkan` | `bookings.remind` | Hari ini's "Ingatkan via WA": 302 to WhatsApp with the customer link, so the token stays out of Hari ini's props (ADR-0029 §1) |

`pesanan` is a reserved slug, so `/pesanan/…` never collides with the public `/{slug}/{trip}` fallback routes, and the
customer page `/{slug}/pesanan/{token}` has two segments before the token. The `seller` shared prop
(`HandleInertiaRequests`) gains the nav items, counts (lazy, AC-4) and the public link.

### Reads

- **Perlu tindakan** (AC-8): `status = needs_review` ∪ (`status = awaiting_payment` ∧ `hold_expires_at > now UTC`).
  "Oldest status change first" for `needs_review` uses `updated_at` until F-006 adds its own timestamp.
- **Seat breakdown** (AC-12), per departure: lunas = Σ participants where `paid`; ditahan = Σ participants where
  `awaiting_payment` or `needs_review`; kosong = capacity − `seats_taken`. One grouped query for the 3 departures.
  An expired-but-unreleased hold still counts as ditahan (it still holds the seat until released, ADR-0026 §7); the
  expiry job fixes it within a minute.
- **Search** (AC-17): name `ILIKE '%term%'` with `%`/`_` escaped; phone: strip non-digits, normalise a leading `0` /
  `62` / `0062` like `PhoneNumber`, then match the digit string as a suffix/substring of `customer_phone`; reference
  `ILIKE`. No new index in P0 (pilot volume); the existing `(organization_id, created_at)` index serves the list order.
- All reads go through the tenant-scoped `Booking` / `TripDeparture` queries — no scope bypass, no allowlist entry.

### Cancellation (ADR-0026 §3)

New `BookingService::cancelBySeller(Booking, User)`: `DB::transaction` → `lockForUpdate` departure → lock booking →
re-check `status->holdsSeats()` → status `cancelled`, `cancel_reason = seller` → `seats_taken -= participants`
(through the same private seat helper as the release) → `AuditLog` `booking.cancelled`. Returns whether it changed
anything (AC-30). It reuses the release path's locking, so it cannot deadlock with booking creation, the expiry job or
the F-001 time-zone change.

### Statuses and transitions added here

| From | To | By | Seats |
|---|---|---|---|
| `awaiting_payment`, `needs_review`, `paid` | `cancelled` (`seller`) | seller (F-007) | − participants |

(All other transitions: F-005 table and F-006.)

### Audit

- `booking.cancelled` — actor `seller` (user id), IP; `before`/`after`: `status`, `cancel_reason`. No name, phone,
  notes or token. Viewing, searching and copying links are not audited.

### Props (AC-36)

- **Hari ini:** `greeting` (`text`, `date`), `organization` (`name`, `slug`, `plan_label`), `public_url`,
  `action_needed` (`count`, `items[]`), `recent[]`, `departures[]`, `copy`, `urls` (`new_trip`, `bookings`,
  `action_needed`, `departures`, `public_page`).
  - booking item: `public_id`, `reference`, `customer_name`, `participants`, `total_amount`, `status`,
    `hold_expires_at` (ISO UTC or null), `created_at` (ISO UTC), `trip` (`name`), `departure` (`start_date`,
    `end_date`), `urls` (`show`), `whatsapp_reminder_url` (null unless `awaiting_payment` with an active hold).
  - departure item: F-003's quick-edit departure shape (`DeparturePresenter`) plus `trip` (`name`), `seats`
    (`capacity`, `paid`, `held`, `empty`), `closes_on` (local date or null).
- **List:** `bookings[]` (booking item, without `whatsapp_reminder_url`), `filters` (the applied values), `options`
  (`statuses`, `trips[]` with `id`, `name`; the departure chip label), `pagination` (`page`, `next_url`, `prev_url`),
  `copy`, `urls`.
- **Detail:** `booking` (booking item + `customer_phone`, `notes`, `unit_price`, `cancel_reason`,
  `customer_url`, `can_cancel`), `trip` (`name`, `edit_url`), `meeting` (`point`, `time`), `whatsapp` (`chat_url`,
  `reminder_url`), `context` (`now` ISO UTC, `timezone`), `copy`, `urls` (`back`, `cancel`).
- **Added while building (ADR-0029 §6):** Hari ini `context` (F-003's quick-edit context + `now`) and `urls.trips`
  (the empty state's "+ Tambah keberangkatan"); booking items' `whatsapp_reminder_url` is the `bookings.remind` route;
  list `has_any` (AC-21's two empty states) and `context` (`now`, `timezone`, `today`); the detail's `booking` is the
  list item (no `whatsapp_reminder_url`, see `whatsapp.reminder_url`), `whatsapp.cancelled_url` (AC-29, set while the
  seller's cancellation stands) and `context.today`. Trip and departure ids in props and filters are `public_id`s.

### Edge cases covered

- Hold expired but not yet released: not in Perlu tindakan or the nav count (AC-8), still "ditahan" in the seat bar
  until the job runs (Reads), cancellable (AC-27), no "Ingatkan via WA" (AC-25).
- Cancel racing the expiry job (AC-31), double submit or a second tab (AC-30); cancel on a full / closed / past
  departure (AC-32, AC-33).
- A trip unpublished, switched to `request_date` or with its departure closed after booking: bookings still listed and
  open normally; the departure still appears in Keberangkatan terdekat if upcoming.
- Departures across years ("24 Okt 2027" when not the current year), time-zone change by the seller (local times move
  with it), DST-free Indonesian zones (ADR-0014).
- Long names and trip names truncate with an ellipsis on one line in rows (full text on the detail); 3-digit
  participant counts and Rp100.000.000+ totals fit at 360 px.
- Search input with `%`, `_`, quotes, emoji, or only spaces (AC-17); invalid or foreign filter ids (AC-20).
- A seller with no trips, no departures or no bookings yet: every empty state (AC-9, AC-10, AC-11, AC-21).
- Seller still in onboarding or without an organization (AC-1, AC-35).

### Dependencies

- **F-005** (done): `bookings`, `BookingService`, `BookingStatus`, `CancelReason::Seller` (already declared),
  `holdIsActive`, the encrypted access token, `BuildsBookings` fixtures and the seat invariant check.
- **F-003** (built): `QuickEditSheet`, `DeparturePresenter`, `/keberangkatan`, departure state pills, money and date
  formatters. F-003's departure cards may link "Lihat pesanan" to `/pesanan?keberangkatan=…` (AC-18).
- **F-001** (built): `users.name`, organization name/slug, the D5 share component, E3 landing on `dashboard`.
- **F-009**: tenancy primitives, `AuditLog`, `InteractsWithTenants` (A9), log masking (A24).
- **F-006** (next, wk 4): adds Konfirmasi / Tolak and evidence to Perlu tindakan rows and the detail; the
  `needs_review`/`paid`/`rejected` transitions; may give Pembayaran its own page.
- **F-004**: re-scoped to a WhatsApp handoff (2026-10-22, "Option B") — the in-app date-request rows ("◐ Permintaan
  tanggal", "Balas") are deferred to P1, so nothing is added to Pesanan terbaru or the list in P0.
- **F-008**: moves `whatsapp.reminder` and `whatsapp.cancelled` into its template set.
- **F-012/F-011**: the admin seat summary uses the same breakdown as AC-12 (F-012).

## Test plan

| Area | Tests |
|---|---|
| Nav & landing (A) | `tests/Feature/Dashboard/NavigationTest`: landing after sign-in/onboarding, `/dashboard` redirect, nav items and active state, counts (expired hold excluded), no seller props on public pages |
| Hari ini (B) | `HariIniTest`: greeting by local hour (`travelTo`, org time zone), plan badge, Perlu tindakan order/limit/count/empty, Pesanan terbaru wording (today / Kemarin / date), Keberangkatan terdekat order and empty, seat breakdown = `seats_taken`, quick edit returns to Hari ini, query count with 3 vs 30 bookings |
| List (C) | `BookingListTest`: search data set (name, phone formats, last 4 digits, reference parts, `%`/`_`), each filter, combinations, URL round-trip, pagination, invalid filters → 200, empty states |
| Detail (D) | `BookingDetailTest`: content per status and cancel reason, unit-price snapshot after a price change, customer link decrypts to a working token, `no-store`, 404 cases, WhatsApp URLs encoded |
| Cancellation (E) | `CancelBookingTest`: each holding status, refused statuses, seats returned, audit event, already-changed and double submit, full → bookable again, closed stays closed, past departure; `CancelConcurrencyTest` (two connections, like F-005's) for cancel vs expiry job; F-005 invariant after each |
| Tenancy (F) | `DashboardTenancyTest` with `InteractsWithTenants`: AC-34 for every route and query, policy 404s, exact prop keys |
| JS (Vitest) | `tests/js/dashboard.test.js`: greeting period boundaries, seat summary text and segment widths (zero parts dropped), relative creation time, filter ⇄ query-string mapping, WhatsApp text encoding |
| Manual | 360 px browser check of Hari ini, list + filter sheet, detail + cancel sheet; real-device review with the other P0 screens |

## Open questions

All decided 2026-10-19 (Rengga: "all as recommended").

- [x] **Q1 Mobile Hari ini without a mockup.** 1e/2f are desktop only. Decided 2026-10-19 as recommended: build phone-first from the same
  sections, stacked Perlu tindakan → Keberangkatan terdekat → Pesanan terbaru → link card (tasks first, AC-6); device
  review in week 3 instead of a new mockup. — Rengga
- [x] **Q2 Navigation and counts.** The mockup has six nav items and two counts ("Pesanan 18", "Pembayaran 3") without
  saying what they count. Decided 2026-10-19 as recommended: desktop keeps the six items; the phone bottom nav has four (Hari ini, Pesanan,
  Trip, Pengaturan); both badges show the Perlu tindakan count — the only number a seller must act on — and
  Pembayaran is a shortcut to the list filtered to Perlu tindakan until F-006 decides whether it needs its own page.
  "Pesanan 18" (a total) is dropped. — Rengga
- [x] **Q3 Plan badge "Paket Gratis · Lihat Pro".** There is no Pro plan, price or billing yet (ADR-0010, ADR-0019).
  Decided 2026-10-19 as recommended: show "Paket Gratis" only; add "Lihat Pro" when a pricing page exists. Alternative: link "Lihat Pro" to
  a WhatsApp chat with Rakyt to collect interest during the pilot. — Rengga
- [x] **Q4 Manual booking entry** (bookings that arrive by chat). Decided 2026-10-19 as recommended: not in P0. The seller shares the link,
  or fills the public form for the customer with the customer's number (works today and takes seats the same way).
  If pilot sellers ask for it, specify "Tambah pesanan" as a new feature (cash/paid-on-entry rules, no access token
  sent). — Rengga
- [x] **Q5 `completed` ("Selesai").** ADR-0026 allows `paid` → `completed` but no feature sets it, and `completed` is not
  a holding status, so completing would lower `seats_taken` on past departures. Decided 2026-10-19 as recommended: nothing becomes
  `completed` in P0; paid bookings on past departures stay "Lunas"; the status filter does not offer "Selesai". Revisit
  with reports (P1) together with an ADR-0026 amendment. — Rengga
- [x] **Q6 Seller cancellation scope.** Decided 2026-10-19 as recommended: any holding booking, any date (AC-27, AC-33), no reason field,
  no automatic customer message (the seller uses "Kabari pelanggan via WA"); refunds stay outside Rakyt (ADR-0003). No
  "undo" — a cancelled booking cannot be restored (the customer books again). — Rengga
- [x] **Q7 Customer link in the reminder.** Decided 2026-10-19 as recommended: "Ingatkan via WA" includes the customer's booking page link
  (AC-25), so the customer can reopen the payment page; the token goes only to the customer's own number through the
  seller's WhatsApp. — Rengga
- [x] **Q8 Support/admin view for Rakyt staff** (blueprint week 4) — separate feature? Yes: F-010–F-015. — Rengga

## Build notes (2026-10-19)

Built test-first on `claude/f-007-seller-dashboard-654ef1`; every AC above has a passing test (AC-38's real-device
review is still to do with F-002/F-003/F-005's, as the AC says). Choices the spec left open are in
[ADR-0029](../decisions/0029-seller-dashboard-implementation-choices.md) (accepted by Rengga on 2026-10-19).

- **Code:** `App\Dashboard\DashboardPresenter` (Hari ini), `App\Bookings\BookingFilters` (search/filters, AC-17–AC-20),
  `App\Bookings\SellerBookingPresenter` (items, detail, WhatsApp URLs), `BookingService::cancelBySeller()` (departure →
  booking lock, `booking.cancelled` audit), `Booking::scopeActionNeeded()`, `BookingPolicy::viewAny/cancel`,
  `HandleInertiaRequests::sellerNav()`; controllers `Dashboard\DashboardController`, `Bookings\SellerBookingController`;
  Vue `pages/Dashboard/Today`, `pages/Bookings/{Index,Show}`, components `BookingRow`, `BookingStatusPill`, `SeatBar`,
  `LinkCard`; `resources/js/lib/dashboard.js`; copy in `lang/id/dashboard.php` and `lang/id/bookings.php`.
- **Tests:** `tests/Feature/Dashboard/` — `NavigationTest` (AC-1–AC-4), `HariIniTest` (AC-5–AC-15),
  `BookingListTest` (AC-16–AC-22), `BookingDetailTest` (AC-23–AC-26), `CancelBookingTest` (AC-27–AC-30, AC-32,
  AC-33), `CancelConcurrencyTest` (AC-31, two connections, `tests/Support/Concurrency/cancel_booking.php`),
  `DashboardTenancyTest` (AC-34–AC-37), `DashboardDesignTest` (AC-38); `tests/js/dashboard.test.js`.
- `/dashboard` now redirects to `/hari-ini`; F-003's `SellerPagesTest` and F-001's `ResumeTest` were updated for the
  six-item nav and the new landing.
- `organization` and `onboarded` now run before route model binding (`bootstrap/app.php` priority list), so a user
  without an organization is redirected to onboarding on booking URLs too (AC-35) and a seller still onboarding
  resumes their step; a form write without an organization stays a 404 (F-001 E1).
- Logs: the WhatsApp `text` parameter and a URL-encoded `/pesanan/<token>` are redacted (`RedactSensitiveData`).
- **Status:** every AC has a passing test and ADR-0029 is accepted (Rengga, 2026-10-19), so the spec is `done`; AC-38's
  real-device review is still to do with the other P0 screens.
- Not done: `Inertia::encryptHistory()` on the detail (the customer link sits in `history.state`) — it needs
  `crypto.subtle`, which plain-HTTP local dev does not have; revisit with HTTPS everywhere (F-009 hardening).
- The audit catalogue gains `booking.cancelled` (actor `user`); F-009's audited-actions table lists it.

## Changelog

- 2026-10-17 — created
- 2026-10-17 — admin view moved to F-010–F-015
- 2026-10-19 — Planner: full acceptance criteria (AC-1…AC-38: navigation & landing, Hari ini, booking list, booking
  detail, seller cancellation, tenancy/security/design), routes, reads, cancellation transaction, audit, props, edge
  cases, test plan. Konfirmasi / Tolak moved to F-006 (F-007 hosts the rows); date-request rows to F-004; `completed`
  not built (Q5). Mobile layout, nav counts and plan badge given defaults (Q1–Q3); Q4–Q7 added with defaults.
  Status → `ready`
- 2026-10-19 — Q1–Q7 decided as recommended (Rengga)
- 2026-10-19 — Builder: AC-1…AC-38 built and covered by tests; ADR-0029 (proposed) for the reminder route, filter
  round-trip, search and extra props. Status → `done` (AC-38 device review pending with the other P0 screens)
- 2026-10-19 — Review fixes: AC-35 redirect before binding, token/`text` redaction in logs, fuller exact-key tests,
  currency from lang. Status → `in-progress` until ADR-0029 is accepted
- 2026-10-19 — ADR-0029 accepted (Rengga). Status → `done` (AC-38 real-device review pending with the other P0 screens)
- 2026-10-22 — Planner: F-004 re-scoped to a WhatsApp handoff ("Option B", Rengga) — the in-app "◐ Permintaan tanggal"
  rows / "Balas" are deferred to P1, so the Out list and the F-004 dependency note now record that nothing is added here
  in P0. No code change; status stays `done`
- 2026-10-23 — Mockup revisions (Rengga): side nav on ink with the shared "Rakyt | Booking" mark, Hari ini section
  titles as the `section-header` kicker, departure rows and seat bar redrawn to 2f (AC-2, AC-12 wording updated;
  ADR-0036 proposed). Status stays `done`
- 2026-10-23 — Phone bottom nav on the same ink panel as the side nav (AC-3 wording). Status stays `done`
- 2026-10-23 — AC-5 to the mockup: date kicker, larger greeting, roomier page padding; business name and plan moved from the Hari ini header to the foot of the desktop side nav (Rengga). Status stays `done`
- 2026-10-23 — Button sizes to 2f: gold "+ Trip baru" is the biggest (48px phone / 44px desktop); Konfirmasi, Tolak, Ingatkan via WA, Salin link and Bagikan use the 36px secondary size on desktop (44px tap target on a phone); link card title as a Sogan kicker. Status stays `done`
