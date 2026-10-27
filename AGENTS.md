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
npm test                  # Vitest (UI helpers)
```

For plain-HTTP local dev set `SESSION_SECURE_COOKIE=false` in `.env` (or run `herd secure`).
Never drop or reset the dev database to "start clean"; tests reset their own test database. There is no `.env.testing`:
`php artisan migrate --env=testing` migrates the **dev** database — just run `php artisan test`.

When you add or change an npm dependency, regenerate `package-lock.json` with the latest npm 11 (`npx -y npm@11 install` may resolve to your local npm; pin the newest 11.x, e.g. `npx -y npm@11.21.1 install --package-lock-only`). Older local npm drops optional peer entries (e.g. `react` under `@laravel/multiplex`), and CI's `npm ci` then fails.

Inertia pages live in `resources/js/pages` (lower case; CI runs on case-sensitive Linux). macOS ignores case, so a
checkout made before the rename may still show `Pages/`: fix it once with `mv resources/js/Pages resources/js/p && mv resources/js/p resources/js/pages`.

## Working in the code

- Seller-owned model → `use BelongsToOrganization`, a policy extending `OrganizationOwnedPolicy`, and
  `ExistsInCurrentOrganization` for ids in payloads. Cross-tenant tests use `tests/Support/InteractsWithTenants`.
  The schema, model and scope-bypass guard tests fail if any of this is skipped.
- Never bypass the scope — `acrossOrganizations()`, `withoutGlobalScope(s)`, `newModelQuery()`, `toBase()`,
  `getConnection()`, or any `DB::` call other than transaction control — outside
  `config/rakyt.php → cross_organization_allowlist` or `app/Admin/Queries`. The guard test lists the exact patterns.
- Bulk `insert` on tenant models gets the current organization automatically; `upsert` must include `organization_id`
  in its conflict target; `updateOrInsert`, `updateFrom`, `insertUsing` and `truncate` throw — use `updateOrCreate`.
  `organization_id` can never be updated. Log models, not their attributes (they are logged as class + key). Never add an unscoped relation to `Organization` (e.g. `users()`); authorise organizations via `OrganizationPolicy`.
- Never-flashed fields (passwords, tokens) live in `App\Security\SensitiveInput::FIELDS`.
- All UI copy lives in `lang/id/*.php` (Vue pages get it as props); never hard-code Bahasa strings in views.
- Audited actions go through `App\Audit\AuditLog`, inside the same `DB::transaction` as the change. A seller-level event must resolve an organization (it throws otherwise). A **platform-level** admin event (the admin is a member of no organization, F-010) is the only exception: add the case to `AuditAction::isPlatformLevel()`; `AuditLog` then stores it with a null `organization_id`, allowed because `AuditEvent implements OrganizationOptional` (and `audit_events.organization_id` is nullable). Never make a seller model `OrganizationOptional`.
- Admin area (F-010): the `/admin` route group sits behind `auth` + the `platform-admin` gate (and `platform-admin.pending` guards only the TOTP challenge) — never `organization`/`onboarded`, since the admin is not a tenant. The flag `users.is_platform_admin` and `two_factor_secret` are **not** mass-assignable (set only by `admin:grant`/`admin:revoke`); access needs required TOTP (hand-rolled on `pragmarx/google2fa`, not Fortify) within a rolling idle window (`config('rakyt.admin.session_lifetime')`). Admin pages hang on `AdminLayout.vue` and read the shared `admin` Inertia prop.
- Admin reads (F-012, ADR-0039): the admin has no current organization, so the tenant scope returns **nothing** — also
  in eager loads and `withCount`. In `app/Admin/Queries` read one organization with
  `Model::acrossOrganizations()->where('organization_id', …)` and drop the scope on related loads
  (`with(['departure' => fn ($q) => $q->withoutGlobalScope(OrganizationScope::class)])`); never `runAs()` the seller.
  Admin URLs take the organization id as a plain string resolved through the query class (no route-model binding), so
  the gate runs before any lookup and the access tests' literal `{param}` URIs still redirect/403.
- Admin writes (F-015, ADR-0038): record with `Actor::platformAdmin($admin)` and, where the action needs one, `AuditLog::record(…, reason:)` in the change's transaction; add every new non-GET `admin.*` route to `AdminAuditRecordingTest::ADMIN_WRITE_ROUTES` with its audit action. Audit events are read only in `app/Admin/Queries/AuditEventQueries` — seller code never reads `audit_events` (a guard test lists the exceptions). Redaction rules live in `App\Audit\AuditRedaction`.
- Inertia merges shared props (`HandleInertiaRequests::share()`: `errors`, `flash`, `seller`, `admin`, `account`, …) and page props **shallowly**: a page prop with the same name silently replaces the shared one (e.g. an Akun `account` prop once hid the verification banner's `copy`/`urls`). Never name a page prop after a shared key.
- `Model::shouldBeStrict()` is on outside production: accessing an attribute a model instance never loaded throws (`MissingAttributeException`), and mass-assigning a non-fillable key throws instead of dropping it. A column with only a DB default (e.g. a boolean flag) is absent on a freshly made/factory instance — give it a `protected $attributes` default so middleware and `share()` can read it everywhere, not only after a DB reload.
- Public endpoints get a named limiter (`throttle:public-booking`, …); customer token pages get `no-referrer`.
- Public pages (`/<slug>`, `/<slug>/<trip>`, F-002) are *fallback* routes that run without a session (no
  `StartSession`, CSRF or `ResolveCurrentOrganization`): the organization comes from the slug (`ResolvePublicOrganization`). Read trips via `PublicVisibility::trips()`, add fields to
  `PublicPagePresenter` one by one (the exact-keys test lists them), and never show a signed-in seller's data there.
  "Mulai Rp…" is `TripPricing::fromPrice()` everywhere.
- Test names: `test_ac_<id>_…` in lower case (Pint rewrites `test_AC_…` to `test_a_c_…`).
- Timestamps: PostgreSQL sessions run in UTC and Laravel writes Carbon values **without an offset**. Convert to UTC
  (`->utc()`) before storing or comparing in a query; compute local times with the organization's `timezone`.
- In tests Laravel caches controller instances per route: bind fakes (`PaymentReadiness`, `DepartureBookings`) **before**
  the first request of the test. `jsonb` does not keep key order: compare audit before/after with `assertEquals`.
- Seat changes lock the departure row (`lockForUpdate()`) inside `DB::transaction` and re-read `seats_taken`; reuse
  `DepartureService` (capacity) and `App\Bookings\BookingService` (bookings: create, hold release) rather than updating
  `capacity`/`seats_taken` directly. Lock order: organization → departure → bookings (ADR-0026 §3).
- Booking test fixtures take seats with `Tests\Support\BuildsBookings::bookingFor()`, never `withSeatsTaken()`: classes
  using the trait check `seats_taken` = Σ participants of holding bookings after every test (F-005 AC-23).
- Every organization gets a `booking_code` from its slug when it is inserted (ADR-0028 §2). Create test sellers with
  their slug (`createTenant(['slug' => …])`, `seller()`), not by updating it afterwards; raw `organizations` inserts
  need a `booking_code`.
- Session payloads are encrypted (`SESSION_ENCRYPT=true`): customer token pages run the session like the booking form,
  and Laravel stores each visited URL in it. JS UI logic lives in `resources/js/lib`
  with Vitest tests in `tests/js` (`npm test`).
- In tests the current organization stays on the last request's user: create seller rows inside `asTenant()`, and
  assert with `assertDatabase*` or `acrossOrganizations()`.
- `Organization::factory()` is past onboarding by default (`onboarding_skipped_at` set), so seller pages open normally;
  use `->onboarding()` (or `BuildsOnboarding::onboardingTenant()`) for a seller still in the guided flow — their page
  visits redirect to the resume step (F-001 E2). Find a user's organization with
  `ResolveCurrentOrganization::membershipOf()`, never a new unscoped query.
- Poppins (headings) reaches a page only through `@fonts` in its layout — `@vite` does not emit the Vite fonts helper's
  `@font-face` and preloads. Tests blank it with `Tests\Support\NullVite`, so test output never depends on a local build.
- Reset and verification links in `storage/logs/mail.log` are redacted on purpose; use an SMTP catcher (e.g. Mailpit)
  to click them locally. Inertia requests in tests need `X-Inertia-Version` once a build manifest exists, or they get 409.

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
