---
id: 0035
title: "Sogan Cerah" landing reskin (scoped), WCAG review of the brighter brown
status: proposed        # proposed | accepted | rejected | superseded by NNNN
date: 2026-10-23
deciders: [Rengga]
related: [F-017, ADR-0008, ADR-0033]
---

# 0035. "Sogan Cerah" landing reskin (scoped), WCAG review of the brighter brown

## Context

Two landing mockups were added for F-017
([`docs/design/mockups/rakyt-booking-landing-sogan-cerah.html`](../design/mockups/rakyt-booking-landing-sogan-cerah.html),
[`-mobile.html`](../design/mockups/rakyt-booking-landing-mobile.html)). "Sogan Cerah" is a brighter, warmer take on the
Rakyt Sogan palette fixed in [ADR-0008](0008-brand-tokens-and-cta-rule.md): lighter grounds, a warmer ink, and a
markedly brighter terracotta brown. The gold (Prada) CTA and all six status colours are unchanged. The mobile mockup
keeps the current (darker) tokens; where the two conflict on colour, Cerah wins (per the design brief). The mockups also
restore two things missing from the shipped landing: the **`Rakyt | Booking` monogram lockup** in the nav and footer
(the product signature), and a brand-mark footer in place of the mis-used "Dibuat dengan Rakyt" attribution badge.

The palette shift touches **global** brand tokens (`ink`, `gading`, `surface`, `sogan`, `secondary`): changing them in
`@theme` reskins the whole product (seller dashboard, public pages, admin) and would supersede ADR-0008. That is a
larger, launch-affecting decision than a landing prototype warrants, and the new `sogan` (`#a65f2c`) is lighter than the
`#6e4527` that ADR-0008 chose partly for its measured 7.2:1 contrast — it needs re-measuring before it can be a link
colour anywhere.

## Decision

- Apply Cerah to the **marketing pages only**, via a `.cerah` scope in `resources/css/app.css` that redefines the brand
  token custom properties for the marketing subtree. Global `@theme` tokens, and every non-marketing page, are untouched.
  ADR-0008 stands.
- Ground `#fdf8ef`, surface `#fffdf9`, ink `#2b1e15`, sogan `#a65f2c`, secondary `#574434` (+ `#7a6250` metadata tier);
  warmed status tints; new landing-only accents `sogan-deep #9c5b2c` (closing band), `payment-ground #f7e7ce`,
  `accent-tan #efddc4`, `gold-dull #b8862f` (monogram node). Prada and the status colours are unchanged.
- Restore the `Rakyt | Booking` lockup (nav + footer) and the brand-mark footer. "Booking" is `nav.brand_suffix` in
  `lang/id/landing.php`; the monogram is `resources/js/components/marketing/RakytMark.vue`.
- Type: tighten tracking on display headings (~−0.02 to −0.03em), lift body to 15px, large brown step numerals, and hold
  the 11px floor (the old `text-[10px]` labels are gone).

## Consequences

- Good: a true before/after of the brighter brand on the real landing, reversible in one file, with zero risk to the
  dashboard/public/admin surfaces or their visual tests. The product signature is back.
- Bad / costs: the landing's palette now diverges from the rest of the app until a product-wide decision is made; two
  token systems (global + `.cerah`) coexist meanwhile.
- Follow-ups: **(1)** measure `#a65f2c` on `#fdf8ef` and on the `#f7e7ce` payment ground for AA text/UI contrast before
  using it as a link or small-text colour; **(2)** if adopted product-wide, graduate the `.cerah` values into `@theme`
  and supersede ADR-0008 with a new accepted ADR (re-audit every status tint and the dashboard at that point).

## Alternatives considered

- **Change the global `@theme` tokens now** — reskins the whole product and supersedes ADR-0008 in one step. Too broad
  for a prototype, and blocks on a full contrast re-audit; deferred to the follow-up above.
- **Mobile (darker) palette** — it is essentially today's tokens; the brief resolves colour conflicts in favour of Cerah.
