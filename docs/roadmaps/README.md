# Roadmap

What is in scope **now**, **next** and **later**. Agents only build items under **Now**.
Dated plans live alongside this file; this page is the summary that must always be current.

_Last updated: 2026-10-19_

## Now — MVP build (P0)

Plan: [`2026-q4-mvp.md`](2026-q4-mvp.md)

| Item | Status |
|---|---|
| Week 1 — resolve open questions, confirm proposed ADRs, scaffold Laravel app | scaffold done (ADR-0020); ADR-0011 still proposed |
| [F-009](../features/F-009-tenancy-security-operations.md) Tenancy & security baseline (slice A wk 2 · ops wk 4 · hardening wk 6) | in-progress — slice A built (A5 done with F-002); B, C to do |
| [F-003](../features/F-003-trips-and-departures.md) Trips & departures | in-progress — built on `feat/F-003-trips-and-departures`; 38/43 ACs covered by tests; AC-3/7/7a/41 partly covered, AC-35 is a pilot check; device review after F-001 section A |
| [F-001](../features/F-001-seller-onboarding.md) Seller onboarding | in-progress — merged (PR #5); manual-test fixes on `fix/F-001-manual-test` add Pengaturan → Rekening (F5, the bank / e-wallet subset of F-006) and F6; 63/65 ACs covered by tests (D7, F1, T3 completed by F-002); C3 partly covered, P1 device review; ADR-0023 accepted |
| [F-002](../features/F-002-public-booking-page.md) Public booking page | in-progress — built on `feat/F-002-public-booking-page`; 39/40 ACs covered by tests (AC-35 Lighthouse is a pre-pilot manual check; AC-36 device review pending); closes F-001 D7/F1/T3 and F-009 A5; ADR-0025 accepted; Q13 decided |
| [F-005](../features/F-005-customer-booking-and-seat-hold.md) Customer booking & seat hold | planned — spec `ready` 2026-10-19 (AC-1…AC-42); ADR-0026, ADR-0027 proposed, defaults in use |
| [F-004](../features/F-004-request-a-date.md) Request-a-date | planned |
| [F-007](../features/F-007-seller-dashboard-and-bookings.md) Seller dashboard & bookings | planned |
| [F-006](../features/F-006-payment-instructions-and-records.md) Payment instructions & records | planned |
| [F-008](../features/F-008-whatsapp-handoff.md) WhatsApp handoff | planned |
| Internal admin panel — [F-010](../features/F-010-admin-access-control.md) access control, [F-011](../features/F-011-admin-overview-dashboard.md) overview, [F-012](../features/F-012-admin-seller-directory.md) seller directory, [F-013](../features/F-013-admin-booking-search.md) booking search, [F-014](../features/F-014-seller-suspension.md) suspension, [F-015](../features/F-015-admin-audit-log.md) admin audit log | planned |

The admin panel is an internal tool for the sole platform admin (product owner) to onboard pilot sellers, inspect
bookings, investigate problems and suspend/reactivate sellers — not a back office (ADR-0011).

## Pending decisions

Every open product or technical decision, in one place. The detail and the recommended default live in the linked
spec question or ADR; this table is the index. Builders use the recommended default unless it is marked **blocking**.
When a decision is made: tick it in its source, record it (ADR or spec changelog), and remove the row here.

### Proposed ADRs — accept or reject

| Decision | Recommended | Source | Needed by |
|---|---|---|---|
| Platform admin inside the same app, gated, no impersonation; admin login = email + password + TOTP | Accept | [ADR-0011](../decisions/0011-platform-admin-access.md) · F-010–F-015 | Before admin build (wk 4) |
| Booking record: one status column (glossary set + `cancel_reason`); holding statuses; lock order; price snapshot; reference `RKB-<departure MMDD>-<per-seller NNNN>`; idempotent submit; inline release of expired holds | Accept | [ADR-0026](../decisions/0026-booking-record-and-lifecycle.md) · F-005 Q2, Q3 · F-006 Q1 | Before F-006 is `ready` (F-005 builds on the default) |
| Customer booking routes under the slug (`/<slug>/pesanan/<token>`), token stored as hash + encrypted copy, session + CSRF on booking routes only | Accept | [ADR-0027](../decisions/0027-customer-booking-routes-and-access-token.md) · F-005 Q4, Q5 | Before pilot (F-005 builds on the default) |

### Week 1 — choices outside the specs

| Decision | Recommended | Source | Needed by |
|---|---|---|---|
| Transactional email provider (SPF/DKIM) | — | [F-001](../features/F-001-seller-onboarding.md#dependencies) | Pilot |
| **Pre-pilot gate:** ADR-0018 24-hour block on payment-instruction changes after a password reset or email change, and the change notice to the sign-in email — not built by F-001. Two write paths now go through `PaymentMethodService::sync`: onboarding step 4 and Pengaturan → Rekening (F-001 F5); both must be covered | Build with F-006 Pengaturan → Pembayaran | [F-001 Q16](../features/F-001-seller-onboarding.md#open-questions) · [ADR-0018](../decisions/0018-account-recovery-and-payment-instruction-protection.md) | Pilot (blocks "Pilot go") |
| Syarat & Privasi text; PDP law (UU 27/2022) | Short Bahasa notice before pilot; formal review before launch | [F-009 Q9](../features/F-009-tenancy-security-operations.md#open-questions) | Pilot |
| Free-tier limits and pricing | Decide after pilot | [ADR-0019](../decisions/0019-defer-free-tier-limits.md) | After pilot |

### Ready specs — defaults in use, confirm or override

| Decision | Recommended | Source |
|---|---|---|
| Sellers see a change history in P0 | No; capture now, show later | [F-009 Q7](../features/F-009-tenancy-security-operations.md#open-questions) |
| Changing a trip's duration when departures have bookings | Only unbooked departures move; notice shown | [F-003 Q3](../features/F-003-trips-and-departures.md#open-questions) |
| Deleting a departure with bookings | Not allowed; close only; cancel flow in wk 4 | [F-003 Q4](../features/F-003-trips-and-departures.md#open-questions) |
| Default booking deadline | H-3 | [F-003 Q6](../features/F-003-trips-and-departures.md#open-questions) |
| "Sampai hari keberangkatan" cut-off | 1 h before meeting time; hidden without one | [F-003 Q7](../features/F-003-trips-and-departures.md#open-questions) |
| Mobile trip editor without a mockup | Build from onboarding patterns; device review wk 3 | [F-003 Q8](../features/F-003-trips-and-departures.md#open-questions) |
| Nights derived from days (Hari − 1, or Hari with "+1 malam") instead of set by hand | Derive; one "+1 malam" toggle | [F-003 Q16](../features/F-003-trips-and-departures.md#open-questions) |
| Booking link change after publish | Only before first publish; then via support, no redirects | [F-001 Q3](../features/F-001-seller-onboarding.md#open-questions) |
| Onboarding "selesai dalam" timer | Show only if published ≤ 60 min after sign-up | [F-001 Q5](../features/F-001-seller-onboarding.md#open-questions) |
| "Termasuk apa saja?" chips | Generic set (Transportasi, Penginapan, Makan, Guide, …) | [F-001 Q7](../features/F-001-seller-onboarding.md#open-questions) |
| Who owns logo / bio / contact editing | F-001 section F | [F-001 Q9](../features/F-001-seller-onboarding.md#open-questions) |
| QRIS-only or cash-only sellers in onboarding | Bank/e-wallet only; others add theirs via F-006 (a draft without any account can add a bank / e-wallet in Pengaturan → Rekening, F-001 F5) | [F-001 Q10](../features/F-001-seller-onboarding.md#open-questions) |
| Onboarding time budget (±9/8/4/2 min) | Keep for build; retime in wk-1 user tests | [F-001 Q12](../features/F-001-seller-onboarding.md#open-questions) |
| Ask "Nama Anda" at sign-up | Yes | [F-001 Q13](../features/F-001-seller-onboarding.md#open-questions) |
| Password confirmation field | No; show/hide toggle | [F-001 Q14](../features/F-001-seller-onboarding.md#open-questions) |
| Auth events of a seller without an organization yet | Not audited in P0 | [F-001 Q17](../features/F-001-seller-onboarding.md#open-questions) |
| Booking reference `RKB-MMDD-NNNN`: MMDD = departure date; NNNN per seller, unique per seller | As stated (ADR-0026) | [F-005 Q2](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| International (non-+62) customer WhatsApp numbers | Indonesian mobiles only in P0 | [F-005 Q6](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Maximum participants per booking | No cap beyond seats left | [F-005 Q7](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Telling the seller about a new booking | No email/push in F-005; customer "Kabari operator via WhatsApp"; F-007 dashboard | [F-005 Q8](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Price changed between booking form and submit | Refuse and show the new price | [F-005 Q9](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Booking page before F-006 | No payment instructions until F-006 (wk 4, before pilot) | [F-005 Q10](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Seat-hold wording on the booking form | Keep the mockup text | [F-005 Q11](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Hold that outlasts the departure's meeting time | Keep exactly 2 h (ADR-0006); seller handles it | [F-005 Q12](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Privacy line on the booking form | One-line notice linking `/privasi` now | [F-005 Q13](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |

### Draft specs — to resolve when the spec is made `ready`

| Decision | Source |
|---|---|
| Accepted date request → public or private departure; price on accept | [F-004](../features/F-004-request-a-date.md#open-questions) |
| Booking vs payment status model (one status column proposed in ADR-0026); re-upload and hold clock after rejection; does `rejected` hold seats; DP 50% in P0 or P1 | [F-006](../features/F-006-payment-instructions-and-records.md#open-questions) |
| Mobile dashboard mockup; manual booking entry | [F-007](../features/F-007-seller-dashboard-and-bookings.md#open-questions) |
| Admin auth mechanism; admin UI language (Bahasa or English) | [F-010](../features/F-010-admin-access-control.md#open-questions) |
| Admin status mapping (depends on F-006) | [F-011](../features/F-011-admin-overview-dashboard.md#open-questions) |
| Meaning of "last activity" in the seller directory | [F-012](../features/F-012-admin-seller-directory.md#open-questions) |
| Suspension effects: public page, seller sign-in, unpaid holds, paid upcoming bookings (F-002 routes the public page through one `PublicVisibility` check) | [F-014](../features/F-014-seller-suspension.md#open-questions) |
| Do sellers see admin actions on their organization | [F-015](../features/F-015-admin-audit-log.md#open-questions) |

### Missing mockups

Sign-up / sign-in / password reset (F-001) · Pengaturan → Akun, Profil usaha and Rekening (F-001) · mobile trip editor (F-003 Q8) ·
mobile dashboard (F-007).

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
| Pilot go | End wk 4 | Is the core loop safe for real customers? | No known overbooking or cross-tenant leaks; backup restore tested; ADR-0018 payment-instruction block and notice built (F-001 Q16) |
| Continue / pivot | End wk 6 | Do operators use it without us? | Time to first trip < 10 min · independent onboarding · first real booking ≤ 14 days · repeat use |
| Enterprise pilot | Month 5–6 | Will an established operator pay? | Qualified pipeline; paid pilot scope |
