# AGENTS.md

Instructions for any AI coding agent (Claude, Codex, Cursor, Copilot, Gemini…) working in this repository.
Humans should read it too — it is the short version of how this project is run.

## What we are building

**Rakyt Booking** — a hosted, mobile-first booking tool for small Indonesian tour operators and activity providers.
Sellers publish trips, manage departures and seats, and receive organised bookings through one shareable link
(`rakyt.id/<seller>`). Customers pay the seller directly; Rakyt records the payment status.

It is **not** a marketplace, not an ecommerce platform, and not a payment processor.

Full context: [`docs/product/blueprint.md`](docs/product/blueprint.md) · UI reference: [`docs/design/`](docs/design/README.md)

## Read before you work

| You are about to… | Read first |
|---|---|
| Start any task | [`docs/README.md`](docs/README.md) and [`docs/roadmaps/README.md`](docs/roadmaps/README.md) (what is in scope *now*) |
| Build or change a feature | Its spec in [`docs/features/`](docs/features/README.md) |
| Make a technical or product choice | Existing ADRs in [`docs/decisions/`](docs/decisions/README.md) |
| Touch UI | [`docs/design/README.md`](docs/design/README.md) (tokens, status set, CTA rule, copy language) |
| Use domain words | [`docs/product/glossary.md`](docs/product/glossary.md) |
| Follow the workflow | [`docs/agents/workflow.md`](docs/agents/workflow.md) |

## Non-negotiables

1. **Tenant isolation.** Every seller-owned record is scoped to an `organization`. Authorise server-side (policies), never by hiding UI. Cross-seller data exposure is launch-blocking. Write a cross-tenant access test for every new seller-owned resource.
2. **No overbooking.** Seat changes happen inside a DB transaction with a row lock or atomic update. Holds expire and release automatically. See [ADR-0006](docs/decisions/0006-seat-hold-and-capacity-locking.md).
3. **Seller-direct payments.** Rakyt never holds, pools, splits or pays out customer funds. See [ADR-0003](docs/decisions/0003-seller-direct-payments.md).
4. **Scope discipline.** Only P0 work until the roadmap says otherwise. P2 items (marketplace, plugin store, reseller hierarchies, dynamic pricing, ERP/CRM, native apps, AI features) are out — do not build them "while you're there".
5. **Mobile first.** Design and test at phone width first. Large tap targets, clear validation, loading/error states.
6. **UI copy in Bahasa Indonesia**; code, comments, commits and docs in English. See [ADR-0007](docs/decisions/0007-ui-language-bahasa-indonesia.md).
7. **Secrets never in git.** `.env` stays local.

## Stack (see [ADR-0002](docs/decisions/0002-stack-laravel-inertia-vue-tailwind.md))

Laravel (modular monolith) · Inertia.js · Vue 3 · Tailwind CSS · Laravel queue + scheduler · S3-compatible media storage.
Local development runs on **Laravel Herd** (`rakyt-booking.test`). Database engine: see [ADR-0002](docs/decisions/0002-stack-laravel-inertia-vue-tailwind.md) (open question).

> The Laravel app has not been scaffolded yet. When it is, add the real commands (install, dev, test, lint) to the section below and remove this note.

## Commands

```bash
# To be filled in once the app is scaffolded, e.g.:
# composer install && npm install
# composer run dev
# php artisan test
# ./vendor/bin/pint && npm run lint
```

## Keeping the docs true (definition of done)

A change is not done until the docs match the code. In the same PR:

- **Feature behaviour changed?** Update the feature spec (status, acceptance criteria, open questions).
- **Made a choice someone could later ask "why?" about?** Add an ADR in `docs/decisions/` (copy `_template.md`). Never rewrite an accepted ADR — supersede it.
- **Scope or timing moved?** Update `docs/roadmaps/`.
- **Open question added or answered, or ADR proposed/decided?** Update the *Pending decisions* table in `docs/roadmaps/README.md`.
- **New domain term?** Add it to the glossary.
- **Learned something agents keep getting wrong?** Add it to this file.

## Conventions

- Branches: `feat/F-005-seat-hold`, `fix/…`, `docs/…`, `chore/…` — reference the feature ID when there is one.
- Commits: Conventional Commits (`feat(bookings): hold seats for 2 hours`).
- Feature IDs `F-NNN` and ADR numbers `NNNN` are permanent — never reuse or renumber.
- Dates in docs: ISO `YYYY-MM-DD`. Money: integer rupiah (no floats).
