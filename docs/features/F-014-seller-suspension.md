---
id: F-014
title: Seller account suspension & reactivation
status: done
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0003", "0004", "0011", "0038", "0039", "0040"]
updated: 2026-10-27
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

Tests: `tests/Feature/Admin/SellerSuspensionTest.php` (admin), `tests/Feature/Auth/SuspendedSellerSignInTest.php`
(sign-in), `tests/Feature/PublicPages/SuspendedSellerPublicTest.php` (customers), `tests/Feature/Bookings/SuspensionConcurrencyTest.php`
(races), `tests/Feature/Database/CheckConstraintsTest.php` (`organizations_suspension_check`).

- [x] AC-1 Suspend and reactivate each require a confirmation step and a reason; both write an audit event (actor, action, organization, reason, timestamp) in the same transaction as the status change.
- [x] AC-2 Suspending never deletes or alters trips, departures, bookings or payment records (test compares before/after).
- [x] AC-3 While suspended, new bookings and date requests for that organization are rejected server-side.
- [x] AC-4 While suspended, the public page returns HTTP 200 with a neutral "tidak tersedia" state via `PublicVisibility::organizationIsPublic()` — no trips, no booking CTA, no profile data, and not a 404 (test).
- [x] AC-5 While suspended, the seller owner cannot sign in to the seller app; an authenticated session for a now-suspended org is rejected on its next request (test).
- [x] AC-6 While suspended, a customer upload of payment evidence against a booking of that org is rejected, and the release-expired-holds job still expires that org's holds normally (test).
- [x] AC-7 Suspension never auto-cancels or auto-notifies paid/upcoming bookings; those records are unchanged (covered by the before/after test).
- [x] AC-8 Reactivating sets `status` back to `active`, clears the suspension columns, and restores public page, sign-in and uploads to normal — with no data changes.
- [x] AC-9 Only the platform admin can suspend/reactivate (seller and guest attempts → denied; test).

## Open questions

- **Q1 — Payment instructions on a suspended seller's booking page.** While suspended, a customer's own booking page
  (`/<slug>/pesanan/<token>`) stays reachable (the record stays valid) and the evidence upload is replaced by a
  neutral notice — but the seller's bank / e-wallet / QRIS instructions are still shown for an `awaiting_payment`
  booking whose hold has not expired. Should they be hidden while suspended (a fraud suspension would otherwise still
  invite a transfer the customer cannot prove)? *Default in use:* shown (the spec only blocks uploads). Product call
  for Rengga; a one-line change in `BookingPresenter::page()` / `Public/Booking.vue` either way.

## Changelog

- 2026-10-17 — created
- 2026-10-23 — **ready.** Suspension effects decided (Rengga): public page shows a neutral "tidak tersedia" state (not 404) via `PublicVisibility`; seller sign-in blocked entirely (no read-only mode in P0); unpaid holds expire normally while evidence uploads are blocked; paid/upcoming bookings are never auto-cancelled or auto-notified (admin handles comms out-of-band). Added `status` + `suspended_at`/`suspended_by`/`suspended_reason` columns and ACs for each effect.
- 2026-10-27 — F-012 added `organizations.status` (`active` default / `suspended`, check constraint, cast to
  `App\Organizations\OrganizationStatus`, guarded — set with `forceFill`) for its status filter (ADR-0039). F-014
  adds `suspended_at` / `suspended_by` / `suspended_reason` and the actions on the seller detail page's "Akun"
  section (`resources/js/pages/Admin/Sellers/Show.vue`, `/admin/penjual/{id}`).
- 2026-10-27 — **built; status → done** (`feat/F-014-seller-suspension`). New `suspended_at` / `suspended_by` /
  `suspended_reason` on `organizations` (check constraint ties them to `status`; all guarded) and
  `App\Organizations\OrganizationSuspension` (locks the organization row, changes only it, audits
  `organization.suspended` / `organization.reactivated` with actor `platform_admin` and the required reason in the
  same transaction; a repeat is a no-op without an event). Admin: `POST /admin/penjual/{id}/tangguhkan` and
  `/aktifkan` (`alasan` required, ≤ 500), a confirmation sheet on the detail page's "Akun" section listing the effects,
  and who / when / why shown while suspended; listed in F-015's `ADMIN_WRITE_ROUTES`. Seller: `ResolveCurrentOrganization`
  signs a suspended seller out on any request (remember-me token cycled; JSON → 403); "Masuk" refuses valid
  credentials via `Auth::attemptWhen()` with a neutral notice; a password reset no longer signs them in; no
  `last_login_at`. Public: `PublicVisibility::organizationIsPublic()` is false while suspended and every route of the
  slug renders `Public/Unavailable` (200, `noindex`, fixed copy only) or redirects a submit to it; only the customer's
  token routes stay reachable, where the upload is refused (in the controller and again under a shared organization
  lock in `BookingService`) and the page shows `payment.uploads_paused`. `BookingService::create()` refuses under the
  organization lock, so a booking racing the suspension never slips through (concurrency test). Release-holds job
  unchanged. ADR-0040 proposed. Q1 (payment instructions while suspended) added.
