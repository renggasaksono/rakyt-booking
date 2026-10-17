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

- [ ] Map blueprint booking statuses (pending/confirmed/cancelled/completed) to payment statuses (awaiting/needs review/paid/rejected). Proposal: booking.status + payment_record.status as two fields.
- [ ] After `rejected`, can the customer upload again, and does the 2-hour clock restart or resume? (Upload stops the clock while under review — ADR-0006.)
- [ ] DP 50% appears in mockup 1e/2f — P0 or P1?

## Changelog

- 2026-10-17 — created
