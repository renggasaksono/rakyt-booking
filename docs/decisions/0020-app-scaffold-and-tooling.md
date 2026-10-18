---
id: 0020
title: App scaffold, module layout and tooling
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: [ADR-0002, ADR-0013, F-009]
---

# 0020. App scaffold, module layout and tooling

*Proposed by the builder agent on 2026-10-17; accepted by Rengga on 2026-10-18.*

## Context

ADR-0002 chose Laravel + Inertia + Vue 3 + Tailwind as a modular monolith but left the scaffold open. F-009 slice A
needs the app to exist. Laravel's Vue starter kit ships auth screens, settings pages and English copy that overlap
F-001 (email + password per ADR-0012, Bahasa copy per ADR-0007) and would have to be rewritten.

## Decision

- **Laravel 13 skeleton without a starter kit.** Inertia (`inertiajs/inertia-laravel` + `@inertiajs/vue3`), Vue 3 and
  Tailwind 4 added by hand. Auth screens are built in F-001.
- **Modules are top-level namespaces under `app/`**, named after the domain: `app/Tenancy`, `app/Audit`,
  `app/Security`, `app/Media`, `app/Logging` now; `app/Trips`, `app/Bookings`, `app/Admin`, … as features land.
  Eloquent models stay in `app/Models` and policies in `app/Policies` so Laravel's discovery keeps working.
- **Tests:** PHPUnit (Laravel default), run against PostgreSQL locally and in CI (ADR-0013). Test methods are
  snake_case with the acceptance-criterion id in lower case, e.g. `test_ac_a2_…` for F-009 A2 — Pint's
  `php_unit_method_casing` rewrites `test_AC_A2_…` into `test_a_c_a2_…`.
- **Lint:** Laravel Pint (PHP), ESLint with `eslint-plugin-vue` (`flat/recommended`, 4-space indent).
- **CI:** GitHub Actions — Pint, ESLint, Vite build, PHPUnit on PostgreSQL 17, `composer audit`,
  `npm audit --audit-level=high`, gitleaks.
- **Image processing:** `intervention/image` (GD driver) for re-encoding public photos (ADR-0015).
- **Fonts:** Poppins via the Vite fonts helper (Bunny Fonts); body uses the system Arial stack (design README).

## Consequences

- Good: no starter-kit code to delete; one obvious place per module; CI matches production's database.
- Bad / costs: auth scaffolding must be written in F-001 instead of generated.
- Follow-ups: add `app/Admin/Queries` with F-010 (cross-organization reads, ADR-0011).

## Alternatives considered

- Laravel Vue starter kit — fast start, but English auth/settings screens and extra pages to strip.
- Pest instead of PHPUnit — nicer syntax; not worth the extra dependency for now.
- `app/Modules/<Name>` with per-module providers — more ceremony than a small team needs.
