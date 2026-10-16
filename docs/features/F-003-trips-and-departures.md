---
id: F-003
title: Trips & departures management
status: draft
priority: P0
roadmap: mvp-weeks-2-3
mockups: [1f, 1g, 3a, 3b]
decisions: ["0005", "0006"]
updated: 2026-10-17
---

# F-003 · Trips & departures management

## Problem

Operators change prices, dates and seats often, usually on their phone. Target: a simple price/capacity update in **under 30 seconds**.

## Scope

**In:** create/edit trip (photos, name, duration, short description, inclusions/exclusions, meeting point & schedule, terms, base price, booking mode); departures table (dates, seats, price override, booking deadline, status); "Salin dari tanggal sebelumnya"; publish checklist + live preview (1f); mobile quick edit sheet (1g); draft autosave.

**Out:** recurring departure templates (P1), add-ons/packages (P1), dynamic pricing (P2).

## Acceptance criteria

- [ ] Duration picked from presets (Beberapa jam, 1 hari, 2H1M, 3H2M, 4H3M) or "Atur sendiri" (days/nights steppers, nights ≤ days). End date derived from start + duration.
- [ ] Booking deadline picked from presets (Sampai hari keberangkatan, H-1, H-3, H-7, custom date & time), shown as a real date ("Ditutup Senin, 21 Okt pukul 23.59"). Default from org settings. Cannot be after meeting time or in the past.
- [ ] Empty price on a departure = base price.
- [ ] Publish checklist: cover photo, name & description, base price, ≥1 departure (scheduled mode), payment instructions.
- [ ] Quick edit: price, total seats, open/closed in one sheet. Seats cannot go below seats taken ("14 terisi — tidak bisa di bawah 14"). New price applies to new bookings only — say so in the sheet.
- [ ] Every price/capacity/status change writes an audit event.
- [ ] Departure auto-closes at its deadline.

## Open questions

- [ ] Photo limits (count, size) and storage per plan.
- [ ] Can a departure with bookings be deleted, or only closed/cancelled?

## Changelog

- 2026-10-17 — created
