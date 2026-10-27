---
id: F-010
title: Admin authentication & access control
status: done
priority: P0
roadmap: mvp-week-4
mockups: []
decisions: ["0004", "0011"]
updated: 2026-10-27
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

- [x] Guest requesting any `/admin` route is redirected to sign-in. *(`auth` redirects to `route('login')`; iterate test.)*
- [x] Authenticated seller (any organization role) requesting any `/admin` route or admin endpoint gets 403 (or 404); covered by a test that iterates all admin routes. *(403; `AdminAccessControlTest::test_ac_authenticated_seller_is_forbidden_from_every_admin_route`.)*
- [x] Only a user with the platform-admin flag passes the gate; the flag cannot be set through any HTTP request. *(`is_platform_admin` is not fillable; proven via a sign-up payload that smuggles it.)*
- [x] Admin flag is granted by an artisan command or seeder and the grant is audited (F-015). *(`admin:grant` / `admin:revoke`; both audited via `AuditLog` as platform-level, org-less events.)*
- [x] Admin login is rate-limited; 2FA is enforced for the admin account if adopted in ADR-0011. *(Sign-in uses the `seller-login` limiter; the TOTP challenge uses `throttle:admin-2fa`; required TOTP enforced by the gate.)*
- [x] Admin pages are not linked from seller or public UI, but security does not depend on that. *(No admin link in the seller/public UI; the `admin` shared prop is null for sellers; the iterate 403 test proves security is independent of links.)*
- [x] The platform admin is never a tenant: every seller route (each `auth` route outside `/admin` except "Keluar") refuses an admin, before and after the TOTP challenge — page visits redirect to `/admin` (whose gate asks for TOTP), writes and JSON get 403 — and an admin can never get an organization membership. A sign-in or TOTP redirect never follows a stale seller URL. *(`not-platform-admin` middleware, run right after `auth`; `AdminSellerRouteBoundaryTest` iterates every seller route for a pending and a confirmed admin; `admin:grant` refuses a seller member; `OrganizationMember` refuses an admin on create. Added 2026-10-27 after a local check found an admin could open `/mulai` and Pengaturan → Akun.)*

## Security

Server-side gate on the route group, not only in Vue. Admin session lifetime shorter than seller sessions.
No admin credentials or flags in `.env` committed to git.

## Open questions

- [x] Admin auth mechanism — ADR-0009 (OTP) was rejected; sellers use email + password (ADR-0012). **Resolved (2026-10-23):** the admin uses the same email + password sign-in as sellers, plus **required TOTP 2FA**. The TOTP is hand-rolled on `pragmarx/google2fa` rather than Laravel Fortify, because F-009's auth is hand-rolled and Fortify would duplicate/conflict the login routes — ADR-0011 says "e.g. Fortify", so the library, not the package, is what matters. Confirmed in ADR-0011. — Rengga
- [x] Admin UI language: **Resolved (2026-10-23):** Bahasa Indonesia per ADR-0007 (copy in `lang/id/admin.php`). The panel is internal but consistency wins; English can be revisited if a support team is added. — Rengga

- [ ] How does the admin change their own password or email? Pengaturan → Akun is seller-only (it would let a pre-TOTP session change the credentials). **Default in use:** password via "Lupa password" (the reset link goes to the admin's email; TOTP is still required after it — the reset is not audited today, since the admin has no organization), email via a fresh `admin:revoke` / `admin:grant` on a new account. An admin-only "Akun" page behind the TOTP gate can come later if this proves awkward.

## Implementation notes

- Flag + enrolment: `users.is_platform_admin` (boolean) and `two_factor_secret` (encrypted) / `two_factor_confirmed_at`. Neither is mass-assignable; both are set only by `php artisan admin:grant {email}` (which also prints the TOTP secret/otpauth URL to enrol) and cleared by `admin:revoke {email}`.
- Gate: `platform-admin` middleware on the `/admin` route group (plus `platform-admin.pending` guarding the TOTP challenge). No `organization`/`onboarded` — the admin is not a tenant.
- Seller boundary: every seller route group carries `not-platform-admin` (`App\Admin\Middleware\EnsureNotPlatformAdmin`), prioritised before the limiters, onboarding redirects and route model binding. "Keluar" is the only `auth` route shared with the admin. `LoginController` and the TOTP challenge drop an intended URL outside `/admin` (`App\Admin\AdminArea`) for an admin.
- Session: a rolling idle window from `config('rakyt.admin.session_lifetime')` (30 min, shorter than the 120-min seller session); past it the TOTP challenge is required again.
- Audit: `AuditAction::PlatformAdminGranted` / `PlatformAdminRevoked` are **platform-level** (no organization). `audit_events.organization_id` is now nullable for them; `OrganizationOptional` lets `AuditEvent` opt in without weakening any seller model.
- Shell: `AdminLayout.vue` + `pages/Admin/Dashboard.vue`; nav comes from the shared `admin` Inertia prop. F-011–F-015 hang their pages here and add nav items.

## Changelog

- 2026-10-17 — created
- 2026-10-17 — admin auth question updated: ADR-0009 rejected, seller sign-in is email + password (ADR-0012)
- 2026-10-23 — built; both open questions resolved; all acceptance criteria covered by tests; status → done. Admin auth is email + password + required TOTP 2FA (hand-rolled on pragmarx/google2fa). ADR-0011 accepted.
- 2026-10-27 — boundary fix: seller routes (onboarding, Pengaturan → Akun, verification resend, the seller app) refuse the platform admin before and after TOTP; sign-in and the TOTP challenge no longer follow a stale seller URL; `admin:grant` refuses a seller member and a membership can never be created for an admin. New AC added and covered; new open question on the admin's own password / email change (default: "Lupa password").
