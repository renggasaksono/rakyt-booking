---
id: F-007
title: Seller dashboard ("Hari ini") & booking list
status: draft
priority: P0
roadmap: mvp-weeks-2-4
mockups: [1e, 2f]
decisions: ["0004", "0008"]
updated: 2026-10-17
---

# F-007 · Seller dashboard ("Hari ini") & booking list

## Problem

The common tasks — verify payments, watch seats, share the link — must be one screen away, especially on a phone. Not a shrunk ecommerce admin.

## Scope

**In:** nav (Hari ini, Pesanan, Trip & keberangkatan, Pembayaran, Halaman publik, Pengaturan) with counts; "Hari ini" home: greeting + date, "+ Trip baru", **Perlu tindakan** list (payments to check, unpaid with "Ingatkan via WA"), **Pesanan terbaru** with status pills, **Keberangkatan terdekat** with seat bars (lunas / ditahan / kosong) and "Edit cepat", booking link card (Salin, Bagikan); booking list with search and filters (status, trip, departure date); booking detail.

**Out:** reports, exports (P1), staff/roles (P1).

## Acceptance criteria

- [ ] Every status shown with the status-set pill (tint + glyph + word).
- [ ] Seat bar splits paid / held / empty with status colours and text summary ("12 lunas · 2 ditahan · 2 kosong · tutup 21 Okt").
- [ ] Konfirmasi / Tolak work inline from the action list.
- [ ] Booking search by name, phone, reference; filters persist in URL.
- [ ] Mobile layout designed for phone first (mobile "Hari ini" not yet mocked — see open questions).
- [ ] Plan badge ("Paket Gratis · Lihat Pro").

## Open questions

- [ ] Mobile version of 1e needs a mockup.
- [ ] Manual booking entry by seller (for bookings that come in by chat)? Not in blueprint P0 but likely needed.
- [x] Support/admin view for Rakyt staff (blueprint week 4) — separate feature? Yes: F-010–F-015.

## Changelog

- 2026-10-17 — created
- 2026-10-17 — admin view moved to F-010–F-015
