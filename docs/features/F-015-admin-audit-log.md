---
id: F-015
title: Audit logging for admin actions
status: done
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0011", "0038"]
updated: 2026-10-26
---

# F-015 · Audit logging for admin actions

## Problem

An admin who can see and suspend every seller must leave a trail: what was done, to whom, when and why.

## Scope

**In:** reuse F-009's `audit_events` (no second log) for admin actions; record actor (platform admin user), action,
target type/id, organization (if any), timestamp and relevant before/after or reason; admin view of audit events
filterable by organization, actor and action; organization detail (F-012) and booking detail (F-013) show related events.

Admin actions audited in the MVP: seller suspension, seller reactivation, platform-admin flag grant/revoke. Any
sensitive admin change added later must be audited too.

**Out (explicitly):** logging page views or searches; editing or deleting audit events; SIEM/export integrations;
**surfacing admin actions in the seller's own audit history** — admin events are platform-internal and never shown in
the seller app in P0 (resolved 2026-10-23). A suspended seller is informed out-of-band, not via an in-app notice.

## Dependencies

F-009 (`audit_events` table and writer) · F-010.

## Acceptance criteria

- [x] **AC-1** Each audited action writes exactly one event in the same transaction as the change. *(`admin:grant` / `admin:revoke` write one event each and nothing on a no-op revoke; a failing audit write rolls the grant back — `AdminAuditRecordingTest::test_ac_1_*`. F-014 suspension/reactivation get their own test when built; the write-route registry test forces it.)*
- [x] **AC-2** Events record actor, action, target, timestamp and reason/change details. *(`Actor::platformAdmin` + `AuditLog::record(…, reason:)` → `audit_events.reason`; the view shows actor (type, id, name, IP), action, organization, target, WIB timestamp, reason and the field diff — `AdminAuditRecordingTest::test_ac_2_*`, `AdminAuditLogTest::test_ac_2_*`.)*
- [x] **AC-3** Events never contain passwords, OTP codes, 2FA secrets, tokens or payment evidence contents; customer data limited to IDs. *(`AuditRedaction` strips them at any depth before the write and again before display, even for a row written around `AuditLog`; the grant event never holds the TOTP secret — `test_ac_3_*`.)*
- [x] **AC-4** Audit events are append-only through the app (no update/delete routes); tested. *(Every route touching the audit log is GET-only; POST/PUT/PATCH/DELETE on `/admin/audit[/{id}]` are 404/405; the model refuses update/delete of admin events (and the F-009 DB trigger backs it) — `AdminAuditLogTest::test_ac_4_*`.)*
- [x] **AC-5** Admin audit view is behind the F-010 gate; sellers cannot read admin events (test). *(Guest → sign-in, seller → 403 (also with filters naming their own organization), admin without TOTP → challenge, admin → page; `AuditEventPolicy` denies sellers — `test_ac_5_*` plus `AdminAccessControlTest`'s every-admin-route iteration.)*
- [x] **AC-6** Admin events are not exposed in any seller-facing audit view or page in P0 (test, if a seller audit view exists). *(There is no seller audit view; every parameterless seller page, the booking detail and the public pages render without an admin event's reason or diff, and a guard test lists the only non-admin readers of `audit_events` — `AdminEventsHiddenFromSellersTest`.)*

## Open questions

None — seller visibility resolved 2026-10-23 (see changelog).

## Implementation notes

- **View:** `/admin/audit` (`admin.audit.index`, nav "Log audit") → `AdminAuditController` → `pages/Admin/AuditLog.vue`.
  Newest first, 25 per page, filters in the URL: `organisasi` (an organization id or `platform` for org-less events),
  `aktor` (actor type), `aktor_id` (only with `aktor`), `aksi` (an `AuditAction` value), `halaman`. Unknown values are
  dropped (`App\Admin\AuditEventFilters`). An event's organization and actor are links that filter by them.
  `Cache-Control: no-store, private`.
- **Reads:** cross-organization only in `App\Admin\Queries\AuditEventQueries` — `search()` for the view,
  `forOrganization()` for F-012's organization detail and `forSubject()` for F-013's booking detail (both tested; the
  pages themselves come with F-012 / F-013 and render rows with `App\Admin\AuditEventPresenter`).
- **Writes:** admin actions call `AuditLog::record($action, $subject, $before, $after, Actor::platformAdmin($admin),
  reason: $reason)` inside the change's transaction. `reason` is a column (≤ 500 chars, trimmed, blank → null), not a
  `before`/`after` key (ADR-0038).
- **Redaction:** `App\Audit\AuditRedaction` — passwords, tokens, secrets, remember tokens, OTP / TOTP / recovery codes,
  payment evidence, `customer_*` (except `customer_id`) and `notes`, at any depth.
- **New admin write routes** must be added to `AdminAuditRecordingTest::ADMIN_WRITE_ROUTES` with the audit action they
  write (F-014 adds suspend / reactivate there).
- Labels for every `AuditAction`, actor type and subject type live in `lang/id/admin.php` → `audit` (a test checks
  every action).

## Changelog

- 2026-10-17 — created
- 2026-10-23 — **ready.** Sellers do **not** see admin actions on their org in P0 (Rengga); admin audit events stay platform-internal. Moved to scope "Out" with an AC.
- 2026-10-26 — **built; status → done.** Admin audit view `/admin/audit` (filters: organization incl. platform-level, actor, action), `audit_events.reason` column, shared `AuditRedaction` (writer + view), `AuditEventQueries::forOrganization/forSubject` for F-012/F-013, write-route registry test. AC-1…AC-6 numbered and covered by tests. ADR-0038 proposed.
- 2026-10-26 — ADR-0038 accepted (Rengga).
