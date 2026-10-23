---
id: 0034
title: Landing-funnel analytics — Google Analytics 4 with Consent Mode v2 (default-denied) + opt-in
status: accepted
date: 2026-10-23
deciders: [Rengga]
related: [F-017, ADR-0008, ADR-0033]
---

# 0034. Landing-funnel analytics — Google Analytics 4 with Consent Mode v2 (default-denied) + opt-in

## Context

To tell whether the public landing (F-017, ADR-0033) actually turns visitors into sellers, we need to measure the
sign-up funnel: landing CTA click → completed registration → onboarding start → onboarding publish. Rakyt had no
product analytics.

Two forces pull against each other:

- We want a familiar, low-cost tool the product owner already knows. Rengga chose **Google Analytics 4** over a
  cookieless/self-hosted alternative.
- Indonesia's Personal Data Protection law (UU 27/2022) and the project's "decline non-essential cookies by default"
  rule (ADR-0008 spirit) mean we cannot set analytics cookies or process personal data without consent.

GA also must not slow the landing on a mid-range Android, must be absent entirely in tests and local development, and
must never receive personally identifying data.

## Decision

We will add **Google Analytics 4 for the landing sign-up funnel only**, wired as follows:

- **Config-gated.** The measurement id comes from config (`services.google_analytics.measurement_id`, env
  `GA_MEASUREMENT_ID`). When it is unset — tests and local by default — the page emits **no** analytics script and makes
  **no** analytics network call. The id is shared to the client only on the funnel pages (landing, register, login,
  onboarding), never across the signed-in seller app.
- **Consent Mode v2, default-denied.** On load, `analytics_storage` (and ad storage/signals) default to `denied`; GA
  sets no cookie until the visitor opts in through a lightweight prompt. Declining keeps it denied. The choice is
  remembered in `localStorage`.
- **Non-blocking.** `gtag.js` loads after the page is interactive (`requestIdleCallback` / `load`), so first paint is
  never blocked.
- **No PII.** Funnel events (`landing_cta_click`, `sign_up`, `onboarding_start`, `onboarding_publish`) carry no email,
  name or phone; a `stripPii` guard drops identifying params and any value that looks like an email or phone number.
  Server-side milestones are passed to the client as a flashed event name only (`analytics_event`), never customer or
  seller data.
- **Tested.** The consent-gated helper and PII stripping live in `resources/js/lib/analytics.js` with Vitest coverage;
  config-gating is covered by a PHPUnit feature test.
- **Disclosure.** `/privasi` must disclose Google/third-party analytics before the landing goes live (F-017 AC-23/Q8),
  tracked on the roadmap's PDP gate.

## Consequences

- Good: the funnel is measurable with a tool the owner knows, at no cost, without blocking first paint or leaking PII,
  and with analytics off by default everywhere analytics is not wanted (tests, local, the seller app, and any visitor
  who has not opted in).
- Bad / costs: data sent to Google (a US processor) — hence the default-denied consent gate and the `/privasi`
  disclosure requirement. With consent denied, GA reports only Consent-Mode modelled/cookieless signals, so numbers are
  partial until opt-in rates are known.
- Follow-ups: write the `/privasi` GA disclosure (AC-23); revisit a cookieless or self-hosted analytics option if
  consent rates or PDP guidance make GA unsuitable; any app-wide product analytics beyond the sign-up funnel is a
  separate, later decision.

## Alternatives considered

- **Cookieless / self-hosted analytics (e.g. Plausible).** Avoids the consent gate, but the owner preferred GA4's
  familiarity and ecosystem; revisit if consent rates disappoint.
- **Full cookie-consent banner before any load.** Heavier UX than needed for one default-denied analytics tag; the
  lightweight opt-in prompt plus Consent Mode v2 covers the PDP requirement.
- **No analytics in P0.** Rejected — we would be flying blind on whether the landing converts, which is its whole point.
