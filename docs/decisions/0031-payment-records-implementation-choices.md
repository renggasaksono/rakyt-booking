---
id: 0031
title: Payment-records implementation choices (F-006)
status: accepted
date: 2026-10-20
deciders: [builder agent]
related: [F-006, ADR-0030, ADR-0026, ADR-0018, ADR-0003, F-001, F-005, F-007, F-009]
---

# 0031. Payment-records implementation choices (F-006)

## Context

Building F-006 against the accepted ADR-0030 left a handful of routine technical
calls the spec deliberately did not pin down (it marked some as "a build
choice"). Recording them so a later reader is not left guessing, in the shape of
F-005/F-007's ADR-0028/0029.

## Decision

1. **Evidence (and QRIS) are served by streaming from an authorised controller,
   not a signed URL.** The private disk is `serve => false` (closing F-009 R2 /
   AC-31), so Laravel's local driver cannot mint a `temporaryUrl`; instead the
   seller (`bookings.evidence`, `settings.payment.qris`) and customer
   (`public.booking.evidence.show`, `public.booking.qris`) routes authorise, then
   stream through `PrivateMedia::response()` with `Cache-Control: no-store`.
   `temporaryUrl()` stays for a future S3 move; a guard test bans
   `temporaryUploadUrl()`. The F-009 A20 primitive test was updated to this model.

2. **The verify audit action is `payment.confirmed`.** The F-009 scaffold enum
   had `PaymentVerified = 'payment.verified'`; it was renamed to
   `PaymentConfirmed = 'payment.confirmed'` to match the spec's AC-17 wording
   (the scaffold case was never used). `payment.rejected` and
   `payment.evidence_uploaded` already matched.

3. **The ADR-0018 24-hour block is read from the audit log, not a new column.**
   `PaymentInstructionLock` checks for a recent `auth.password_reset`,
   `auth.email_changed` or `auth.account_recovered` event (written in the same
   transaction as the change) for the current organization. An ordinary
   `auth.password_changed` is excluded, so a signed-in password change does not
   block (AC-26). No auth flow needed a new timestamp column.

4. **QRIS is a `payment_methods` type with one row per organization.** Pengaturan
   → Pembayaran manages a single QRIS image (`PaymentMethodService::saveInstructions`
   keeps bank/e-wallet sync separate from QRIS so onboarding's `sync()` never
   touches it). The audit before/after snapshots a QRIS row by a hash of its
   image path, never the path itself (AC-19). At least one payment method (a bank
   / e-wallet account or QRIS) must remain on the settings page.

5. **UI copy placement.** Customer payment-screen copy lives in
   `lang/id/booking.php → payment` (the page already receives `__('booking')`);
   seller verification copy in `lang/id/bookings.php → show`; the settings page
   in a new `lang/id/payment.php`. This matches the spec's AC header split.

6. **Pengaturan → Rekening is replaced, not kept.** The route becomes
   `GET/POST /pengaturan/pembayaran` (`settings.payment[.update]`), a multipart
   POST so it can carry the QRIS image; the `Settings/PaymentMethods.vue` page and
   the `payment-methods.*` route names are gone, with their references updated.

## Consequences

- Good: one serving path works for local dev and S3; the booking lifecycle and
  seat invariant are untouched (ADR-0026); the ADR-0018 block adds no schema and
  cannot be bypassed from the client; QRIS reuses the evidence storage + gating.
- Bad / costs: streaming evidence through the app (rather than a presigned URL)
  costs a little bandwidth on S3 later — revisit if it matters. Reading the block
  from the audit log couples the control to the audit write staying in-transaction
  (it is, by `AuditLog`'s own guard).
- Follow-ups: cash / pay-on-arrival and DP 50% remain P1 (ADR-0030); automated
  customer/seller notifications remain P1; F-011 maps these statuses.

## Alternatives considered

- **Signed `temporaryUrl` for evidence** — simplest serving, but the local disk's
  `serve => true` also registers a public signed `PUT /storage/{path}` upload
  route (F-009 R2); streaming from a controller avoids it.
- **A `payment_instructions_locked_until` column** — more explicit than reading
  the audit log, but needs every recovery/email-change flow to set it; the audit
  events already carry the time, in-transaction.
- **QRIS as a separate table / a config "provider"** — more moving parts than a
  `qris` row on `payment_methods`, which already has tenancy, a policy and the
  publish-checklist seam (`ActivePaymentMethods`).
