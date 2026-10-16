---
id: 0001
title: Record decisions as ADRs in the repo
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: []
---

# 0001. Record decisions as ADRs in the repo

## Context

The project will be built and maintained largely with AI agents across many sessions. Agents start each session without memory, so choices made in chat are lost unless they are written where the code is.

## Decision

We will keep product and technical decisions as numbered ADRs in `docs/decisions/`, alongside feature specs (`docs/features/`) and roadmaps (`docs/roadmaps/`). `AGENTS.md` at the root tells every agent to read and update them.

## Consequences

- Good: any agent or new contributor can rebuild context from the repo; decisions are reviewed in PRs.
- Costs: docs must be updated in the same PR as code, or they drift.
- Follow-ups: periodic docs health check (see `docs/agents/roles.md`).

## Alternatives considered

- External wiki / Notion — drifts from code, invisible to coding agents.
