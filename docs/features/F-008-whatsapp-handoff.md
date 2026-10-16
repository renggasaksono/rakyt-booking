---
id: F-008
title: WhatsApp contact & prefilled messages
status: draft
priority: P0
roadmap: mvp-week-4
mockups: [1a, 1d, 2e, 1e]
decisions: ["0009"]
updated: 2026-10-17
---

# F-008 · WhatsApp contact & prefilled messages

## Problem

WhatsApp is where operators and customers already talk. Rakyt should hand off cleanly, not replace it.

## Scope

**In:** `wa.me` deep links with prefilled, URL-encoded Bahasa messages for: customer → seller (Chat WhatsApp; Kabari operator with booking reference), seller → customer (Ingatkan pembayaran; Balas permintaan tanggal; booking confirmed). Message templates editable in one place (translation files).

**Out:** automated sending via WhatsApp Business API (P1 "automated notifications"), except OTP if ADR-0009 is accepted.

## Acceptance criteria

- [ ] Each link opens WhatsApp (app or web) with the right number and message including the booking reference, trip, date, amount where relevant.
- [ ] Numbers stored E.164; links use digits only.
- [ ] Works on Android and iOS browsers and in IG/TikTok in-app browsers.

## Changelog

- 2026-10-17 — created
