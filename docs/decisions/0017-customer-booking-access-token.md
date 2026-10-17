---
id: 0017
title: Customer booking access via unguessable token
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: [F-005, F-006, F-009, ADR-0004]
---

# 0017. Customer booking access via unguessable token

## Context

Customers have no account. The booking reference (`RKB-MMDD-NNNN`) is short and sequential, so using it as the access key would let anyone enumerate other customers' names, phone numbers and payment proofs.

## Decision

- Each booking gets a random access token (≥ 128 bits), stored hashed or compared in constant time.
- Customer booking/payment URLs carry the token; the reference is display-only.
- Customer pages send `Referrer-Policy: no-referrer`; payment-evidence access is authorised by the token.
- Lookups by reference alone (e.g. over WhatsApp) are handled by the seller, not self-serve.

## Consequences

- Good: no enumeration; no customer accounts needed.
- Bad / costs: a lost link means asking the seller to resend; long URLs in WhatsApp messages (F-008).

## Alternatives considered

- Reference + phone number as a "login" — phone numbers are guessable or known; weaker.
- Customer accounts — friction; out of P0.
