---
id: F-005
title: Customer booking & seat hold
status: draft
priority: P0
roadmap: mvp-weeks-2-3
mockups: [1b, 1c, 1d]
decisions: ["0005", "0006"]
updated: 2026-10-17
---

# F-005 · Customer booking & seat hold

## Problem

Bookings via chat get lost and seats get double-sold.

## Scope

**In:** booking form (Nama lengkap, Nomor WhatsApp, Jumlah peserta, Catatan opsional); total shown before submit; seat hold policy shown before submit; booking reference; concurrency-safe seat taking; automatic hold expiry.

**Out:** customer accounts, per-participant details, promo codes (P1).

## Acceptance criteria

- [ ] Participant stepper limited by seats left; total = participants × departure price.
- [ ] Hold policy text shown before "Kirim pesanan".
- [ ] Booking reference format `RKB-MMDD-NNNN` (e.g. RKB-1024-0142) — unique per platform. *(Format to confirm.)*
- [ ] Two simultaneous bookings for the last seat: exactly one succeeds; the other gets a clear message.
- [ ] Booking a closed, full, past-deadline or unpublished departure is rejected server-side.
- [ ] Each unpaid booking holds its seats for exactly 2 hours from creation (ADR-0006; not configurable); the hold policy text says "Kursi ditahan 2 jam".
- [ ] Expired holds release seats automatically and the booking becomes cancelled (hold expired).
- [ ] Uploading payment evidence before expiry stops the clock: the booking moves to `needs_review` and is never auto-released while under review.
- [ ] Evidence upload after expiry is refused. There is no seller action to extend a hold.
- [ ] Phone numbers normalised to E.164 (+62…).
- [ ] Basic abuse protection: rate limiting per IP/phone.

## Open questions

- [x] Hold length and whether proof upload freezes it — decided 2026-10-17: 2 h fixed, upload stops the clock, no manual extension (ADR-0006 accepted).
- [ ] Reference format: does "1024" encode departure date (24 Okt) or booking date?

## Changelog

- 2026-10-17 — created
- 2026-10-17 — hold rules from ADR-0006 (accepted) added
