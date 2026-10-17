---
id: F-010
title: Admin authentication & access control
status: draft
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0004", "0011"]
updated: 2026-10-17
---

# F-010 · Admin authentication & access control

## Problem

The product owner needs a protected place to run the platform during the pilot. Today the only way to see across
sellers would be the database — slow and risky.

## Scope

**In:** protected `/admin` area in the existing app; one platform admin flagged on `users` (set by seeder/artisan
command); server-side gate on every admin route; strong admin login (password + TOTP 2FA recommended — ADR-0011);
admin layout shell and navigation for F-011–F-015.

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
- [ ] Admin flag is granted by an artisan command or seeder and the grant is audited (F-015).
- [ ] Admin login is rate-limited; 2FA is enforced for the admin account if adopted in ADR-0011.
- [ ] Admin pages are not linked from seller or public UI, but security does not depend on that.

## Security

Server-side gate on the route group, not only in Vue. Admin session lifetime shorter than seller sessions.
No admin credentials or flags in `.env` committed to git.

## Open questions

- [ ] Admin auth mechanism — ADR-0009 (OTP) was rejected; sellers use email + password (ADR-0012). *Recommended:* same Fortify email + password stack plus required TOTP 2FA for the admin. Confirm when accepting ADR-0011. — Rengga
- [ ] Admin UI language: Bahasa Indonesia per ADR-0007, or English since it is internal-only? — Rengga

## Changelog

- 2026-10-17 — created
- 2026-10-17 — admin auth question updated: ADR-0009 rejected, seller sign-in is email + password (ADR-0012)
