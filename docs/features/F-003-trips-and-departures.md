---
id: F-003
title: Trips & departures management
status: ready
priority: P0
roadmap: mvp-weeks-2-3
mockups: [1f, 1g, 3a, 3b, 4c, 4d, 1e]
decisions: ["0002", "0004", "0005", "0006", "0007", "0008", "0010", "0013", "0014", "0015", "0019"]
updated: 2026-10-17
---

# F-003 · Trips & departures management

## Problem

Operators change prices, dates and seats often, usually on their phone, and today they do it in chat threads,
spreadsheets and notebooks. Generic ecommerce product/variant screens (Liburdulu) confused them.
Targets (blueprint §9): **first trip published in under 10 minutes**; a **price/capacity update in under 30 seconds**.

## Scope

**In:**
- Trip create/edit (mockup 1f, 3a, 3b): trip type preset, photos (first = cover), name, duration, short description,
  inclusions ("Termasuk"), exclusions ("Tidak termasuk"), meeting point & time with optional map link,
  per-day itinerary ("Kumpul & jadwal"), terms ("Ketentuan"), base price, booking mode.
- Departures table per trip (1f §04): start date (end date derived), seats, optional price override, booking deadline, open/closed.
- "Salin dari tanggal sebelumnya" (copy previous departure).
- Publish checklist + live preview (1f); publish and unpublish a trip.
- Mobile quick edit sheet (1g): price, total seats, open/closed.
- Draft autosave for draft trips ("Draf tersimpan 10.42").
- Automatic closing of departures at their booking deadline.
- Organization default booking deadline ("Default diambil dari Pengaturan", 3a) — one setting field.
- Audit events for price, capacity, status and publish changes.
- Shared trip/departure validation and services that F-001's onboarding steps (4c Trip, 4d Jadwal) reuse.

**Out (explicitly):**
- Recurring departure templates / bulk date generation (P1).
- Add-ons, packages, promo codes, child/group pricing tiers (P1).
- Dynamic pricing (P2).
- Departure cancellation that cancels/refunds existing bookings ("Batalkan keberangkatan") — booking cancellation belongs to F-006/F-007 (week 4).
- Hour-level duration (e.g. "3 jam"); "Beberapa jam" is a label only.
- Multiple meeting points per departure, per-departure itinerary, per-departure start time.
- Video, photo captions, photo editing/cropping.
- Storage quotas per plan; departure-count limits per plan.
- Changing a trip's public URL slug after creation.
- Staff roles/permissions (P1) — every member of the organization can manage trips in the MVP.
- Customer-facing rendering of trips (F-002), booking and seat holds (F-005), request handling (F-004),
  payment-method management (F-001 onboarding step 4 / F-006).

## User stories

- As a seller, I want to create a trip from a type preset (Open trip / Private / Day trip) so that I only fill what matters.
- As a seller, I want to add departures with dates, seats and an optional special price so that customers can book specific dates.
- As a seller, I want to pick durasi and tutup pesan from presets and see the real closing date so that I don't have to calculate "H-3".
- As a seller, I want to copy my previous departure so that adding the next date takes seconds.
- As a seller, I want a checklist and preview before publishing so that I know my trip page is complete.
- As a seller on my phone, I want to change a departure's price, seats or open/closed in one sheet in under 30 seconds.
- As a seller, I want departures to close by themselves at the deadline so that nobody books too late.
- As a seller, I want my draft saved automatically so that a dropped connection doesn't lose my work.

## Acceptance criteria

Times below are in the organization's time zone (see Data & rules → Time). UI copy is Bahasa Indonesia (ADR-0007).

### Trip — create, edit, draft

- [ ] **AC-1 Trip type preset.** Given a seller on "Trip baru", when they choose *Open trip*, then booking mode = `scheduled` and duration = 3H2M is prefilled; *Day trip* → `scheduled`, duration 1 hari; *Private* → `request_date` with **no** duration prefilled (the seller picks one if they want). All prefilled values remain editable. Same presets as F-001 onboarding (4c).
- [ ] **AC-2 Minimal draft.** Given a new trip, when the seller enters only a name, then the trip is saved as `draft`. No other field is required to save a draft.
- [ ] **AC-3 Autosave.** Given a draft trip, when the seller changes a field and stops typing for 2 s (or leaves the field), then the change is saved and the header shows "Draf tersimpan HH.MM". When the save fails (offline/server error), then the form keeps the entered values, shows "Belum tersimpan · Coba lagi", and retries on reconnect; navigating away with unsaved changes asks for confirmation.
- [ ] **AC-4 Published trips are not autosaved.** Given a published trip, when the seller edits it, then nothing goes live until they tap "Simpan perubahan"; on success the change is visible on the public page.
- [ ] **AC-5 Duration presets.** Given the Durasi picker, then options are *Beberapa jam*, *1 hari*, *2 hari 1 malam*, *3 hari 2 malam*, *4 hari 3 malam* and *Atur sendiri*. When *Atur sendiri* is chosen, then Hari and Malam steppers appear with Hari 1–30 and Malam 0–Hari; trying to set Malam above Hari is prevented and shows "Malam maksimal sama dengan jumlah hari."
- [ ] **AC-6 Inclusions.** Given the Termasuk field, then suggestion chips (Kapal, Homestay, Makan, Alat snorkel, Guide) can be toggled and "+ Lainnya" adds a free-text item; same input pattern (without suggestions) for Tidak termasuk.
- [ ] **AC-7 Photos.** Given the photo section, when the seller adds photos from the gallery/camera, then each is uploaded with a progress state, the first photo is labelled "sampul", photos can be reordered (the new first becomes the cover) and removed. A file that is not JPEG/PNG/WebP, is over 10 MB, or would exceed 10 photos per trip is rejected with a Bahasa message and the other photos are unaffected. Stored photos and their variants have EXIF metadata (incl. GPS) stripped (ADR-0015).
- [ ] **AC-8 Money input.** Given any price field, then it accepts digits only (numeric keypad on phones), displays thousands with dots ("1.250.000"), and is stored as integer rupiah. Values below Rp1.000 or above Rp100.000.000 are rejected.

### Publish

- [ ] **AC-9 Checklist.** Given a trip editor, then a checklist "Siap terbit n/5" shows: Foto sampul · Nama trip · Harga dasar · Minimal 1 keberangkatan · Instruksi pembayaran (with a link "atur rekening" when missing). For `request_date` trips the departure item is not shown and the count is n/4. The short description is optional and is **not** a checklist item (decision 2026-10-17).
- [ ] **AC-10 Departure item.** The "Minimal 1 keberangkatan" item is satisfied only by a departure that is open, starts in the future and whose booking deadline is in the future.
- [ ] **AC-11 Publish blocked until complete.** Given an incomplete checklist, then "Terbitkan trip" is disabled and the helper reads "Lengkapi n langkah lagi untuk terbit"; a direct publish request is also rejected server-side with the missing items listed.
- [ ] **AC-12 Publish.** Given a complete checklist, when the seller taps "Terbitkan trip", then the trip becomes `published`, is visible on the public page (F-002), and an audit event `trip.published` is written.
- [ ] **AC-13 Live preview.** Given the editor, then a preview card shows cover, name, "Mulai Rp…" (lowest effective price among open future departures, else base price) and next departure date + seats — matching the F-002 trip card. On phone width the preview is reachable from the sticky bottom bar instead of a side column.
- [ ] **AC-14 No plan limit in P0.** Given an organization on any plan, when it publishes another trip, then no plan-based limit is applied (ADR-0019). The organization's `plan` is not read by publish in P0.
- [ ] **AC-15 Unpublish.** Given a published trip, when the seller chooses "Sembunyikan dari halaman publik", then the trip returns to `draft`, disappears from the public page, existing bookings and their payment pages keep working, and an audit event `trip.unpublished` is written.
- [ ] **AC-16 Delete trip.** Given a trip with no bookings ever, when the seller deletes it, then it and its departures and photos are removed. Given a trip with any booking, then delete is not offered; only unpublish is.

### Departures

- [ ] **AC-17 Add departure.** Given a `scheduled` trip, when the seller taps "+ Tambah keberangkatan" and picks a start date, then the end date is shown derived from the duration (e.g. 24 Okt + 3H2M → "24 – 26 Okt 2026"), seats default to the previous departure's seats (else empty), price is empty with hint "Kosongkan harga untuk pakai harga dasar", and the deadline defaults to the organization's default preset.
- [ ] **AC-18 Start date rules.** Start dates before today are not selectable; a start date on the same date as another departure of the same trip is allowed but shows "Sudah ada keberangkatan di tanggal ini."
- [ ] **AC-19 Seats.** Seats are a whole number 1–999.
- [ ] **AC-20 Effective price.** Given a departure with an empty price, then its effective price is the trip's base price, and it follows later base-price changes. Given a departure with a price, then that price is used. Clearing the price reverts to the base price.
- [ ] **AC-21 Deadline presets.** Given the Tutup pemesanan picker for a departure starting Kam 24 Okt with meeting time 06.00, then options show real dates: *Sampai hari keberangkatan* — 24 Okt 05.00; *1 hari sebelum* — 23 Okt 23.59; *3 hari sebelum* — 21 Okt 23.59; *7 hari sebelum* — 17 Okt 23.59; *Pilih tanggal & jam…*. The chosen value is confirmed as "Ditutup Senin, 21 Okt pukul 23.59." with "Setelah itu pelanggan hanya bisa chat WhatsApp."
- [ ] **AC-22 No meeting time.** Given a trip without a meeting time — including every trip created through F-001 onboarding (4d asks only for a date) — then *Sampai hari keberangkatan* is not offered, the other presets (H-1/H-3/H-7) work unchanged, and a custom deadline may be at most the start date 23.59. When the seller later adds a meeting time, *Sampai hari keberangkatan* becomes available; existing deadlines are not changed by that alone.
- [ ] **AC-23 Deadline validation.** Given a deadline (preset or custom) that is already in the past, or later than the meeting time (start date + meeting time), when saving, then the departure is rejected with an inline message (e.g. "Tutup pesan H-3 sudah lewat untuk tanggal ini. Pilih yang lain."); past dates are not selectable in the custom picker.
- [ ] **AC-24 Preset follows the date.** Given a departure with a relative preset (H-1/H-3/H-7/same day), when its start date or the trip's meeting time changes, then the deadline is recomputed; a custom deadline is not moved and is re-validated.
- [ ] **AC-25 Copy previous.** Given a trip with at least one departure, when the seller taps "Salin dari tanggal sebelumnya", then a new row is added with the latest-starting departure's seats, price override and deadline preset (a custom deadline is replaced by the organization default), date empty and focused. Given no departures, the action is hidden.
- [ ] **AC-26 Departure list.** Given a trip's departures, then upcoming ones are sorted by start date ascending showing dates, "terisi/total" seats, effective price, deadline (H-n or date) and status; departures whose start date has passed are grouped under "Sudah lewat", collapsed, read-only.
- [ ] **AC-27 Status display.** Each departure shows one of: *Buka*, *Tutup* (closed manually or by deadline), *Penuh* (open but seats taken = seats), *Sudah lewat* — each with a glyph + word, never colour alone and never brown or gold (design status rule).
- [ ] **AC-28 Date change with bookings.** Given a departure with seats taken > 0, then its start date cannot be changed; the UI explains "Sudah ada pesanan. Tutup keberangkatan ini dan buat tanggal baru."
- [ ] **AC-29 Delete departure.** Given a departure with no bookings ever, then it can be deleted. Given one with any booking, then delete is not offered; it can only be closed.
- [ ] **AC-30 Last bookable departure.** Given a published `scheduled` trip, when the seller closes or deletes its last open future departure, then the trip stays published and the seller sees "Trip tetap tampil, tapi belum ada tanggal yang bisa dipesan."

### Quick edit (1g)

- [ ] **AC-31 Entry points.** Given the seller's departures list on a phone (and "Edit cepat" on Hari ini, F-007), when they tap a departure, then a bottom sheet opens titled with trip + dates ("Karimunjawa · 24–26 Okt") containing only: Harga / orang, Total kursi (stepper), Buka / Tutup pemesanan toggle, and a gold "Simpan".
- [ ] **AC-32 Price in quick edit.** The price field is prefilled with the effective price; saving a different value sets the departure's price override. The sheet always shows "Harga baru hanya berlaku untuk pesanan berikutnya. n pesanan yang ada tidak berubah." (n = active bookings on the departure; sentence omitted when n = 0).
- [ ] **AC-33 Seats floor.** Given 14 seats taken, then the seats stepper cannot go below 14 and shows "14 terisi — tidak bisa di bawah 14". If bookings arrived after the sheet opened and the new total is below the current seats taken, then the server rejects it and the sheet refreshes to the current number with the same message — nothing is saved.
- [ ] **AC-34 Reopen after deadline.** Given a departure closed by its passed deadline, when the seller switches it to Buka, then the sheet asks for a new deadline ("Tutup pesan sudah lewat (21 Okt 23.59). Pilih tanggal tutup baru untuk membuka lagi.") and it reopens only with a valid future deadline.
- [ ] **AC-35 Speed.** Given a seller on a mid-range Android phone, a price-or-seats change from the departures list (tap row → change → Simpan → confirmation toast "Tersimpan") takes ≤ 3 taps plus typing and the sheet's save round trip shows a loading state; usability target < 30 s (verified in pilot sessions, not in CI).

### Auto-close, audit, concurrency

- [ ] **AC-36 Deadline is authoritative.** Given a departure whose deadline has passed, then it is not bookable even before any scheduled job runs (bookability is computed at request time; F-005 enforces). E.g. deadline 23.59.59: a booking at 23.59.30 is accepted, at 00.00.00 rejected.
- [ ] **AC-37 Auto-close job.** Given open departures with deadline ≤ now, when the scheduler runs (every minute), then each becomes `closed` with reason `deadline` and an audit event with actor `system` is written; running the job twice produces no duplicate events.
- [ ] **AC-38 Audit events.** Every change to base price, price override, seats, departure status (open/closed, manual or system), deadline, departure create/delete and trip publish/unpublish writes one audit event with organization, actor (user or `system`), subject, event name, before and after values and timestamp (F-009 format).
- [ ] **AC-39 No overbooking on capacity change.** Given a departure with seats 16 and 14 taken, when a seller reduces seats to 14 at the same moment a customer books 2 seats, then exactly one succeeds and afterwards seats taken ≤ seats (concurrency test; ADR-0006).
- [ ] **AC-40 Tenant isolation.** Given a seller of organization A, when they request to view, edit, autosave, publish, unpublish, delete, upload a photo to, reorder photos of, quick-edit, or copy-from a trip or departure of organization B — or submit a payload referencing B's trip/departure/photo IDs — then the response is 404 and nothing changes. One test per action (ADR-0004).
- [ ] **AC-41 Phone width.** All screens in this feature work at 360 px width without horizontal scroll: editor sections stack, the departures table becomes cards, checklist + "Terbitkan trip" sit in a sticky bottom bar, tap targets ≥ 44 px, text ≥ 12 px (11 px only for uppercase kickers).
- [ ] **AC-42 Empty states.** No trips → "Belum ada trip" + "+ Trip baru"; scheduled trip without departures → "Belum ada keberangkatan" + "+ Tambah keberangkatan"; no upcoming departures in the quick-edit list → "Belum ada keberangkatan terdekat".

## Data & rules

### Entities (all carry `organization_id`; ADR-0004)

**trips**
- `organization_id`, `public_id` (ULID used in URLs — no sequential IDs exposed), `slug` (unique per organization, generated from the name at creation, never changed automatically; used by F-002).
- `type`: `open_trip` | `private` | `day_trip`. `booking_mode`: `scheduled` | `request_date`.
- `status`: `draft` | `published`. (`published_at`.)
- `name` (≤ 80 chars, required to save and to publish), `short_description` (≤ 300, optional — not required to publish).
- Duration: `duration_days` (1–30), `duration_nights` (0–`duration_days`), `duration_label`: `hours` | `days` (`hours` = "Beberapa jam", stored as 1 day 0 nights). Nullable: *Private* trips start without one. A `scheduled` trip needs a duration before a departure can be added (the end date is derived from it); the departure form asks for it inline ("Pilih durasi dulu") if it is missing. `request_date` trips may stay without a duration.
- `inclusions[]`, `exclusions[]` (each ≤ 20 items × ≤ 100 chars), `meeting_point` (≤ 200), `meeting_time` (HH:MM, nullable), `map_url` (http/https, nullable), `itinerary[]` (one optional line per day, ≤ 300 chars each), `terms` (≤ 2 000 chars).
- `base_price` (integer rupiah, Rp1.000–Rp100.000.000; required to publish).
- Display labels: 3H2M = "3 hari 2 malam"; `hours` = "Beberapa jam"; 1/0 = "1 hari".

**trip_photos** (ADR-0015) — `organization_id`, `trip_id`, `path` (organization-prefixed, UUID file name, e.g. `org/{organization_public_id}/trips/{trip_public_id}/{uuid}.{ext}`), `position`. Lowest position = cover. JPEG/PNG/WebP, ≤ 10 MB each, ≤ 10 per trip. EXIF stripped from the stored original and all variants. Resized variants (e.g. 400/800/1600 px wide) generated on the queue for F-002's 3G target. Photos are public assets; payment evidence is not (F-006).

**trip_departures** (ADR-0005)
- `organization_id` (must equal the trip's), `trip_id`, `public_id`.
- `start_date`; `end_date` = `start_date + (duration_days − 1)`, computed and stored on save.
- `capacity` (1–999); `seats_taken` (integer, default 0, **maintained by F-005**: held + paid, ADR-0006).
- `price_override` (nullable integer rupiah). Effective price = `price_override ?? trip.base_price`.
- `booking_deadline_preset`: `same_day` | `h1` | `h3` | `h7` | `custom`; `booking_deadline_at` (timestamp, stored UTC).
- `status`: `open` | `closed`; `closed_reason`: `manual` | `deadline` (nullable).

**organizations** (owned by F-001; this feature reads/needs): `default_booking_deadline_preset` (default `h3`, per mockups 3a/4d), `timezone` (default `Asia/Jakarta`, edited in Pengaturan → Profil usaha by F-001; see Time), `plan`.

### Rules

- **Bookable** (single server-side definition shared with F-002/F-005): trip `published` and `scheduled` ∧ departure `open` ∧ `now < booking_deadline_at` ∧ `seats_taken < capacity` ∧ `start_date ≥ today`.
- **Deadline presets:** `h{n}` = (start_date − n days) at 23:59:59; `same_day` = start_date at (meeting_time − 1 h) (mockup 3a: kumpul 06.00 → 05.00); `custom` = seller-chosen, must be > now and ≤ start_date + meeting_time (or start_date 23:59:59 if no meeting time). Displayed as "23.59".
- **Prices:** bookings snapshot their unit price at creation (F-005), so changing base price or override never changes existing bookings (ADR-0005).
- **Capacity change:** inside a DB transaction, lock the departure row (`SELECT … FOR UPDATE`), re-read `seats_taken`, reject if new capacity < `seats_taken`, update, write audit event — same lock F-005 uses when taking seats (ADR-0006).
- **Duration change** on a trip: recompute `end_date` for departures with `seats_taken = 0`; departures with bookings keep their dates and the seller is told "n keberangkatan yang sudah ada pesanan tidak berubah." (see Q3).
- **Booking mode change** is refused while any future departure has `seats_taken > 0`.
- **Plan limits:** none enforced in P0 (ADR-0019). Free-tier limits are decided with pricing after the pilot and added in a later spec.
- **Authorisation:** policy = user is a member of the record's organization (any role in MVP). Lookups resolve through the current organization's scope, so foreign IDs return 404. Validate that `trip_id` in departure payloads belongs to the current organization.
- **Audit event names:** `trip.published`, `trip.unpublished`, `trip.base_price_changed`, `departure.created`, `departure.deleted`, `departure.price_changed`, `departure.capacity_changed`, `departure.status_changed`, `departure.deadline_changed`.
- **Scheduler:** auto-close job runs every minute, is idempotent, and its heartbeat is monitored (F-009).

### Time

- ADR-0014: store timestamps in UTC; compute and display deadlines in the organization's time zone (`Asia/Jakarta` WIB, `Asia/Makassar` WITA, `Asia/Jayapura` WIT). Default `Asia/Jakarta`; the seller changes it in Pengaturan → Profil usaha (F-001). Indonesia has no DST.
- Dates in UI: Indonesian locale, "Senin, 21 Okt pukul 23.59", ranges "24 – 26 Okt 2026".

### Edge cases covered

- Last seat booked while seller reduces capacity (AC-39); stale quick-edit sheet (AC-33).
- Deadline passes while the seller is editing; default deadline preset already in the past for a near date (AC-23).
- Departure for today with `same_day` deadline after meeting time − 1 h (rejected, AC-23).
- Reopening after deadline (AC-34); scheduler late or down (AC-36).
- Unpublishing or editing a trip with active bookings (AC-15, AC-28); last departure closed (AC-30).
- Flaky connection during autosave and photo upload (AC-3, AC-7).
- Cross-tenant ID tampering (AC-40). Phone width (AC-41). Empty states (AC-42).

### Dependencies

- **F-009:** organization scoping helpers, policies base, `audit_events` table/writer, scheduler monitoring.
- **F-001:** `organizations` (incl. `plan`, `timezone`, `default_booking_deadline_preset`) and payment methods (checklist item 5). F-001 onboarding steps 4c/4d reuse this feature's trip/departure services and validation.
- **F-005:** maintains `seats_taken`; snapshots unit price on bookings; uses the same row lock and the *Bookable* rule.
- **F-002:** renders trips/departures, uses `slug`, photo variants and the *Bookable* rule.
- **F-004:** decides whether accepted requests create departures (they would appear in this list).
- **F-007:** "Edit cepat" on Hari ini opens this feature's quick-edit sheet.
- **ADR-0013:** database is PostgreSQL (row locks via `SELECT … FOR UPDATE`, as ADR-0006 assumes).

## Open questions

None of these block building; each has a default that the builder implements unless the human overrides it.

- [x] **Q1 Time zone** — decided 2026-10-17: [ADR-0014](../decisions/0014-timezones-utc-storage.md) accepted. Sellers outside WIB (Bali, Lombok, Labuan Bajo = WITA; Raja Ampat = WIT) need correct deadlines. *Recommended:* `organizations.timezone` default `Asia/Jakarta`, changeable by the seller in Pengaturan → Profil usaha (F-001). — Rengga
- [x] **Q2 Photo limits & storage** — decided 2026-10-17: [ADR-0015](../decisions/0015-trip-photo-storage.md) accepted. *Recommended:* ≤ 10 photos per trip, ≤ 10 MB per file before resize, JPEG/PNG/WebP, org-prefixed path, UUID names, EXIF stripped; no per-plan storage quota in MVP (revisit with Pro). Mockup shows "foto 1/6". — Rengga
- [ ] **Q3 Duration change with bookings.** *Recommended:* only departures without bookings get new end dates; booked ones keep theirs, with a notice. Alternative: block duration changes while any future departure has bookings. — Rengga
- [ ] **Q4 Deleting a departure with bookings.** *Recommended:* not allowed — close only (AC-29). A "Batalkan keberangkatan" flow that cancels bookings and messages customers belongs with booking cancellation in week 4 (F-006/F-007). — Rengga
- [x] **Q5 Free-plan limit.** Decided 2026-10-17: not enforced in P0; the "1 trip" in the mockups is sample copy (ADR-0019).
- [ ] **Q6 Default deadline preset.** *Recommended:* H-3 (selected in mockups 3a and 4d); seller changes it in Pengaturan. — Rengga
- [ ] **Q7 "Sampai hari keberangkatan" cut-off.** *Recommended:* 1 hour before meeting time (mockup 3a: 06.00 → 05.00); not offered when the trip has no meeting time. — Rengga
- [ ] **Q8 Mobile trip editor mockup.** 1f is desktop-only; phone layout is specified by AC-41 reusing onboarding patterns (4c/4d). *Recommended:* build from those patterns; review on a device in week 3 rather than wait for a mockup. — design

## Changelog

- 2026-10-17 — created
- 2026-10-17 — Planner: full acceptance criteria (AC-1…AC-42), data model & rules, tenancy/overbooking cases, dependencies; open questions given defaults; status → `ready`
- 2026-10-17 — Aligned with F-001/F-009 and decisions relayed by the coordinator: description optional (checklist item "Nama trip"); Private preset has no duration; free-plan limit counts `published` trips; AC-22 covers onboarding trips without meeting time; ADR-0013 (PostgreSQL), ADR-0014 (time zone), ADR-0015 (trip photos, EXIF stripped) linked; time zone default Asia/Jakarta, set in Pengaturan → Profil usaha
- 2026-10-17 — Free-plan trip limit removed from P0 (ADR-0019): Scope item dropped, AC-14 now asserts no limit, Q5 closed
- 2026-10-17 — ADR-0014 and ADR-0015 accepted; Q1 and Q2 closed
