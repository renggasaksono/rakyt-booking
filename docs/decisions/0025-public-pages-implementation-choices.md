---
id: 0025
title: Implementation choices for the public booking pages (F-002)
status: accepted
date: 2026-10-19
deciders: [Rengga]
related: [F-002, F-003, F-009, ADR-0015, ADR-0021, ADR-0022, ADR-0024]
---

# 0025. Implementation choices for the public booking pages (F-002)

*Proposed by the builder agent on 2026-10-19 while building F-002. Accepted by Rengga on 2026-10-19.*

## Context

F-002 puts the first public, unauthenticated pages on the root of the app (`/<slug>`, `/<slug>/<trip>`), next to
every seller route. ADR-0021 and ADR-0024 fix the tenancy and rendering model. Building it meant several smaller
choices that the spec leaves open or words differently, each of which someone could later ask "why?" about.

## Decision

1. **The public routes are Laravel *fallback* routes with a permissive segment pattern (`[^/]+`).** They are matched
   only after every other route, including routes registered later (packages, tests), so they can never shadow an app
   route; F-001 B7 still keeps every app route's first segment on the reserved list. The spec asked for a slug-format
   constraint on the route; instead, the format, length, reserved-word and UTF-8/NUL checks run in
   `ResolvePublicOrganization`, so unknown, reserved and malformed slugs all go through the same middleware stack and
   give byte-identical 404 bodies and the same header set (AC-3).
2. **A path that is not valid UTF-8 is a 404, app-wide.** Laravel's `ValidatePathEncoding` answers such paths with a
   400 before routing; `bootstrap/app.php` maps that `MalformedUrlException` to a 404, so it is the same Bahasa page as
   an unknown slug. These requests never reach the web middleware, so their response has no session cookies — which
   reveals only what the client already knows (it sent bad bytes), never which slugs exist.
3. **`ResolvePublicOrganization` clears the session's organization, then sets the slug's** (and the log context). It
   runs after `ResolveCurrentOrganization` and before `SubstituteBindings` (middleware priority list). The trip is
   looked up by the controller through `PublicVisibility::trips()` (published, organization-scoped), not by implicit
   binding: `{trip:slug}` under `{organization}` would make Laravel call an `Organization::trips()` relation, and
   ADR-0021 forbids unscoped relations on `Organization`.
4. **Shared Inertia props are dropped on public routes** (`flash`, `seller`, `account`), decided by route name because
   `HandleInertiaRequests` runs before the route middleware. A signed-in seller's name, menu and toasts never reach a
   public page (AC-7).
5. **Departure rows carry seats, price and deadline only when bookable.** Full and closed rows send `null` for
   `seats_left`, `capacity`, `price`, `deadline`, `deadline_preset`; the page shows the word instead (AC-20). The
   AC-19a closing-date rule and date wording run in the browser (`resources/js/lib/publicPage.js`, Vitest) from the
   local wall-clock deadline, its preset and the organization's "now".
6. **One "Mulai Rp…" function, `App\Trips\TripPricing::fromPrice()`.** `TripDeparture::scopeOpenForBooking()` is the
   departure half of `scopeBookable()` (which now calls it). The editor preview uses it, so a draft previews the price
   its card will show once published; the public card uses `bookable()`. Same rule, no second implementation (Q7).
7. **Client titles:** the title callback keeps a title that already contains " · " (the public pages' full server
   titles) and adds " · Rakyt" to the rest (`resources/js/lib/title.js`).
8. **Bundle budget check:** `npm run check:bundle` (`scripts/check-public-bundle.mjs`) follows static imports of the
   app entry and each public page in the Vite manifest, sums their gzip size against 150 KB and fails if another page's
   chunk is pulled in. CI runs it after the build. At the time of writing: ~66 KB (seller) and ~68 KB (trip).
9. **The 429 page gains a "Ke beranda" link** next to "Kembali" (AC-39), for every route.
10. **Photo and logo URLs keep the organization's `public_id` in their path** (ADR-0015 storage layout). AC-8's
    "no `public_id` of the organization" is read as "no such field"; the test asserts it appears nowhere outside
    media paths. F-002 Q13 accepted this on 2026-10-19.

## Consequences

- Good: public pages cannot shadow app routes; all "not found" cases look the same; no seller session data leaks
  into public responses; preview and card prices cannot drift apart.
- Bad / costs: any future route that should win over a seller slug must still be registered normally (fallbacks
  always lose). Non-UTF-8 URLs are now 404 instead of 400 everywhere.
- Follow-ups: F-005 and F-004 register `/{slug}/{trip}/pesan` and `/ajukan-tanggal` (three segments, not shadowed).
  F-014 adds suspension to `PublicVisibility::organizationIsPublic()`. ADR-0015's path layout stays (F-002 Q13
  accepted).

## Alternatives considered

- A slug-format route constraint (as the spec worded it) — leaves malformed slugs to the router's own 404, which skips
  the web middleware and so differs in headers from an unknown slug's 404.
- Implicit binding with an `Organization::trips()` relation — an unscoped relation on the tenant (ADR-0021).
- Computing the closing-date wording on the server — the rest of the date wording already lives in the browser
  helpers (ADR-0022 §10); keeping it together keeps one formatter.
