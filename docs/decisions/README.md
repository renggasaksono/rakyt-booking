# Decisions (ADRs)

Architecture & product decision records. Short, append-only, numbered.

- One decision per file: `NNNN-kebab-title.md`, from [`_template.md`](_template.md).
- Never edit the substance of an `accepted` ADR. To change course, write a new one and mark the old one `superseded by NNNN`.
- `proposed` ADRs need a human to accept or reject them. Agents may implement against a proposed ADR only if the spec says so.

## Index

| # | Decision | Status | Date |
|---|---|---|---|
| [0001](0001-record-decisions-as-adrs.md) | Record decisions as ADRs in the repo | accepted | 2026-10-17 |
| [0002](0002-stack-laravel-inertia-vue-tailwind.md) | Laravel + Inertia + Vue 3 + Tailwind, modular monolith | accepted (DB engine open) | 2026-10-15 |
| [0003](0003-seller-direct-payments.md) | Seller-direct payments; Rakyt never holds funds | accepted | 2026-10-15 |
| [0004](0004-organization-scoped-multi-tenancy.md) | Single database, organization-scoped multi-tenancy | accepted | 2026-10-15 |
| [0005](0005-trip-and-departure-domain-model.md) | Trip (offering) vs departure (bookable occurrence) | accepted | 2026-10-15 |
| [0006](0006-seat-hold-and-capacity-locking.md) | Seat holds with automatic expiry; transactional capacity | proposed (hold length) | 2026-10-17 |
| [0007](0007-ui-language-bahasa-indonesia.md) | UI copy in Bahasa Indonesia; code & docs in English | accepted | 2026-10-17 |
| [0008](0008-brand-tokens-and-cta-rule.md) | Rakyt Sogan tokens, status set, one-gold-CTA rule | proposed (Prada shade) | 2026-10-17 |
| [0009](0009-whatsapp-otp-authentication.md) | WhatsApp OTP sign-in for sellers | proposed | 2026-10-17 |
| [0010](0010-freemium-subscription-no-transaction-fee.md) | Freemium + subscription; no mandatory transaction fee | accepted | 2026-10-15 |
