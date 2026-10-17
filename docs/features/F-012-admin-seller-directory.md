---
id: F-012
title: Admin seller directory & organization details
status: draft
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0004", "0011"]
updated: 2026-10-17
---

# F-012 · Admin seller directory & organization details

## Problem

To onboard and support pilot sellers, the product owner must find a seller and see what they have set up and sold.

## Scope

**In:** searchable list of organizations (name, slug, WhatsApp, city); organization detail: profile, owner/member
users, account status (active/suspended), plan, registration date, last activity where recorded; read-only lists of
the organization's trips, departures (seats paid/held/empty) and recent bookings; entry point for suspend/reactivate (F-014).

**Out (explicitly):** editing seller profiles, trips, departures or payment methods; impersonation; exports.

## Dependencies

F-010 · F-001 · F-003 · F-005 · F-006 · F-014 (actions) · F-015 (audit trail on the detail page).

## Acceptance criteria

- [ ] Search by organization name, slug or owner phone number; paginated.
- [ ] Filter by account status (active / suspended).
- [ ] Detail page shows profile, status, plan, registration date and last activity (if available), plus trips, departures and recent bookings — all read-only.
- [ ] Seat summary per departure uses the same counts as the seller dashboard (F-007).
- [ ] Cross-organization reads only through admin query classes; a seller session cannot reach these pages or their JSON (test).
- [ ] Payment evidence opens only through a signed, short-lived URL issued to the admin (same private storage as F-006).
- [ ] Usable at phone width.

## Open questions

- [ ] What "last activity" means (last sign-in, last trip edit, last booking) and whether it needs a new column — Rengga

## Changelog

- 2026-10-17 — created
