---
id: F-014
title: Seller account suspension & reactivation
status: ready
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0003", "0004", "0011"]
updated: 2026-10-23
---

# F-014 · Seller account suspension & reactivation

## Problem

If a pilot seller abuses the platform (fraudulent trips, spam) or asks to pause, the product owner must be able to
stop new activity without destroying data or customers' booking records.

## Scope

**In:** admin action to suspend and reactivate an organization, with confirmation and required reason;
organization `status` (`active` / `suspended`) plus who/when/why (`suspended_at`, `suspended_by`, `suspended_reason`,
cleared on reactivation); suspension blocks new bookings and date requests on that seller's public page and blocks the
seller's own sign-in; audit events for both actions.

**Out (explicitly):** deleting organizations, trips or bookings; automatic refunds or customer notifications;
self-service account closure.

## Suspension effects (resolved 2026-10-23, Rengga)

While an organization's `status` is `suspended`:

- **Public page** (`/<slug>`, `/<slug>/<trip>`): `PublicVisibility::organizationIsPublic()` returns `false`; the public
  controller renders a neutral "tidak tersedia" state — HTTP 200, no trips, no booking CTA, no seller profile data —
  **not** a 404. This is the single seam F-002 already routes through (F-002 Q11).
- **Seller sign-in:** blocked entirely. A suspended seller cannot authenticate into the seller app (they see a neutral
  "akun ditangguhkan" message, not their dashboard). No read-only mode in P0.
- **Existing unpaid bookings** (`awaiting_payment` / `needs_review` with a live hold): the release-holds scheduler keeps
  running unchanged, so holds expire and seats free up normally; customers can **no longer upload payment evidence**
  (the upload endpoint rejects while the owning organization is suspended).
- **Existing paid / upcoming bookings:** untouched — never auto-cancelled and never auto-notified. Paid booking records
  stay valid; any communication with customers or the seller is handled by the admin out-of-band. Rakyt only records state.

Reactivation (`status` → `active`, suspension columns cleared) restores all of the above to normal with no data changes.

## Dependencies

F-010 · F-012 · F-015 · F-002 (public page) · F-005 (booking creation).

## Acceptance criteria

- [ ] Suspend and reactivate each require a confirmation step and a reason; both write an audit event (actor, action, organization, reason, timestamp) in the same transaction as the status change.
- [ ] Suspending never deletes or alters trips, departures, bookings or payment records (test compares before/after).
- [ ] While suspended, new bookings and date requests for that organization are rejected server-side.
- [ ] While suspended, the public page returns HTTP 200 with a neutral "tidak tersedia" state via `PublicVisibility::organizationIsPublic()` — no trips, no booking CTA, no profile data, and not a 404 (test).
- [ ] While suspended, the seller owner cannot sign in to the seller app; an authenticated session for a now-suspended org is rejected on its next request (test).
- [ ] While suspended, a customer upload of payment evidence against a booking of that org is rejected, and the release-expired-holds job still expires that org's holds normally (test).
- [ ] Suspension never auto-cancels or auto-notifies paid/upcoming bookings; those records are unchanged (covered by the before/after test).
- [ ] Reactivating sets `status` back to `active`, clears the suspension columns, and restores public page, sign-in and uploads to normal — with no data changes.
- [ ] Only the platform admin can suspend/reactivate (seller and guest attempts → denied; test).

## Open questions

None — all four suspension-effect questions resolved 2026-10-23 (see "Suspension effects" above and the changelog).

## Changelog

- 2026-10-17 — created
- 2026-10-23 — **ready.** Suspension effects decided (Rengga): public page shows a neutral "tidak tersedia" state (not 404) via `PublicVisibility`; seller sign-in blocked entirely (no read-only mode in P0); unpaid holds expire normally while evidence uploads are blocked; paid/upcoming bookings are never auto-cancelled or auto-notified (admin handles comms out-of-band). Added `status` + `suspended_at`/`suspended_by`/`suspended_reason` columns and ACs for each effect.
