---
id: F-017
title: Public landing page
status: in-progress
priority: P0
roadmap: mvp-pilot-prep
mockups: [landing-sogan-cerah]
decisions: ["0007", "0008", "0019", "0024", "0033", "0034"]
updated: 2026-10-23
---

# F-017 · Public landing page

## Problem

A seller who hears about Rakyt lands on `rakyt.id` and today sees a bare splash
([`Home.vue`](../../resources/js/pages/Home.vue)): the wordmark, a one-line tagline, and two buttons. It explains
nothing, builds no trust, and gives an operator no reason to sign up. We need one public page that tells an Indonesian
tour/activity operator what Rakyt does, shows it is free to start and that money goes straight to their own account,
and moves them into the **existing open, free sign-up** (`/daftar` → onboarding) with a single clear call to action.

The goal is self-serve sign-ups, not a waitlist: registration at [`/daftar`](../../routes/web.php) is already open and
free (ADR-0019 defers tier limits), and after it the seller is dropped straight into the F-001 onboarding. The landing
page's job is to get them to that first click.

A mockup exists in four art-direction variants outside the repo
(`Rakyt Booking Landing - Sogan Cerah.html`, `… - Mobile.html`, `… .html`, `Draft v2.html`). "Sogan Cerah" is the
chosen direction, but it is built in a separate component framework, uses rounded corners and a brighter off-token
palette, defaults to a pilot-waitlist funnel, and carries fabricated testimonials — none of which ship as-is (see
Scope and the decisions of 2026-10-23).

## Scope

**In:**
- **One public marketing page at `/`** (the existing `home` route), rebuilt as an Inertia/Vue 3 page that replaces the
  `Home.vue` placeholder. Guest-accessible, no auth, renders with the normal middleware stack (not a `/<slug>` public
  fallback — it never resolves a tenant).
- **Sections**, adapted from the Sogan Cerah mockup:
  - Sticky nav — brand wordmark, in-page anchors (Cara kerja · Fitur · Tanya jawab), "Masuk" (secondary → `/masuk`),
    and the primary gold CTA (→ `/daftar`).
  - Hero — H1 "Trip Anda. Pelanggan Anda. Booking jadi rapi.", sub-copy, primary CTA, and the interactive product
    demo (3-step walkthrough "Lihat trip → Pesan → Bayar" with the 2-hour seat-hold countdown).
  - Value proposition ("dibuat untuk operator yang sudah punya pelanggan sendiri").
  - "Cara kerja" — the five timed steps (Akun → Usaha → Trip → Jadwal → Rekening), which mirror `/daftar` + the four
    F-001 onboarding steps.
  - Seller and customer feature columns ("Untuk Anda, operator" / "Untuk pelanggan Anda").
  - Seller-direct-payment section ("Uangnya langsung ke rekening Anda") — the ADR-0003 differentiator.
  - FAQ accordion (fees, where money goes, customer accounts, private-tour dates, WhatsApp, custom domain).
  - Closing CTA band (→ `/daftar`) and a footer with `/syarat`, `/privasi` and the "Dibuat dengan Rakyt" mark.
- **Single self-serve funnel:** every primary CTA links to `route('register')`; no lead-capture or waitlist form on
  the page. The landing only links into F-001 — it never modifies registration or onboarding.
- **Design-system compliance:** rebuilt on the app's `@theme` tokens and square corners (ADR-0008), Poppins headings
  via `@fonts`, one gold (`prada`) CTA per viewport.
- **Copy in `lang/id/landing.php`**, passed to the page as props (ADR-0007); nothing hard-coded in the Vue views.
- **Server-rendered `<head>`** (title, description, Open Graph) for `/` so the link previews and is crawlable
  (ADR-0024 pattern); the page body is not SSR'd.
- **Mobile-first** layout verified at phone width on a mid-range Android.
- A new `MarketingLayout` + `resources/js/components/marketing/`, mirroring the `components/public/` pattern; the hero
  demo and FAQ accordion logic in `resources/js/lib` with Vitest tests.
- **Analytics — landing sign-up funnel (Google Analytics 4).** Track the conversion funnel: landing CTA click →
  completed registration (`sign_up`) → onboarding start → onboarding publish. The GA4 Measurement ID comes from config
  (`config/services.php` → `GA_MEASUREMENT_ID` env); when it is unset — tests and local by default — no analytics
  script or network call is emitted. GA loads with **Google Consent Mode v2 defaulting `analytics_storage` to
  `denied`** plus a lightweight opt-in prompt, so cookies are set only on consent (honoring the decline-by-default
  rule); the script loads after the page is interactive so it never blocks first paint on a mid-range Android. No PII
  (email, name, phone) is ever sent in event params. `/privasi` discloses Google analytics (ties to the pilot
  privacy-notice gate).

**Out (explicitly):**
- The pilot-waitlist / lead-capture funnel and its name/usaha/email form, the "Daftar pilot" / "16 November 2026"
  framing, and any backend to store leads (decided 2026-10-23 — open self-serve instead).
- Testimonials / social-proof section — the mockup's are fabricated and there are no pilot sellers yet; deferred to
  P1 until real quotes exist (decided 2026-10-23).
- Re-skinning the app to the brighter "Cerah" palette or rounded corners — the landing adapts to the existing tokens,
  it does not supersede ADR-0008 (decided 2026-10-23).
- Marketing sub-pages (`/harga` pricing, `/tentang`, blog) — single page at `/` only for now; those slugs stay
  reserved for later (P1).
- Specific pricing or free-tier limits in copy (ADR-0019 — "gratis untuk mulai", nothing more).
- A-B testing / experimentation framework, and any server-side or app-wide product analytics beyond the sign-up
  funnel — landing-funnel GA4 only for now (P1).
- Inertia SSR of the page body — only `<head>` tags are server-rendered (same as F-002, ADR-0024).
- Multiple languages — Bahasa Indonesia only (ADR-0007).
- Any change to `/daftar`, `/masuk` or the onboarding flow (F-001).

## User stories

- As an operator who just heard about Rakyt, I want the homepage to explain what it does and that it is free, so I
  decide whether to try it without asking anyone.
- As an operator, I want to understand that customers pay me directly into my own account, so I trust it with my money.
- As an operator, I want one obvious button that starts a free account, so I can be running in minutes.
- As a cautious operator, I want my common questions (fees, accounts, WhatsApp, private tours) answered up front, so I
  do not have to email first.
- As a returning signed-in seller who opens `rakyt.id`, I want to get to my dashboard, not a marketing pitch.

## Acceptance criteria

UI copy is Bahasa Indonesia from `lang/id/landing.php` (ADR-0007). The page uses the app design tokens and square
corners (ADR-0008) and never renders tenant data.

### A. Route & rendering

- [x] **AC-1 Guest render.** Given a signed-out visitor, when they GET `/`, then the landing page renders (Inertia
  page replacing the placeholder) with status 200 and no login required.
- [x] **AC-2 No tenant data.** The page is served by the `home` route (not a `/<slug>` fallback) and resolves no
  organization; a signed-in seller's organization, name, bookings or any tenant data never appear in the landing props.
- [x] **AC-3 Signed-in visitor.** Given an authenticated seller, when they GET `/`, then the page still renders but the
  primary CTA points to their `OnboardingProgress::landingUrl()` (dashboard or resume step) labeled "Buka dashboard"
  rather than "Coba gratis" (Q1, recommended default).

### B. Funnel & CTAs

- [x] **AC-4 Self-serve CTA.** The hero, closing and nav primary CTAs all link to `route('register')` (`/daftar`);
  there is no lead-capture, waitlist or email-collection form anywhere on the page.
- [x] **AC-5 One gold CTA.** At any scroll position at most one gold (`prada`) CTA is visible (ADR-0008); "Masuk" links
  to `route('login')` and is a secondary/ink control, never gold.
- [x] **AC-6 Footer links.** The footer links to `route('legal.terms')` and `route('legal.privacy')` and shows the
  "Dibuat dengan Rakyt" mark, consistent with the public-page footer.

### C. Content accuracy

- [x] **AC-7 Copy from lang file.** Every visible string comes from `lang/id/landing.php` passed as props; no Bahasa
  string is hard-coded in the page or its components (asserted by rendering with copy from the lang file).
- [x] **AC-8 Pricing honesty.** Copy states the app is free to start and names no specific free-tier limit or price
  (ADR-0019).
- [x] **AC-9 Request-a-date accuracy.** The private-tour / "ajukan tanggal" copy describes the WhatsApp handoff (F-004
  "Option B"), not an in-app propose/accept inbox.
- [x] **AC-10 No fabricated proof.** No testimonials or named-customer quotes render (deferred until real ones exist).
- [x] **AC-11 Only built features claimed.** "Cara kerja" and feature copy describe only shipped P0 behavior;
  not-yet-built items (custom domain, logo removal) are omitted or clearly marked "belum".

### D. Design system

- [x] **AC-12 Tokens & square corners.** The page uses the `@theme` tokens (gading/ink/surface/secondary/prada) and
  square corners; it introduces none of the mockup's rounded radii or off-token hex values.
- [x] **AC-13 Poppins via @fonts.** Headings render in Poppins supplied by `@fonts` in the layout (not `@vite`); tests
  pass under `Tests\Support\NullVite`.
- [ ] **AC-14 Mobile-first.** The page is laid out, legible and tappable at 360–390px width; real-device review on a
  mid-range Android (with the other P0 screens).

### E. Head / SEO

- [x] **AC-15 Server-rendered head.** `/` serves a server-rendered `<head>` with title, meta description and Open
  Graph tags (ADR-0024 pattern) so the link previews on WhatsApp/Instagram and is crawlable; the body is not SSR'd.

### F. Interactive pieces

- [x] **AC-16 Hero demo & FAQ.** The 3-step hero demo (with the 2-hour hold countdown) and the FAQ accordion behave as
  in the mockup; their logic lives in `resources/js/lib` with Vitest tests (`npm test`) — or ships as a static hero if
  deferred (Q2).
- [x] **AC-17 Motion.** Demo autoplay pauses on manual interaction and respects `prefers-reduced-motion`.

### H. Analytics (sign-up funnel)

- [x] **AC-19 Config-gated.** GA4 loads only when `GA_MEASUREMENT_ID` is configured; with it unset (tests and local
  default) the page emits no analytics script and makes no analytics network call.
- [x] **AC-20 Consent default-denied.** On first load `analytics_storage` is `denied` and no analytics cookie is set
  until the visitor opts in; declining keeps it denied (ADR-0008-style default; PDP).
- [x] **AC-21 Funnel events.** Events fire at the funnel points — primary CTA click, `sign_up` on successful
  registration, onboarding start, onboarding publish — and carry no PII (no email, name or phone) in their params.
- [x] **AC-22 Non-blocking & test-safe.** The GA script loads after the page is interactive and is excluded under
  `Tests\Support\NullVite` / in tests, so first paint and test output are unaffected.
- [ ] **AC-23 Privacy disclosure.** `/privasi` discloses Google/third-party analytics before the landing goes live
  (copy / legal item — see Q8).

### G. Tests

- [x] **AC-18 Coverage.** Feature tests cover: guest render (AC-1), no-tenant-data-leak (AC-2), CTA targets →
  `/daftar` (AC-4), the signed-in CTA swap (AC-3), copy-from-lang (AC-7), GA config-gating (AC-19); Vitest covers the
  demo/accordion logic and the consent-gated event helper (AC-20/AC-21).

## Data & rules

- **Routing.** `/` stays the `home` named route (closure or thin controller) rendering the new Inertia page with props
  built from `lang/id/landing.php`. It is **not** a `/<slug>` fallback and does **not** pass through
  `ResolvePublicOrganization`; no reserved-slug change is needed. The nav anchors are in-page (`#cara-kerja`, etc.).
- **No tenancy.** The page holds no seller-owned data, so there is no cross-tenant query to guard — but the no-leak
  test (AC-2) stands in for the usual isolation test: an authenticated seller's shared props must not surface on the
  marketing page.
- **Auth-aware CTA.** The only auth coupling is AC-3: resolve the signed-in seller's `landingUrl()` for the CTA; use
  `ResolveCurrentOrganization::membershipOf()` / `OnboardingProgress`, never a new unscoped query.
- **Design.** App `@theme` tokens, square corners, one gold CTA per viewport (ADR-0008); Poppins via `@fonts`
  (ADR-0024 / app.blade.php). Bahasa copy in `lang/id/landing.php` (ADR-0007).
- **Head.** Reuse the `publicHead` view-data mechanism in `app.blade.php` so crawlers get OG tags without JS (ADR-0024).
- **Links out only.** The page links to `/daftar`, `/masuk`, `/syarat`, `/privasi`; it changes nothing in F-001.

## Open questions

- [x] **Q1 — Signed-in visitor at `/`.** Decided 2026-10-23: show the landing with the CTA swapped to "Buka dashboard"
  → `OnboardingProgress::landingUrl()` (the recommended default; built, AC-3).
- [x] **Q2 — Hero demo in P0.** Decided 2026-10-23: port the interactive 3-step demo + 2-hour hold countdown now (the
  recommended default; built, AC-16/AC-17).
- [x] **Q3 — Conversion analytics.** Decided 2026-10-23: in scope now — Google Analytics 4, landing sign-up funnel
  only (Rengga).
- [x] **Q7 — GA consent UX.** Decided 2026-10-23: Consent Mode v2 with `analytics_storage` default-denied + a
  lightweight opt-in prompt (the recommended default; built, AC-20). Recorded in ADR-0034.
- [ ] **Q8 — Privacy notice.** Update `/privasi` to disclose Google analytics before the landing is published (ties to
  the roadmap "Syarat & Privasi · PDP law" gate). — Rengga / legal **(AC-23, pre-pilot manual/legal item — not written in this PR)**
- [ ] **Q4 — Pricing page.** Single page at `/` for now (recommended; pricing deferred by ADR-0019), or a `/harga`
  page before pilot? — Rengga
- [x] **Q5 — Record the funnel decision.** Decided 2026-10-23: captured "public landing drives open self-serve sign-up
  (not a waitlist)" and "marketing reuses app tokens" as **ADR-0033** (accepted).
- [ ] **Q6 — Mockup in the repo.** Add the chosen `Sogan Cerah` mockup to `docs/design/mockups/` with a screen ID
  (`landing-sogan-cerah`) and reconcile it with the `… - Mobile.html` variant for the responsive layout? — Rengga

## Changelog

- 2026-10-23 — created as `ready`. Decisions locked with Rengga: open self-serve sign-up funnel (CTA → `/daftar`, no
  waitlist); adapt the Sogan Cerah mockup to the existing app tokens and square corners (not a re-skin); remove the
  fabricated testimonials. Proposed ADR-0033 to record the funnel/token decision (Q5).
- 2026-10-23 — Q3 resolved: landing sign-up-funnel analytics added in scope as Google Analytics 4 (Rengga's choice
  over the cookieless recommendation). Added scope, AC-19…AC-23, and open questions Q7 (GA consent UX — default-denied
  Consent Mode v2 recommended) and Q8 (privacy-notice disclosure). Propose ADR-0034 to record the GA4 + consent choice.
- 2026-10-23 — built on `feat/F-017-public-landing`. `Home.vue` rebuilt as the Inertia landing (`HomeController`,
  `MarketingLayout`, `resources/js/components/marketing/*`); copy in `lang/id/landing.php` (ADR-0007); server-rendered
  head via `publicHead` (ADR-0024). Interactive hero demo + FAQ accordion logic in `resources/js/lib`
  (`landingDemo.js`, `faq.js`) with Vitest. GA4 funnel analytics: `services.google_analytics.measurement_id`
  (`GA_MEASUREMENT_ID`), consent-gated helper `resources/js/lib/analytics.js` + `useAnalytics.js`, funnel-event flashes
  in Register/Onboarding controllers, shared only on funnel routes. Q1, Q2, Q5, Q7 decided (recommended defaults).
  AC-1…AC-13, AC-15…AC-22 covered by PHPUnit + Vitest; status `in-progress` pending AC-14 (device review), AC-23
  (`/privasi` legal copy) and Lighthouse — pre-pilot manual items. ADR-0033 + ADR-0034 accepted.
