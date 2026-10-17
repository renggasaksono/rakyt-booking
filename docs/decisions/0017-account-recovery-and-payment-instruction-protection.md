---
id: 0017
title: Account recovery and payment-instruction change protection
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: [F-001, F-006, F-009, ADR-0003, ADR-0011]
---

# 0017. Account recovery and payment-instruction change protection

## Context

Blueprint P0 requires account recovery. A hijacked seller account can change bank details and redirect customer transfers; because payments go seller-direct (ADR-0003), Rakyt cannot claw funds back. Sellers sign in with email + password (ADR-0011).

## Decision

- Self-serve recovery: password reset by expiring, single-use email link (ADR-0011).
- Staff-assisted recovery (seller lost access to their email) only via an audited artisan command, after all of: the request comes from the organization's stored WhatsApp contact number, the KTP name matches the payment-instruction account holder, and the requester names two recent booking references with amounts (F-009 Q5).
- Any password reset, staff recovery or sign-in email change revokes all other sessions and blocks payment-instruction changes for 24 hours. An ordinary password change by a signed-in seller who knows the current password revokes other sessions but does not trigger the 24-hour block.
- Every payment-instruction change is audited with before/after values and notified to the sign-in email.

## Consequences

- Good: limits damage from account takeover; clear support procedure.
- Bad / costs: legitimate sellers wait 24 h to change bank details after a recovery.

## Alternatives considered

- No cooling-off period — simpler, but leaves customer funds exposed.
