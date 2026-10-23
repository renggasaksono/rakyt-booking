---
id: 0036
title: Ink-ground side nav, "Rakyt | Booking" lockup and solid seat-bar colours
status: proposed
date: 2026-10-23
deciders: [Rengga]
related: [F-007, F-017, ADR-0008, ADR-0035]
---

# 0036. Ink-ground side nav, "Rakyt | Booking" lockup and solid seat-bar colours

## Context

Mockups 1e/2f draw the seller side nav as an ink (`#211710`) panel with the Rakyt monogram, but the app shipped a
Surface panel with the word "Rakyt" only. The landing nav (F-017) uses the product lockup **Rakyt | BOOKING**, with
"Booking" as an 11 px uppercase kicker in Sogan. The two places must show the same mark.

Sogan on ink is **2.1 : 1**, which fails WCAG for text, so the kicker cannot keep its colour on the dark panel. The
mockup itself already uses a soft gold, `#D9AE5C`, on the ink panel for the brand accent (the monogram's accent node,
the Pembayaran count, the "Lihat Pro" link), and `#D8C9B6` for resting nav text.

Mockup 2f also draws the Hari ini seat bar as thin, square, **solid** segments (Lunas green `#2F6B3A`, ditahan amber
`#D9963A`, kosong a faint ink track) with 3 px gaps. The shipped bar used outlined tints in a rounded pill, which looked
broken when a departure was empty (one outlined capsule).

## Decision

We will:

1. Draw the desktop seller side nav on **ink**: resting items in **sand** `#D8C9B6` (10.8 : 1), the current item on a
   **Sogan** fill with gading text (7.2 : 1), the Perlu tindakan counts as plain numbers with Pembayaran's in
   gold-soft.
2. Use one `ProductMark` component for the **Rakyt | Booking** lockup in both the landing nav (`tone="ink"`) and the
   side nav (`tone="reverse"`).
3. On the ink ground, give Sogan's "brand & links" role to **gold-soft** `#D9AE5C` (8.5 : 1), so "Booking" is
   gold-soft there. Gold-soft is text and small marks only. It is **never a button fill**, so the one-gold-CTA rule
   (ADR-0008) is unchanged.
4. Draw the seat bar as in 2f: `bg-status-paid`, a new `--color-status-waiting-bar` `#D9963A`, and `bg-ink/12`, sized
   by flex-grow. The summary text stays the accessible signal, with "n ditahan" in the waiting deep colour.

New tokens in `resources/css/app.css` `@theme`: `--color-sand`, `--color-gold-soft`, `--color-status-waiting-bar`.

## Consequences

- Good: dashboard matches the mockup; one product mark everywhere; every text colour on ink passes AA.
- Good: the bar reads at a glance and an empty departure is a calm grey track.
- Bad / costs: the amber segment is 2.2 : 1 on gading. That is below the 3 : 1 non-text guideline, so the bar stays
  `aria-hidden` and the text summary always carries the numbers.
- Bad / costs: F-007 AC-12 said "tint/deep with a border" for the bar segments. The spec is updated to the solid 2f
  colours.
- Follow-ups: if Sogan Cerah (ADR-0035) goes product-wide, re-measure gold-soft and sand against its ink.

## Alternatives considered

- **Sogan kicker on ink.** It fails contrast at 2.1 : 1.
- **Sand `#D8C9B6` for "Booking".** It passes at 10.8 : 1, but it matches the resting nav items, so the product name
  stops reading as an accent.
- **A lightened Sogan (terracotta) for "Booking".** It would add a third brown that the mockups never use.
- **Keep the bar tinted, square it and drop the borders.** This is simpler, but on Surface the pale tints are hard to
  tell apart, and it still is not the mockup.
