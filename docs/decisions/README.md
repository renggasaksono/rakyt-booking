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
| [0023](0023-onboarding-and-accounts-implementation-choices.md) | Implementation choices for seller accounts and onboarding (F-001) | accepted | 2026-10-19 |
| [0024](0024-public-page-rendering-and-caching.md) | Public pages — Inertia without SSR, server-rendered head tags, split JavaScript, no page cache | accepted | 2026-10-19 |
| [0025](0025-public-pages-implementation-choices.md) | Implementation choices for the public booking pages (F-002) | accepted | 2026-10-19 |
| [0026](0026-booking-record-and-lifecycle.md) | Booking record, status lifecycle, reference and seat accounting | accepted | 2026-10-19 |
| [0027](0027-customer-booking-routes-and-access-token.md) | Customer booking routes — URL shape, token storage, session | accepted | 2026-10-19 |
| [0028](0028-customer-booking-implementation-choices.md) | Implementation choices for customer booking and seat hold (F-005) | accepted | 2026-10-19 |
| [0029](0029-seller-dashboard-implementation-choices.md) | Implementation choices for the seller dashboard and booking list (F-007) | accepted | 2026-10-19 |
| [0030](0030-payment-records-and-verification.md) | Payment records, evidence and verification lifecycle (F-006) | accepted | 2026-10-20 |
| [0031](0031-payment-records-implementation-choices.md) | Payment-records implementation choices (F-006) | accepted | 2026-10-20 |
| [0032](0032-gold-cta-whatsapp-handoff-for-request-date.md) | The one gold CTA may be a WhatsApp handoff on request-date trips | accepted | 2026-10-22 |
