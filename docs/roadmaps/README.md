# Roadmap

What is in scope **now**, **next** and **later**. Agents only build items under **Now**.
Dated plans live alongside this file; this page is the summary that must always be current.

_Last updated: 2026-10-17_

## Now — MVP build (P0)

Plan: [`2026-q4-mvp.md`](2026-q4-mvp.md)

| Item | Status |
|---|---|
| Week 1 — resolve open questions, confirm proposed ADRs, scaffold Laravel app | planned |
| [F-009](../features/F-009-tenancy-security-operations.md) Tenancy & security baseline | planned |
| [F-001](../features/F-001-seller-onboarding.md) Seller onboarding | planned |
| [F-003](../features/F-003-trips-and-departures.md) Trips & departures | planned |
| [F-002](../features/F-002-public-booking-page.md) Public booking page | planned |
| [F-005](../features/F-005-customer-booking-and-seat-hold.md) Customer booking & seat hold | planned |
| [F-004](../features/F-004-request-a-date.md) Request-a-date | planned |
| [F-007](../features/F-007-seller-dashboard-and-bookings.md) Seller dashboard & bookings | planned |
| [F-006](../features/F-006-payment-instructions-and-records.md) Payment instructions & records | planned |
| [F-008](../features/F-008-whatsapp-handoff.md) WhatsApp handoff | planned |
| Internal admin panel — [F-010](../features/F-010-admin-access-control.md) access control, [F-011](../features/F-011-admin-overview-dashboard.md) overview, [F-012](../features/F-012-admin-seller-directory.md) seller directory, [F-013](../features/F-013-admin-booking-search.md) booking search, [F-014](../features/F-014-seller-suspension.md) suspension, [F-015](../features/F-015-admin-audit-log.md) admin audit log | planned |

The admin panel is an internal tool for the sole platform admin (product owner) to onboard pilot sellers, inspect
bookings, investigate problems and suspend/reactivate sellers — not a back office (ADR-0011).

## Next — after pilot feedback (P1)

Recurring departure templates · CSV export · automated notifications/reminders · deposits & remaining balance ·
promo codes, add-ons, packages · staff accounts & permissions · seller-owned payment-provider integrations ·
custom domains & branding removal on paid plans · subscription billing · admin system-health view (failed jobs, recent errors;
link to hosting/error-tracking tools until then).

Promote an item only with pilot evidence; give it an F-number when it moves to **Now**.

## Later / explicitly deferred (P2)

Plugin/extension marketplace · marketplace discovery & traffic promises · reseller/agent/affiliate hierarchies ·
dynamic pricing · accounting/ERP/CRM/inventory · native mobile apps · AI features · multi-provider payment orchestration ·
admin back-office powers (impersonation, multi-admin roles, support-team accounts, booking/payment editing) — need a new ADR.

Enterprise capabilities (white-label, multi-brand, API, SLA) are built only when a qualified buyer validates them —
see [`year-1-commercial.md`](year-1-commercial.md).

## Decision gates

| Gate | When | Question | Evidence |
|---|---|---|---|
| Pilot go | End wk 4 | Is the core loop safe for real customers? | No known overbooking or cross-tenant leaks; backup restore tested |
| Continue / pivot | End wk 6 | Do operators use it without us? | Time to first trip < 10 min · independent onboarding · first real booking ≤ 14 days · repeat use |
| Enterprise pilot | Month 5–6 | Will an established operator pay? | Qualified pipeline; paid pilot scope |
