---
id: 0010
title: Freemium + subscription; no mandatory transaction fee
status: accepted
date: 2026-10-15
deciders: [Rengga]
related: [ADR-0003]
---

# 0010. Freemium + subscription; no mandatory transaction fee

## Context

Blueprint §6 and §11. Per-booking commissions made Liburdulu sellers hesitate.

## Decision

- Free tier (mockup: "Gratis untuk 1 trip aktif"), paid Pro and Business tiers, later Enterprise.
- No mandatory Rakyt commission per booking at launch.
- Price points are hypotheses (Pro Rp79k–149k/mo, Business Rp199k–299k/mo) — not hard-coded; plan limits live in config/entitlements so they can change without migrations.

## Consequences

- Billing/entitlements are not P0; the MVP needs only a plan field and a free-tier limit check.
