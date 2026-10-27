# Roadmap

What is in scope **now**, **next** and **later**. Agents only build items under **Now**.
Dated plans live alongside this file; this page is the summary that must always be current.

_Last updated: 2026-10-27_

## Now — MVP build (P0)

Plan: [`2026-q4-mvp.md`](2026-q4-mvp.md)

| Item | Status |
|---|---|
| Week 1 — resolve open questions, confirm proposed ADRs, scaffold Laravel app | scaffold done (ADR-0020); ADR-0011 accepted (2026-10-23) |
| [F-009](../features/F-009-tenancy-security-operations.md) Tenancy & security baseline (slice A wk 2 · ops wk 4 · hardening wk 6) | in-progress — slice A built (A5 done with F-002); B, C to do |
| [F-003](../features/F-003-trips-and-departures.md) Trips & departures | in-progress — merged (PR #4); 39/43 ACs covered by tests (AC-1 re-ticked 2026-10-22 after dropping the Day Trip type — two-type enum + data migration); AC-3/7/7a/41 partly covered, AC-35 is a pilot check; device review after F-001 section A |
| [F-001](../features/F-001-seller-onboarding.md) Seller onboarding | in-progress — merged (PR #5); manual-test fixes (PR #6) add Pengaturan → Rekening (F5, the bank / e-wallet subset of F-006) and F6; 64/66 ACs covered by tests (D7, F1, T3 completed by F-002; A14 "Nama Anda" in Pengaturan → Akun merged 2026-10-23, PR #36); C3 partly covered, P1 device review; ADR-0023 accepted |
| [F-002](../features/F-002-public-booking-page.md) Public booking page | in-progress — merged (PR #9); 39/40 ACs covered by tests (AC-35 Lighthouse is a pre-pilot manual check; AC-36 device review pending); closes F-001 D7/F1/T3 and F-009 A5; ADR-0025 accepted; Q13 decided |
| [F-005](../features/F-005-customer-booking-and-seat-hold.md) Customer booking & seat hold | done — merged (PR #10); AC-1…AC-42 + AC-17b covered by tests (AC-42 real-device review with F-002/F-003's); closes F-009 R1, R20; ADR-0028 accepted; Q6–Q8, Q10–Q13 defaults in use |
| [F-004](../features/F-004-request-a-date.md) Request-a-date (WhatsApp handoff) | in-progress — merged (PR #21); 10/10 ACs covered by tests ("Option B": `request_date` + dateless publish kept; the "Ajukan tanggal →" CTA hands off to WhatsApp via `whatsapp.date_request`, dead `/ajukan-tanggal` removed); in-app date-request entity/inbox/"Balas"/dashboard rows deferred to P1 (Out); ADR-0032 (gold-CTA exception) accepted 2026-10-22 |
| [F-007](../features/F-007-seller-dashboard-and-bookings.md) Seller dashboard & bookings | done — merged (PR #11); AC-1…AC-38 covered by tests (AC-38 real-device review with the other P0 screens); Konfirmasi / Tolak come with F-006, date-request rows with F-004; ADR-0029 accepted |
| [F-006](../features/F-006-payment-instructions-and-records.md) Payment instructions & records | done — AC-1…AC-32 covered by tests (payment screen, evidence upload, Konfirmasi / Tolak inline, QRIS, ADR-0018 block); supersedes F-001's Rekening with Pengaturan → Pembayaran; closes F-009 A20/A21, R2, R19 and the Pilot-go payment-security gate; AC-32 real-device review pending; cash & DP → P1; ADR-0030 + ADR-0031 accepted |
| [F-008](../features/F-008-whatsapp-handoff.md) WhatsApp handoff | done — built 2026-10-21 (sections A+B): `App\WhatsApp\WhatsAppLink`, `lang/id/whatsapp.php` template home, "Tanya via WhatsApp" on the trip page; `date_request` handoff + AC-B9a shipped with F-004 (2026-10-22); all 24 ACs covered — AC-A11/AC-B12 real-device review passed (Rengga 2026-10-22) |
| [F-016](../features/F-016-booking-confirmation-retrieval.md) Booking confirmation — copy link & print proof | done — merged to `main`; AC-1…AC-10, AC-12…AC-15 covered by tests (customer "Salin tautan pesanan" + browser-native "Simpan bukti pesanan" print snapshot + `urls.self`; retrieval, content and WhatsApp CTA are regressions); AC-11/AC-16 device review passed (Rengga 2026-10-23, paid + awaiting-payment states); token revoke/regenerate deferred to P1 (Q1) |
| Internal admin panel — [F-010](../features/F-010-admin-access-control.md) access control, [F-011](../features/F-011-admin-overview-dashboard.md) overview, [F-012](../features/F-012-admin-seller-directory.md) seller directory, [F-013](../features/F-013-admin-booking-search.md) booking search, [F-014](../features/F-014-seller-suspension.md) suspension, [F-015](../features/F-015-admin-audit-log.md) admin audit log | F-010 done (`feat/F-010-admin-access-control`, PR #29): protected `/admin`, platform-admin flag + required TOTP 2FA, server-side gate, admin shell; F-015 done (`feat/F-015-admin-audit-log`, PR #41, 2026-10-26): read-only `/admin/audit` filterable by organization / actor / action, `audit_events.reason`, shared redaction, related-event queries for F-012/F-013, AC-1…AC-6 covered by tests, ADR-0038 accepted (2026-10-26); F-012 done (`feat/F-012-admin-seller-directory`, 2026-10-27): `/admin/penjual` searchable / status-filtered directory and read-only organization detail (members, last activity = owner's last sign-in, trips, departures with F-007's seat counts, recent bookings with signed evidence links, audit events), `organizations.status` + `users.last_login_at`, audit view's organization filter now a searchable picker, AC-1…AC-8 covered by tests (AC-8 device review pending), ADR-0039 proposed; F-014 done (`feat/F-014-seller-suspension`, 2026-10-27): admin suspend / reactivate with confirmation + required reason on the detail page's "Akun" section, audited in the same transaction (`organization.suspended` / `.reactivated`), idempotent; suspended → link shows a neutral "tidak tersedia" page (200), new bookings refused under the organization lock (concurrency-tested), evidence uploads refused, seller signed out on the next request and refused at "Masuk"; holds still expire, no data changes, no notifications; AC-1…AC-9 covered by tests, payment instructions hidden while suspended (Q1 decided), ADR-0040 accepted (2026-10-27); F-013 done (`feat/F-013-admin-booking-search`, 2026-10-27): `/admin/pesanan` cross-seller booking search (exact reference, whole phone → E.164, customer / seller name or slug) with status, seller and departure-date filters in the URL, paginated; read-only `/admin/pesanan/{id}` detail with seller (+ suspended status, link to F-012), customer incl. phone (detail only), departure seats from `SeatBreakdown`, amounts, payment record + evidence via F-012's signed route, hold expiry and audit history; four indexes for the cross-organization reads; AC-1…AC-7 covered by tests (AC-7 device review pending), ADR-0041 proposed; F-011 spec `ready` (2026-10-23), ADR-0011 accepted — next F-011 (wk 4) |
| [F-017](../features/F-017-public-landing-page.md) Public landing page | in-progress — merged (PR #28); drives open self-serve sign-up (every CTA → `/daftar`, no waitlist), "Sogan Cerah" mockup adapted to app tokens + square corners, testimonials dropped; interactive hero demo + FAQ accordion (`resources/js/lib` + Vitest); GA4 sign-up-funnel analytics, config-gated, Consent Mode v2 default-denied + opt-in; AC-1…AC-13, AC-15…AC-22 covered by PHPUnit + Vitest; AC-14 device review, AC-23 `/privasi` legal copy and Lighthouse are pre-pilot manual items; ADR-0033 + ADR-0034 accepted |

Everything above that says "merged" is on `main`. Next build order: admin panel F-011 (F-015 done, PR #41; F-012 done, PR #43; F-014 done, PR #44; F-013 done, PR #45), then
F-009 slice B (ops baseline). Small UI polish merged since: trip-editor back link (PR #37), "kuota" instead of "kursi" in UI
copy ([ADR-0037](../decisions/0037-ui-term-kuota-instead-of-kursi.md), PR #38), "Rakyt | Booking" lockup in the auth header (PR #39).

The admin panel is an internal tool for the sole platform admin (product owner) to onboard pilot sellers, inspect
bookings, investigate problems and suspend/reactivate sellers — not a back office (ADR-0011).

## Pending decisions

Every open product or technical decision, in one place. The detail and the recommended default live in the linked
spec question or ADR; this table is the index. Builders use the recommended default unless it is marked **blocking**.
When a decision is made: tick it in its source, record it (ADR or spec changelog), and remove the row here.

### Proposed ADRs — accept or reject

| Decision | Recommended | Source | Needed by |
|---|---|---|---|
| Seller side nav on ink with the "Rakyt \| Booking" mark; "Booking" kicker in gold-soft `#D9AE5C` on ink (Sogan fails at 2.1 : 1); solid 2f seat-bar colours | Accept — built and merged (PR #34, #35) | [ADR-0036](../decisions/0036-ink-ground-side-nav-and-seat-bar-colours.md) · [F-007](../features/F-007-seller-dashboard-and-bookings.md) | Pre-pilot (visual sign-off) |
| "Sogan Cerah" landing reskin — keep scoped to the landing, or adopt the brighter palette product-wide (supersede ADR-0008)? | Keep scoped (`.cerah`) for now; measure `#a65f2c` contrast before any product-wide move | [ADR-0035](../decisions/0035-sogan-cerah-landing-reskin.md) · [F-017](../features/F-017-public-landing-page.md) | Pre-pilot (visual sign-off) |

### Week 1 — choices outside the specs

| Decision | Recommended | Source | Needed by |
|---|---|---|---|
| Transactional email provider (SPF/DKIM) | — | [F-001](../features/F-001-seller-onboarding.md#dependencies) | Pilot |
| **Pre-pilot gate:** ADR-0018 24-hour block on payment-instruction changes after a password reset or email change, and the change notice to the sign-in email | **Built by F-006 §E (AC-24–AC-26)** — `PaymentInstructionLock` + the change-notice email on Pengaturan → Pembayaran; onboarding's `PaymentMethodService::sync` stays unblocked | [F-006 §E](../features/F-006-payment-instructions-and-records.md) · [ADR-0018](../decisions/0018-account-recovery-and-payment-instruction-protection.md) · [ADR-0031](../decisions/0031-payment-records-implementation-choices.md) | Pilot (gate cleared) |
| Syarat & Privasi text; PDP law (UU 27/2022) | Short Bahasa notice before pilot; formal review before launch. `/privasi` must disclose Google Analytics before the landing goes live (F-017 AC-23/Q8) | [F-009 Q9](../features/F-009-tenancy-security-operations.md#open-questions) · [F-017 Q8](../features/F-017-public-landing-page.md#open-questions) | Pilot |
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
| Password confirmation field | No; show/hide toggle | [F-001 Q14](../features/F-001-seller-onboarding.md#open-questions) |
| Auth events of a seller without an organization yet | Not audited in P0 | [F-001 Q17](../features/F-001-seller-onboarding.md#open-questions) |
| International (non-+62) customer WhatsApp numbers | Indonesian mobiles only in P0 | [F-005 Q6](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Maximum participants per booking | No cap beyond seats left | [F-005 Q7](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Telling the seller about a new booking | No email/push in F-005; customer "Kabari operator via WhatsApp"; F-007 dashboard | [F-005 Q8](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Booking page before F-006 | No payment instructions until F-006 (wk 4, before pilot) | [F-005 Q10](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Seat-hold wording on the booking form | Keep the mockup text | [F-005 Q11](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Hold that outlasts the departure's meeting time | Keep exactly 2 h (ADR-0006); seller handles it | [F-005 Q12](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Privacy line on the booking form | One-line notice linking `/privasi` now | [F-005 Q13](../features/F-005-customer-booking-and-seat-hold.md#open-questions) |
| Booking access-token revoke / regenerate | Defer to P1; seller-owned rotation when built (needs ADR extending ADR-0017/0027) | [F-016 Q1](../features/F-016-booking-confirmation-retrieval.md#open-questions) |

### Draft specs — to resolve when the spec is made `ready`

_None — all admin specs (F-010–F-015) made `ready` on 2026-10-23 (F-010 since built & merged, PR #29); their open questions are resolved in the spec changelogs:_

- _F-011 status mapping pinned to the shipped `BookingStatus` enum (pending = `awaiting_payment`, verification = `needs_review`)._
- _F-012 "last activity" = owner's last successful sign-in (`users.last_login_at`)._
- _F-014 suspension effects: neutral "tidak tersedia" public page (not 404), seller sign-in blocked, unpaid holds expire + uploads blocked, paid bookings untouched / no auto-notify._
- _F-015 admin actions stay platform-internal; sellers do not see them._

### Missing mockups

Sign-up / sign-in / password reset (F-001) · Pengaturan → Akun, Profil usaha and Rekening (F-001) · mobile trip editor (F-003 Q8) ·
mobile dashboard (F-007 Q1 decided: build phone-first, device review instead) ·
admin panel screens (F-010–F-015: no mockups — internal tool, build phone-first from the seller-app patterns, device review instead).

## Next — after pilot feedback (P1)

Recurring departure templates · CSV export · automated notifications/reminders · deposits & remaining balance ·
promo codes, add-ons, packages · staff accounts & permissions · seller-owned payment-provider integrations ·
custom domains & branding removal on paid plans · subscription billing · booking access-token revoke / regenerate
(seller-owned, F-016 Q1) · admin system-health view (failed jobs, recent errors; link to hosting/error-tracking tools until then).

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
