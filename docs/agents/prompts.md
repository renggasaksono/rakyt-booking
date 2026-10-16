# Prompts

Copy, fill the brackets, paste.

## Make a spec ready

> Read AGENTS.md, docs/product/blueprint.md and docs/features/[F-NNN-…].md. Bring the spec to `ready`:
> write testable acceptance criteria, list edge cases, and list open questions that need my decision.
> Don't write code.

## Build a feature

> Read AGENTS.md, then implement docs/features/[F-NNN-…].md on branch feat/[F-NNN-name].
> Start with tests from the acceptance criteria. Update the spec status and changelog when done,
> and add an ADR for any non-obvious decision.

## Review a branch

> Act as the Reviewer in docs/agents/roles.md. Review branch [name] against its feature spec and the
> non-negotiables in AGENTS.md. List findings by severity with file:line. Don't change code.

## Record a decision

> We decided [decision] because [reason]. Write an ADR from docs/decisions/_template.md with the next number,
> link it from the affected feature specs, and update the ADR index.

## Docs health check

> Act as the Docs keeper in docs/agents/roles.md. Compare docs/roadmaps, feature statuses and the code.
> Report drift and broken links; propose fixes as a single docs PR.
