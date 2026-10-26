---
id: 0038
title: Admin audit trail — reason column, shared redaction, admin-only reads
status: accepted
date: 2026-10-26
deciders: [Rengga]
related: [F-015, F-014, F-012, F-013, F-009, F-010, ADR-0011]
---

# 0038. Admin audit trail — reason column, shared redaction, admin-only reads

## Context

F-015 requires every sensitive admin action to leave a trail in F-009's `audit_events` (no second log): actor,
action, target, organization, timestamp and the before/after or **reason**. F-014's suspension and reactivation
require a reason. Admin events are platform-internal: sellers must never see them (F-015, resolved 2026-10-23), yet
an admin event about a seller carries that seller's `organization_id`, so a seller-scoped `AuditEvent` query would
find it. The admin also needs to read events across organizations without weakening the tenant scope (ADR-0011).

## Decision

- **Reason is its own column.** `audit_events.reason` (nullable, max 500 characters), written through
  `AuditLog::record(…, reason:)`, trimmed, blank stored as null. Not a key inside `before`/`after`, so those stay a
  diff of the record's fields. Null for every seller and system event.
- **Admin actor.** An admin action over HTTP is recorded with `Actor::platformAdmin($admin)` (type
  `platform_admin`, the admin's user id, request IP). The `admin:grant` / `admin:revoke` commands keep
  `Actor::system('admin:…')` — there is no signed-in admin at the console.
- **One redaction rule, applied twice.** `App\Audit\AuditRedaction` drops passwords, tokens, secrets, remember
  tokens, OTP/TOTP and recovery codes, payment evidence and customer details beyond IDs (`customer_*` except
  `customer_id`, `notes`) at any depth. `AuditLog` applies it before writing; the admin view applies it again before
  display, so even a row written around `AuditLog` shows no secret.
- **Reads across organizations only in `app/Admin/Queries/AuditEventQueries`.** It serves the admin audit view
  (`/admin/audit`) and the related-events lists of F-012 (`forOrganization`) and F-013 (`forSubject`).
- **Sellers never read audit events.** No seller-facing controller, presenter or page reads `audit_events`; a guard
  test lists the only non-admin readers (the model and ADR-0018's `PaymentInstructionLock`, which reads the seller's
  own auth events by action). A future seller history view (P1, F-009 Q7) must exclude `actor_type =
  platform_admin` and get a new decision before it ships.
- **Every admin write route is reviewed for auditing.** A test lists each non-GET `admin.*` route with the audit
  action it writes; a new route fails the suite until it is added there with its audit test.

## Consequences

- Good: F-014 records suspension/reactivation reasons without a schema change; one redaction rule for writer and
  reader; the admin/seller boundary for audit data is enforced by tests, not by convention.
- Costs: one more column on an append-only table (DDL only; rows stay immutable). The redaction rule is key-based —
  a secret stored under an innocuous key is not caught; code review still matters.
- Follow-ups: F-014 adds `organization.suspended` / `organization.reactivated` actions (organization-scoped, actor
  `platform_admin`, required reason) and lists its routes in the write-route test; F-012 / F-013 render
  `forOrganization` / `forSubject` with `AuditEventPresenter`.

## Alternatives considered

- Reason inside `after` (e.g. `after.reason`) — no migration, but mixes intent with the field diff and every reader
  must know the convention.
- A separate `admin_audit_events` table — rejected by F-015 scope ("no second log"), and splits the trail an admin
  investigates.
- Excluding admin events from the seller scope globally (e.g. a second global scope on `AuditEvent`) — there is no
  seller reader in P0, so it would guard nothing today; the guard test plus a decision gate on any seller view is
  enough.
