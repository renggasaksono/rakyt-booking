---
id: F-003
title: Trips & departures management
status: in-progress
priority: P0
roadmap: mvp-weeks-2-3
mockups: [1f, 1g, 3a, 3b, 4c, 4d, 1e]
decisions: ["0002", "0004", "0005", "0006", "0007", "0008", "0010", "0013", "0014", "0015", "0019", "0021", "0022"]
updated: 2026-10-19
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

- [x] **AC-1 Trip type preset.** Given a seller on "Trip baru", when they choose *Open trip*, then booking mode = `scheduled` and duration = 3H2M is prefilled; *Day trip* → `scheduled`, duration 1 hari; *Private* → `request_date` with **no** duration prefilled (the seller picks one if they want). All prefilled values remain editable. Same presets as F-001 onboarding (4c).
- [x] **AC-2 Minimal draft.** Given a new trip, when the seller enters only a name, then the trip is saved as `draft`. No other field is required to save a draft.
- [ ] **AC-3 Autosave.** Given a draft trip, when the seller changes a field and stops typing for 2 s (or leaves the field), then the change is saved and the header shows "Draf tersimpan HH.MM". When the save fails (offline/server error), then the form keeps the entered values, shows "Belum tersimpan · Coba lagi", and retries on reconnect; navigating away with unsaved changes asks for confirmation.
  *Partly covered:* server autosave, the time in the organization's zone, and the autosave controller (2 s debounce, save on blur, keeps values on failure, retry, one save at a time) are tested. The header copy, retry on `online` and the leave confirmation are wired in `Trips/Edit.vue` and passed a manual Chrome smoke test, but have no automated test.
- [x] **AC-4 Published trips are not autosaved.** Given a published trip, when the seller edits it, then nothing goes live until they tap "Simpan perubahan"; on success the change is visible on the public page.
- [x] **AC-5 Duration presets.** Given the Durasi picker, then options are *Beberapa jam*, *1 hari*, *2 hari 1 malam*, *3 hari 2 malam*, *4 hari 3 malam* and *Atur sendiri*. When *Atur sendiri* is chosen, then Hari and Malam steppers appear with Hari 1–30 and Malam 0–Hari; trying to set Malam above Hari is prevented and shows "Malam maksimal sama dengan jumlah hari."
- [x] **AC-6 Inclusions.** Given the Termasuk field, then suggestion chips (Kapal, Homestay, Makan, Alat snorkel, Guide) can be toggled and "+ Lainnya" adds a free-text item of at most 40 characters (longer input is stopped at 40 with a counter "n/40"); at most 20 items; same input pattern (without suggestions) for Tidak termasuk.
- [ ] **AC-7 Photos.** Given the photo section, when the seller adds photos from the gallery/camera, then each is uploaded with a progress state, the first photo is labelled "sampul", photos can be reordered (the new first becomes the cover) and removed. A file that is not JPEG/PNG/WebP, is over 10 MB, or would exceed 10 photos per trip is rejected with a Bahasa message and the other photos are unaffected. Stored photos and their variants have EXIF metadata (incl. GPS) stripped (ADR-0015).
- [ ] **AC-7a Cover change on a published trip.** Given a published trip, when the seller moves another photo into first place or removes the cover photo, then a confirmation asks first ("Jadikan foto ini sampul? Sampul langsung tampil di halaman publik dan pratinjau link." / "Hapus foto sampul? Foto berikutnya langsung jadi sampul di halaman publik."); cancelling changes nothing. Uploads (always added last) and changes to later photos do not ask. Drafts never ask. *Detection is unit-tested (`tests/js/photos.test.js`); the dialog itself is a manual check.* (Q13, decided 2026-10-18)
  *Partly covered:* storage path, EXIF stripping (original and variants), cover order, reorder, remove, type/size/limit rejection, the original stored at ≤ 2560 px, an undecodable file (422, not 500) and the memory-budget pixel cap (ADR-0022 §5) are tested. The per-photo progress state is UI-only (XHR progress) and has no automated test.
- [x] **AC-8 Money input.** Given any price field, then it accepts digits only (numeric keypad on phones), displays thousands with dots ("1.250.000"), and is stored as integer rupiah. Values below Rp1.000 or above Rp100.000.000 are rejected.

### Publish

- [x] **AC-9 Checklist.** Given a trip editor, then a checklist "Siap terbit n/5" shows: Foto sampul · Nama trip · Harga dasar · Minimal 1 keberangkatan · Instruksi pembayaran (with a link "atur rekening" when missing). For `request_date` trips the departure item is not shown and the count is n/4. The short description is optional and is **not** a checklist item (decision 2026-10-17).
- [x] **AC-10 Departure item.** The "Minimal 1 keberangkatan" item is satisfied only by a departure that is open, starts in the future and whose booking deadline is in the future.
- [x] **AC-11 Publish blocked until complete.** Given an incomplete checklist, then "Terbitkan trip" is disabled and the helper reads "Lengkapi n langkah lagi untuk terbit"; a direct publish request is also rejected server-side with the missing items listed.
  *Note:* the server refusal and the helper copy are tested; the button is disabled when `done < total` (`PublishPanel.vue`).
- [x] **AC-12 Publish.** Given a complete checklist, when the seller taps "Terbitkan trip", then the trip becomes `published`, is visible on the public page (F-002), and an audit event `trip.published` is written.
- [x] **AC-13 Live preview.** Given the editor, then a preview card shows cover, name, "Mulai Rp…" (lowest effective price among the departures that are open for booking — open, before their deadline, seats left, not started — else base price; the same function as the F-002 card, `TripPricing::fromPrice()`, F-002 Q7) and next departure date + seats — matching the F-002 trip card. On phone width the preview is reachable from the sticky bottom bar instead of a side column.
- [x] **AC-14 No plan limit in P0.** Given an organization on any plan, when it publishes another trip, then no plan-based limit is applied (ADR-0019). The organization's `plan` is not read by publish in P0.
- [x] **AC-15 Unpublish.** Given a published trip, when the seller chooses "Sembunyikan dari halaman publik", then the trip returns to `draft`, disappears from the public page, existing bookings and their payment pages keep working, and an audit event `trip.unpublished` is written.
- [x] **AC-16 Delete trip.** Given a trip with no bookings ever, when the seller deletes it, then it and its departures and photos are removed. Given a trip with any booking, then delete is not offered; only unpublish is.

### Departures

- [x] **AC-17 Add departure.** Given a `scheduled` trip, when the seller taps "+ Tambah keberangkatan" and picks a start date, then the end date is shown derived from the duration (e.g. 24 Okt + 3H2M → "24 – 26 Okt 2026"), seats default to the previous departure's seats (else empty), price is empty with hint "Kosongkan harga untuk pakai harga dasar", and the deadline defaults to the organization's default preset.
- [x] **AC-18 Start date rules.** Start dates before today are not selectable; a start date on the same date as another departure of the same trip is allowed but shows "Sudah ada keberangkatan di tanggal ini."
- [x] **AC-19 Seats.** Seats are a whole number 1–999.
- [x] **AC-20 Effective price.** Given a departure with an empty price, then its effective price is the trip's base price, and it follows later base-price changes. Given a departure with a price, then that price is used. Clearing the price reverts to the base price.
- [x] **AC-21 Deadline presets.** Given the Tutup pemesanan picker for a departure starting Kam 24 Okt with meeting time 06.00, then options show real dates: *Sampai hari keberangkatan* — 24 Okt 05.00; *1 hari sebelum* — 23 Okt 23.59; *3 hari sebelum* — 21 Okt 23.59; *7 hari sebelum* — 17 Okt 23.59; *Pilih tanggal & jam…*. The chosen value is confirmed as "Ditutup Senin, 21 Okt pukul 23.59." with "Setelah itu pelanggan hanya bisa chat WhatsApp."
- [x] **AC-22 No meeting time.** Given a trip without a meeting time — including every trip created through F-001 onboarding (4d asks only for a date) — then *Sampai hari keberangkatan* is not offered, the other presets (H-1/H-3/H-7) work unchanged, and a custom deadline may be at most the start date 23.59. When the seller later adds a meeting time, *Sampai hari keberangkatan* becomes available; existing deadlines are not changed by that alone. The hint "Hanya boleh sebelum jam kumpul…" is shown only when the departure has a meeting time or *Pilih tanggal & jam…* is chosen.
- [x] **AC-23 Deadline validation.** Given a deadline (preset or custom) that is already in the past, or later than the meeting time (start date + meeting time), when saving, then the departure is rejected with an inline message (e.g. "Tutup pesan H-3 sudah lewat untuk tanggal ini. Pilih yang lain."); past dates are not selectable in the custom picker. Presets that are already past (or after the meeting time) are hidden from the picker, not shown struck through; *Pilih tanggal & jam…* is always listed.
- [x] **AC-24 Preset follows the date.** Given a departure with a relative preset (H-1/H-3/H-7/same day), when its start date or the trip's meeting time changes, then the deadline is recomputed; a custom deadline is not moved and is re-validated.
- [x] **AC-25 Copy previous.** Given a trip with at least one departure, when the seller taps "Salin dari tanggal sebelumnya", then a new row is added with the latest-starting departure's seats, price override and deadline preset (a custom deadline is replaced by the organization default), date empty and focused. Given no departures, the action is hidden.
- [x] **AC-26 Departure list.** Given a trip's departures, then upcoming ones are sorted by start date ascending showing dates, "terisi/total" seats, effective price, deadline (H-n or date) and status; departures whose start date has passed are grouped under "Sudah lewat", collapsed, read-only.
- [x] **AC-27 Status display.** Each departure shows one of: *Buka*, *Tutup* (closed manually or by deadline), *Penuh* (open but seats taken = seats), *Sudah lewat* — each with a glyph + word, never colour alone and never brown or gold (design status rule).
- [x] **AC-28 Date change with bookings.** Given a departure with seats taken > 0, then its start date cannot be changed; the UI explains "Sudah ada pesanan. Tutup keberangkatan ini dan buat tanggal baru."
- [x] **AC-29 Delete departure.** Given a departure with no bookings ever, then it can be deleted. Given one with any booking, then delete is not offered; it can only be closed.
- [x] **AC-30 Last bookable departure.** Given a published `scheduled` trip, when the seller closes or deletes its last open future departure, then the trip stays published and the seller sees "Trip tetap tampil, tapi belum ada tanggal yang bisa dipesan."

### Quick edit (1g)

- [x] **AC-31 Entry points.** Given the seller's departures list on a phone (and "Edit cepat" on Hari ini, F-007), when they tap a departure, then a bottom sheet opens titled with trip + dates ("Karimunjawa · 24–26 Okt") containing only: Harga / orang, Total kursi (stepper), Buka / Tutup pemesanan toggle, and a gold "Simpan".
  *Note:* the phone list (`/keberangkatan`) and the editor's departure cards open the sheet. The "Edit cepat" entry on Hari ini comes with F-007.
- [x] **AC-32 Price in quick edit.** The price field is prefilled with the effective price; saving a different value sets the departure's price override. The sheet always shows "Harga baru hanya berlaku untuk pesanan berikutnya. n pesanan yang ada tidak berubah." (n = active bookings on the departure; sentence omitted when n = 0).
- [x] **AC-33 Seats floor.** Given 14 seats taken, then the seats stepper cannot go below 14 and shows "14 terisi — tidak bisa di bawah 14". If bookings arrived after the sheet opened and the new total is below the current seats taken, then the server rejects it and the sheet refreshes to the current number with the same message — nothing is saved.
- [x] **AC-34 Reopen after deadline.** Given a departure closed by its passed deadline, when the seller switches it to Buka, then the sheet asks for a new deadline ("Tutup pesan sudah lewat (21 Okt 23.59). Pilih tanggal tutup baru untuk membuka lagi.") and it reopens only with a valid future deadline.
- [ ] **AC-35 Speed.** Given a seller on a mid-range Android phone, a price-or-seats change from the departures list (tap row → change → Simpan → confirmation toast "Tersimpan") takes ≤ 3 taps plus typing and the sheet's save round trip shows a loading state; usability target < 30 s (verified in pilot sessions, not in CI).
  *Not CI:* the sheet shows a loading state and the "Tersimpan" toast (tap row → change → Simpan). The < 30 s target is checked in pilot sessions.

### Auto-close, audit, concurrency

- [x] **AC-36 Deadline is authoritative.** Given a departure whose deadline has passed, then it is not bookable even before any scheduled job runs (bookability is computed at request time; F-005 enforces). E.g. deadline 23.59.59: a booking at 23.59.30 is accepted, at 00.00.00 rejected.
- [x] **AC-37 Auto-close job.** Given open departures with deadline ≤ now, when the scheduler runs (every minute), then each becomes `closed` with reason `deadline` and an audit event with actor `system` is written; running the job twice produces no duplicate events.
- [x] **AC-38 Audit events.** Every change to base price, price override, seats, departure status (open/closed, manual or system), deadline, departure create/delete and trip publish/unpublish writes one audit event with organization, actor (user or `system`), subject, event name, before and after values and timestamp (F-009 format).
- [x] **AC-39 No overbooking on capacity change.** Given a departure with seats 16 and 14 taken, when a seller reduces seats to 14 at the same moment a customer books 2 seats, then exactly one succeeds and afterwards seats taken ≤ seats (concurrency test; ADR-0006).
- [x] **AC-40 Tenant isolation.** Given a seller of organization A, when they request to view, edit, autosave, publish, unpublish, delete, upload a photo to, reorder photos of, quick-edit, or copy-from a trip or departure of organization B by its URL, then the response is 404 and nothing changes; when they submit a payload referencing B's trip/departure/photo IDs, then the response is 422 with the same message as for an unknown id, and nothing changes (ADR-0021). One test per action (ADR-0004). *Reworded for payload ids (Q12, decided 2026-10-18).*
- [ ] **AC-41 Phone width.** All screens in this feature work at 360 px width without horizontal scroll: editor sections stack, the departures table becomes cards, checklist + "Terbitkan trip" sit in a sticky bottom bar, tap targets ≥ 44 px, text ≥ 12 px (11 px only for uppercase kickers).
  *Partly verified:* a Chrome DevTools check at a true 360 px viewport found no horizontal overflow (`scrollWidth` = 360) on all five pages. Tap targets use `min-h-11` (44 px), the departure table becomes cards below `md`, and the checklist with "Terbitkan trip" sits in a sticky bottom bar. This is not in CI; the device review (Q8, Q10) is still to do.
- [x] **AC-42 Empty states.** No trips → "Belum ada trip" + "+ Trip baru"; scheduled trip without departures → "Belum ada keberangkatan" + "+ Tambah keberangkatan"; no upcoming departures in the quick-edit list → "Belum ada keberangkatan terdekat".

## Data & rules

### Entities (all carry `organization_id`; ADR-0004)

**trips**
- `organization_id`, `public_id` (ULID used in URLs — no sequential IDs exposed), `slug` (unique per organization, generated from the name at creation, never changed automatically; used by F-002).
- `type`: `open_trip` | `private` | `day_trip`. `booking_mode`: `scheduled` | `request_date`.
- `status`: `draft` | `published`. (`published_at`.)
- `name` (3–80 chars after trimming, required to save and to publish), `short_description` (≤ 300, optional — not required to publish).
- Duration: `duration_days` (1–30), `duration_nights` (0–`duration_days`), `duration_label`: `hours` | `days` (`hours` = "Beberapa jam", stored as 1 day 0 nights). Nullable: *Private* trips start without one. A `scheduled` trip needs a duration before a departure can be added (the end date is derived from it); the departure form asks for it inline ("Pilih durasi dulu") if it is missing. `request_date` trips may stay without a duration.
- `inclusions[]`, `exclusions[]` (each ≤ 20 items × ≤ 40 chars, trimmed, blank items dropped — same limit as F-001 C4; decided 2026-10-18), `meeting_point` (≤ 200), `meeting_time` (HH:MM, nullable), `map_url` (http/https, nullable), `itinerary[]` (one optional line per day, ≤ 300 chars each), `terms` (≤ 2 000 chars).
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
- **F-009 hand-offs (2026-10-18):** trips, departures and trip media use `BelongsToOrganization` + a policy extending `OrganizationOwnedPolicy` + the cross-tenant harness (guard tests fail otherwise). Photos use `MediaPath`, `UploadRules::tripPhoto()` and `PublicImage::sanitize()`; add the pixel-dimension cap first (**F-009 R3**). The deadline auto-close job must be added to `config/rakyt.php → cross_organization_allowlist` and audit with `Actor::system()` (**F-009 A11**). Also owned here: **F-009 R14** (`UniqueInCurrentOrganization` for the trip slug; no bare `exists:`/`unique:` rules), **R15** (soft-delete route binding) and **R16** (nested `scopeBindings()` routes) — non-numeric ids must be a 404, not a 500.

### Build order — F-003 before F-001 (2026-10-18)

F-003 is built next, before F-001 (F-001's steps 2–3 reuse this feature's services, so it goes second). The app today
has F-009 slice A only: `organizations` holds just `public_id`, there is no seller sign-in, no seller layout and no
`payment_methods` table. F-003 therefore owns these pieces and leaves the rest to F-001:

| Piece | F-003 builds | Left to F-001 |
|---|---|---|
| `organizations` columns | Migration adding `timezone` (default `Asia/Jakarta`; one of the three Indonesian zones) and `default_booking_deadline_preset` (default `h3`) | Profile fields; the time-zone field in Pengaturan → Profil usaha. `plan` is not added here (AC-14: publish never reads it) |
| Default-deadline setting | The one field in Pengaturan → Pemesanan, authorised via `OrganizationPolicy` | The rest of Pengaturan |
| Seller layout | Minimal authenticated layout (phone bottom nav / desktop side nav) showing only "Trip & keberangkatan" | Dashboard (F-007); onboarding redirect (F-001 E2) |
| Sign-in | None — seller routes sit behind `auth` + the F-009 organization resolver and are covered by feature tests (Q10) | Sign-up / sign-in (section A) |
| Checklist item 5 "Instruksi pembayaran" | Asked through one interface (e.g. `PaymentReadiness::hasActivePaymentMethod(Organization)`); the default binding returns `false`, tests bind a fake (Q9); the "atur rekening" link points to Pengaturan → Rekening (F-001 F5, 2026-10-19) | `payment_methods` table and form, and the real binding |
| Photo pixel cap (F-009 R3) | Added here first | Reused by onboarding photos |

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
- [x] **Q9 Payment-method check before F-001.** *Resolved by F-001 (2026-10-18): `ActivePaymentMethods` is bound; the `payment_methods` table exists.* Checklist item 5 needs payment methods, which F-001 (pulled forward from F-006) creates. *Recommended:* F-003 asks through a `PaymentReadiness` interface whose default answer is "no method"; F-001 swaps in the real query. Nobody can publish in the app until F-001 lands, which is fine because nobody can sign in either. Alternative: F-003 creates the `payment_methods` table now — rejected because ADR-0018's protection of payment instructions belongs with the form that edits them. — Rengga
- [x] **Q10 Sign-in before F-001.** *Resolved by F-001 (2026-10-18): sign-in exists, so the device review (Q8, AC-41) can be done.* *Recommended:* no development login shortcut; F-003 is verified by feature tests, and the device review (Q8, AC-41) happens once F-001 section A ships. A local-only login link would be a backdoor that has to be proven absent in production. — Rengga
- [x] **Q11 Name length differs from F-001.** F-001 C4 says "Nama trip" is 3–100 characters; F-003 Data & rules says ≤ 80. *Built:* ≤ 80 (F-003), with no minimum beyond "not blank". F-001 should reuse `TripRules`, or the human picks one limit. — Rengga Decided 2026-10-18: 3–80 characters, enforced by the shared `TripRules` that F-001 C4 reuses.
- [x] **Q12 Foreign ids in payloads: 404 or 422?** AC-40 says 404. ADR-0021 (accepted) says payload ids are a 422, and the F-009 harness asserts 422. *Built:* 422 with nothing written; every URL-level action is a 404. Recommend rewording AC-40. — Rengga Decided 2026-10-18: 422 for payload ids; AC-40 reworded and ticked.
- [x] **Q13 Published trips: photos and departures apply immediately.** AC-4's "nothing goes live until Simpan perubahan" is built for the trip's own fields. Photo and departure changes save on their own, like the quick edit. A published trip cannot drop its last photo or its base price (ADR-0022 §8). Decided 2026-10-18: keep, and confirm cover changes (AC-7a) so a wrong photo never silently becomes the cover. — Rengga
- [x] **Q14 Default deadline excludes "Sampai hari keberangkatan".** Pengaturan → Pemesanan offers H-1, H-3 and H-7 only, because "same day" needs a meeting time (ADR-0022 §6). *Recommended:* keep. — Rengga Decided 2026-10-18: keep H-1/H-3/H-7 only.
- [x] **Q15 Time-zone change recomputes deadlines.** ADR-0014 requires preset deadlines on future departures to move when an organization's time zone changes. The time-zone field is F-001's, so the recompute ships with that field. `DeadlineCalculator` is ready to use. — F-001 Decided 2026-10-18: ships with F-001's time-zone field (F-001 F4).

## Changelog

- 2026-10-17 — created
- 2026-10-17 — Planner: full acceptance criteria (AC-1…AC-42), data model & rules, tenancy/overbooking cases, dependencies; open questions given defaults; status → `ready`
- 2026-10-17 — Aligned with F-001/F-009 and decisions relayed by the coordinator: description optional (checklist item "Nama trip"); Private preset has no duration; free-plan limit counts `published` trips; AC-22 covers onboarding trips without meeting time; ADR-0013 (PostgreSQL), ADR-0014 (time zone), ADR-0015 (trip photos, EXIF stripped) linked; time zone default Asia/Jakarta, set in Pengaturan → Profil usaha
- 2026-10-17 — Free-plan trip limit removed from P0 (ADR-0019): Scope item dropped, AC-14 now asserts no limit, Q5 closed
- 2026-10-17 — ADR-0014 and ADR-0015 accepted; Q1 and Q2 closed
- 2026-10-18 — F-009 slice A hand-offs noted (R3 photo dimension cap, A11 auto-close audit)
- 2026-10-18 — F-009 hand-offs R14–R16 noted
- 2026-10-18 — Planner: F-003 is built before F-001; added "Build order" (organization columns, Pengaturan field, seller layout, payment-readiness interface, R3 first) and Q9/Q10 with defaults; stays `ready`
- 2026-10-18 — Inclusion/exclusion items capped at 40 chars (was 100) to match F-001 C4 and keep chips readable on phones — decided by Rengga
- 2026-10-18 — Builder: built server side (schema, services, audit, auto-close, photos, concurrency-safe capacity), seller UI (editor, departures, quick edit, Pengaturan → Pemesanan) and tests (PHPUnit + Vitest). ACs ticked where tests cover every clause; AC-3, AC-7 and AC-41 are partly covered, AC-35 is a pilot check. Q3, Q4, Q6, Q7, Q8 defaults implemented. ADR-0022 proposed; Q11–Q15 added. F-009 R3, R14, R15, R16 and A11 done. Status → `in-progress`
- 2026-10-18 — Review fixes: photo decoding fits an explicit memory budget (320 MB, cap derived as 29 MP, originals stored at ≤ 2560 px; B1). AC-40 unticked until Q12 is decided (S1). A meeting-time change that puts a today departure's deadline in the past now closes it, audited, with a notice (S2). Status tint tokens and a "Sudah lewat" glyph of its own (S3, S4). Trip list/create go through `TripPolicy` (N2). Checklist deadlines compared in UTC (N1). Bahasa close label (N4). The upcoming list shows 20 at a time with "Tampilkan lebih banyak" (N5).
- 2026-10-18 — Q13 decided: published-trip photo/departure changes stay immediate; cover changes ask for confirmation (AC-7a)
- 2026-10-18 — Rengga: ADR-0022 accepted; Q11 (name 3–80), Q12 (AC-40 reworded: URL 404, payload 422; ticked), Q14 (keep) and Q15 (with F-001) decided
- 2026-10-18 — F-001 build: real `PaymentReadiness` bound (Q9), sign-in available (Q10); `DepartureService::rezoneDeadlines()` added for F-001 F4. Seller pages now redirect sellers still in onboarding (F-001 E2) and guests to `/masuk`
- 2026-10-19 — F-001 manual-test fixes: past deadline presets are hidden instead of struck through and the meeting-time hint shows only when it applies (AC-22, AC-23); the checklist's "atur rekening" link now opens Pengaturan → Rekening (F-001 F5); Pengaturan → Pemesanan links back to Pengaturan
- 2026-10-19 — F-002 Q7: AC-13 reworded — "Mulai Rp…" and the next departure count only departures open for booking, shared with the public card (`TripPricing`, `TripDeparture::openForBooking()`)
