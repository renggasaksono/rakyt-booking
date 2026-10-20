---
id: F-006
title: Payment instructions & records
status: ready
priority: P0
roadmap: mvp-week-4
mockups: [1d, 2e, 3e, 2f, 4e]
decisions: ["0003", "0006", "0008", "0017", "0018", "0026", "0027", "0030"]
updated: 2026-10-20
---

# F-006 · Payment instructions & records

## Problem

Since F-005 a customer can book and gets a booking page with a reference, a status pill and a hold countdown — but
nothing tells them *where to pay*, and the seller has no way to confirm a payment. The hold runs out in two hours and
the seat is lost even though the customer is ready to transfer. Sellers, meanwhile, see a booking sitting in "Menunggu
pembayaran" with no way to mark it paid. Rakyt never touches the money (ADR-0003): it must show the seller's own
account details clearly, let the customer prove they paid, and let the seller confirm or reject that proof — turning a
held seat into a paid one, or back into a free one.

## Scope

**In:**
- **Customer payment screen** — the bottom half of mockups 1d/2e/3e on the existing booking page
  `/{slug}/pesanan/{token}` (F-005 built the top half): the seller's active payment methods (bank / e-wallet account
  number + account holder; QRIS image), the account-holder helper "Harus sama persis dengan buku tabungan.", copy
  buttons for the account number and the amount, the 3-step progress (Transfer → Dicek operator → Lunas), the
  "Rakyt tidak memotong biaya transaksi" line, and the gold CTA **"Unggah bukti transfer"** (WhatsApp secondary).
- **Evidence upload** — image ≤ 5 MB to the private disk, checked under the booking lock against
  `Booking::holdIsActive(now)` (F-005 AC-27); moves the booking to `needs_review` and stops the hold clock (ADR-0006).
- **`payment_records`** — amount due, evidence path, verification result and optional reject reason (ADR-0030 §1).
- **Seller verification** — **Konfirmasi** / **Tolak** inline on F-007's "Perlu tindakan" rows and the booking detail:
  Konfirmasi → `paid`; Tolak → `rejected` (terminal, seats released) with an optional reason (ADR-0030 §2). Both go
  through `BookingService` under the departure → booking lock and are audited.
- **Evidence viewing** — the seller sees the evidence (thumbnail + full) after a policy check (closes F-009 A20); the
  customer sees their own through the booking token (closes F-009 A21); both via a gated stream / signed URL ≤ 5 min.
- **QRIS** — a new `qris` payment-method type carrying an uploaded QR image, added in Pengaturan → Pembayaran and shown
  on the customer screen (ADR-0030 §4).
- **Pengaturan → Pembayaran** — the full payment-instructions settings page (superseding F-001's interim "Rekening"),
  with the **ADR-0018** protections wired: a 24-hour block on payment-instruction changes after a password reset or
  sign-in-email change, and a change notice to the sign-in email (ADR-0030 §6). Closes the "Pilot go" gate (F-001 Q16).
- **`needs_review` / `paid` / `rejected` status content** on the customer booking page (F-005 AC-32 hand-off).
- F-009 hand-offs: **A20 / A21** (gated evidence), **R2** (ban the signed upload route), **R19** (redact presigned
  URL parameters).

**Out (explicitly):**
- **Cash / pay-on-arrival** (P1, ADR-0030 §4 — needs its own hold model; sellers arrange cash over WhatsApp for now).
- **Deposits / DP 50%** (P1; the mockup's "DP 50%" is deferred — Q3).
- Payment-provider integrations / payment links (P1, ADR-0003).
- Refunds of any kind — money is settled seller ↔ customer outside Rakyt (ADR-0003); a rejected or cancelled paid
  booking shows the "pengembalian dana di luar Rakyt" note (F-007 AC-28), nothing more.
- Re-upload after rejection and any "fresh clock" (ADR-0030 §2: `rejected` is terminal).
- Automated messages to customer or seller on upload, confirm or reject (P1 automated notifications; ADR-0030 §5).
- `completed` ("Selesai") — not set in P0 (F-007 Q5).
- Editing a customer's uploaded evidence, multiple evidences per booking, or a payment history timeline.

## User stories

- As a customer, I want to see exactly which account to transfer to and copy the number and the amount, so that I don't
  mistype and lose my money.
- As a customer, I want to upload a screenshot of my transfer and see my booking move to "sedang dicek", so that my
  seat is safe while the seller checks.
- As a customer whose proof is rejected, I want to understand why and be able to book again, so that I'm not stuck.
- As a seller, I want each uploaded proof to appear in my tasks with the amount and a tappable image, so that I can
  confirm or reject it in seconds without leaving Hari ini.
- As a seller, I want confirming a payment to mark the seat paid and rejecting it to put the seat back on sale, so that
  my seat counts stay true.
- As a seller, I want my bank details protected for 24 hours after a password reset, so that a hijacker cannot redirect
  my customers' transfers (ADR-0018).
- As a seller, I want customers to pay me directly with no Rakyt cut, so that I keep my margin (ADR-0003).

## Acceptance criteria

UI copy is Bahasa Indonesia from `lang/id/booking.php` (customer page), `lang/id/bookings.php` and
`lang/id/dashboard.php` (seller verification) and `lang/id/payment.php` (new; Pengaturan → Pembayaran) (ADR-0007).
Dates and times are in the organization's time zone (ADR-0014). "Holding statuses" are `awaiting_payment`,
`needs_review`, `paid` (ADR-0026 §2); `rejected` does **not** hold seats (ADR-0030 §2). "Hold active" is
`Booking::holdIsActive(now)` (F-005 AC-27). Every status is shown with the status-set pill: tint + deep text + glyph +
word (ADR-0008, `docs/design` status set). Money is integer rupiah with the F-003 formatter. Test names:
`test_ac_<n>_…`.

### A. Customer payment screen (1d / 2e / 3e)

- [ ] **AC-1 Instructions shown while payable.** Given a booking in `awaiting_payment` with an active hold, when its
  link is opened, then below the F-005 summary the page shows a **"Cara pembayaran"** block listing the organization's
  **active** payment methods in `position` order: for a bank / e-wallet, the provider label ("BCA"), the account number
  and the account holder; for QRIS, the QR image. The amount to pay is the booking's `total_amount` ("Rp2.500.000").
- [ ] **AC-2 Account-holder helper.** Each bank / e-wallet method shows the account holder with the helper
  "Harus sama persis dengan buku tabungan." (`payment.account_holder_hint`). Reuses F-001's copy.
- [ ] **AC-3 Copy buttons.** The account number and the amount each have a copy button (≥ 44 × 44 px, Bahasa
  `aria-label`): tapping copies the digits-only value and shows "Nomor disalin" / "Jumlah disalin". QRIS has no copyable
  number (it is the image).
- [ ] **AC-4 Three-step progress.** The page shows the 3 steps **Transfer → Dicek operator → Lunas** with the current
  step marked by status: `awaiting_payment` → step 1 active; `needs_review` → step 2 active; `paid` → step 3 done.
  `rejected` and `cancelled` do not show the progress (AC-9, AC-10).
- [ ] **AC-5 Gold CTA.** "Unggah bukti transfer" is the one gold button on the page (it replaces the plain F-005 page's
  having none). "Kabari operator via WhatsApp" (F-005 AC-33) stays ink-outline, secondary.
- [ ] **AC-6 No-cut copy.** The block shows "Pembayaran langsung ke :seller. Rakyt tidak memotong biaya transaksi."
  (`payment.direct_notice`) with the seller's name.
- [ ] **AC-7 Seller has no payment method.** Given the seller has no active payment method (every one removed), then
  the page shows "Operator belum memasang instruksi pembayaran. Hubungi via WhatsApp." instead of the accounts and
  hides the upload CTA; "Kabari operator via WhatsApp" stays. The hold still counts down normally.
- [ ] **AC-8 Needs-review content.** Given a booking in `needs_review`, then the pill is "◐ Perlu dicek", the countdown
  is gone and replaced by "Bukti sudah dikirim. Menunggu operator mengecek." (`payment.under_review`), the uploaded
  evidence is shown as a thumbnail the customer can open (AC-19), and the upload CTA is replaced by "Ganti bukti"
  **only while still `needs_review`** — re-uploading replaces the evidence and keeps the booking `needs_review` (it does
  not restart any clock; the hold is already stopped). The instructions stay visible for reference.
- [ ] **AC-9 Paid content.** Given a booking in `paid`, then the pill is "✓ Lunas", step 3 is done, and the page shows
  "Pembayaran lunas. Sampai jumpa di :trip." (`payment.paid`) with the summary; no countdown, no upload, no
  instructions block.
- [ ] **AC-10 Rejected content.** Given a booking in `rejected`, then the pill is "✕ Ditolak", the page shows
  "Bukti transfer ditolak." and, when present, "Alasan: :reason" (the seller's reason), and — while the trip is still
  published — an ink link "Pesan lagi" to the trip page (as F-005 AC-31's expired state). No countdown, no upload, no
  instructions. The seats are already released (AC-15).
- [ ] **AC-11 Cancelled / expired unchanged.** Given `cancelled` (hold expired or seller), the page is exactly F-005
  AC-31 (no payment content added).

### B. Evidence upload

- [ ] **AC-12 Upload.** Given a booking in `awaiting_payment` with an active hold, when the customer submits an image
  (`UploadRules::paymentEvidence()`: JPEG / PNG / WebP / HEIC / HEIF, ≤ 5 MB), then in one transaction (departure lock →
  booking lock, re-reading `holdIsActive`): the file is stored on the private disk under
  `MediaPath::for($organization, 'evidence/'.$booking->public_id, …)`, a `payment_records` row is created (or its
  evidence replaced) with `amount_due = total_amount`, `evidence_uploaded_at = now`, the booking moves to
  `needs_review`, `hold_expires_at` is left as-is but no longer counts (the hold clock is stopped, ADR-0006), and a
  `payment.evidence_uploaded` audit event is written (actor `customer`, booking id, IP; no file bytes). The response
  redirects (303) back to the booking page showing AC-8.
- [ ] **AC-13 Upload after expiry refused.** Given the hold has expired (`holdIsActive(now)` false — whether or not the
  release job has run), when the customer submits evidence, then nothing is stored, no record is created, the booking
  is unchanged, and the page shows AC-11's expired state with "Waktu pembayaran sudah habis." (ADR-0006: no upload after
  expiry). Checked at the second boundary (F-005 AC-25): at `created_at + 1:59:59` the upload succeeds, at `+ 2:00:00`
  it is refused.
- [ ] **AC-14 Upload validation.** A missing file, a non-image, a disallowed type, or a file > 5 MB → the page keeps
  its state and shows the Bahasa error ("Unggah foto/berkas gambar (JPG/PNG), maks. 5 MB.", `payment.errors.evidence`);
  nothing is stored and the booking is unchanged. Upload is rate-limited by `throttle:evidence-upload` (10 per hour per
  token, `config/rakyt.php`); over the limit shows "Terlalu banyak percobaan. Coba lagi dalam n menit." (F-009 A14).
- [ ] **AC-15 Only `awaiting_payment` and `needs_review` accept an upload.** Uploading against a `paid`, `rejected` or
  `cancelled` booking is refused (the booking is unchanged); against `needs_review` it replaces the evidence (AC-8).

### C. Seller verification (on F-007's rows and detail)

- [ ] **AC-16 Konfirmasi / Tolak offered.** Given a booking in `needs_review`, then its "Perlu tindakan" row (F-007
  AC-7) and its booking detail (F-007 AC-23) show the evidence thumbnail and two ink / ink-outline buttons
  **"Konfirmasi"** and **"Tolak"** — never gold ("+ Trip baru" stays Hari ini's one gold button, F-007 AC-5). No other
  status shows them.
- [ ] **AC-17 Konfirmasi → paid.** Given the seller taps "Konfirmasi", then in one transaction (departure lock →
  booking lock, re-reading the status): the booking becomes `paid`, `seats_taken` is **unchanged** (it already held the
  seat), the `payment_records` row gets `verified_by = user`, `verified_at = now`, `decision = confirmed`, and a
  `payment.confirmed` audit event is written (actor seller). The response returns with the toast "Pembayaran
  dikonfirmasi." and the row/detail now shows "✓ Lunas".
- [ ] **AC-18 Tolak → rejected, seats released.** Given the seller taps "Tolak" (optionally typing a reason ≤ 200
  chars), then in one transaction (departure lock → booking lock): the booking becomes `rejected`, `seats_taken` drops
  by the booking's participants (seats back on sale — ADR-0030 §2), the record gets `decision = rejected`,
  `reject_reason`, `verified_by`, `verified_at`, and a `payment.rejected` audit event is written. The response returns
  with "Bukti ditolak. n kursi dibuka lagi." and an ink-outline "Kabari pelanggan via WA" (opens `whatsapp.rejected`:
  "Halo :name, bukti transfer untuk pesanan :reference belum bisa kami terima. Silakan hubungi kami."). `rejected` is
  terminal: there is no re-confirm and no re-open (the customer re-books, AC-10).
- [ ] **AC-19 Evidence viewing is gated.** The evidence image is served only after an authorisation check — the seller
  through `PaymentRecordPolicy` (same organization), the customer through their booking access token — by streaming
  from an authorised controller or a `temporaryUrl()` with TTL ≤ 5 min (`config('rakyt.media.private_url_ttl')`). Given
  seller B, an anonymous visitor with the storage path, or an expired URL → 403/404 (F-009 A20); given the customer's
  own token → served, another booking's token → not (F-009 A21). The storage path is never in props, logs or audit
  events.
- [ ] **AC-20 Verify races the expiry job / a second tab.** Given a `needs_review` booking Konfirmasi/Tolak'd twice, or
  a booking whose status changed between the detail loading and the tap, then exactly one transition happens, one audit
  event is written, seats move at most once, and the second attempt returns the booking's current status with "Pesanan
  ini sudah diproses." (200 after redirect, not an error). A `needs_review` booking is never touched by the F-005
  expiry job (F-005 AC-25), so Konfirmasi/Tolak cannot race it; a seller cancellation vs. verify on the same booking is
  serialised by the booking lock, and the invariant (F-005 AC-23) holds after.

### D. QRIS (Pengaturan → Pembayaran)

- [ ] **AC-21 Add QRIS.** Given a seller in Pengaturan → Pembayaran, when they add a QRIS method and upload a QR image
  (`UploadRules::paymentEvidence()` rules — image ≤ 5 MB), then a `payment_methods` row with `type = qris` and
  `qris_image_path` (private disk) is saved; it needs no account number or holder. The publish checklist counts it as
  an active payment method (`ActivePaymentMethods`, F-003 AC-9).
- [ ] **AC-22 QRIS shown to the customer.** A QRIS method appears on the customer payment screen as its image (gated
  like evidence, AC-19 — the customer with a valid booking token can view it; it is never on a guessable path). No copy
  button. Bank / e-wallet and QRIS methods can coexist and are listed in `position` order (AC-1).
- [ ] **AC-23 QRIS validation.** A QRIS method with no image is refused ("Unggah gambar QRIS."); bank / e-wallet rows
  keep F-001's rules (account number `^[0-9]{5,20}$`, holder 2–100 chars). The `payment_methods` type `CHECK` now
  allows `qris` and the account-number `CHECK` applies only to bank / e-wallet rows.

### E. Payment-instruction change protection (ADR-0018)

- [ ] **AC-24 Change audited and notified.** Given a seller saves Pengaturan → Pembayaran with any change, then one
  `organization.payment_instructions_changed` audit event is written with before/after (already built,
  `PaymentMethodService::sync`) **and** a notice email is sent to the sign-in email ("Instruksi pembayaran usaha Anda
  diubah."). No change → no audit, no email (F-001 F5 behaviour kept).
- [ ] **AC-25 24-hour block after recovery.** Given a password reset or a sign-in-email change in the last 24 hours
  (ADR-0018), when the seller opens Pengaturan → Pembayaran, then the form is read-only with a notice "Demi keamanan,
  instruksi pembayaran terkunci 24 jam setelah perubahan kata sandi atau email. Coba lagi setelah :time."; a POST in
  that window is refused server-side (not only hidden). The window is computed from the stored reset/email-change time
  in the organization's zone.
- [ ] **AC-26 Block lifts.** Past the 24 hours, the form is editable again with no notice; an ordinary password change
  by a signed-in seller who knows the current password does **not** trigger the block (ADR-0018).

### F. Tenancy, lifecycle, logs, design

- [ ] **AC-27 Cross-tenant.** Given sellers A and B (`InteractsWithTenants`, F-009 A9): B's evidence and
  `payment_records` are never visible to A on any seller page, in Perlu tindakan, the detail or search; `POST`
  Konfirmasi/Tolak on B's booking → 404 with B's booking, record and `seats_taken` unchanged and no event; A opening
  B's evidence path → 403/404 (AC-19). `PaymentRecordPolicy` extends `OrganizationOwnedPolicy`; the customer screen
  only ever shows its own booking's seller's methods and evidence.
- [ ] **AC-28 Seat invariant across the new transitions.** After every test that confirms or rejects: for each
  departure `seats_taken` = Σ participants of its bookings in holding statuses (F-005 AC-23). Confirm keeps the seat;
  reject returns it; a `rejected` booking is not counted.
- [ ] **AC-29 Lifecycle guards.** A booking cannot skip states: upload requires `awaiting_payment`/`needs_review`
  (AC-15); Konfirmasi/Tolak require `needs_review` (AC-16, AC-20). The transitions go through `BookingService`
  (`confirmPayment`, `rejectPayment`) under the ADR-0026 §3 lock order — never a direct `seats_taken` or `status`
  update.
- [ ] **AC-30 Logs and redaction.** No log line, error-tracker payload or exception context contains the evidence
  storage path, a presigned URL, or its signature parameters: `X-Amz-Signature`, `X-Amz-Security-Token`,
  `X-Amz-Credential` and the evidence path are redacted (F-009 R19); the booking token stays redacted (F-005 AC-40).
  The upload POST sends `Cache-Control: no-store, private`.
- [ ] **AC-31 R2 — no signed upload route.** The `private` disk is configured `serve => false` (evidence is streamed
  from the authorised controller), and a guard test asserts the app registers no public signed `PUT /storage/{path}`
  upload route and that `temporaryUploadUrl()` is not used (F-009 R2).
- [ ] **AC-32 Phone width and design rules.** The payment screen, the upload control, the QRIS settings and the verify
  buttons work at 360 px without horizontal scroll; tap targets ≥ 44 px; text ≥ 12 px (11 px only for uppercase
  kickers); exactly one gold button per screen ("Unggah bukti transfer" on the customer page; Konfirmasi/Tolak are
  never gold); every status carries its glyph; tints from `--color-status-*-tint`; all copy from `lang/id/*.php`.
  *Device review with F-002/F-003/F-005/F-007's.*

## Data & rules

### Routes

| Method & path | Name | Middleware |
|---|---|---|
| `POST /{organization}/pesanan/{token}/bukti` | `public.booking.evidence` | same group as F-005 `public.booking.show` (session, encrypted payloads, `no-referrer`, `public-organization`), `throttle:evidence-upload` |
| `GET /{organization}/pesanan/{token}/bukti` | `public.booking.evidence.show` | same; streams the customer's own evidence (AC-19) |
| `GET /pesanan/{booking:public_id}/bukti` | `bookings.evidence` | seller group (`auth`, `organization`, `onboarded`); streams evidence after `PaymentRecordPolicy` (AC-19) |
| `POST /pesanan/{booking:public_id}/konfirmasi` | `bookings.confirm` | seller group; Konfirmasi (AC-17) |
| `POST /pesanan/{booking:public_id}/tolak` | `bookings.reject` | seller group; Tolak (AC-18) |
| `GET /pengaturan/pembayaran` | `settings.payment` | seller group; Pengaturan → Pembayaran (supersedes `settings.rekening`) |
| `POST /pengaturan/pembayaran` | `settings.payment.update` | seller group, `throttle:payment_change`; ADR-0018 block (AC-25) |

The customer routes are fallback routes under the F-005 prefix (`pesanan` is reserved, so no collision with
`/{slug}/{trip}`). The seller routes sit inside the existing `auth`/`organization`/`onboarded` group.

### `payment_records` (new, seller-owned; `BelongsToOrganization`, `PaymentRecordPolicy extends OrganizationOwnedPolicy`)

| Column | Type | Rules |
|---|---|---|
| `id` | bigint | Never exposed publicly |
| `organization_id` | FK, not null | Tenant scope (ADR-0004) |
| `public_id` | ULID, unique | For seller URLs if needed |
| `booking_id` | bigint | Composite FK `(organization_id, booking_id)` → `bookings (organization_id, id)`, cascade on delete; `UNIQUE (organization_id, booking_id)` (one record per booking) |
| `amount_due` | bigint | Snapshot of `bookings.total_amount` at upload; `CHECK (amount_due >= 0)` |
| `evidence_path` | string, nullable | Private-disk path; never public |
| `evidence_uploaded_at` | timestamptz, nullable | AC-12 |
| `verified_by` | FK users, nullable | Set on confirm/reject |
| `verified_at` | timestamptz, nullable | Set on confirm/reject |
| `decision` | string(12), nullable | `CHECK IN ('confirmed','rejected')`; set exactly when `verified_at` is set |
| `reject_reason` | string(200), nullable | Only with `decision = 'rejected'` |
| `created_at`, `updated_at` | timestamptz | UTC |

Index: `(organization_id, booking_id)`. Schema and model guards (F-009 A6/A7) must pass.

### `payment_methods` changes (QRIS; ADR-0030 §4)

- Add `type = 'qris'` to the type `CHECK` (`IN ('bank','ewallet','qris')`).
- Add `qris_image_path` (string, nullable, private disk) — set only for `qris` rows.
- `account_number` / `account_holder` become nullable; the account-number `CHECK` (`^[0-9]{5,20}$`) applies only when
  `type <> 'qris'` (rewrite the `CHECK` as `(type = 'qris') OR (account_number ~ '^[0-9]{5,20}$')`).
- `PaymentMethodType` enum gains `Qris`; `PaymentMethodRules` validates a QRIS block (image required, no account
  number/holder). `config('rakyt.payment_providers')` gains a `qris` entry (or QRIS is a type, not a provider — a build
  choice; the spec needs only: a QRIS method carries an image, not a number).

### Statuses and transitions added here (ADR-0026, ADR-0030 §2)

| From | To | By | Seats |
|---|---|---|---|
| `awaiting_payment` | `needs_review` | customer uploads evidence while `holdIsActive` (AC-12) | unchanged; clock stops |
| `needs_review` | `needs_review` | customer replaces evidence (AC-8) | unchanged |
| `needs_review` | `paid` | seller Konfirmasi (AC-17) | unchanged |
| `needs_review` | `rejected` | seller Tolak (AC-18) | **− participants** (seats released) |

Holding statuses stay `awaiting_payment`, `needs_review`, `paid` (ADR-0026 §2); `rejected` is terminal and non-holding
(ADR-0030 §2). All seat-moving transitions go through `BookingService` (`confirmPayment`, `rejectPayment`) under the
departure → booking lock (ADR-0026 §3); evidence upload uses the same lock to re-check `holdIsActive`.

### Evidence storage (ADR-0030 §3; F-009 A20/A21, R2, R19)

- Stored on the private disk (`config('rakyt.media.private_disk')`) under `MediaPath::for($organization, …)`; never a
  guessable or public path. The disk is set `serve => false` (AC-31).
- Served only by an authorised controller stream or `PrivateMedia::temporaryUrl()` (TTL ≤ 5 min) after the seller
  policy (AC-19, A20) or the customer token check (A21).
- QRIS images use the same private + gated path (AC-22).
- Log redaction adds the evidence path and `X-Amz-*` presigned parameters (AC-30, R19).

### Audit (F-009 A10)

- `payment.evidence_uploaded` — actor `customer` (booking id), IP; `after`: `status`. No file bytes, no path.
- `payment.confirmed` — actor seller (user id), IP; `before`/`after`: `status`.
- `payment.rejected` — actor seller; `before`/`after`: `status`, and `reject_reason` in `after`.
- `organization.payment_instructions_changed` — already built (`PaymentMethodService::sync`, before/after methods);
  F-006 adds the email notice (AC-24), not a new event.

### Public fields (customer screen; extends F-005 AC-37's booking-page props)

The booking-page presenter adds, built explicitly (no model `toArray()`): `payment` (`methods[]` with
`type`, `provider_label`, `account_number`, `account_holder`, `qris_image_url` — the gated URL, null unless `qris`),
`amount_due`, `evidence` (`thumb_url`, `uploaded_at` — null until uploaded), `reject_reason` (only on `rejected`),
`progress` (the three steps + current), `urls` (`evidence_upload`, `evidence_show`). A test asserts the exact added
keys and that no storage path, `organization_id`, other seller's method, numeric id or `payment_records.id` leaks.

### Config

`config/rakyt.php`: `evidence_upload` limiter already present (10/60 per token). `media.payment_evidence` already
present (mimetypes incl. HEIC/HEIF, 5 MB). Add `media.payment_change_block_hours = 24` (ADR-0018, AC-25). QRIS image
reuses `media.payment_evidence` size/type rules.

### Edge cases covered

- Seller with no active method (AC-7); seller removes every method while a customer is on the page (shows AC-7 on
  reload).
- Upload at the 2-hour second boundary; upload after the job has/has not run (AC-13).
- Replace evidence while `needs_review` (AC-8, AC-15); upload against paid/rejected/cancelled (AC-15).
- Konfirmasi/Tolak double-tap or second tab (AC-20); verify vs. seller cancellation serialised (AC-20, F-007 AC-31).
- Rejected booking: seats back on sale, customer re-books, no re-confirm (AC-10, AC-18).
- Evidence path guessed, expired signed URL, other-seller or other-token access (AC-19).
- QRIS with no image; bank row with a bad number; QRIS + bank coexisting (AC-21–AC-23).
- Payment-instruction change inside / outside the 24-hour window; ordinary password change (AC-24–AC-26).
- HEIC/HEIF from an iPhone accepted (config); a PDF or a 6 MB image refused (AC-14).
- A paid booking later cancelled by the seller shows the refund-is-outside-Rakyt note (F-007 AC-28) — no refund here.

### Dependencies

- **F-005** (done): the booking page `/{slug}/pesanan/{token}`, `bookings`, `BookingService`, `Booking::holdIsActive`,
  the status pills, the seat invariant, `BuildsBookings` fixtures. F-006 adds the payment content and the
  `needs_review`/`paid`/`rejected` transitions (F-005 AC-32 hand-off).
- **F-007** (done): "Perlu tindakan" rows and the booking detail that host Konfirmasi/Tolak and the evidence thumbnail;
  `SellerBookingPresenter`, `BookingService::cancelBySeller` (the lock pattern to reuse). Until F-006, no booking was
  ever `needs_review`.
- **F-001** (built): `payment_methods`, `PaymentMethodService::sync` (audited), `PaymentMethodRules`,
  `ActivePaymentMethods`, Pengaturan → Rekening (F5) which F-006's Pengaturan → Pembayaran supersedes; the
  account-holder helper copy; the password-reset / email-change times for the ADR-0018 block.
- **F-009** (built): `UploadRules::paymentEvidence()`, `MediaPath`, `PrivateMedia::temporaryUrl()`, the private disk,
  the `evidence_upload` limiter, `AuditLog`, log redaction, `InteractsWithTenants`. F-006 closes **A20/A21** and
  resolves **R2** and **R19**.
- **ADR-0003** (seller-direct), **ADR-0006** (hold / upload-stops-clock), **ADR-0017** (token), **ADR-0018**
  (payment-instruction protection), **ADR-0026** (lifecycle / lock order), **ADR-0030** (this feature's choices,
  accepted 2026-10-20).
- **F-011** (admin overview) maps these statuses later (its open question depends on this feature).

## Test plan

| Area | Tests |
|---|---|
| Customer screen (A) | `tests/Feature/Payments/PaymentScreenTest`: instructions per method type, copy values, 3-step state per status, no-method state, content for `needs_review`/`paid`/`rejected`/`cancelled`, exact added prop keys |
| Upload (B) | `UploadEvidenceTest`: happy path → `needs_review` + record + audit + clock stopped; second-boundary refuse (`travelTo`); validation (type, size, missing); replace while `needs_review`; refused on paid/rejected/cancelled; limiter buckets |
| Verification (C) | `VerifyPaymentTest`: Konfirmasi → `paid` seat unchanged + audit; Tolak → `rejected` seats released + audit + reason; buttons only on `needs_review`; double-tap / second-tab idempotent; gated evidence (seller policy, customer token, expired URL, other seller) |
| QRIS (D) | `QrisMethodTest`: add with image, shown to customer (gated), validation (no image), coexist with bank; publish checklist counts it; `CHECK` allows `qris` and bank-number rule still bites |
| ADR-0018 (E) | `PaymentInstructionBlockTest`: audit + email on change; 24-h block after reset/email change (read-only + server refuse); block lifts; ordinary password change does not block |
| Tenancy / lifecycle / logs (F) | `PaymentTenancyTest` (`InteractsWithTenants`): AC-27 for every route; `PaymentRecordPolicy` 404s; seat invariant after confirm/reject; lifecycle guards; no signed upload route (R2); log capture asserts no path / presigned params (R19) |
| Concurrency | `VerifyConcurrencyTest` (two connections, like F-007's): verify vs. seller cancel on one booking; invariant after |
| JS (Vitest) | `tests/js/payment.test.js`: copy-button values, 3-step mapping per status, upload control states (sending/disabled/error) |
| Manual | 360 px browser check of the payment screen, upload, QRIS settings, verify buttons; real-device review with the other P0 screens; a real HEIC upload from an iPhone |

## Open questions

Resolved 2026-10-20 (Rengga) — see [ADR-0030](../decisions/0030-payment-records-and-verification.md). Defaults are in
the acceptance criteria; each can change before the pilot at the cost of test data only.

- [x] **Q1 Booking status model** (also F-005 Q3). Decided 2026-10-19: one `bookings.status` with the glossary set +
  `cancel_reason`; amounts and evidence in `payment_records`; pending (`awaiting_payment`/`needs_review`) and confirmed
  (`paid`) are UI groupings. ADR-0026 §1–2. — Rengga
- [x] **Q2 Rejected evidence: seats, re-upload, clock** (ADR-0006 / ADR-0026 §2 open item). Decided 2026-10-20:
  `rejected` is **terminal and does not hold seats** — Tolak releases the seats immediately, there is no re-upload and
  no fresh clock; the customer books again. The customer page shows "✕ Ditolak" with the optional reason and
  "Pesan lagi". ADR-0030 §2. — Rengga
- [x] **Q3 DP 50% (deposit).** Decided 2026-10-20: **P1**, not P0 — the mockup's "DP 50%" is deferred; a booking's
  amount due is the full `total_amount`. Consistent with F-005 and F-007 treating deposits as P1. — Rengga
- [x] **Q4 Cash / pay-on-arrival.** Decided 2026-10-20: **P1** — it needs its own hold model (no transfer to wait for,
  so no 2-hour auto-release); sellers arrange cash over WhatsApp for now. ADR-0030 §4. — Rengga
- [x] **Q5 QRIS.** Decided 2026-10-20: **P0, as a seller-uploaded QR image** shown on the customer page (gated like
  evidence); no account number. ADR-0030 §4. — Rengga
- [x] **Q6 Telling the seller about new evidence** (F-005 Q8 hand-off). Decided 2026-10-20: **dashboard only** — the
  upload surfaces in F-007's "Perlu tindakan" and the nav badge; no email or push in P0. ADR-0030 §5. — Rengga
- [x] **Q7 ADR-0018 payment-instruction protection in F-006.** Decided 2026-10-20: F-006 adds Pengaturan → Pembayaran
  and wires the 24-hour block and the change notice (section E), closing the "Pilot go" gate (F-001 Q16). ADR-0030 §6.
  — Rengga

## Changelog

- 2026-10-17 — created
- 2026-10-18 — F-009 slice A hand-offs noted (A20/A21, R1, R2)
- 2026-10-18 — F-009 hand-off R19 noted
- 2026-10-19 — F-005 hand-offs noted: evidence upload, `needs_review` transition and `holdIsActive` rule; ADR-0026 proposed for Q1
- 2026-10-19 — Q1 decided: one booking status column (F-005 Q3, ADR-0026 §1–2)
- 2026-10-19 — F-005 built: booking page keeps the session (encrypted, ADR-0028 §1) noted for the upload route
- 2026-10-19 — F-007 ready: Konfirmasi / Tolak inline on its rows moved here; hand-off noted
- 2026-10-20 — Planner: full acceptance criteria (AC-1…AC-32: customer payment screen, evidence upload, seller
  verification, QRIS, ADR-0018 protection, tenancy/lifecycle/logs/design), `payment_records` table, `payment_methods`
  QRIS changes, transitions, evidence storage, audit, public fields, test plan. Q2 decided (rejected terminal, seats
  released); cash and DP → P1; QRIS → P0 as an image; seller notice → dashboard only; ADR-0018 wired here. ADR-0030
  proposed. Status → `ready`
- 2026-10-20 — ADR-0030 accepted (Rengga)
