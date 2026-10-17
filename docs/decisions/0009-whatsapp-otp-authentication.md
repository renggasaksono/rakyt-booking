---
id: 0009
title: WhatsApp OTP sign-in for sellers
status: rejected (replaced by 0012)
date: 2026-10-17
deciders: [Rengga]
related: [F-001, F-008]
---

# 0009. WhatsApp OTP sign-in for sellers

## Context

Onboarding mockup (4a–4b) replaces email + password with a 6-digit code sent to the seller's WhatsApp business number, to hit "first trip live in 10 minutes". Blueprint requires account recovery and notifications "through compliant integrations".

## Decision (proposed)

- Seller identity = verified WhatsApp number; sign-in by OTP, no password.
- Use an approved WhatsApp Business API provider for OTP delivery (provider TBD).
- Fallback channel for recovery (email or SMS OTP) — TBD.
- Rate-limit OTP requests per number and IP; codes expire quickly and are single-use.

## To confirm

1. Provider and cost per OTP message.
2. Fallback when WhatsApp delivery fails, and account recovery when the number changes.
3. Is prefilling the business name "from the WhatsApp Business profile" (4b) actually available from the provider? If not, it's a manual field.

## Alternatives considered

- Email + password (Laravel Breeze/Fortify default) — slower onboarding, but zero messaging cost. Could ship first and add OTP later.
