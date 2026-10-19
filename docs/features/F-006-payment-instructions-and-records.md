---
id: F-006
title: Payment instructions & records
status: draft
priority: P0
roadmap: mvp-week-4
mockups: [4e, 1d, 2e, 3e, 2f]
decisions: ["0003", "0006", "0008"]
updated: 2026-10-17
---

# F-006 · Payment instructions & records

## Problem

Customers need to know exactly where to pay; sellers need to see who has paid. Rakyt never touches the money (ADR-0003).

## Scope

**In:** seller payment methods (bank/e-wallet, account number, account holder; QRIS / extra accounts); post-booking payment screen (status, hold countdown, 3-step progress Transfer → Dicek operator → Lunas, copyable account & amount, evidence upload); seller verification (Konfirmasi / Tolak); payment status set; reminder via WA.

**Out:** deposits/remaining balance (P1 — note mockup shows "DP 50%"), payment-provider integrations (P1), refunds handling beyond status.

## Acceptance criteria

- [ ] Account holder name shown to customers; helper: "Harus sama persis dengan buku tabungan."
- [ ] Copy buttons for account number and amount.
- [ ] Evidence upload: image, max 5 MB, stored privately (organization-scoped, signed URLs).
- [ ] After upload, status → `needs_review` and seller is notified.
- [ ] Seller Konfirmasi → `paid`; Tolak → `rejected` with optional reason; both audited.
- [ ] Gold CTA on the payment screen is "Unggah bukti transfer"; WhatsApp is secondary.
- [ ] Copy states: "Pembayaran langsung ke <seller>. Rakyt tidak memotong biaya transaksi."
- [ ] Cash / pay-on-arrival supported as a payment method where seller enables it.

## Open questions

- [x] Map blueprint booking statuses (pending/confirmed/cancelled/completed) to payment statuses (awaiting/needs review/paid/rejected). Decided 2026-10-19 (F-005 Q3, ADR-0026 §1–2): one `bookings.status` column with the glossary set + `cancel_reason`; pending (`awaiting_payment`, `needs_review`) and confirmed (`paid`) are UI groupings; amounts and evidence live in `payment_records`, which has no second booking status. — Rengga
- [ ] After `rejected`, can the customer upload again, and does the 2-hour clock restart or resume? (Upload stops the clock while under review — ADR-0006.)
- [ ] DP 50% appears in mockup 1e/2f — P0 or P1?

**F-009 hand-offs (2026-10-18):** payment evidence uses `UploadRules::paymentEvidence()`, `MediaPath` and the private disk via `PrivateMedia::temporaryUrl()` (≤ 5 min), issued only after the seller policy or the customer token check — that closes **F-009 A20/A21**. Before then, resolve **F-009 R2**: the local private disk's `serve => true` also registers a signed upload route; stream evidence from an authorised controller instead, or ban `temporaryUploadUrl()` with a guard test. `throttle:evidence-upload` needs the route parameter to be named `token` (**R1**). Before the private disk moves to S3, redact presigned URL parameters (`X-Amz-Signature`, `X-Amz-Security-Token`, `X-Amz-Credential`) from logs (**R19**).

**F-005 hand-offs (2026-10-19):** F-005 builds the customer booking page `/{slug}/pesanan/{token}` (reference, status,
hold countdown, summary, "Kabari operator via WhatsApp") with no payment content; this feature adds the instructions,
progress steps, "Unggah bukti transfer" (the page's gold CTA) under the same prefix. The upload must check
`Booking::holdIsActive(now)` under the booking row lock (refuse after expiry even if the release job has not run;
ADR-0006) and moves the booking to `needs_review`, which the F-005 expiry job never releases. Q1 is decided (one `bookings.status` column,
ADR-0026 §1–2); this feature still decides whether `rejected` holds seats, re-upload and
the clock (ADR-0006 "still open"), and how the seller is told about new evidence (F-005 Q8).
*Built 2026-10-19:* the booking page `GET /{slug}/pesanan/{token}` runs **without a session** (ADR-0028 §1, so the
token never lands in the `sessions` table); give the upload POST the session/CSRF group like F-005's form. Use
`BookingService` for every status change that moves seats, and `Booking::holdIsActive()` for the upload check.

## Changelog

- 2026-10-17 — created
- 2026-10-18 — F-009 slice A hand-offs noted (A20/A21, R1, R2)
- 2026-10-18 — F-009 hand-off R19 noted
- 2026-10-19 — F-005 hand-offs noted: evidence upload, `needs_review` transition and `holdIsActive` rule; ADR-0026 proposed for Q1
- 2026-10-19 — Q1 decided: one booking status column (F-005 Q3, ADR-0026 §1–2)
- 2026-10-19 — F-005 built: booking page without a session (ADR-0028 §1) noted for the upload route
