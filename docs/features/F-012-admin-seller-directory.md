---
id: F-012
title: Admin seller directory & organization details
status: ready
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0004", "0011"]
updated: 2026-10-27
---

# F-012 · Admin seller directory & organization details

## Problem

To onboard and support pilot sellers, the product owner must find a seller and see what they have set up and sold.

## Scope

**In:** searchable list of organizations (name, slug, WhatsApp, city); organization detail: profile, owner/member
users, account status (active/suspended), plan, registration date, last activity (= the owner's last successful
sign-in); read-only lists of the organization's trips, departures (seats paid/held/empty) and recent bookings; entry
point for suspend/reactivate (F-014).

"Last activity" is defined as the owner's **last successful sign-in**, stored as a nullable `users.last_login_at`
updated on each successful login (added here or by F-010; null until the seller first signs in). It answers "is this
seller still using Rakyt?" with no per-feature activity plumbing. A suspended seller cannot sign in (F-014), so their
`last_login_at` stays frozen at the pre-suspension value — correct for the directory.

**Out (explicitly):** editing seller profiles, trips, departures or payment methods; impersonation; exports.

## Dependencies

F-010 · F-001 · F-003 · F-005 · F-006 · F-014 (actions) · F-015 (audit trail on the detail page).

## Acceptance criteria

- [ ] Search by organization name, slug or owner phone number; paginated.
- [ ] Filter by account status (active / suspended).
- [ ] Detail page shows profile, status, plan, registration date and last activity (owner's `last_login_at`; "Belum pernah masuk" when null), plus trips, departures and recent bookings — all read-only.
- [ ] Seat summary per departure uses the same counts as the seller dashboard (F-007).
- [ ] Detail page lists the organization's recent audit events (newest first) via `AuditEventQueries::forOrganization()`, rendered with `AuditEventPresenter` — admin only; a seller session never sees them (F-015 AC-6).
- [ ] Cross-organization reads only through admin query classes; a seller session cannot reach these pages or their JSON (test).
- [ ] Payment evidence opens only through a signed, short-lived URL issued to the admin (same private storage as F-006).
- [ ] Usable at phone width.

## Open questions

None — "last activity" resolved 2026-10-23 (see changelog).

## Changelog

- 2026-10-17 — created
- 2026-10-23 — **ready.** "Last activity" = owner's last successful sign-in, via a new nullable `users.last_login_at` (Rengga, recommended default). Null shown as "Belum pernah masuk".
- 2026-10-27 — Added AC: recent audit events on the detail page via `AuditEventQueries::forOrganization()` + `AuditEventPresenter` (F-015 review follow-up). The audit view's organization dropdown becomes a searchable picker together with this feature's search.
