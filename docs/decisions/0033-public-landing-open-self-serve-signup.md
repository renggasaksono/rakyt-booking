---
id: 0033
title: Public landing drives open self-serve sign-up; marketing reuses app tokens
status: accepted
date: 2026-10-23
deciders: [Rengga]
related: [F-017, ADR-0008, ADR-0019, ADR-0024, ADR-0034]
---

# 0033. Public landing drives open self-serve sign-up; marketing reuses app tokens

## Context

The homepage at `/` was a bare placeholder. We have an approved marketing mockup ("Sogan Cerah") to turn into a real
landing page (F-017). The mockup was drawn around a **pilot-waitlist** funnel — a name/usaha/email lead-capture form
and "Daftar pilot · 16 November 2026" framing — and it carried two fabricated testimonials. It also used rounded
corners and a brighter off-token palette that differ from the app's design system (ADR-0008).

Meanwhile registration at `/daftar` is already open and free: anyone can create an account and is dropped straight into
the F-001 onboarding, and free-tier limits are deferred (ADR-0019). So the product already supports self-serve
sign-up; a waitlist would be a step backwards and would need a lead store we do not have.

We had to decide, before building, what funnel the landing drives and how closely it follows the mockup's look.

## Decision

We will build **one public landing page at `/`** whose single job is to move an operator into the existing open,
free sign-up:

- **Open self-serve funnel, not a waitlist.** Every primary call to action links to `route('register')` (`/daftar`).
  There is no lead-capture, waitlist or email-collection form on the page. The landing only links into F-001; it never
  modifies registration or onboarding.
- **A signed-in seller still sees the landing**, but the primary CTA swaps to their dashboard / onboarding-resume URL
  (`OnboardingProgress::landingUrl()`), labelled "Buka dashboard" (F-017 Q1).
- **Marketing reuses the app's design system — no re-skin.** The page is rebuilt on the existing `@theme` tokens
  (gading / ink / surface / secondary / prada), square corners and the one-gold-CTA rule (ADR-0008), with Poppins
  headings via `@fonts` (ADR-0024). The mockup's rounded radii and brighter palette are not adopted; the mockup is a
  content and layout reference only.
- **No fabricated social proof.** The mockup's testimonials are dropped and testimonials are deferred to P1 until real
  pilot-seller quotes exist.
- **Pricing stays honest.** Copy says the app is free to start and names no specific free-tier limit or price
  (ADR-0019).

## Consequences

- Good: no new backend (no lead store); the landing is a thin, guest-accessible Inertia page that links into flows we
  already ship. One design system to maintain, so the marketing page and the app never drift.
- Good: visitors reach a working product in one click instead of joining a queue.
- Bad / costs: we lose the "scarcity" framing a pilot waitlist can create, and we have no marketing testimonials until
  real ones exist.
- Follow-ups: testimonials, a `/harga` pricing page and other marketing sub-pages are P1. Landing-funnel analytics is
  covered by [ADR-0034](0034-landing-funnel-analytics-ga4-consent-mode.md).

## Alternatives considered

- **Pilot-waitlist funnel (as mocked).** Rejected — registration is already open and free; a waitlist adds a lead
  store and a worse first experience for no benefit now.
- **Redirect signed-in sellers away from `/`.** Rejected for now — showing the landing with a dashboard CTA is simpler
  and lets a logged-in operator still read/share the pitch (F-017 Q1).
- **Re-skin the app to the mockup's brighter "Cerah" palette and rounded corners.** Rejected — it would supersede
  ADR-0008 and split the design system for one page.
