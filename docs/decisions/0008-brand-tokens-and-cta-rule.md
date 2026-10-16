---
id: 0008
title: Rakyt Sogan tokens, status set, one-gold-CTA rule
status: proposed
date: 2026-10-17
deciders: [Rengga]
related: [docs/design/README.md]
---

# 0008. Rakyt Sogan tokens, status set, one-gold-CTA rule

## Context

Mockup rounds 2–3 defined colour tokens with measured contrast, a semantic status set, and tested four gold (Prada) shades for the primary CTA.

## Decision

- Implement the tokens and status set in `docs/design/README.md` as Tailwind theme values (single source in `tailwind` config / CSS variables).
- One gold CTA per screen, ink label; everything else ink outline.
- Statuses always combine tint + deep text + glyph + word.
- **Prada = D `#E5A823`** (recommended in mockup 3d) — *pending confirmation*.

## To confirm

- Prada shade D vs C.

## Consequences

- Accessible by construction; consistent "gold = next step" learning for customers.
