# Decisions (ADRs)

Architecture & product decision records. Short, append-only, numbered.

- One decision per file: `NNNN-kebab-title.md`, from [`_template.md`](_template.md).
- Never edit the substance of an `accepted` ADR. To change course, write a new one and mark the old one `superseded by NNNN`.
- `proposed` ADRs need a human to accept or reject them. Agents may implement against a proposed ADR only if the spec says so.

## Index

| # | Decision | Status | Date |
|---|---|---|---|
| [0001](0001-record-decisions-as-adrs.md) | Record decisions as ADRs in the repo | accepted | 2026-10-17 |
| [0002](0002-stack-laravel-inertia-vue-tailwind.md) | Laravel + Inertia + Vue 3 + Tailwind, modular monolith | accepted (DB engine: [0013](0013-database-postgresql.md)) | 2026-10-15 |
| [0003](0003-seller-direct-payments.md) | Seller-direct payments; Rakyt never holds funds | accepted | 2026-10-15 |
| [0004](0004-organization-scoped-multi-tenancy.md) | Single database, organization-scoped multi-tenancy | accepted | 2026-10-15 |
| [0005](0005-trip-and-departure-domain-model.md) | Trip (offering) vs departure (bookable occurrence) | accepted | 2026-10-15 |
| [0006](0006-seat-hold-and-capacity-locking.md) | Seat holds with automatic expiry; transactional capacity | accepted | 2026-10-17 |
| [0007](0007-ui-language-bahasa-indonesia.md) | UI copy in Bahasa Indonesia; code & docs in English | accepted | 2026-10-17 |
| [0008](0008-brand-tokens-and-cta-rule.md) | Rakyt Sogan tokens, status set, one-gold-CTA rule | accepted | 2026-10-17 |
| [0009](0009-whatsapp-otp-authentication.md) | WhatsApp OTP sign-in for sellers | rejected (replaced by 0012) | 2026-10-17 |
| [0010](0010-freemium-subscription-no-transaction-fee.md) | Freemium + subscription; no mandatory transaction fee | accepted (limit check deferred: 0019) | 2026-10-15 |
| [0011](0011-platform-admin-access.md) | Platform admin inside the same app, explicitly authorised, no impersonation | proposed | 2026-10-17 |
| [0012](0012-email-password-authentication.md) | Email + password sign-in for sellers | accepted | 2026-10-17 |
| [0013](0013-database-postgresql.md) | PostgreSQL as the database engine | accepted | 2026-10-17 |
| [0014](0014-timezones-utc-storage.md) | Store times in UTC; deadlines in the organization's time zone | accepted | 2026-10-17 |
| [0015](0015-trip-photo-storage.md) | Trip photos — public, unguessable paths with queued resizing | accepted | 2026-10-17 |
| [0016](0016-operations-baseline.md) | Operations baseline — monitoring, heartbeats, backups | accepted | 2026-10-17 |
| [0017](0017-customer-booking-access-token.md) | Customer booking access via unguessable token | accepted | 2026-10-17 |
| [0018](0018-account-recovery-and-payment-instruction-protection.md) | Account recovery and payment-instruction change protection | accepted | 2026-10-17 |
| [0019](0019-defer-free-tier-limits.md) | Defer free-tier limits; no plan limit enforced in P0 | accepted | 2026-10-17 |
| [0020](0020-app-scaffold-and-tooling.md) | App scaffold, module layout and tooling | accepted | 2026-10-17 |
| [0021](0021-tenancy-enforcement-mechanics.md) | How organization scoping is enforced in code | accepted | 2026-10-17 |
| [0022](0022-trips-implementation-choices.md) | Implementation choices for trips and departures (F-003) | accepted | 2026-10-18 |
