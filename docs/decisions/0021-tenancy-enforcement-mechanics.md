---
id: 0021
title: How organization scoping is enforced in code
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: [ADR-0004, ADR-0011, F-009]
---

# 0021. How organization scoping is enforced in code

*Proposed by the builder agent on 2026-10-17; accepted by Rengga on 2026-10-18. Amended before merge on 2026-10-18 (review rounds 3–4); re-accepted by Rengga.*

## Context

ADR-0004 requires every seller-owned query to be scoped to the current organization and enforced by policies, with
cross-tenant tests. F-009 slice A has to choose the mechanism so that a forgotten scope is caught by tests, not by a
seller.

## Decision

- **One trait, `App\Tenancy\BelongsToOrganization`,** on every model whose table has `organization_id`. It adds a
  global scope, fills `organization_id` from the current organization on create, guards it from mass assignment and
  throws if code tries to change it.
- **The scope fails closed.** With no current organization (guest, unresolved user, job without context) scoped
  queries return no rows. Creating without a context throws unless the caller sets `organization_id` explicitly.
- **The current organization** (`App\Tenancy\CurrentOrganization`, a scoped singleton) is set only by
  `ResolveCurrentOrganization` from the user's membership, by `ScopedToOrganization` job middleware from an explicit
  id, or (F-002) by the public page from the seller slug. The middleware runs before route model binding, so bound
  models are already scoped and another seller's id is a 404.
- **Policies extend `OrganizationOwnedPolicy`** and deny with 404 (`denyAsNotFound`), a second layer behind the scope.
- **Payload ids** are validated with `ExistsInCurrentOrganization`, never a bare `exists:` rule.
- **Bulk writes** go through `TenantBuilder` (the trait's Eloquent builder): `insert`, `insertGetId`, `insertOrIgnore`
  and `fillAndInsert*` get the same organization rule as the `creating` hook. `upsert` must name `organization_id` in its
  conflict target (`ON CONFLICT` ignores the scope) and never updates it. Writes the scope cannot reach —
  `insertUsing`, `updateOrInsert`, `updateFrom`, `truncate` — are refused; use `create`, `save` or `updateOrCreate`.
  Updates and increments may never touch `organization_id`. `forceDelete()` and `withoutGlobalScopes()` without
  arguments keep the organization scope. Saves, deletes and reloads by key also match `organization_id`, so a model
  built by hand or hydrated from an unscoped query cannot touch another organization's row.
- **Bypassing the scope** — `acrossOrganizations()`, `withoutGlobalScope(s)`, `newModelQuery()`,
  `newQueryWithoutScope(s)`, `toBase()`, `getQuery()`, `getConnection()`, `resolveConnection()`, and any database access
  outside Eloquent (`DB::` except transaction control, an aliased `DB` import, the container's `db` binding,
  `DatabaseManager`, injected connections) — is allowed only in files listed in
  `config/rakyt.php → cross_organization_allowlist` (system jobs), under `app/Admin/Queries` (ADR-0011), or in the
  four tenancy mechanism files. A test scans `app/` and `routes/` and fails on any other use. The scan is a safety net,
  not a proof — determined code can still reach the database — so review and the F-009 C1 audit still check scoping.
- **The `Organization` model** is not scoped (it is the tenant); `OrganizationPolicy` allows only the current
  organization and answers others with 404. Non-numeric ids in URLs are a 404, in payloads a 422.
- **Guards in CI:** a schema test (every table has a NOT NULL, indexed, FK'd `organization_id` unless allowlisted) and
  a model test (every such model uses the trait, has a policy and does not make `organization_id` fillable).
- **Audit events** are written through `App\Audit\AuditLog` inside the change's transaction (it throws otherwise); a
  model guard and PostgreSQL triggers (row-level UPDATE/DELETE, statement-level TRUNCATE) make `audit_events`
  append-only.

## Consequences

- Good: the safe path is the default; new tables, models and bypasses are caught by failing tests.
- Bad / costs: tests and seeders must set an organization context (`runAs`) before creating seller-owned rows;
  after a request in a test, the context stays on the last actor, so assertions use `assertDatabase*` or
  `acrossOrganizations()`.
- Follow-ups: F-002 sets the public-page context from the slug; F-010 adds `app/Admin/Queries`.

## Alternatives considered

- Scope that returns everything when no organization is set — a forgotten context would leak every seller's data.
- PostgreSQL row-level security — strong, but adds per-connection session state and makes system jobs and tests
  harder; revisit if the app-level guard proves insufficient.
- Policy-only enforcement — every query would need a manual `where`; one miss leaks list views.
