---
id: 0004
title: Single database, organization-scoped multi-tenancy
status: accepted
date: 2026-10-15
deciders: [Rengga]
related: [F-009, ADR-0002]
---

# 0004. Single database, organization-scoped multi-tenancy

## Context

Blueprint §4 "Multi-tenant security". Cross-seller data exposure is launch-blocking. Many small tenants; low ops budget.

## Decision

- One database. Every seller-owned table carries `organization_id`.
- Scope queries through the current organization (e.g. a global scope / tenant-aware base query) **and** enforce with Laravel policies server-side.
- Media paths are organization-prefixed; signed/authorised URLs for private files (payment evidence).
- Every seller-owned resource gets a test that tries to read/update it from another organization.
- `users` ↔ `organizations` via `organization_members` (role column; minimal in MVP).

## Consequences

- Good: simple ops, easy cross-tenant admin/support view.
- Costs: discipline required — a missing scope is a data leak. Tests are the guardrail.

## Alternatives considered

- Database-per-tenant — heavy ops for many tiny tenants.
