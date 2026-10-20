# Features

One file per feature: `F-NNN-kebab-name.md`, copied from [`_template.md`](_template.md).
The spec is the contract for "done": acceptance criteria become tests.

Status flow: `draft` → `ready` → `in-progress` → `done` (or `parked`). Keep this index in sync with each spec's front matter.

## Index

| ID | Feature | Priority | Status | Roadmap |
|---|---|---|---|---|
| [F-001](F-001-seller-onboarding.md) | Seller onboarding & organization profile | P0 | in-progress | MVP wk 2–3 |
| [F-002](F-002-public-booking-page.md) | Public booking page | P0 | in-progress | MVP wk 2–3 |
| [F-003](F-003-trips-and-departures.md) | Trips & departures management (incl. quick edit) | P0 | ready | MVP wk 2–3 |
| [F-004](F-004-request-a-date.md) | Request-a-date mode | P0 | draft | MVP wk 2–3 |
| [F-005](F-005-customer-booking-and-seat-hold.md) | Customer booking & seat hold | P0 | done | MVP wk 2–3 |
| [F-006](F-006-payment-instructions-and-records.md) | Payment instructions & records | P0 | ready | MVP wk 4 |
| [F-007](F-007-seller-dashboard-and-bookings.md) | Seller dashboard ("Hari ini") & booking list | P0 | done | MVP wk 2–4 |
| [F-008](F-008-whatsapp-handoff.md) | WhatsApp contact & prefilled messages | P0 | ready (section A; B draft) | MVP wk 4 |
| [F-009](F-009-tenancy-security-operations.md) | Tenancy, security & operations baseline | P0 | in-progress | MVP wk 2–6 |
| [F-010](F-010-admin-access-control.md) | Admin authentication & access control | P0 | draft | MVP wk 4 |
| [F-011](F-011-admin-overview-dashboard.md) | Admin overview dashboard | P0 | draft | MVP wk 4 |
| [F-012](F-012-admin-seller-directory.md) | Admin seller directory & organization details | P0 | draft | MVP wk 4 |
| [F-013](F-013-admin-booking-search.md) | Admin cross-seller booking search & details | P0 | draft | MVP wk 4 |
| [F-014](F-014-seller-suspension.md) | Seller account suspension & reactivation | P0 | draft | MVP wk 4 |
| [F-015](F-015-admin-audit-log.md) | Audit logging for admin actions | P0 | draft | MVP wk 4 |

F-010–F-015 form the internal admin panel for the sole platform admin during MVP/pilot (ADR-0011).

Next free ID: **F-016**

## Backlog (P1 — not specced yet)

Recurring departure templates · CSV export · automated notifications & reminders · deposit / remaining balance ·
promo codes, add-ons, package options · staff accounts & permissions · seller-owned payment-provider integrations ·
custom domains & Rakyt-branding removal · admin system-health view (failed jobs, recent errors — use hosting/error-tracking tools from F-009 until then).

Write a spec only when the item moves to **Next** on the [roadmap](../roadmaps/README.md).
