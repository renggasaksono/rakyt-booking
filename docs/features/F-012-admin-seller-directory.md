---
id: F-012
title: Admin seller directory & organization details
status: done
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0004", "0011", "0038", "0039"]
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

- [x] AC-1 Search by organization name, slug or owner phone number; paginated.
- [x] AC-2 Filter by account status (active / suspended).
- [x] AC-3 Detail page shows profile, status, plan, registration date and last activity (owner's `last_login_at`; "Belum pernah masuk" when null), plus trips, departures and recent bookings — all read-only.
- [x] AC-4 Seat summary per departure uses the same counts as the seller dashboard (F-007).
- [x] AC-5 Detail page lists the organization's recent audit events (newest first) via `AuditEventQueries::forOrganization()`, rendered with `AuditEventPresenter` — admin only; a seller session never sees them (F-015 AC-6).
- [x] AC-6 Cross-organization reads only through admin query classes; a seller session cannot reach these pages or their JSON (test).
- [x] AC-7 Payment evidence opens only through a signed, short-lived URL issued to the admin (same private storage as F-006).
- [x] AC-8 Usable at phone width. (Built phone-first and checked statically; real-device review with the other admin screens.)

Tests: `tests/Feature/Admin/AdminSellerDirectoryTest.php` (AC-1, AC-2, AC-6, AC-8, organization lookup),
`tests/Feature/Admin/AdminSellerDetailTest.php` (AC-3–AC-8), `tests/Feature/Auth/LastLoginTest.php` (last activity),
`tests/js/adminSellers.test.js`; scope-bypass guard `CrossOrganizationAccessGuardTest` (AC-6).

## Open questions

None — "last activity" resolved 2026-10-23 (see changelog).

## Changelog

- 2026-10-17 — created
- 2026-10-23 — **ready.** "Last activity" = owner's last successful sign-in, via a new nullable `users.last_login_at` (Rengga, recommended default). Null shown as "Belum pernah masuk".
- 2026-10-27 — Added AC: recent audit events on the detail page via `AuditEventQueries::forOrganization()` + `AuditEventPresenter` (F-015 review follow-up). The audit view's organization dropdown becomes a searchable picker together with this feature's search.
- 2026-10-27 — **built; status → done** (`feat/F-012-admin-seller-directory`). `/admin/penjual` (search `cari` by
  name / slug / WhatsApp in any format, `status` filter, 25 per page, newest registration first) and
  `/admin/penjual/{id}` (profile, account status, plan, registration, last activity, members, trips ≤ 50, upcoming
  departures ≤ 20 with `SeatBreakdown` — the seat sums now shared with "Hari ini" — the 10 newest bookings and 20
  newest audit events). Reads in `App\Admin\Queries\SellerDirectoryQueries`, one organization per query. Evidence via
  `admin.sellers.evidence` → a ≤ 5-minute signed `admin.sellers.evidence.show`. New `users.last_login_at` (stamped
  on the `Login` event) and `organizations.status` (`active` / `suspended`; F-014 adds the actions and the
  who/when/why columns). "Penjual" in the admin nav. The audit view's organization filter is now a searchable picker
  over `GET /admin/penjual/cari`. No write routes; F-014's suspend / reactivate hangs on the detail page's "Akun"
  section. ADR-0039 proposed. AC-8 real-device review pending with the other admin screens.
- 2026-10-27 — Review fixes (PR #43): the trips' departure count names the organization, so it uses the
  `(organization_id, trip_id, start_date)` index instead of scanning every seller's departures (query-shape test);
  `last_login_at` tests now cover sign-up, password reset and remember-me as well as the login form — all stamp it,
  as ADR-0039 states.
- 2026-10-27 — ADR-0039 accepted (Rengga).
