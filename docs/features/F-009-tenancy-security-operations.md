---
id: F-009
title: Tenancy, security & operations baseline
status: in-progress
priority: P0
roadmap: mvp-weeks-2-6
mockups: []
decisions: ["0002", "0003", "0004", "0006", "0007", "0012", "0013", "0014", "0015", "0016", "0017", "0018", "0020", "0021"]
updated: 2026-10-17
---

# F-009 · Tenancy, security & operations baseline

## Problem

Cross-seller data exposure or overbooking would end trust in the product; blueprint §4 and §9 treat both as
launch-blocking. A small team with a low ops budget also needs to know, without watching dashboards, when the app,
the scheduler or the backups stop working, and must be able to restore data after a mistake or outage.
The Pilot-go gate (end of week 4) requires "no known overbooking or cross-tenant leaks; backup restore tested".

## Scope

F-009 is cross-cutting. It ships the **primitives and guardrails** that every other feature uses, then the
**operations** needed before real customers arrive. It is delivered in three slices:

| Slice | When | Delivers |
|---|---|---|
| **A — Tenancy & app security primitives** | Week 2, before any other seller-owned model | Org scoping, policies pattern, cross-tenant test harness, schema guard, audit events, rate limiting, private media, security headers, error pages, secrets hygiene, structured logs |
| **B — Operations before pilot** | By end of week 4 (Pilot-go gate) | Error tracking, uptime checks, scheduler/queue heartbeat, daily backups + rehearsed restore, account-recovery runbook |
| **C — Hardening** | Week 6 | Cross-tenant coverage audit across all P0 resources, second restore rehearsal, security checklist pass |

**In:**
- Organization scoping for every seller-owned record (ADR-0004): `organization_id` column, global scope, auto-fill, immutable after create.
- Server-side authorization via Laravel policies; current organization resolved from the authenticated membership, never from request input.
- Reusable cross-tenant test harness + a schema/model guard test that fails when a new table or model is not tenant-scoped.
- `audit_events` table, writer service and the P0 list of audited actions (see Data & rules).
- Rate limiting for public and auth endpoints (defaults below).
- Media storage: organization-prefixed paths; public disk for published trip photos; private disk for payment evidence served only after an authorization check.
- Security baseline: HTTPS only, secure cookies, CSRF (framework default), security headers, dependency audit in CI, secret scanning in CI.
- Structured logs with request ID and tenant context, with PII redaction.
- Error tracking, uptime checks, scheduler and queue heartbeats with alerting.
- Daily automated DB backups to off-host storage, a written restore runbook, and a rehearsed restore.
- Account recovery (ADR-0018): session revocation, the 24-hour block on changing payment instructions, audit, and a staff-assisted recovery runbook for sellers who lost access to their email. The self-serve password-reset screens are part of F-001 (ADR-0012); F-009 sets the security rules they must meet.
- Bahasa Indonesia error pages (404, 419, 429, 500, 503), phone-width first.
- `.env.example` complete; no secrets in git.

**Out (explicitly):**
- The platform admin panel itself — specced in F-010–F-015 ([ADR-0011](../decisions/0011-platform-admin-access.md), proposed). F-009 only provides the hooks it needs (A8, A8a, `platform_admin` audit actor). An in-app system-health view is P1; until then the admin uses the hosting, log and error-tracking tools chosen here (ADR-0016).
- Seller-visible audit log / activity history UI (data is captured now; UI later if pilots ask).
- Staff accounts and roles beyond `owner` (P1). The policy layer must not assume roles exist beyond owner.
- Organization switching for users with several organizations (P1+; see Data & rules).
- Self-serve account or organization deletion, data export, formal PDP-law compliance programme (see Q9).
- SSO, 2FA, WhatsApp sign-in codes (ADR-0009 rejected), WAF, DDoS protection beyond the host/CDN defaults.
- Microservices, Kubernetes, log aggregation stacks, APM/tracing (ADR-0002 "not now").

## User stories

- As a seller, I want certainty that no other operator can see or change my trips, bookings, customers or payment proofs, so that I can trust Rakyt with my business.
- As a customer, I want my name, phone number and transfer proof to be visible only to the operator I booked with, so that my data is not exposed.
- As a seller who lost access to my sign-in, I want a clear way to get my account back without a stranger being able to take it over, so that I don't lose my bookings or have my bank details changed.
- As the Rakyt operator (founder), I want to be alerted when the app, the scheduler or the backups fail, and to be able to restore yesterday's data, so that a fault doesn't silently become overbooking or data loss.
- As a builder, I want tenancy and audit primitives that make the safe path the default, so that a forgotten scope is caught by tests, not by a seller.

## Acceptance criteria

Tags: **(auto)** = automated test in CI · **(ops)** = verified by a rehearsal or check, with evidence (date, who, result) recorded in `docs/operations/`.

### Slice A — Tenancy primitives

- [x] **A1 (auto)** Given a model uses the organization-scoping trait, when Org A's owner queries it (index, show, count, search), then only rows with Org A's `organization_id` are returned; a seller with zero records sees an empty result, never another org's rows.
- [x] **A2 (auto)** Given Org A's owner is signed in, when they request show/update/delete/custom actions for a record belonging to Org B by ID in the URL, then the response is **404** and the record is unchanged in the database.
- [x] **A3 (auto)** Given Org A's owner submits a form whose payload references an Org B record (e.g. `departure_id`, `trip_id`, `booking_id`), when the request is processed, then it fails validation (422) and nothing is written.
- [x] **A4 (auto)** Given a seller-owned record is created through the app, when `organization_id` is omitted, then it is filled from the current organization; when a request tries to set or change `organization_id` (mass assignment or update), then the value is ignored or rejected and the stored value is unchanged.
- [ ] **A5 (auto)** Given a public (unauthenticated) request for `rakyt.id/<slug-of-A>/…` that names a trip, departure or booking ID belonging to Org B, or an unpublished/draft trip of Org A, then the response is 404.
- [x] **A6 (auto) Schema guard:** Given the full migration set, when the guard test runs, then every table has a non-null, indexed, foreign-keyed `organization_id`, except tables on an explicit allowlist in the test (e.g. `users`, `organizations`, `sessions`, `jobs`, `failed_jobs`, `cache`, `migrations`, `password_reset_tokens`, framework tables). Adding a table without either is a failing test.
- [x] **A7 (auto) Model guard:** every Eloquent model whose table has `organization_id` uses the scoping trait and has a registered policy; otherwise the test fails.
- [x] **A8 (auto)** Given a queued job or scheduled command acts on seller data, when it runs without a signed-in user, then it either (a) receives an explicit `organization_id` and scopes to it, or (b) is on a reviewed allowlist of system jobs that deliberately query across organizations (hold expiry, deadline closing) and only touches the columns it needs. A test asserts the allowlist is exactly what is expected.
- [ ] **A8a (auto)** Given the admin panel (F-010–F-015, ADR-0011), then cross-organization reads happen only in admin-module query classes behind the `platform-admin` gate; seller-facing scopes and policies are unchanged, and every seller cross-tenant test stays green with the admin module installed.
- [ ] **A9 (auto) Harness:** a reusable cross-tenant test helper exists (two orgs, two owners, factories) that a feature test can call with a resource name and its routes to assert A2/A3. F-001, F-003, F-004, F-005, F-006, F-007 each use it for their resources.

### Slice A — Audit events

- [ ] **A10 (auto)** Given any action in the "Audited actions" table below, when it succeeds, then exactly one `audit_events` row is written **in the same DB transaction** as the change, with `organization_id`, actor (type + id), action, subject (type + id), `before` and `after` values of the changed fields, and `created_at` (UTC). If the change rolls back, no audit row exists. *Writer built and tested (`AuditLog`); ticks when each action's owning feature has its audit test — re-checked in C1.*
- [ ] **A11 (auto)** Given a system process changes data (hold expiry, deadline auto-close), then the audit row has actor type `system` and names the job. *`Actor::system()` built and tested; ticks with the hold-expiry (F-005) and auto-close (F-003) jobs.*
- [x] **A12 (auto)** Given an existing audit event, when application code attempts to update or delete it, then the attempt throws; deleting the audited subject does not delete its audit events.
- [x] **A13 (auto)** Audit events are tenant-scoped like any other seller-owned record: Org A cannot read Org B's events (covered by A6/A7/A9).

### Slice A — Abuse protection & app security

- [x] **A14 (auto)** Given the rate-limit defaults in Data & rules, when a client exceeds a limit, then: an Inertia form submit is sent back to the form with "Terlalu banyak percobaan. Coba lagi dalam {n} menit." as a `rate_limit` validation error and the typed input kept (passwords never flashed); a JSON request gets **429** with that message; any other request gets the Bahasa 429 page. *(Reworded 2026-10-17, approved by Rengga: a bare 429 would make Inertia replace the form with an error modal and lose the input.)*
- [ ] **A14b (auto)** Given a seller form hits a limit, then the form shows the `rate_limit` message and keeps what was typed. Owned by the first form that uses a limiter (F-001 sign-in).
- [x] **A15 (auto)** Customer limits keyed on phone number use the normalised E.164 number, so `0812…`, `+62812…` and `62812…` share one bucket. Seller sign-in and password-reset limits are keyed on the email (lower-cased, trimmed) and on IP, so `Budi@Mail.com` and ` budi@mail.com` share one bucket.
- [ ] **A15a (auto)** Given a password-reset request for an email that has no account, then the response and its timing match a request for a real account ("Jika email terdaftar, link reset sudah dikirim."), so sign-up emails cannot be enumerated.
- [x] **A16 (auto)** Responses carry: `Strict-Transport-Security` (production), `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` (customer booking/payment pages: `no-referrer`), and `frame-ancestors 'none'` (CSP) or `X-Frame-Options: DENY`. Session cookies are `Secure`, `HttpOnly`, `SameSite=Lax`.
- [x] **A17 (auto)** Given an expired CSRF token / session (419), a missing page (404), a rate limit (429), a server error (500) or maintenance (503), then the user sees a Bahasa Indonesia page that fits a 360 px wide screen without horizontal scroll, uses ink-outline buttons only (no gold CTA — nothing moves a booking forward), and never shows a stack trace.
- [ ] **A18a (auto)** CI runs the full test suite against **PostgreSQL** (ADR-0013), the same major version as production — never SQLite — so row locks, `CHECK` constraints and concurrency tests behave as they do in production.
- [x] **A18b (auto)** Database `CHECK` constraints back up the application rules F-009 owns: `audit_events.actor_type IN ('user','customer','system','rakyt_staff','platform_admin')`, and `organization_members.role IN ('owner')` (widened when roles arrive in P1). A test inserts a bad value directly and expects the database to reject it. The capacity constraints owned by F-003/F-005 are listed under Data & rules.
- [ ] **A18 (auto)** CI runs `composer audit` and `npm audit --audit-level=high` and a secret scanner (e.g. gitleaks) on every PR; a high-severity finding or a detected secret fails the build.

### Slice A — Media

Media rules follow ADR-0015 (accepted); if it changes limits or formats, ADR-0015 wins over the numbers below.

- [x] **A19 (auto)** Uploaded files are stored under `org/{organization public_id}/…` (ADR-0015) with random (UUID) file names; the original file name is never used as the path.
- [ ] **A20 (auto)** Payment evidence is stored on a private disk. Given Org A's owner, when they open evidence for their own booking, then it is served (stream or temporary signed URL, TTL ≤ 5 min) after a policy check; given Org B's owner or an anonymous user with the storage path or an expired URL, then they get 403/404.
- [ ] **A21 (auto)** Given a customer, when they open their own evidence via their booking access token (ADR-0017), then it is served; with another booking's token, it is not.
- [x] **A22 (auto)** Uploads are validated server-side by detected MIME type (not extension) and size (evidence: JPEG/PNG/WebP/HEIC ≤ 5 MB per F-006); public trip photos are re-encoded so EXIF metadata (incl. GPS) is stripped.

### Slice A — Secrets & logs

- [x] **A23 (auto)** `.env.example` lists every env variable the app reads (test compares `env()` keys used in `config/` against `.env.example`), with placeholder values only. `.env` is git-ignored.
- [x] **A24 (auto)** Logs are structured (JSON) and every entry from a web request carries `request_id`, `user_id` and `organization_id` when known. A test asserts that passwords, password-reset tokens, session and booking access tokens and signed URLs are never logged, and phone numbers are masked (e.g. `+62812****789`).

### Slice B — Operations before pilot (end of week 4)

Operations follow ADR-0016.

- [ ] **B1 (ops)** An unhandled exception in production appears in the error tracker within 5 minutes with environment, release and request ID, with PII scrubbing on (no request bodies, cookies or phone numbers), and triggers an alert to the configured channel (Q4).
- [ ] **B2 (ops)** An external uptime monitor checks the health endpoint and one public seller page every ≤ 5 minutes; two consecutive failures trigger an alert. Verified by taking the app down on staging.
- [ ] **B3 (auto + ops) Scheduler heartbeat:** the scheduler writes a heartbeat every minute; the health endpoint returns 503 when the heartbeat is older than 5 minutes. Given the scheduler is stopped, then an alert fires within 10 minutes (verified once on staging). Same for the queue worker if any P0 feature uses queued jobs.
- [ ] **B4 (auto)** Scheduled tasks that release holds or close departures run with "one server" and "without overlapping" protection and are idempotent: running the same task twice, or two instances at once, produces the same end state and one audit event per change.
- [ ] **B5 (auto)** Booking correctness does not depend on the scheduler: with the scheduler stopped, a booking on a departure past its booking deadline is still rejected server-side (F-005 rule), and capacity is still enforced inside the booking transaction (ADR-0006).
- [ ] **B6 (ops)** The health endpoint reports only `ok` / `degraded` per check (DB, cache, scheduler, queue) — no versions, hostnames or error details.
- [ ] **B7 (ops) Backups:** a full, consistent DB backup runs daily (target 02:00 WIB) to S3-compatible storage at a **different provider or account** from the app server, encrypted at rest, with the retention set in Q3. A failed backup, or no successful backup for 26 hours, raises an alert.
- [ ] **B8 (ops) Restore rehearsal:** following only the written runbook in `docs/operations/restore.md`, someone restores the latest backup into a fresh database, points a staging app at it, signs in as a test seller and opens a known booking and its payment evidence. Time taken and backup age are recorded. Done once before the Pilot-go gate and again in week 6.
- [ ] **B9 (ops)** Media bucket protection is in place per Q3 (versioning or a scheduled copy) and covered by the runbook.
- [ ] **B10 (ops) Staff-assisted recovery runbook** `docs/operations/account-recovery.md` exists for sellers who lost access to their email. It states the accepted proof of identity (Q5), who may perform recovery, and that recovery runs only through the artisan command in B11c.
- [ ] **B11 (auto) Self-serve password reset (ADR-0012, ADR-0018):** Given a seller requests a reset, then a single-use reset link is emailed that expires within 60 minutes. Requesting a new link invalidates the old one, and a used or expired link shows "Link sudah tidak berlaku. Minta link baru." After a successful reset:
  - (a) every other session of that user is revoked; the session that completed the reset stays signed in;
  - (b) changes to payment instructions are blocked for 24 hours. The server rejects them and the UI shows "Demi keamanan, rekening baru bisa diubah {tanggal, jam}.";
  - (c) an `auth.password_reset` audit event is written.
- [ ] **B11b (auto)** Given a signed-in seller changes their password, then other sessions are revoked and `auth.password_changed` is audited. The 24-hour payment-instruction block applies only to resets and email changes, not to a normal password change by a signed-in seller who knows the current password.
- [ ] **B11c (auto) Staff-assisted recovery:** Given Rakyt staff run the recovery artisan command (never ad-hoc SQL) to set a new verified email for the account, then all sessions are revoked, a reset link goes to the new email, payment-instruction changes are blocked for 24 hours, and `auth.account_recovered` is audited with actor `rakyt_staff:<name>`.
- [ ] **B12 (auto) Email change:** Given a signed-in seller changes their sign-in email, then the change takes effect only after they click a verification link sent to the new address (the old address is told the change was requested). On verification, other sessions are revoked, payment-instruction changes are blocked for 24 hours, and `auth.email_changed` is audited with the old and new email.

### Slice C — Hardening (week 6)

- [ ] **C1** A review lists every seller-owned resource and route shipped in P0 (at least: organizations profile, organization members, trips, trip media, departures, bookings, date requests, payment instructions, payment records, payment evidence, audit events) and confirms each has a policy and a passing cross-tenant test (A2/A3, plus A5 for public routes). Gaps are launch-blocking.
- [ ] **C2** Production config check passes: `APP_ENV=production`, `APP_DEBUG=false`, HTTPS enforced, trusted proxies configured so rate limits see real client IPs.
- [ ] **C3** Second restore rehearsal (B8) done and recorded.

## Data & rules

### Tenancy model (ADR-0004)

- One database. Every seller-owned table has `organization_id` (FK, not null, indexed). Composite indexes start with `organization_id` where lists are filtered by org.
- **Current organization** is resolved server-side from the signed-in user's `organization_members` row. It is never taken from a route parameter, query string, body or header.
- P0 assumes **one membership per user** (F-001 creates owner + organization atomically). The schema allows many; if a user somehow has more than one, the app refuses to guess and shows an error rather than picking one. Organization switching is out of scope.
- Cross-tenant access returns **404, not 403**, so IDs of other sellers' records are not confirmed to exist.
- Route model binding for nested routes is scoped (child must belong to parent, parent must belong to current org).
- Validation `exists` rules on IDs are org-scoped.
- Cache keys and queued-job payloads that touch seller data include `organization_id`.
- System jobs that intentionally cross organizations (hold expiry, deadline auto-close) and the admin-module query classes (ADR-0011) are the **only** code allowed to remove the global scope; system jobs are listed in one allowlist (A8), admin queries are covered by A8a.
- No super-admin bypass through seller routes, scopes or memberships; the platform admin is not an organization member (ADR-0011). Production data fixes by Rakyt staff go through artisan commands that write audit events (actor `rakyt_staff:<name>`).
- **Customer access** (no account, ADR-0017): a customer reaches their booking/payment page through an **unguessable access token** (at least 128 random bits) in the URL, never through the human-readable booking reference alone. `RKB-MMDD-NNNN` can be guessed and would expose other customers' names and phone numbers. (Built in F-005/F-006.)

### Owner organization tables (built in slice A)

Slice A creates the minimal `organizations` (id, timestamps, plus whatever F-001 adds) and `organization_members` (`organization_id`, `user_id`, `role` = `owner`) tables, the scoping trait, the current-organization resolver and the test harness. F-001 adds profile fields and the sign-up flow on top. Agree this split with F-001 before building.

### `audit_events`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint / ulid | |
| `organization_id` | FK, not null | Tenant scope |
| `actor_type` | string + `CHECK` | `user`, `customer`, `system`, `rakyt_staff`, `platform_admin` (admin actions, F-015) |
| `actor_id` | nullable | User ID, booking ID for customer, job name for system, staff name |
| `action` | string | Dot-namespaced, e.g. `departure.capacity_changed` |
| `subject_type`, `subject_id` | string, id | No FK cascade — events outlive their subject |
| `before`, `after` | `jsonb` | Only the changed fields. Money as integer rupiah. Never store password hashes or tokens |
| `ip` | nullable string | For user/customer actions |
| `created_at` | timestamp (UTC) | No `updated_at`; append-only |

**Audited actions (P0):**

| Area | Actions |
|---|---|
| Bookings | `booking.created`, `booking.status_changed`, `booking.hold_expired` (system) |
| Payments | `payment.evidence_uploaded`, `payment.verified`, `payment.rejected`, `payment_record.amount_changed` |
| Departures | `departure.created`, `departure.price_changed`, `departure.capacity_changed`, `departure.status_changed` (incl. auto-close by deadline, system), `departure.deleted` |
| Trips | `trip.base_price_changed`, `trip.published`, `trip.unpublished` |
| Payment instructions | `payment_instructions.changed` (bank / e-wallet / QRIS details, before/after) — high risk: a hijacked account could redirect customer payments |
| Organization | `organization.slug_changed`, `organization.member_added`, `organization.member_removed` |
| Auth | `auth.password_reset`, `auth.password_changed`, `auth.email_changed`, `auth.account_recovered` |

Retention: kept indefinitely in P0. Not shown in seller UI in P0 (Q7).

### Rate-limit defaults (config, tunable without deploy changes to code)

| Endpoint | Key | Default |
|---|---|---|
| Seller sign-in attempts (ADR-0012) | email + IP | 5 per minute, then lockout per Laravel default |
| Seller sign-in attempts | IP | 30 per 10 min |
| Password-reset link request | email | 3 per 15 min |
| Password-reset link request | IP | 20 per hour |
| Sign-up | IP | 10 per hour |
| Create booking / date request (public) | normalised phone | 5 per hour |
| Create booking / date request (public) | IP | 30 per 10 min |
| Upload payment evidence | booking token | 10 per hour |
| Slug availability check | IP | 60 per min |

Indonesian mobile carriers use carrier-grade NAT, so many customers share one IP. Per-IP limits are deliberately loose. The main key is the phone number or token for customers, and the email for sellers. All limits live in config.

### Time

- All timestamps are stored and compared in **UTC**; the scheduler runs in UTC.
- Display and deadline calculations (e.g. "H-3, 23.59") use the organization's time zone (ADR-0014, accepted). Field and default are owned by F-001/F-003.

### Logging & PII

- Customer and seller phone numbers, names and bank details are personal data. Never in logs, error-tracker payloads or URLs other than the token-bearing customer link.
- Log retention: 14 days on the host.

### Backups & restore

- DB: a consistent `pg_dump` daily; encrypted; stored off-host; retention per ADR-0016 / Q3.
- Media: per Q3. Payment evidence is the only media that cannot be re-uploaded by the seller easily.
- Runbook location: `docs/operations/restore.md` (created by the builder in slice B).

### Concurrency / overbooking (what F-009 provides)

- CI runs the test suite against **PostgreSQL** (ADR-0013), not SQLite, so row-lock and concurrency tests (ADR-0006, F-005 "two bookings for the last seat") mean something (A18a).
- **`CHECK` constraints as the last line of defence** (the app still validates first). F-009 owns the audit and membership constraints (A18b). The capacity constraints are owned by F-003/F-005 and are listed here so reviewers check them: `trip_departures.capacity >= 0`; if a seat counter column exists, `seats_taken BETWEEN 0 AND capacity`; `bookings.participants >= 1`; money columns (`base_price`, `price_override`, amounts due/paid) `>= 0`. If seats are counted from bookings instead of a counter, the row lock in ADR-0006 is the guard and no counter constraint applies.
- A test helper that runs two booking attempts on separate DB connections, used by F-005.
- Scheduler failure never causes overbooking: capacity is enforced in the booking transaction and deadlines are checked at booking time (B5). A stalled scheduler can only cause *under*-booking (holds not released), which the heartbeat alert (B3) catches.

### Edge cases

- Seller with no trips/bookings yet: every list is empty; no "fallback" query ever drops the scope.
- Seller tries another seller's ID in the URL, the payload, or a nested route (A2, A3, A5).
- Customer guesses another booking reference (token required — Data & rules).
- Two scheduler instances on two servers, or a slow run overlapping the next minute (B4).
- Backup taken during writes (consistent snapshot); restore of a DB with holds already past expiry — the next scheduler run releases them and audits as `system`.
- Session expires mid-form on a phone (419 page, A17); rate limit hit mid-form (A14 keeps input).
- Signed evidence URL forwarded in WhatsApp: expires within 5 minutes (A20).
- Referrer leakage of the customer token to `wa.me` or images: `no-referrer` on customer pages (A16).
- Account takeover through a password reset, an email change or staff recovery: other sessions are revoked, payment-instruction changes are blocked for 24 hours, and the event is audited (B11–B12, ADR-0018).
- Password reset requested for an email that isn't registered: same response as for a real account (A15a).
- Reset link opened twice, or after a newer link was sent: rejected (B11).

## Implementation status (slice A)

Built on `feat/F-009-tenancy-baseline` (mechanics in [ADR-0021](../decisions/0021-tenancy-enforcement-mechanics.md), layout in [ADR-0020](../decisions/0020-app-scaffold-and-tooling.md)).
Primitives are exercised by a test-only resource (`tests/Fixtures/Tenancy/ProbeNote`) until real seller resources exist.

| Piece | Where |
|---|---|
| Scope, trait, current organization, resolver middleware, job middleware, 404 base policy, org-scoped `exists` rule | `app/Tenancy/` |
| Cross-tenant harness (A9) | `tests/Support/InteractsWithTenants.php` — `createTenants()`, `asTenant()`, `assertCrossTenantNotFound()`, `assertCrossTenantPayloadRejected()` |
| Schema / model / bypass guards (A6–A8) | `tests/Feature/Tenancy/` |
| Audit writer and actions (A10–A13) | `app/Audit/`, `audit_events` migration (append-only trigger) |
| Rate limiters (`throttle:seller-login`, `password-reset`, `signup`, `public-booking`, `evidence-upload`, `slug-check`) | `app/Security/RateLimits.php`, values in `config/rakyt.php` |
| Headers, `no-referrer` route middleware, request id | `app/Security/Middleware/` |
| Error pages | `resources/views/errors/` |
| Media paths, upload rules, EXIF stripping, signed private URLs | `app/Media/` |
| JSON logs + redaction | `app/Logging/`, `json` channel in `config/logging.php` |
| CI (PostgreSQL 17, Pint, ESLint, build, audits, gitleaks) | `.github/workflows/ci.yml` |

**Still open in slice A** (each ticks when the named feature lands):

- **A5** public 404s — needs the public pages and their slug context (F-002).
- **A8a** admin queries — the bypass guard already permits only `app/Admin/Queries`; the `platform-admin` gate comes with F-010.
- **A9** harness exists and is proven on the probe resource; ticks when F-001, F-003–F-007 use it for their resources.
- **A15a** reset enumeration — the reset screen is F-001 (A7 there); the `password-reset` limiter is ready.
- **A18 / A18a** — CI is configured; tick after the first green run on GitHub. Gitleaks did not run locally.
- **A20 / A21** — private disk and ≤ 5-minute signed URLs are built and tested; the policy/token check that issues them comes with payment evidence (F-006).

- **A10 / A11** — audit writer and system actor are built; each audited action ticks with its owning feature.
- **A14b** — the form-side display of the rate-limit message (F-001).

**Decisions taken while building:**

- **A19 path prefix** follows ADR-0015 (`org/{organization public_id}/…`); `organizations.public_id` (UUID) was added for it. Criterion reworded.
- **A14** reworded to redirect-with-error for Inertia forms, 429 for JSON and plain requests (approved 2026-10-17).
- **Two-connection concurrency helper** (Data & rules) moves to F-005, which owns the last-seat test and the tables it locks (noted in F-005).
- **Scope-bypass guard** covers Eloquent bypasses (`newModelQuery`, `newQueryWithoutScope(s)`, `toBase`, `getQuery`, `getConnection`) and any database access outside Eloquent (`DB::` except transaction control, `app('db')`, `DatabaseManager`) in `app/` and `routes/`; only the four tenancy mechanism files are exempt.
- **Every log channel** redacts (`RedactLogs` tap); objects and models in log context are flattened and scrubbed; traces carry no argument values.
- **`audit_events`** also rejects `TRUNCATE` (statement-level trigger).
- **Fields never flashed** to the session live in one list (`App\Security\SensitiveInput`), used by both the exception handler and the rate-limit redirect.
- **Non-numeric or out-of-range ids** (beyond bigint) are a 404 in URLs (trait route binding) and a 422 in payloads, never a PostgreSQL 500 (`App\Tenancy\IntegerKey`). Scoped child bindings on a non-tenant parent still need checking when the first one is added.

**Review follow-ups** (from the 2026-10-17 and 2026-10-18 reviews; each is also noted in its owning spec):

- [ ] **R1** Empty phone/email collapses everyone into one rate-limit bucket (`booking-phone:`); return `Limit::none()` for an empty key (the IP limit still applies) and fail loudly if `evidence-upload` has no `token` route parameter. — before F-005.
- [ ] **R2** The local `private` disk's `serve => true` also registers a signed `PUT /storage/{path}` upload route; before A20, stream evidence from an authorised controller (`serve => false`) or ban `temporaryUploadUrl()` with a guard test. — F-006.
- [ ] **R3** Cap pixel dimensions for public photos (`dimensions:max_width,max_height` or a `getimagesize` check) before GD decodes them. — F-001/F-003 photo upload.
- [ ] **R4** `AuditLog` should throw when the subject's organization differs from the current one (the trait silently overrides it), and resolve a `User` subject through its membership so `auth.password_reset` works for a guest. — F-001 (reset flow).
- [x] **R5** Query-builder writes: done 2026-10-18 via `TenantBuilder` — bulk inserts/upserts get the organization rule, `insertUsing` is refused, updates/upserts/increments cannot touch `organization_id`.
- [ ] **R6** `DatabaseSeeder` uses `WithoutModelEvents`, which disables the tenancy and audit hooks; drop it when seeders are written.
- [ ] **R7** Inertia visits that hit 409/419/429 HTML pages show them in Inertia's error modal; add an Inertia-aware renderer with F-001.
- [ ] **R8** Rate limits use `$request->ip()`; configure trusted proxies with hosting (C2).
- [ ] **R9** Laravel's `emergency` logger (used only when a configured log channel cannot be built) takes no taps, so it is not redacted. Accept, or wrap it, at slice C.
- [ ] **R10** `ExistsInCurrentOrganization` ignores soft deletes; add a `whereNull('deleted_at')` option when the first soft-deleting model arrives.

## Readiness

Nothing blocks building. The database engine is decided (PostgreSQL, ADR-0013) and seller sign-in is email + password (ADR-0012), so all of slice A can start.
Q2–Q5 have recommended defaults (most are captured in ADR-0016 and ADR-0018). They must be confirmed before slice B is closed at the Pilot-go gate (end of week 4), but they do not block building it. Q6–Q9 do not block anything.

## Open questions

- [x] **Q1 Database engine.** Resolved 2026-10-17: **PostgreSQL** (ADR-0013, accepted). CI runs on PostgreSQL (A18a) and `CHECK` constraints back up key rules (A18b, Data & rules).
- [x] **Q2 Hosting target and backup storage** — decided 2026-10-17 (ADR-0016 accepted): one VPS managed with Laravel Forge (or an equivalent managed Laravel host) in Singapore or Jakarta, with backups written to S3-compatible object storage at a different provider. The code stays host-agnostic, so this blocks only the closing of slice B.
- [x] **Q3 Backup policy (RPO / retention / media)** — decided 2026-10-17 (ADR-0016 accepted): daily DB backup, keep 14 daily and 8 weekly; accept up to 24 hours of data loss during the pilot (bookings also exist in WhatsApp chats); use point-in-time recovery if the database is managed; turn on 30-day versioning for the media bucket. Revisit before public launch.
- [x] **Q4 Error tracker and alert channel** — decided 2026-10-17 (ADR-0016 accepted): Sentry (free tier, official Laravel SDK, built-in PII scrubbing); alerts to the founder's email and phone push. Low-stakes and reversible, so the builder may go ahead with this if there is no answer.
- [x] **Q5 Staff-assisted recovery when the seller has lost their email: what proof is enough?** — decided 2026-10-17 (ADR-0018 accepted). Self-serve reset, session revocation and the 24-hour block are settled in ADR-0018. Only the identity check for staff-assisted recovery is still open. *Recommended:* the founder verifies that (a) the requester writes from the WhatsApp number stored on the organization profile, (b) their KTP name matches the account-holder name in the payment instructions, and (c) they can name two recent booking references with amounts. Then the B11c command sets the new email. Why: a takeover lets an attacker swap the bank account and collect customers' transfers. This is needed for the runbook (B10), not for the code.
- [x] **Q6 Rakyt staff access to seller data in P0** — superseded 2026-10-17: the sole platform admin gets read-only cross-seller access through the admin panel (F-010–F-015, [ADR-0011](../decisions/0011-platform-admin-access.md), proposed). Production DB access stays limited to named people; data fixes still go through audited artisan commands.
- [ ] **Q7 Should sellers see an audit/history trail in P0?** — Rengga. *Recommended:* no. Capture now, show later (e.g. booking history in F-007) if pilots ask.
- [x] **Q8 Time zones (WIB / WITA / WIT)**: decided 2026-10-17, ADR-0014 accepted; F-001/F-003 own it. *Recommended:* an organization time-zone field, default `Asia/Jakarta`, with `Asia/Makassar` and `Asia/Jayapura` selectable; deadlines like "H-3 23.59" use it. Does not block F-009, which only requires "store in UTC".
- [ ] **Q9 Privacy notice and Indonesia's PDP law (UU 27/2022)** — Rengga. *Recommended:* before pilot, publish a short privacy notice and terms (Bahasa), linked from sign-up and the booking form; handle deletion/access requests manually; get a formal review before public launch. Kept outside F-009 scope unless you want it here.

## Changelog

- 2026-10-17 — created
- 2026-10-17 — audit/tenancy hooks for admin panel (F-010–F-015)
- 2026-10-17 — planner pass: split into slices A/B/C; testable Given/When/Then criteria for tenancy, audit, rate limits, media, logs, ops, backups and recovery; data rules (current-org resolution, 404 on cross-tenant, customer access token, audit schema and action list, rate-limit defaults, UTC); edge cases; resolved "error tracking service" as a non-blocking default (Q4); status stays `draft`, blocked only by Q1 (DB engine)
- 2026-10-17 — after decisions: Q1 closed with PostgreSQL (ADR-0013); CI on PostgreSQL and `CHECK` constraints added (A18a, A18b). Recovery rewritten for email + password (ADR-0012, ADR-0018): self-serve reset link, revoke other sessions, 24-hour payment-instruction block on reset, email change or staff recovery (B11–B12). Sign-in and reset rate limits keyed by email + IP; customer limits stay phone-keyed. Added links to ADR-0014/0015/0016/0017; dropped ADR-0009. Status → `ready`
- 2026-10-17 — ADR-0014 to ADR-0018 accepted; Q2, Q3, Q4, Q5 and Q8 closed
- 2026-10-17 — merged with admin panel (F-010–F-015, ADR-0011): admin panel out of F-009 scope, A8a added, `platform_admin` audit actor, Q6 superseded; branch ADRs renumbered 0012–0019
- 2026-10-17 — slice A built (builder): tenancy primitives, guards and harness, audit events, rate limiters, security headers, Bahasa error pages, media primitives, `.env.example` guard, JSON logs, CI. A1–A4, A6–A8, A10–A17, A18b, A19, A22–A24 ticked; A5, A8a, A9, A15a, A18, A18a, A20, A21 wait on the features named above. ADR-0020, ADR-0021 added. Status → `in-progress`
- 2026-10-17 — review fixes: exception and SQL-binding redaction in logs (A24), raw-query coverage in the bypass guard (A8), non-numeric ids → 404/422, `OrganizationPolicy`, Bahasa copy moved to `lang/id` with Indonesian validation/auth/password messages and `id` locale defaults. A14 reworded and split (A14b → F-001); A10/A11 unticked until their owning features audit; A19 reworded to ADR-0015. Review follow-ups R1–R8 recorded
- 2026-10-18 — second review fixes: log redaction covers objects/models, argument-free traces and every channel (A24); bulk inserts/upserts and query-builder updates enforce the organization rule (`TenantBuilder`, R5 closed); wider scope-bypass guard; ids beyond bigint → 404/422; `audit_events` rejects TRUNCATE; reset tokens never flashed; unscoped `Organization::users()` removed; HSTS on any HTTPS response; same-site back links; `0062…` phone numbers; ADR-0020/0021 accepted by Rengga; hand-offs noted in F-001, F-003, F-006; CI lock file fixed. R9, R10 added
