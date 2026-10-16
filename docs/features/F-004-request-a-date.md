---
id: F-004
title: Request-a-date mode
status: draft
priority: P0
roadmap: mvp-weeks-2-3
mockups: [1a, 1b, 1e, 4c]
decisions: ["0005"]
updated: 2026-10-17
---

# F-004 · Request-a-date mode

## Problem

Private tours and some day trips have no fixed schedule; the operator confirms availability manually.

## Scope

**In:** trips with booking mode `request_date` ("Pelanggan ajukan tanggal"); customers submit preferred date + participants + contact; seller sees "◐ Permintaan tanggal" with a "Balas" action; seller accepts (creates a departure/booking at a price) or declines. Scheduled trips also offer "Tanggal lain? Ajukan tanggal ke operator".

**Out:** automatic availability calendars.

## Acceptance criteria

- [ ] Request form uses the same 4 fields as booking (F-005) plus preferred date.
- [ ] Request appears in "Pesanan terbaru" and the booking list with its own status.
- [ ] Accepting a request produces a booking in `awaiting_payment` with payment instructions (F-006).
- [ ] "Balas" opens WhatsApp with a prefilled message (F-008).

## Open questions

- [ ] Does accepting create a public departure (others can join) or a private one?
- [ ] Price on accept: base price, or seller enters a quote?

## Changelog

- 2026-10-17 — created
