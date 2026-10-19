---
id: F-008
title: WhatsApp contact & prefilled messages
status: ready
priority: P0
roadmap: mvp-week-4
mockups: [1a, 1b, 1d, 2e, 1e]
decisions: ["0007", "0008", "0021", "0025"]
updated: 2026-10-19
---

# F-008 · WhatsApp contact & prefilled messages

> **Build state:** section **A** (Tanya via WhatsApp on the trip page) is `ready` — approved 2026-10-19. Section
> **B** (the remaining template set) is still a draft: make it ready before building it.

## Problem

WhatsApp is where operators and customers already talk. Rakyt should hand off cleanly, not replace it.

A customer reading one trip page today can only reach the seller from the seller page ("Chat WhatsApp", F-002
AC-11), with a generic message that names neither the trip nor the date. Customers who want to ask one thing before
booking must go back, open WhatsApp, and explain which trip they mean.

## Scope

**In:**
- **A.** A contextual "Tanya via WhatsApp" link on the public trip page (`/<slug>/<trip>`), prefilled with the trip
  name and, when one is selected, the departure date. One shared server-side link builder used by every
  customer → seller link (seller page, trip page, booking page).
- **B (draft).** `wa.me` deep links with prefilled, URL-encoded Bahasa messages for: customer → seller (Chat WhatsApp;
  Kabari operator with booking reference), seller → customer (Ingatkan pembayaran; Balas permintaan tanggal; booking
  confirmed). Message templates editable in one place (translation files).

**Out (explicitly):**
- Automated sending via WhatsApp Business API (P1 "automated notifications"). (Seller sign-in is email + password,
  [ADR-0012](../decisions/0012-email-password-authentication.md) — no WhatsApp OTP.)
- Built-in chat, inbox, inquiry records/statuses, or booking creation from a conversation.
- Any change to booking, capacity, payment, confirmation or cancellation.
- Changing the seller-page "Chat WhatsApp" label or text (only its number guard changes, AC-A9).

## User stories

- As a customer, I want to ask the seller about this trip on WhatsApp straight from the trip page, with the trip and
  date already written, so that I don't have to explain which trip I mean.
- As a customer, I want to book without contacting the seller first, so that asking stays optional.
- As a seller, I want questions to arrive already naming the trip and date, so that I can answer without asking back.

## Acceptance criteria

### A. Tanya via WhatsApp on the trip page (ready)

- [ ] **AC-A1 Link shown.** Given a public trip page of a seller with a valid WhatsApp number (AC-A6), then the page
  shows "Tanya via WhatsApp" (`lang/id/public.php → trip.ask_whatsapp`) linking to
  `https://wa.me/<digits>?text=<encoded>`, where digits are the seller's E.164 number without "+". It opens in a new
  tab/app (`target="_blank"`, `rel="noopener noreferrer"`).
- [ ] **AC-A2 Placement and CTA rule.** The link is an ink-outline button (min. 44 px tall, full width at phone width)
  placed directly after the departures section; on a trip without that section (request-a-date mode) it follows the
  header. It is never gold and never in the sticky bar: the page keeps exactly one gold button, "Pesan →" or
  "Ajukan tanggal →" (F-002 AC-37, ADR-0008).
- [ ] **AC-A3 Trip-only message.** Given no departure is selected — request-a-date trip, scheduled trip with no
  bookable departure, or no departure listed — then the text is `public.whatsapp.trip`:
  "Halo :name, saya tertarik dengan :trip. Saya mau tanya beberapa detail sebelum memesan." with the seller's Nama
  usaha and the trip name. No placeholder, empty brackets or stray spaces remain.
- [ ] **AC-A4 Dated message.** Given a departure is selected, then the text is `public.whatsapp.trip_dated`:
  "Halo :name, saya tertarik dengan :trip keberangkatan :date. Saya mau tanya beberapa detail sebelum memesan."
  where `:date` is that departure's dates formatted by `BookingPresenter::dates()` (the same text as the F-005
  booking page).
- [ ] **AC-A5 Follows the selection.** The selected departure is the one the sticky bar shows and "Pesan →" books —
  including the first bookable departure preselected on load (D2). When the customer selects another departure, the
  link changes to that departure's dated message without a reload. Full and closed rows cannot be selected, so they
  never appear in a message.
- [ ] **AC-A6 No broken link.** Given the seller's `whatsapp` is not a valid Indonesian mobile number
  (`PhoneNumber::isMobile()` false — e.g. the `'-'` backfill of older rows), then the trip page shows no WhatsApp
  button and sends no WhatsApp URL; the rest of the page and the booking CTA are unchanged.
- [ ] **AC-A7 Encoding.** Trip and seller names with `&`, `?`, `#`, `+`, quotes, emoji or non-Latin
  letters arrive intact in WhatsApp: the text is encoded with `rawurlencode` once, server-side.
- [ ] **AC-A8 Booking unaffected.** The "Pesan →" and "Ajukan tanggal →" targets, the departure picker, and the
  F-005 booking flow behave exactly as before; nothing requires a WhatsApp tap.
- [ ] **AC-A9 One link builder.** The seller page (F-002 AC-11), the booking page (F-005 AC-33) and the trip page
  build links through one helper, `App\WhatsApp\WhatsAppLink::to(Organization $organization, string $text): ?string`,
  which returns `null` when AC-A6 applies. Their texts are unchanged; the seller and booking pages now also hide
  their WhatsApp button for an invalid number (D3) instead of linking to `https://wa.me/?text=…`.
- [ ] **AC-A10 Tenant isolation.** A trip page only ever carries its own seller's number: a test with two sellers
  asserts seller B's trip page contains no URL with seller A's digits.
- [ ] **AC-A11 Editable before sending.** The message is only prefilled (`?text=`); the customer edits and sends it
  in WhatsApp. Checked by hand in the real-device review (Android Chrome, iOS Safari, Instagram and TikTok in-app
  browsers), together with F-002 AC-36.

### B. Template set (draft — not ready)

- [ ] Each link opens WhatsApp (app or web) with the right number and message including the booking reference, trip, date, amount where relevant.
- [ ] Numbers stored E.164; links use digits only.
- [ ] Works on Android and iOS browsers and in IG/TikTok in-app browsers.

F-007 hand-off (2026-10-19): F-007 adds two seller → customer messages in `lang/id/bookings.php → whatsapp`
(`reminder` with the customer's booking link, `cancelled`); move them into this feature's template set with F-005's
`booking.php → whatsapp.customer`.

## Data & rules

### Section A

- **No schema change.** Uses `organizations.whatsapp` (E.164, F-001).
- **Link builder** `App\WhatsApp\WhatsAppLink::to()` → `https://wa.me/{digits}?text={rawurlencode(text)}` or `null`.
  `PublicPagePresenter::whatsappUrl()` and `BookingPresenter::whatsappUrl()` delegate to it.
- **Public fields** (F-002 AC-8; `PublicTenancyTest` exact-keys test updated in the same change):
  - Organization (trip page): `name`, `slug`, `whatsapp_url` — trip-only message (AC-A3), or `null` (AC-A6).
  - Departure: bookable rows add `whatsapp_url` — dated message (AC-A4), or `null` (AC-A6); `null` on full and closed
    rows, like the other bookable-only fields.
  - No phone number or template is sent on its own; the browser only picks a prebuilt URL.
- **Choosing the link** (browser): `selected?.whatsapp_url ?? organization.whatsapp_url`, in a helper in
  `resources/js/lib/publicPage.js` with a Vitest test. The button renders only when the result is non-null.
- **Dates** use `BookingPresenter::dates($start, $end, $today)` with the organization's `today`, as on the booking
  page. The trip page already carries `today`, so the text is as fresh as the rest of the page (no page cache, F-002
  Rules).
- **Copy** in `lang/id/public.php`: `trip.ask_whatsapp` ("Tanya via WhatsApp"), `whatsapp.trip`,
  `whatsapp.trip_dated`. Two templates rather than one optional `:date`, so no empty placeholder can leak (AC-A3).
- **Tests** (`test_ac_a<n>_…`): PHPUnit in `tests/Feature/PublicPages` for AC-A1, A3, A4, A6, A7, A9, A10 and the
  exact keys; existing F-002 AC-11 and F-005 AC-33 tests keep passing with their texts unchanged and gain an
  invalid-number case; F-005 booking tests are the AC-A8 regression. Vitest covers link choice (AC-A5).

### Edge cases (section A)

- Request-a-date trip; scheduled trip with all departures full/closed or none listed → trip-only message.
- Preselected first bookable departure → dated message (D2).
- More departures than shown (F-002 AC-18): hidden rows can't be selected until shown; the link follows whichever is.
- Seller with an invalid stored number → no button on any public page (D3).
- Long trip name: the button label stays fixed; only the message carries the name.

## Decisions

- **D1** — Lives in F-008 (owns WhatsApp templates), not F-002. — Rengga · 2026-10-19
- **D2** — The preselected departure counts as selected; the customer can delete the date in WhatsApp. — Rengga ·
  2026-10-19
- **D3** — The shared helper's invalid-number guard also applies to the seller and booking pages. — Rengga ·
  2026-10-19

## Open questions

- None for section A. Section B to be resolved when it is made ready.

## Changelog

- 2026-10-17 — created
- 2026-10-19 — F-007 hand-off noted (reminder and cancellation messages)
- 2026-10-19 — Planner: section A (Tanya via WhatsApp on the trip page) with AC-A1…AC-A11 from the contextual
  WhatsApp change request; D1–D3 approved by Rengga; section A `ready`, section B still draft
