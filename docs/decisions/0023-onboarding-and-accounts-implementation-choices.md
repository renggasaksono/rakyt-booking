---
id: 0023
title: Implementation choices for seller accounts and onboarding (F-001)
status: accepted
date: 2026-10-18
deciders: [Rengga]
related: [F-001, F-003, F-006, F-009, ADR-0003, ADR-0004, ADR-0012, ADR-0018, ADR-0021, ADR-0022]
---

# 0023. Implementation choices for seller accounts and onboarding (F-001)

*Proposed by the builder agent on 2026-10-18 while building F-001. Accepted by Rengga on 2026-10-19.*

## Context

F-001 adds the first sign-in, creates the first tenant and pulls payment methods forward from F-006. The spec and
ADR-0012 / ADR-0018 say *what* must happen; building it meant several technical choices that someone could later
ask "why?" about.

## Decision

1. **Hand-written, Breeze-style auth controllers; no Fortify package.** Five small controllers under
   `app/Http/Controllers/Auth` and `Settings/AccountController`, using Laravel's guard, password broker and
   notifications. Every screen is an Inertia page with Bahasa copy from `lang/id/account.php`; no extra dependency.
2. **Other sessions end through the password hash, not by deleting session rows.** `AuthenticateSession` runs in the
   `web` group; a reset, a password change and an email change call `Auth::logoutOtherDevices()` (or change the hash)
   and cycle the remember-me token. Deleting rows from `sessions` would need raw `DB::` access, which the scope guard
   forbids (ADR-0021).
3. **"Ingat saya" off means a browser-session cookie.** `SESSION_EXPIRE_ON_CLOSE` defaults to `true`; the idle timeout
   is `SESSION_LIFETIME`. With "Ingat saya" the remember-me cookie keeps the seller signed in.
4. **Reset links carry the token in the query string** (`/password/baru?token=…&email=…`), so the existing log
   redaction (`token=`) catches it anywhere a URL is logged. "Lupa password?" always goes through the broker, whose
   built-in timebox makes known and unknown emails take the same time; its status is never shown.
5. **Local mail goes to a separate `mail` log channel** (`storage/logs/mail.log`), redacted like every channel, with
   the quoted-printable HTML part dropped (redaction cannot read `signature=3D…`). Reset and verification links are
   therefore unusable from the log; use an SMTP catcher (e.g. Mailpit) to click them locally.
6. **The membership lookup stays in the tenancy mechanism.** `ResolveCurrentOrganization::membershipOf()` is reused by
   onboarding (after locking the user row, so a double tap sees the first organization) and by `AuditLog` (a `User`
   subject is audited against its membership; F-009 R4). No new file joins the cross-organization allowlist.
7. **Slug uniqueness and format are database rules too:** a unique index on `lower(slug)` decides a race (B10) and a
   CHECK constraint mirrors B5. The reserved list lives in `config/rakyt.php` and a test keeps it a superset of every
   route's first segment.
8. **The onboarding trip is the organization's first trip (lowest id)**; step 3 edits that trip's first departure
   that has not started, and a stale draft gets a new departure (the past one stays read-only, F-003).
   The resume step is derived from data (E2). `organizations.first_published_at` is set by `TripService::publish`,
   so a first publish from the onboarding flow or the F-003 editor both lock the slug. `EnsureOnboardingFinished` redirects only page visits (GET, not JSON),
   so step 2's photo uploads through the F-003 trip routes pass.
9. **Payment methods (pulled forward from F-006):** a `payment_methods` table (bank / e-wallet; provider from a config
   list; ULID `public_id`), owned by the organization. Step 4 sends the whole list: listed ids are updated, new blocks
   added, unlisted accounts removed — in one transaction, audited once as `payment_instructions.changed` with
   before/after (ADR-0018). `ActivePaymentMethods` replaces F-003's `NoPaymentMethodsYet` as `PaymentReadiness`.
10. **A time-zone change recomputes preset deadlines in `DepartureService::rezoneDeadlines()`** inside the profile
    transaction, audited per departure; a deadline that lands in the past closes the departure (reason `deadline`),
    as `syncWithTrip()` already does for meeting-time changes.
11. **Organization profile columns are NOT NULL;** rows created before F-001 (tests, local data) are back-filled with
    placeholders by the migration.

## Consequences

- Good: no new dependency; no new scope bypass; the auth flows reuse framework primitives that are well tested.
- Good: every payment-instruction change is already audited when F-006 adds QRIS, cash and the change notification.
- Bad / costs: reset and verification links cannot be read from `mail.log` locally; a mail catcher is needed.
- Bad / costs: auth events of a seller who has no organization yet (e.g. a reset before step 1) are not audited —
  `audit_events` needs an organization.
- Follow-ups: ADR-0018's 24-hour block on payment-instruction changes after a reset or email change, and the
  change notification to the sign-in email, are not built (F-006 / F-009 B12). F-001 Q15–Q17.

## Alternatives considered

- Laravel Fortify — adds routes, actions and config to override for Bahasa Inertia pages; little left to reuse.
- Deleting other sessions from the `sessions` table — needs raw database access outside the allowlist.
- Token as a route segment (Laravel default) — path segments are not redacted from logs.
- A per-step "current onboarding trip id" on the organization — extra state that can drift; the first trip is enough
  while one user owns one organization.
