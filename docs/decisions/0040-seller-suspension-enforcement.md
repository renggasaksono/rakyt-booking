---
id: 0040
title: Seller suspension — where it is enforced, and what stays reachable
status: proposed
date: 2026-10-27
deciders: [Rengga]
related: [F-014, F-012, F-015, F-002, F-005, F-006, ADR-0006, ADR-0011, ADR-0026, ADR-0038, ADR-0039]
---

# 0040. Seller suspension — where it is enforced, and what stays reachable

## Context

F-014 lets the platform admin suspend and reactivate a seller organization. The spec fixes the effects (2026-10-23):
a neutral "tidak tersedia" public page (HTTP 200, not 404) through `PublicVisibility::organizationIsPublic()`, no
seller sign-in at all, no new bookings or date requests, unpaid holds expire normally while evidence uploads are
refused, and paid / upcoming bookings are untouched and nobody is notified. It does not fix where each rule lives,
how a suspension racing a booking is ordered, how an already-open seller session ends, or what the customer's own
booking page (`/<slug>/pesanan/<token>`, ADR-0027) does while the seller is suspended.

## Decision

- **Data.** `organizations.status` (ADR-0039) plus `suspended_at`, `suspended_by` (→ `users`, null on delete) and
  `suspended_reason` (≤ 500 characters). A check constraint keeps them in step: `active` has none of the three,
  `suspended` has a time and a reason. All four columns are guarded; only `App\Organizations\OrganizationSuspension`
  writes them.
- **The action.** `OrganizationSuspension::suspend()` / `reactivate()` lock the organization row (`FOR UPDATE`), change
  only that row, and write `organization.suspended` / `organization.reactivated` (actor `platform_admin`, required
  reason, `before`/`after` = the status) in the same transaction (ADR-0038). A no-op (already suspended / already
  active) writes nothing and returns `false`; the controller tells the admin "tidak ada yang berubah". Routes:
  `POST /admin/penjual/{id}/tangguhkan` and `/aktifkan`, behind `auth` + `platform-admin`, reason in `alasan`.
- **Sign-in.** `ResolveCurrentOrganization` never resolves a suspended organization: it signs the member out
  (`logout()` also cycles the remember-me token), invalidates the session and redirects to "Masuk" with a neutral
  notice (JSON callers get 403). Because every seller route runs it, an open session ends on its **next request**,
  whatever the route. The login form uses `Auth::attemptWhen()` so valid credentials of a suspended seller never
  authenticate (no session, no `Login` event, no `last_login_at`); a password reset saves the password but does not
  sign in. `RecordLastLogin` skips suspended sellers, so a remember-me cookie that authenticates for an instant before
  the sign-out is not "activity" (ADR-0039).
- **Public pages.** `ResolvePublicOrganization` finds the organization by slug, then asks
  `PublicVisibility::organizationIsPublic()`. When it is not public, every route of that slug answers with
  `App\PublicPages\UnavailablePage` — `Public/Unavailable`, HTTP 200, `noindex`, no Open Graph, props are fixed copy
  only (the organization is never passed in) — and a non-GET (the booking submit) is redirected to `/<slug>`. Closed
  by default: a new public route is unavailable for a suspended seller unless it is added to
  `ResolvePublicOrganization::isBookingRecord()`. Date requests have no endpoint (F-004 is a WhatsApp handoff on the
  trip page), so hiding the page removes them.
- **The customer's own booking stays reachable.** The token routes — booking page, own evidence, QRIS image and the
  upload — are the `isBookingRecord()` allowlist: "paid booking records stay valid", so a customer keeps their
  reference, status and proof. The page shows a neutral notice instead of the upload button
  (`payment.uploads_paused`), and the upload endpoint refuses before storing anything.
- **Races are decided under the organization lock.** `BookingService::create()` already locks the organization row
  first (ADR-0026 §3); it now refuses when that locked row is suspended (after a replay check, which creates nothing).
  `uploadEvidence()` takes a **shared** lock on the organization row before the departure and booking (same lock
  order) and refuses when suspended. The suspension's `FOR UPDATE` therefore waits for an in-flight booking or upload
  and is seen by every later one; a concurrency test covers both orders.
- **Untouched.** The release-expired-holds job, the departure-closing job, trips, departures, bookings, payment
  records and notifications do not look at the status. Suspension never deletes, cancels or notifies.

## Consequences

- Good: one seam per surface (seller app, public pages, booking transaction); no seller page needs its own check; a
  suspension cannot lose a race with a booking; reactivation is a single row change with nothing to restore.
- Costs: every seller request already loads the membership's organization, so the check is free there; sign-in and
  the `Login` listener add one membership query. The unavailable page is rendered by middleware, before
  `HandleInertiaRequests`, so it carries no shared props (it needs none). A suspended seller's customers still see the
  seller's payment instructions on their booking page while uploads are paused (see F-014 Q1).
- Follow-ups: F-014 Q1 (hide payment instructions while suspended?) is a product call; any new public route must
  decide whether it belongs to `isBookingRecord()`.

## Alternatives considered

- 404 for a suspended slug — rejected by the spec (a neutral state, not "this link never existed").
- Per-controller checks on each public action — open by default: a new action would forget it.
- A separate middleware on the seller route groups — would miss `auth`-only routes (onboarding, Akun, logout) and
  every new group; `ResolveCurrentOrganization` already runs on all of them and never on public pages.
- Hiding the customer's booking page too — loses the record the spec says stays valid; the customer would have no
  reference or proof to take to the seller or the admin.
- Signing a suspended seller in read-only — explicitly out of P0 (spec).
