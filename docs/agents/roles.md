# Roles

One agent can play several roles in a session, but keep the hats distinct — especially builder vs reviewer.

## Planner

- Turns blueprint and roadmap items into feature specs with testable acceptance criteria.
- Surfaces open questions; does not answer product questions on the human's behalf.
- Owns: `docs/features/`, `docs/roadmaps/`.

## Builder

- Implements one feature spec at a time on its own branch.
- Writes tests from the acceptance criteria; follows `AGENTS.md` non-negotiables.
- Records decisions it makes as ADRs.
- Owns: application code, tests, spec status.

## Reviewer

- Reviews a branch against its spec and the non-negotiables, with fresh eyes (ideally a separate session).
- Checklist: tenant scoping + cross-tenant test · transactional capacity · no P2 scope creep · Bahasa UI copy · mobile layout · docs updated.
- Reports findings; does not silently fix them.

## Docs keeper

- Runs periodically (e.g. end of each roadmap week).
- Checks that spec statuses, roadmap and code agree; flags drift.
- Checks links in `docs/` resolve and ADR index is complete.
