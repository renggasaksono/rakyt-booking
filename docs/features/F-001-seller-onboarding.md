---
id: F-001
title: Seller onboarding & organization profile
status: draft
priority: P0
roadmap: mvp-weeks-2-3
mockups: [4a, 4b, 4c, 4d, 4e, 4f]
decisions: ["0004", "0009"]
updated: 2026-10-17
---

# F-001 · Seller onboarding & organization profile

## Problem

Operators won't sit through training. The promise on the sign-up screen is: **"Trip pertama Anda online dalam 10 menit."**

## Scope

**In:** sign-up/sign-in, organization profile (name, base city, booking slug, WhatsApp contact), guided 4-step first-trip flow (Usaha → Trip → Jadwal → Rekening), publish, share link.
Time budget per step: Akun 1′ · Usaha 1′ · Trip 4′ · Jadwal 2′ · Rekening 2′.

**Out:** logo & bio, itinerary per day, terms (done later from dashboard — 4f "Nanti, dari dashboard"); staff invites (P1).

## User stories

- As a new seller, I sign up with my WhatsApp number and get a code, no password.
- As a seller, I pick my booking link (`rakyt.id/<slug>`) and see instantly whether it's available.
- As a seller, I create my first trip, set price and first departure, add bank details and publish — in one guided flow.
- As a seller, I can save a draft and come back.

## Acceptance criteria

- [ ] Each step header shows "Langkah n dari 4" and estimated minutes left.
- [ ] Slug: lowercase, URL-safe, unique, reserved words blocked; availability checked live.
- [ ] Trip type choice (Open trip / Private / Day trip) presets booking mode and duration.
- [ ] Payment step comes last; trip can be saved as draft without it, but cannot be published without at least one payment method.
- [ ] Publish screen shows elapsed time, the booking link with Salin (copy) and Bagikan (share sheet).
- [ ] Organization and first membership (owner) created atomically.

## Data & rules

`users`, `organizations` (name, slug, city, whatsapp, plan), `organization_members` (role=owner).
Free plan: 1 active trip (ADR-0010).

## Open questions

- [ ] Auth: WhatsApp OTP vs email+password for the first release — ADR-0009
- [ ] Can the business name actually be prefilled from WhatsApp Business profile?
- [ ] Reserved slug list; can sellers change their slug later (breaks shared links)?
- [ ] "Akun" step (4a) is outside the 4 numbered steps — confirm count.

## Changelog

- 2026-10-17 — created from blueprint §3 and mockup round 4
