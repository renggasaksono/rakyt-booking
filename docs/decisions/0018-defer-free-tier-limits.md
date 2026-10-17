---
id: 0018
title: Defer free-tier limits; no plan limit enforced in P0
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: [ADR-0010, F-001, F-003]
---

# 0018. Defer free-tier limits; no plan limit enforced in P0

## Context

ADR-0010 sets a freemium model and quotes the mockup copy "Gratis untuk 1 trip aktif". Its consequences say the MVP needs a free-tier limit check. The "1 trip" in the mockup is sample copy, not a decided limit. Billing and upgrades are not in P0, so a hard limit would only block pilot sellers.

## Decision

- No plan limit (trips, departures, photos or bookings) is enforced in P0. The free-tier limit check from ADR-0010's consequences is deferred.
- Keep the `organizations.plan` field (default `free`) so the limits can be added later without a migration.
- UI copy does not promise a specific limit (no "Gratis untuk 1 trip aktif"); neutral copy such as "Gratis untuk mulai." is used.
- The actual free-tier limits are decided together with pricing, after the pilot, and recorded in a new ADR.
- The rest of ADR-0010 (freemium, no mandatory transaction fee, limits in config/entitlements) stands.

## Consequences

- Good: pilot sellers aren't blocked; no limit logic built against an unknown number.
- Bad / costs: limit enforcement (including publish races) has to be built later; pilot sellers may need to be told when limits arrive.

## Alternatives considered

- Enforce 1 published trip now (mockup copy) — would turn sample copy into a commitment.
- Enforce a configurable limit with a high default — builds and tests logic for a rule that isn't decided yet.
