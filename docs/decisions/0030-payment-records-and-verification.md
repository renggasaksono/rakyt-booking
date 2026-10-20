---
id: 0030
title: Payment records, evidence and verification lifecycle
status: accepted
date: 2026-10-20
deciders: [Rengga]
related: [F-006, F-005, F-007, F-001, ADR-0003, ADR-0006, ADR-0017, ADR-0018, ADR-0026]
---

# 0030. Payment records, evidence and verification lifecycle

*Proposed by the planner agent on 2026-10-20 while making F-006 `ready`; accepted by Rengga on 2026-10-20. It completes
the points ADR-0026 §2 and ADR-0006 left to F-006 and records Rengga's F-006 decisions of 2026-10-20 (rejected
handling, cash, QRIS, seller notice).*

## Context

F-005 built `bookings` with one `status` column (ADR-0026) and the `awaiting_payment → needs_review` transition
reserved for F-006. F-001 built `payment_methods` (bank / e-wallet) and audits every change
(`AuditAction::PaymentInstructionsChanged`, ADR-0018). F-009 built the evidence upload primitives
(`UploadRules::paymentEvidence()`, `MediaPath`, `PrivateMedia::temporaryUrl()`, the `evidence-upload` limiter) and
left two review items for the feature that first serves evidence: **R2** (the local `private` disk's `serve => true`
registers a signed `PUT /storage/{path}` upload route) and **R19** (redact S3 presigned-URL parameters from logs).

Four things are undecided and gate F-006's acceptance criteria:

- **What `rejected` means for seats and re-upload.** ADR-0006 and ADR-0026 §2 both left this open: "Whether `rejected`
  keeps holding seats is F-006's to decide."
- **Where the amount, evidence and verification live.** A second status column was ruled out (ADR-0026 §1); they need a
  home.
- **Cash / pay-on-arrival and QRIS**, both named in the F-006 scope, have no flow or storage yet.
- **How the seller learns a customer uploaded evidence** (handed over as F-005 Q8).

## Decision

1. **A `payment_records` row per booking** (seller-owned, `BelongsToOrganization`) holds what `bookings.status` does
   not: `amount_due` (snapshot of `bookings.total_amount` at upload), `evidence_path` (private disk), `evidence_uploaded_at`,
   `verified_by` (user), `verified_at`, `decision` (`confirmed` | `rejected`), `reject_reason` (nullable, ≤ 200 chars).
   One record per booking (`UNIQUE (organization_id, booking_id)`), created when the customer first uploads evidence.
   `bookings.status` stays the single source of truth for the lifecycle and seat accounting (ADR-0026); the record is
   the payment detail behind it. Rakyt still never holds funds (ADR-0003) — there is no "amount received" the platform
   asserts, only the seller's confirmation.

2. **`rejected` is terminal and does not hold seats.** When the seller taps "Tolak", the booking goes
   `needs_review → rejected` in one transaction (departure lock → booking lock, ADR-0026 §3): `seats_taken` drops by the
   booking's participants and the seats are immediately on sale again. There is **no re-upload and no fresh clock** — the
   customer books again from the trip page if they still want to go. The customer page shows "✕ Ditolak" with the
   seller's optional reason and a "Pesan lagi" link (the same shape as the expired state, F-005 AC-31). This resolves
   ADR-0006's open item and completes ADR-0026 §2: **holding statuses stay `awaiting_payment`, `needs_review`, `paid`**;
   `rejected` joins `cancelled` and `completed` as non-holding. The seat invariant (F-005 AC-23) is unchanged.

3. **Evidence is private and gated.** Evidence is stored on the private disk under `MediaPath::for($organization, …)`
   and never served from a guessable path. It is shown only after an authorisation check — the seller through
   `PaymentRecordPolicy` (closes F-009 A20), the customer through their booking access token (closes F-009 A21) — by
   streaming from a controller or a `temporaryUrl()` with TTL ≤ 5 min. To close **F-009 R2**, the `private` disk is set
   `serve => false` (evidence is streamed from the authorised controller) and a guard test bans `temporaryUploadUrl()`;
   **R19** adds `X-Amz-Signature`, `X-Amz-Security-Token` and `X-Amz-Credential` to the redacted log parameters before
   the private disk moves to S3.

4. **QRIS is a payment method with an uploaded QR image; cash is deferred to P1.** `payment_methods` gains a `qris`
   type carrying an uploaded image (`qris_image_path`, private disk, shown on the customer page through the same gated
   path as evidence) instead of an account number. Bank / e-wallet rows are unchanged. **Cash / pay-on-arrival is P1**:
   it needs its own hold semantics (no transfer to wait for, so no 2-hour auto-release) and is not worth the lifecycle
   branch at pilot; sellers who take cash arrange it over WhatsApp for now.

5. **The seller is told through the dashboard only.** An upload moves the booking to `needs_review`, which already
   surfaces in F-007's "Perlu tindakan" and the nav badge. No email or push in P0 (consistent with F-005 Q8 and the
   "no automated notifications" scope); revisit with P1 automated notifications.

6. **Payment-instruction change protection (ADR-0018) ships with F-006.** F-006 adds "Pengaturan → Pembayaran" (the
   full payment-instructions page, superseding F-001's interim "Rekening") and wires the two ADR-0018 protections not
   yet built: payment-instruction changes are blocked for 24 hours after a password reset or a sign-in-email change, and
   every change is notified to the sign-in email. The before/after audit already exists (`PaymentInstructionsChanged`).
   This closes the "Pilot go" gate item (F-001 Q16).

## Consequences

- Good: one row answers "what did they pay and did the seller confirm it?"; the booking lifecycle and seat invariant
  stay exactly as ADR-0026 defined them; evidence is never public; a rejected seat returns to sale at once; the pilot
  payment-security gate (ADR-0018) is specced in one place.
- Bad / costs: a rejected customer has to re-book from scratch (acceptable — a seller rejects a clearly wrong proof, not
  a blurry one they can ask about on WhatsApp); cash sellers wait for P1; QRIS adds a seller image upload and a second
  gated-image path.
- Follow-ups: cash / pay-on-arrival and deposits (DP 50%) are P1; automated customer/seller notifications are P1;
  F-011 (admin overview) maps these statuses for its dashboard (its Q depends on this ADR).

## Alternatives considered

- **Rejected re-opens the hold (fresh 2 h) and allows re-upload** — kinder to honest mistakes, but adds a re-upload
  path and either makes `rejected` a holding status (amending ADR-0026 §2) or hides the "Ditolak" state; Rengga chose
  the terminal, seats-released model (2026-10-20).
- **Rejected keeps holding seats until a new deadline** — bounds nothing without a second clock and blocks a seat on a
  booking the seller has already judged bad.
- **Amount / evidence / verification as extra `bookings` columns** — bloats the booking row with payment detail and
  mixes two concerns; a `payment_records` row keeps `bookings` about the lifecycle.
- **Cash in P0** — one extra AC but a whole second hold model (no auto-release); deferred to P1 (Rengga, 2026-10-20).
- **Evidence on the public disk (like trip photos)** — simpler serving, but customer payment proofs must never be
  guessable; private + gated is required (F-009 A20/A21).
- **Email the seller on every upload** — real-time, but pulls notification plumbing into P0; the dashboard already
  surfaces it (Rengga, 2026-10-20).
