---
id: 0003
title: Seller-direct payments; Rakyt never holds funds
status: accepted
date: 2026-10-15
deciders: [Rengga]
related: [F-006, ADR-0010]
---

# 0003. Seller-direct payments; Rakyt never holds funds

## Context

Blueprint §5. Liburdulu's transaction and withdrawal costs cut seller margins. A pooled balance or payout system adds compliance and engineering burden.

## Decision

- Customers pay the seller directly (bank transfer, e-wallet, QRIS, the seller's own payment link, or cash/pay-on-arrival).
- Rakyt stores payment *instructions* and *records*: amount due/paid, evidence upload, seller verification.
- No pooled balance, payouts, or platform-controlled split payments in the MVP.
- Phase 2 (optional): direct-to-seller provider integrations only after validating demand and the provider's account/settlement/compliance model.
- Product promise: "no mandatory Rakyt transaction fee" — **not** "free payment processing".

## Consequences

- Good: no custody of funds; simpler compliance; sellers keep margin.
- Costs: manual verification by seller; status UX must make responsibilities obvious.
