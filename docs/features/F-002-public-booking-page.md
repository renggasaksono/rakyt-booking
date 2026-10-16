---
id: F-002
title: Public booking page
status: draft
priority: P0
roadmap: mvp-weeks-2-3
mockups: [1a, 1b, 2c, 3b, 3c]
decisions: ["0005", "0008"]
updated: 2026-10-17
---

# F-002 · Public booking page

## Problem

Sellers already have demand on IG/WA/TikTok but answer the same questions in chat. They need one link that shows trips, dates, seats and prices — with no marketplace chrome.

## Scope

**In:** seller page `rakyt.id/<slug>` (header, Chat WhatsApp, ↗ Bagikan, trip list); trip page (photo gallery, type & duration, short description, departure picker, trip detail sections, sticky price + "Pesan →" bar); "Tanggal lain? Ajukan tanggal ke operator" link.

**Out:** search/discovery across sellers, reviews, SEO landing pages (later), custom domains (P1).

## Acceptance criteria

- [ ] Trip card shows cover photo, name, "Mulai Rp…" (lowest open price), and next departure seats or "Ajukan tanggal" for request-date trips.
- [ ] Departure rows show dates, seats left ("Sisa n kursi" / "Tinggal n dari m kursi"), price (override or base), closing date when relevant.
- [ ] Full or closed departures stay visible, not faded: "– Penuh" / "Tidak bisa dipesan", no price (design rule).
- [ ] Trip detail sections render only if filled: Termasuk, Tidak termasuk, Kumpul & jadwal (with map link), Ketentuan.
- [ ] Bagikan uses Web Share API; fallback copies link and shows "Link disalin".
- [ ] Unpublished/draft trips and other sellers' trips are never reachable.
- [ ] Fast on a mid-range Android over 3G: lazy-loaded, resized images.

## Open questions

- [ ] SSR/caching needed for link previews (OG tags for WhatsApp/IG)? At minimum OG title/image per trip.
- [ ] Free-plan "Rakyt" branding placement.

## Changelog

- 2026-10-17 — created
