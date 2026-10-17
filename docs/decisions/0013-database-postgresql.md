---
id: 0013
title: PostgreSQL as the database engine
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: [ADR-0002, ADR-0004, ADR-0006, F-009]
---

# 0013. PostgreSQL as the database engine

## Context

ADR-0002 left the database engine (PostgreSQL or MySQL) open, to be decided before the first migration. F-009 slice A creates the first migrations and the CI database. Overbooking protection (ADR-0006) relies on row locks and benefits from database-level constraints.

## Decision

- Use **PostgreSQL** in every environment: local (Herd), CI and production.
- CI runs tests against PostgreSQL (not SQLite) so locking and concurrency tests are meaningful.
- Use database constraints as a last line of defence where cheap, e.g. `CHECK (seats_taken >= 0 AND seats_taken <= capacity)`, partial unique indexes, and case-insensitive unique slugs.

## Consequences

- Good: CHECK constraints, partial indexes, transactional DDL; widely available as a managed service.
- Bad / costs: the host must offer PostgreSQL; slightly heavier local setup than SQLite.
- Follow-ups: pick hosting with managed PostgreSQL or backups that support it (F-009 Q2/Q3).

## Alternatives considered

- MySQL 8 — adequate, but weaker constraint support as a safety net.
