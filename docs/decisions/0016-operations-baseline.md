---
id: 0016
title: Operations baseline — monitoring, heartbeats, backups
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: [F-009, ADR-0002, ADR-0006, ADR-0013]
---

# 0016. Operations baseline — monitoring, heartbeats, backups

## Context

Blueprint §4 "Operations" and the Pilot-go gate require backups with a tested restore, error reporting, logs and uptime checks. Hold expiry and deadline closing (ADR-0006) depend on the scheduler. The team is small with a low ops budget.

## Decision

- Error tracking: Sentry (free tier) with PII scrubbing; alerts to the founder's email and phone.
- Uptime: external monitor on the health endpoint and one public page, every ≤ 5 min; alert after 2 failures.
- Scheduler and queue heartbeat written every minute; the health endpoint returns 503 if stale > 5 min.
- Booking correctness never depends on the scheduler (capacity and deadline enforced at booking time).
- Daily consistent PostgreSQL backup to S3-compatible storage at a different provider; encrypted; keep 14 daily + 8 weekly; alert on failure or > 26 h without success. Media bucket versioning 30 days.
- Restore runbook in `docs/operations/restore.md`, rehearsed before the pilot and in week 6.
- Hosting: single managed VPS (e.g. Forge) in Singapore or Jakarta; code stays host-agnostic.

## Consequences

- Good: cheap, vendor-light, alerts on the failures that matter (scheduler stall = seats stuck held).
- Bad / costs: up to 24 h data loss (RPO) during the pilot; manual restore.
- Follow-ups: point-in-time recovery before public launch.

## Alternatives considered

- Managed PaaS with built-in backups — higher monthly cost; fine if budget allows.
- Self-hosted monitoring stack — too much ops for the team size.
