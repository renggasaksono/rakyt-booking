---
id: 0039
title: Admin seller directory — single-organization reads, app-signed evidence URLs, sign-in time, status column
status: proposed
date: 2026-10-27
deciders: [Rengga]
related: [F-012, F-014, F-015, F-006, ADR-0011, ADR-0021, ADR-0030, ADR-0038]
---

# 0039. Admin seller directory — single-organization reads, app-signed evidence URLs, sign-in time, status column

## Context

F-012 gives the platform admin a searchable list of organizations and a read-only detail page per organization:
profile, status, plan, last activity, members, trips, departures with seat counts, recent bookings with payment
evidence, and audit events. ADR-0011 allows cross-organization reads only in `app/Admin/Queries`. Four details were
not fixed by the spec:

1. The admin has no current organization, so the tenant scope (ADR-0021) returns **no rows** for every seller-owned
   query — including eager loads and `withCount` subqueries, not just the top-level query.
2. The spec requires payment evidence to open "only through a signed, short-lived URL issued to the admin (same
   private storage as F-006)". Locally and on any disk without `temporaryUrl()`, `PrivateMedia` has no signed URL; F-006
   streams files from an authorised controller instead (ADR-0030 §3).
3. "Last activity" is the owner's last successful sign-in (`users.last_login_at`, F-012 changelog 2026-10-23), but
   "sign-in" has several paths: the login form, sign-up, password reset and the remember-me cookie.
4. The directory filters on account status (active / suspended), but F-014, which suspends, is built after F-012.

## Decision

- **One organization per detail query.** `SellerDirectoryQueries` reads each seller-owned model with
  `acrossOrganizations()->where('organization_id', $organization)`, and drops the scope on eager loads and counts of
  related seller-owned models (`withoutGlobalScope(OrganizationScope::class)`) — only inside that class. The parent
  rows already belong to the organization, and the composite `(organization_id, id)` foreign keys keep their children
  in it. The admin never "runs as" the seller's organization (`CurrentOrganization::runAs`): that context belongs to
  seller and system code.
- **Seat counts are shared code.** `App\Dashboard\SeatBreakdown` holds F-007's seat sums; "Hari ini" passes its scoped
  `Booking::query()`, the admin detail an organization-limited admin query, so both show the same numbers (F-012 AC-4).
- **Evidence through an app-signed route.** The detail page links to `admin.sellers.evidence`, which (behind
  `auth` + `platform-admin`) checks the booking belongs to the organization and has evidence, then redirects to a
  `URL::temporarySignedRoute` for `admin.sellers.evidence.show` valid for at most 5 minutes
  (`min(5, rakyt.media.private_url_ttl)`, as `PrivateMedia::temporaryUrl`). That route needs the admin gate **and** a
  valid signature, streams the file from the private disk with `no-store` and `no-referrer`, and never puts the storage
  path in props. Issuing on click means a link on a page left open never goes stale. Viewing evidence is a read and is
  not audited (F-015 audits changes).
- **Sign-in time from the `Login` event.** `App\Security\RecordLastLogin` listens to `Illuminate\Auth\Events\Login`,
  so every successful authentication — login form, sign-up, password reset, remember-me — stamps `last_login_at`
  (without touching `updated_at`). A failed attempt never does. The column is not mass-assignable.
- **F-012 adds `organizations.status`.** A `string(16)` defaulting to `active`, checked to `active` / `suspended`, cast
  to `App\Organizations\OrganizationStatus`, guarded on the model (set only by `forceFill`). F-014 adds the
  `suspended_at` / `suspended_by` / `suspended_reason` columns, the actions and their effects.
- **Organization picker over the seller lookup.** The F-015 audit view's organization filter no longer loads every
  organization: it searches `GET /admin/penjual/cari` (JSON, admin-only, ≤ 10 matches, same matching as the
  directory search) and the page receives only the selected organization.

## Consequences

- Good: no admin read can return another organization's rows by mistake — each query names its organization; seat
  numbers cannot drift between the seller dashboard and the admin; evidence links work locally and on S3 alike and
  expire within minutes; "last activity" needs no per-feature plumbing.
- Costs: every new eager load in an admin query must drop the scope explicitly, or it silently returns nothing (a
  missing relation, not a leak). Remember-me sign-ins count as activity, so a seller who only keeps a tab open with a
  remembered session shows as active.
- Follow-ups: F-014 builds on `organizations.status` and hangs suspend / reactivate on the detail page's "Akun"
  section; F-013's booking detail can reuse the evidence routes' pattern.

## Alternatives considered

- Running the detail under `CurrentOrganization::runAs($organization)` — scoped queries and eager loads would work
  unchanged, but it gives the admin a seller context ADR-0011 rules out and makes admin reads indistinguishable from
  seller reads in review.
- `Storage::temporaryUrl()` only — not available on the local disk, which is deliberately not served (F-006 AC-31).
- A signed URL embedded in the page props — expires while the page is open; the issue-and-redirect route avoids that.
- Updating `last_login_at` in `LoginController` only — misses sign-up, password reset and remember-me sign-ins.
- Leaving the status filter to F-014 — F-012's AC-2 could not be tested.
