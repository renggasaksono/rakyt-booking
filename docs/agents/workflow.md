# Workflow

## 1. Pick up work

1. Open [`roadmaps/README.md`](../roadmaps/README.md). Only take items marked **Now**.
2. Open the feature spec. If its status is `draft`, the first job is to make it `ready`: fill acceptance criteria and resolve or explicitly defer open questions. Ask the human for anything that is a product call.
3. Check [`decisions/`](../decisions/README.md) for ADRs that constrain the work.
4. Create a branch: `feat/F-NNN-short-name`.
5. Set the spec's status to `in-progress`.

## 2. Build

- Start from the acceptance criteria; each one should map to at least one test.
- Seller-owned model? Add organization scoping, a policy, and a cross-tenant test **before** the happy path.
- Anything touching seats? Transaction + lock, plus a concurrency test (two bookings for the last seat).
- Build UI at phone width first; match tokens and rules in [`design/README.md`](../design/README.md).
- UI strings in Bahasa Indonesia, everything else in English.

## 3. Decide

If you hit a choice that a future reader might question (library, data shape, policy, trade-off):

1. Copy [`decisions/_template.md`](../decisions/_template.md) to the next number.
2. Status `proposed` if a human must confirm; `accepted` if it is a routine technical call within existing ADRs.
3. Link it from the feature spec.

Don't stall on reversible, low-stakes choices — pick, note it in the spec, move on.

## 4. Close

- Tests pass; lint passes.
- Spec updated: status `done` (or still `in-progress` with what's left), acceptance boxes ticked, changelog line added.
- Roadmap updated if the item is finished or moved.
- PR description uses the template checklist.

## Writing a new feature spec

1. Next free ID in [`features/README.md`](../features/README.md). IDs are never reused.
2. Copy [`features/_template.md`](../features/_template.md) to `F-NNN-kebab-name.md`.
3. Add a row to the feature index and place it on the roadmap.
