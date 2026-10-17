---
id: 0011
title: Email + password sign-in for sellers
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: [F-001, F-009, ADR-0009, ADR-0017]
---

# 0011. Email + password sign-in for sellers

## Context

ADR-0009 proposed WhatsApp OTP sign-in to shorten onboarding (mockups 4a–4b). It needed a WhatsApp Business API provider, a per-message cost, a delivery fallback and a recovery path for changed numbers — all unresolved, and all before the pilot. Laravel ships mature email + password auth.

## Decision

- Sellers sign up and sign in with **email + password** using Laravel's standard auth stack (Fortify/Breeze-style).
- Password ≥ 8 characters; hashed with the framework default.
- Password reset by expiring, single-use email link; the reset flow never reveals whether an email is registered.
- Email verification is sent at sign-up but **does not block onboarding or publishing in P0** (reminder banner instead).
- Changing the sign-in email requires the current password and notifies the old address.
- Sign-in and reset are rate-limited by email + IP (defaults in F-009).
- The business WhatsApp number is still collected in onboarding step 1 as a **contact** field (E.164, not verified) — customers chat the seller there.
- ADR-0009 is rejected.

## Consequences

- Good: zero messaging cost, no provider dependency before pilot, well-trodden security.
- Bad / costs: onboarding is slower than the OTP mockup; sign-up screens differ from 4a–4b and the "±m menit" budget must be rechecked. Sellers must remember a password.
- Follow-ups: WhatsApp OTP can be revisited later as an added sign-in method (new ADR) if pilots show friction.

## Alternatives considered

- WhatsApp OTP, no password (ADR-0009) — rejected for P0: provider cost and recovery questions unresolved.
- Magic link by email — no password, but slow/unreliable email on phones makes every sign-in depend on the inbox.
