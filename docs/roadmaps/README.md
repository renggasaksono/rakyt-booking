# Roadmap

What is in scope **now**, **next** and **later**. Agents only build items under **Now**.
Dated plans live alongside this file; this page is the summary that must always be current.

_Last updated: 2026-10-17_

## Now — MVP build (P0)

Plan: [`2026-q4-mvp.md`](2026-q4-mvp.md)

| Item | Status |
|---|---|
| Week 1 — resolve open questions, confirm proposed ADRs, scaffold Laravel app | planned |
| [F-009](../features/F-009-tenancy-security-operations.md) Tenancy & security baseline (slice A wk 2 · ops wk 4 · hardening wk 6) | ready (spec) |
| [F-001](../features/F-001-seller-onboarding.md) Seller onboarding | ready (spec) |
| [F-003](../features/F-003-trips-and-departures.md) Trips & departures | ready (spec) |
| [F-002](../features/F-002-public-booking-page.md) Public booking page | planned |
| [F-005](../features/F-005-customer-booking-and-seat-hold.md) Customer booking & seat hold | planned |
| [F-004](../features/F-004-request-a-date.md) Request-a-date | planned |
| [F-007](../features/F-007-seller-dashboard-and-bookings.md) Seller dashboard & bookings | planned |
| [F-006](../features/F-006-payment-instructions-and-records.md) Payment instructions & records | planned |
| [F-008](../features/F-008-whatsapp-handoff.md) WhatsApp handoff | planned |

## Pending decisions

Every open product or technical decision, in one place. The detail and the recommended default live in the linked
spec question or ADR; this table is the index. Builders use the recommended default unless it is marked **blocking**.
When a decision is made: tick it in its source, record it (ADR or spec changelog), and remove the row here.

### Proposed ADRs — accept or reject

| Decision | Recommended | Source | Needed by |
|---|---|---|---|
| Prada brand shade D vs C | see ADR | [ADR-0008](../decisions/0008-brand-tokens-and-cta-rule.md) | Before UI build (wk 2) |

### Week 1 — choices outside the specs

| Decision | Recommended | Source | Needed by |
|---|---|---|---|
| Transactional email provider (SPF/DKIM) | — | [F-001](../features/F-001-seller-onboarding.md#dependencies) | Pilot |
| Syarat & Privasi text; PDP law (UU 27/2022) | Short Bahasa notice before pilot; formal review before launch | [F-009 Q9](../features/F-009-tenancy-security-operations.md#open-questions) | Pilot |
| Free-tier limits and pricing | Decide after pilot | [ADR-0018](../decisions/0018-defer-free-tier-limits.md) | After pilot |

### Ready specs — defaults in use, confirm or override

| Decision | Recommended | Source |
|---|---|---|
| Rakyt staff access to seller data | None in-app; audited artisan commands | [F-009 Q6](../features/F-009-tenancy-security-operations.md#open-questions) |
| Sellers see a change history in P0 | No; capture now, show later | [F-009 Q7](../features/F-009-tenancy-security-operations.md#open-questions) |
| Changing a trip's duration when departures have bookings | Only unbooked departures move; notice shown | [F-003 Q3](../features/F-003-trips-and-departures.md#open-questions) |
| Deleting a departure with bookings | Not allowed; close only; cancel flow in wk 4 | [F-003 Q4](../features/F-003-trips-and-departures.md#open-questions) |
| Default booking deadline | H-3 | [F-003 Q6](../features/F-003-trips-and-departures.md#open-questions) |
| "Sampai hari keberangkatan" cut-off | 1 h before meeting time; hidden without one | [F-003 Q7](../features/F-003-trips-and-departures.md#open-questions) |
| Mobile trip editor without a mockup | Build from onboarding patterns; device review wk 3 | [F-003 Q8](../features/F-003-trips-and-departures.md#open-questions) |
| Booking link change after publish | Only before first publish; then via support, no redirects | [F-001 Q3](../features/F-001-seller-onboarding.md#open-questions) |
| Onboarding "selesai dalam" timer | Show only if published ≤ 60 min after sign-up | [F-001 Q5](../features/F-001-seller-onboarding.md#open-questions) |
| "Termasuk apa saja?" chips | Generic set (Transportasi, Penginapan, Makan, Guide, …) | [F-001 Q7](../features/F-001-seller-onboarding.md#open-questions) |
| Who owns logo / bio / contact editing | F-001 section F | [F-001 Q9](../features/F-001-seller-onboarding.md#open-questions) |
| QRIS-only or cash-only sellers in onboarding | Bank/e-wallet only; others add theirs via F-006 | [F-001 Q10](../features/F-001-seller-onboarding.md#open-questions) |
| Onboarding time budget (±9/8/4/2 min) | Keep for build; retime in wk-1 user tests | [F-001 Q12](../features/F-001-seller-onboarding.md#open-questions) |
| Ask "Nama Anda" at sign-up | Yes | [F-001 Q13](../features/F-001-seller-onboarding.md#open-questions) |
| Password confirmation field | No; show/hide toggle | [F-001 Q14](../features/F-001-seller-onboarding.md#open-questions) |

### Draft specs — to resolve when the spec is made `ready`

| Decision | Source |
|---|---|
| SSR/caching for link previews (OG tags); Rakyt branding placement on free plan | [F-002](../features/F-002-public-booking-page.md#open-questions) |
| Accepted date request → public or private departure; price on accept | [F-004](../features/F-004-request-a-date.md#open-questions) |
| Booking reference format (`RKB-MMDD-NNNN`: departure or booking date) | [F-005](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Booking vs payment status model; re-upload and hold clock after rejection; DP 50% in P0 or P1 | [F-006](../features/F-006-payment-instructions-and-records.md#open-questions) |
| Mobile dashboard mockup; manual booking entry; Rakyt support/admin view | [F-007](../features/F-007-seller-dashboard-and-bookings.md#open-questions) |

### Missing mockups

Sign-up / sign-in / password reset (F-001) · Pengaturan → Akun and Profil usaha (F-001) · mobile trip editor (F-003 Q8) ·
mobile dashboard (F-007).

## Next — after pilot feedback (P1)

Recurring departure templates · CSV export · automated notifications/reminders · deposits & remaining balance ·
promo codes, add-ons, packages · staff accounts & permissions · seller-owned payment-provider integrations ·
custom domains & branding removal on paid plans · subscription billing.

Promote an item only with pilot evidence; give it an F-number when it moves to **Now**.

## Later / explicitly deferred (P2)

Plugin/extension marketplace · marketplace discovery & traffic promises · reseller/agent/affiliate hierarchies ·
dynamic pricing · accounting/ERP/CRM/inventory · native mobile apps · AI features · multi-provider payment orchestration.

Enterprise capabilities (white-label, multi-brand, API, SLA) are built only when a qualified buyer validates them —
see [`year-1-commercial.md`](year-1-commercial.md).

## Decision gates

| Gate | When | Question | Evidence |
|---|---|---|---|
| Pilot go | End wk 4 | Is the core loop safe for real customers? | No known overbooking or cross-tenant leaks; backup restore tested |
| Continue / pivot | End wk 6 | Do operators use it without us? | Time to first trip < 10 min · independent onboarding · first real booking ≤ 14 days · repeat use |
| Enterprise pilot | Month 5–6 | Will an established operator pay? | Qualified pipeline; paid pilot scope |
