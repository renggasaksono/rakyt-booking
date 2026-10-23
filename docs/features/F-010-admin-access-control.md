---
id: F-010
title: Admin authentication & access control
status: ready
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0004", "0011"]
updated: 2026-10-23
---

# F-010 · Admin authentication & access control

## Problem

The product owner needs a protected place to run the platform during the pilot. Today the only way to see across
sellers would be the database — slow and risky.

## Scope

**In:** protected `/admin` area in the existing app; one platform admin flagged on `users` (`users.is_platform_admin`,
set by seeder/artisan command only); server-side gate on every admin route; strong admin login (email + password plus
**required** TOTP 2FA — ADR-0011); a short admin session lifetime; admin layout shell and navigation for F-011–F-015,
in Bahasa Indonesia (ADR-0007).

**Out (explicitly):** admin registration, invitations, role/permission management UI, support-team accounts,
multiple admins with different powers, impersonation.

## Dependencies

F-009 (auth baseline, rate limiting, policies) · ADR-0011 accepted.

## User stories

- As the platform admin, I sign in with strong authentication and reach `/admin`.
- As a seller, I can never reach admin pages or admin data, whatever URL I type.

## Acceptance criteria

- [ ] Guest requesting any `/admin` route is redirected to sign-in.
- [ ] Authenticated seller (any organization role) requesting any `/admin` route or admin endpoint gets 403 (or 404); covered by a test that iterates all admin routes.
- [ ] Only a user with the platform-admin flag passes the gate; the flag cannot be set through any HTTP request.
- [ ] Admin flag is granted by an artisan command or seeder and the grant is audited (F-015); the same command can revoke it (also audited).
- [ ] Admin login is rate-limited (reuse F-009's login limiter) and TOTP 2FA is **required**: an admin who has not completed the two-factor challenge cannot reach any `/admin` route. TOTP enrolment happens when the admin flag is granted.
- [ ] Admin session lifetime is shorter than a seller session; the value is set in config, not hard-coded per route.
- [ ] Admin UI copy is in Bahasa Indonesia and lives in `lang/id/*` like the rest of the app (no hard-coded strings).
- [ ] Admin pages are not linked from seller or public UI, but security does not depend on that.

## Security

Server-side gate on the route group, not only in Vue. Admin session lifetime shorter than seller sessions.
No admin credentials or flags in `.env` committed to git.

## Open questions

None — both resolved 2026-10-23 (see changelog).

## Changelog

- 2026-10-17 — created
- 2026-10-17 — admin auth question updated: ADR-0009 rejected, seller sign-in is email + password (ADR-0012)
- 2026-10-23 — **ready.** ADR-0011 accepted. Admin auth = Fortify email + password + **required** TOTP 2FA (Rengga); admin UI language = Bahasa Indonesia (Rengga). ACs firmed for required TOTP, `users.is_platform_admin`, flag grant/revoke, short admin session and Bahasa copy.
