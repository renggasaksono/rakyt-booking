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
8. **Admin is a separate boundary.** `/admin` (F-010–F-015) is gated server-side to the platform admin; cross-organization reads live only in admin query classes — never loosen seller scopes or policies to make admin work easier. Every admin route gets authorization tests (guest, seller, admin) and every sensitive admin action an audit test. No impersonation, DB browser, multi-admin roles or booking/payment editing without a new ADR. See [ADR-0011](docs/decisions/0011-platform-admin-access.md).

## Stack (see [ADR-0002](docs/decisions/0002-stack-laravel-inertia-vue-tailwind.md))

Laravel (modular monolith) · Inertia.js · Vue 3 · Tailwind CSS · Laravel queue + scheduler · S3-compatible media storage.
Local development runs on **Laravel Herd** (`rakyt-booking.test`). Database: **PostgreSQL** ([ADR-0013](docs/decisions/0013-database-postgresql.md)).
App layout and tooling: [ADR-0020](docs/decisions/0020-app-scaffold-and-tooling.md). Tenancy mechanics: [ADR-0021](docs/decisions/0021-tenancy-enforcement-mechanics.md).

## Commands

```bash
# First run — needs PostgreSQL 17 (e.g. DBngin) with databases `rakyt_booking` and `rakyt_booking_test`
composer install && npm install
cp .env.example .env && php artisan key:generate && php artisan migrate

composer run dev          # app + Vite
php artisan test          # PHPUnit on PostgreSQL (rakyt_booking_test)
./vendor/bin/pint         # PHP format/lint (CI runs --test)
npm run lint              # ESLint (CI runs --max-warnings=0)
npm run build             # Vite production build
```

For plain-HTTP local dev set `SESSION_SECURE_COOKIE=false` in `.env` (or run `herd secure`).
Never drop or reset the dev database to "start clean"; tests reset their own test database.

## Working in the code

- Seller-owned model → `use BelongsToOrganization`, a policy extending `OrganizationOwnedPolicy`, and
  `ExistsInCurrentOrganization` for ids in payloads. Cross-tenant tests use `tests/Support/InteractsWithTenants`.
  The schema, model and scope-bypass guard tests fail if any of this is skipped.
- Never bypass the scope — `acrossOrganizations()`, `withoutGlobalScope(s)`, `newModelQuery()`, `toBase()`,
  `getConnection()`, or any `DB::` call other than transaction control — outside
  `config/rakyt.php → cross_organization_allowlist` or `app/Admin/Queries`. The guard test lists the exact patterns.
- Bulk `insert`/`upsert` on tenant models get the current organization automatically; `organization_id` can never be
  updated. Never add an unscoped relation to `Organization` (e.g. `users()`); authorise organizations via `OrganizationPolicy`.
- Never-flashed fields (passwords, tokens) live in `App\Security\SensitiveInput::FIELDS`.
- All UI copy lives in `lang/id/*.php` (Vue pages get it as props); never hard-code Bahasa strings in views.
- Audited actions go through `App\Audit\AuditLog`, inside the same `DB::transaction` as the change.
- Public endpoints get a named limiter (`throttle:public-booking`, …); customer token pages get `no-referrer`.
- Test names: `test_ac_<id>_…` in lower case (Pint rewrites `test_AC_…` to `test_a_c_…`).
- In tests the current organization stays on the last request's user: create seller rows inside `asTenant()`, and
  assert with `assertDatabase*` or `acrossOrganizations()`.

## Keeping the docs true (definition of done)

A change is not done until the docs match the code. In the same PR:

- **Feature behaviour changed?** Update the feature spec (status, acceptance criteria, open questions). Set `done` only when its acceptance criteria are covered by passing tests — scaffolding is not `done`.
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
