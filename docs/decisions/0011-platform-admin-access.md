---
id: 0011
title: Platform admin inside the same app, explicitly authorised, no impersonation
status: accepted
date: 2026-10-23
deciders: [Rengga]
related: [F-010, F-011, F-012, F-013, F-014, F-015, ADR-0002, ADR-0004, ADR-0009, ADR-0012]
---

# 0011. Platform admin inside the same app, explicitly authorised, no impersonation

## Context

During the MVP and pilot the product owner is the only platform administrator. They need to onboard pilot sellers,
look up bookings across sellers, investigate problems and suspend a seller — without direct database access.
ADR-0004 scopes every seller-owned query to the current organization; an admin area must read across organizations
without weakening that scope for sellers. Seller sign-in is email + password (ADR-0012; the WhatsApp OTP proposal ADR-0009 was rejected). That alone is weaker
than we want for an account that can see every seller's data.

## Decision

- The admin area is a module of the existing Laravel app (`/admin`, Inertia + Vue + Tailwind). No separate app, database or API.
- A platform admin is a `users` row explicitly marked as admin (e.g. `users.is_platform_admin`, set by seeder/artisan
  command only). No registration, invitation or role-management UI. One admin in the MVP.
- Every `/admin` route sits behind authentication **and** a server-side gate/middleware (`platform-admin`). A seller
  hitting `/admin` gets 403/404; hidden links are not the control.
- Cross-organization reads happen only in admin-module query classes that bypass the tenant scope explicitly
  (e.g. `withoutGlobalScope` in one place). Seller-facing scopes, policies and tests are unchanged.
- The admin is not a member of any organization and has no seller-side powers through membership.
- No impersonation ("log in as seller"), no generic DB browser, no booking/payment editing in the MVP.
- Admin authentication is stronger than seller sign-in: the same email + password stack (ADR-0012) plus required TOTP 2FA, a shorter session lifetime,
  rate-limited login. **Confirmed at scaffolding (2026-10-23):** the TOTP is hand-rolled on `pragmarx/google2fa` (the library Fortify itself uses), not Laravel Fortify — F-009's auth is hand-rolled, so adding Fortify would duplicate and conflict the login routes for no gain. Enrolment happens when the flag is granted (`admin:grant` prints the secret); the admin session is a rolling idle window from `config('rakyt.admin.session_lifetime')` (30 min), shorter than the 120-min seller session; the challenge is rate-limited (`throttle:admin-2fa`).
- Sensitive admin actions require a confirmation step and write an audit event (F-015, using F-009's `audit_events`). Granting/revoking the admin flag is such an action; because the admin belongs to no organization, these are **platform-level** audit events with a null `organization_id` (`audit_events.organization_id` is nullable for them; the `OrganizationOptional` interface lets `AuditEvent` opt in without weakening any seller model).

## Consequences

- Good: one codebase and deploy; admin reuses models, statuses and tokens; the boundary is a single gate that is easy to test.
- Costs: a mistake in an admin query could leak across tenants — every admin route needs an authorisation test
  (seller → denied, guest → redirected, admin → allowed).
- Follow-ups: multi-admin roles, support-team accounts and impersonation need a new ADR before they are built.

## Alternatives considered

- Off-the-shelf admin panel (Filament, Nova) — fast, but adds a dependency and a second UI stack, and makes broad CRUD powers the default.
- Separate admin app/database — heavier ops for one user; no benefit at MVP scale.
- Admin as a special organization member — blurs the tenant boundary that ADR-0004 relies on.
