---
id: 0024
title: Public pages — Inertia without SSR, server-rendered head tags, split JavaScript, no page cache
status: accepted
date: 2026-10-19
deciders: [Rengga]
related: [F-002, ADR-0002, ADR-0015, ADR-0020, ADR-0021]
---

# 0024. Public pages — Inertia without SSR, server-rendered head tags, split JavaScript, no page cache

*Proposed by the planner on 2026-10-19 while making F-002 ready (F-002 Q1). Accepted by Rengga on 2026-10-19.*

## Context

- The public booking page (F-002) is opened from WhatsApp and Instagram, mostly on mid-range Android phones over slow
  connections. Its link preview (photo + title) is what customers see first in a chat.
- WhatsApp, Instagram and Facebook build previews from Open Graph tags in the HTML; their crawlers do not run
  JavaScript. The app is Inertia + Vue (ADR-0002) and renders the page body in the browser.
- The blueprint (§2) says to pick rendering and caching for public pages "based on actual SEO and performance needs",
  without splitting the system early. SEO landing pages are out of P0 scope.
- `resources/js/app.js` resolves pages with `import.meta.glob(..., { eager: true })`, so every page — seller app,
  onboarding, settings — is in one bundle that a customer would download.
- Seat counts change with every booking and hold expiry; a stale "Sisa 2 kursi" leads to failed bookings.

## Decision

1. **No Inertia SSR in P0.** Public pages are ordinary Inertia pages rendered in the browser.
2. **Head tags are server-rendered.** Public controllers pass `<title>`, Open Graph tags and the canonical URL to the
   Blade root view (`Inertia::render()->withViewData(...)`); `app.blade.php` prints them, escaped, before any
   JavaScript. The client-side title for public pages is set to the same string (it skips the app's " · Rakyt"
   suffix when the server title already ends the way F-002 specifies).
3. **Pages are code-split.** The page resolver becomes lazy (`import.meta.glob('./pages/**/*.vue')` without
   `eager`), so a public page loads the shared runtime plus its own chunk only. Budget: ≤ 150 KB gzip of JavaScript
   for a public page (F-002 AC-33).
4. **No page or query cache for public pages.** Responses are `Cache-Control: no-cache, private`; seat counts are read
   per request with a bounded number of queries. Photos (immutable UUID paths, ADR-0015) are cached long by the
   storage/CDN.
5. Revisit (new ADR) if pilot data shows slow first paint on real phones, or when SEO pages come in scope.

## Consequences

- Good: link previews work with no Node process in production; customers download a fraction of the app's
  JavaScript; seat counts are always current; nothing new to operate.
- Bad / costs: the body is blank until JavaScript loads (no first paint from HTML); search engines that do not run
  JavaScript see only head tags; lazy pages add one extra request on seller-app navigation (small, cached).
- Follow-ups: F-002 builds the head tags, the lazy resolver and the bundle-size check. Seller-app pages gain a
  short loading state on first navigation if needed.

## Alternatives considered

- **Inertia SSR** — full HTML first paint and crawlable bodies, but a Node SSR server to deploy, monitor and keep in
  sync with every page (and SSR-safe components) for a P0 that needs only previews.
- **Separate Blade-only public pages** — fastest first paint, but a second UI stack and duplicated components and
  formatters; reconsider only with evidence from the pilot.
- **Full-page or fragment cache with invalidation on booking** — fast, but every seat change must invalidate it
  correctly; stale seats are worse than a few extra milliseconds of database time at pilot scale.
