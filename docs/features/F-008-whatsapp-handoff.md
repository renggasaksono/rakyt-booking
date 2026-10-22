---
id: F-008
title: WhatsApp contact & prefilled messages
status: in-progress
priority: P0
roadmap: mvp-week-4
mockups: [1a, 1b, 1d, 2e, 1e]
decisions: ["0007", "0008", "0021", "0025", "0029", "0032"]
updated: 2026-10-22
---

# F-008 · WhatsApp contact & prefilled messages

> **Build state:** built 2026-10-21; `date_request` handoff added with F-004 on 2026-10-22 — 22/24 acceptance criteria
> covered by passing tests (all of AC-A1…A10 and AC-B1…B11, plus AC-B9a shipped by F-004). AC-A11 and AC-B12 are manual
> real-device reviews (Android Chrome, iOS Safari, Instagram/TikTok in-app browsers) and remain unticked. The shared builder is `App\WhatsApp\WhatsAppLink` (`to()` / `toNumber()` / the
> text-free `chatNumber()`); every message body lives in `lang/id/whatsapp.php`. Section **A** (Tanya via WhatsApp on
> the trip page) was approved 2026-10-19; section **B** (the template set) was made ready 2026-10-21; QB1 (one real
> home — all bodies move) and QB2 ("booking confirmed" message out of P0) were decided by Rengga 2026-10-21 (D7, D8).
> No open question remains for either section.

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
- **B.** `wa.me` deep links with prefilled, URL-encoded Bahasa messages for: customer → seller (Chat WhatsApp on the
  seller page; Kabari operator with booking reference on the booking page), and seller → customer (Ingatkan
  pembayaran; Kabari pelanggan on cancellation and on a rejected proof). Every message body lives in one translation
  namespace so wording is editable in one place (D4). A seller → customer "Balas permintaan tanggal" message (F-004)
  rides on the same builder and home; its exact wording is owned by F-004 (QB3). A seller → customer "booking
  confirmed" message is **out of P0** (D8).

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

- [x] **AC-A1 Link shown.** Given a public trip page of a seller with a valid WhatsApp number (AC-A6), then the page
  shows "Tanya via WhatsApp" (`lang/id/public.php → trip.ask_whatsapp`) linking to
  `https://wa.me/<digits>?text=<encoded>`, where digits are the seller's E.164 number without "+". It opens in a new
  tab/app (`target="_blank"`, `rel="noopener noreferrer"`).
- [x] **AC-A2 Placement and CTA rule.** On a **scheduled** trip the link is an ink-outline button (min. 44 px tall,
  full width at phone width) placed directly after the departures section. On a `request_date` trip it is **not** shown
  — there the gold "Ajukan tanggal →" WhatsApp handoff is the page's only WhatsApp link (F-004, ADR-0032). It is never
  gold and never in the sticky bar: the page keeps exactly one gold button, "Pesan →" or "Ajukan tanggal →"
  (F-002 AC-37, ADR-0008).

> **Request-date exception (F-004, ADR-0032).** AC-A1…AC-A5 describe the ink "Tanya via WhatsApp" link as it appears on
> **scheduled** trips. On `request_date` trips that ink link is not rendered (the gold "Ajukan tanggal →" handoff is the
> only WhatsApp link); `PublicPagePresenter` still emits `organization.whatsapp_url` with the `whatsapp.trip` message
> (AC-A3 regression keeps asserting it), but no button on a request-date page uses it.
- [x] **AC-A3 Trip-only message.** Given no departure is selected — request-a-date trip, scheduled trip with no
  bookable departure, or no departure listed — then the text is `whatsapp.trip` (new in `whatsapp.php`, §A, D7):
  "Halo :name, saya tertarik dengan :trip. Saya mau tanya beberapa detail sebelum memesan." with the seller's Nama
  usaha and the trip name. No placeholder, empty brackets or stray spaces remain.
- [x] **AC-A4 Dated message.** Given a departure is selected, then the text is `whatsapp.trip_dated` (new in `whatsapp.php`, §A, D7):
  "Halo :name, saya tertarik dengan :trip keberangkatan :date. Saya mau tanya beberapa detail sebelum memesan."
  where `:date` is that departure's dates formatted by `BookingPresenter::dates()` (the same text as the F-005
  booking page).
- [x] **AC-A5 Follows the selection.** The selected departure is the one the sticky bar shows and "Pesan →" books —
  including the first bookable departure preselected on load (D2). When the customer selects another departure, the
  link changes to that departure's dated message without a reload. Full and closed rows cannot be selected, so they
  never appear in a message.
- [x] **AC-A6 No broken link.** Given the seller's `whatsapp` is not a valid Indonesian mobile number
  (`PhoneNumber::isMobile()` false — e.g. the `'-'` backfill of older rows), then the trip page shows no WhatsApp
  button and sends no WhatsApp URL; the rest of the page and the booking CTA are unchanged.
- [x] **AC-A7 Encoding.** Trip and seller names with `&`, `?`, `#`, `+`, quotes, emoji or non-Latin
  letters arrive intact in WhatsApp: the text is encoded with `rawurlencode` once, server-side.
- [x] **AC-A8 Booking unaffected.** The "Pesan →" and "Ajukan tanggal →" targets, the departure picker, and the
  F-005 booking flow behave exactly as before; nothing requires a WhatsApp tap.
- [x] **AC-A9 One link builder.** The seller page (F-002 AC-11), the booking page (F-005 AC-33) and the trip page
  build links through one helper, `App\WhatsApp\WhatsAppLink::to(Organization $organization, string $text): ?string`,
  which returns `null` when AC-A6 applies. Their texts are unchanged; the seller and booking pages now also hide
  their WhatsApp button for an invalid number (D3) instead of linking to `https://wa.me/?text=…`.
- [x] **AC-A10 Tenant isolation.** A trip page only ever carries its own seller's number: a test with two sellers
  asserts seller B's trip page contains no URL with seller A's digits.
- [ ] **AC-A11 Editable before sending.** The message is only prefilled (`?text=`); the customer edits and sends it
  in WhatsApp. Checked by hand in the real-device review (Android Chrome, iOS Safari, Instagram and TikTok in-app
  browsers), together with F-002 AC-36.

### B. Template set (ready)

Section A already specifies the shared customer → seller builder `App\WhatsApp\WhatsAppLink::to(Organization, string):
?string` (org number, invalid-number guard AC-A6/A9/D3, single server-side `rawurlencode`) for the seller, trip and
booking pages. Section B consolidates **every** prefilled WhatsApp message body into one home, and covers the
seller → customer messages that ride on the booking's own customer number.

- [x] **AC-B1 One template home.** Every prefilled WhatsApp message body is defined in one namespace,
  `lang/id/whatsapp.php` (D4). The link builders read bodies only from this namespace; no message body is defined in
  `public.php`, `booking.php` or `bookings.php` after the migration (button labels stay in their page's lang file —
  e.g. `public.seller.chat_whatsapp`, `bookings.show.remind`, `public.trip.ask_whatsapp`). A test asserts the key set
  in `whatsapp.php` and that the old body keys are gone, including the section-A public bodies (D7). Keys: `seller`,
  `customer`, `trip`,
  `trip_dated`, `reminder`, `cancelled`, `rejected`, `tomorrow` (and `date_reply`, reserved — see AC-B9; plus
  `date_request`, added by F-004 — see AC-B9a and D10). When F-004 adds `date_request`, this exact-keys test is updated
  in the same change.
- [x] **AC-B2 Move does not change the text (regression).** Each migrated template renders byte-for-byte identical to
  its pre-move text for the same placeholders. The existing tests for F-002 AC-11 (`seller`), F-005 AC-33
  (`customer`), F-006 AC-18 (`rejected`), F-007 AC-25 (`reminder`, `tomorrow`) and F-007 AC-29 (`cancelled`) keep
  their exact expected strings and still pass against the new home.
- [x] **AC-B3 Customer → seller, seller page.** "Chat WhatsApp" (F-002 AC-11) builds `https://wa.me/<org digits>?text=…`
  via `WhatsAppLink::to($organization, trans('whatsapp.seller', ['name' => Nama usaha]))`:
  "Halo :name, saya lihat halaman booking Anda di Rakyt. Mau tanya…". Opens in a new tab
  (`target="_blank"`, `rel="noopener noreferrer"`); hidden when the org number is invalid (AC-A6/D3). Label text and
  placement are unchanged (Scope "Out").
- [x] **AC-B4 Customer → seller, booking page.** "Kabari operator via WhatsApp" (F-005 AC-33) builds
  `https://wa.me/<org digits>?text=…` via `WhatsAppLink::to($organization, trans('whatsapp.customer', […]))` with
  placeholders `:seller`, `:trip`, `:date`, `:n`, `:reference`:
  "Halo :seller, saya sudah pesan :trip tanggal :date untuk :n orang. Kode pesanan :reference." New tab; hidden on an
  invalid org number (D3); `:date` from `BookingPresenter::dates()`.
- [x] **AC-B5 Seller → customer link builder.** A sibling helper
  `App\WhatsApp\WhatsAppLink::toNumber(?string $e164, string $text): ?string` builds
  `https://wa.me/<customer digits>?text={rawurlencode(text) once}` from the **booking's own customer number** (E.164,
  captured and validated at booking time, F-005), returning `null` when the number fails `PhoneNumber::isMobile()`
  (same guard as `to()`). The reminder, cancelled, rejected and date-reply links all build through it; the text-free
  "Chat WhatsApp" button on the seller booking detail (F-007 AC-25, no `?text=`) uses a sibling `chatNumber(?string
  $e164): ?string` on the same customer number, with the same `PhoneNumber::isMobile()` guard — so that button is now
  hidden (not a broken `https://wa.me/` link) when the booking's number is invalid or a backfilled `'-'`.
- [x] **AC-B6 Seller → customer, payment reminder.** "Ingatkan via WA" (F-007 AC-25, only `awaiting_payment` with an
  active hold) builds via `toNumber(customer number, trans('whatsapp.reminder', […]))` with placeholders `:name`,
  `:reference`, `:trip`, `:date`, `:n`, `:total`, `:time`, `:url` (`:url` = the customer's booking link; `:time` uses
  the `tomorrow` fragment "besok :time" when the hold releases tomorrow):
  "Halo :name, pesanan :reference untuk :trip tanggal :date (:n orang) menunggu pembayaran :total. Kursi ditahan
  sampai :time. Detail pesanan: :url". Served through the `bookings.remind` 302 redirect so the customer token stays
  out of Hari ini's props (ADR-0029 §1).
- [x] **AC-B7 Seller → customer, cancellation.** "Kabari pelanggan via WA" after a seller cancellation (F-007 AC-29)
  builds via `toNumber(customer number, trans('whatsapp.cancelled', […]))` with `:name`, `:reference`, `:trip`,
  `:date`: "Halo :name, pesanan :reference untuk :trip tanggal :date dibatalkan. Hubungi kami jika ada pertanyaan."
- [x] **AC-B8 Seller → customer, rejected proof.** "Kabari pelanggan via WA" after Tolak (F-006 AC-18) builds via
  `toNumber(customer number, trans('whatsapp.rejected', […]))` with `:name`, `:reference`:
  "Halo :name, bukti transfer untuk pesanan :reference belum bisa kami terima. Silakan hubungi kami."
- [x] **AC-B9 Seller → customer, date-request reply (F-004).** When F-004 ships, its "Balas" action (F-004 AC "Balas
  opens WhatsApp with a prefilled message (F-008)") builds via `toNumber(date request's own customer number, …)`
  reading its body from `lang/id/whatsapp.php → date_reply`. F-008 reserves the home and the builder path only; the
  **exact wording is owned by F-004** because it depends on F-004's accept/decline + price flow (QB3). No AC-B here
  asserts the copy; F-004's own ACs do.
- [x] **AC-B9a Customer → seller, date-request handoff (F-004).** F-004's "Ajukan tanggal →" CTA on the public trip
  page builds `https://wa.me/<org digits>?text=…` via `WhatsAppLink::to($organization, trans('whatsapp.date_request',
  ['name' => Nama usaha, 'trip' => trip name]))`, reading its body from `lang/id/whatsapp.php → date_request`:
  "Halo :name, saya mau ajukan tanggal untuk :trip. Boleh tahu tanggal yang tersedia?" (D10). This is a **customer →
  seller** template on the organization number — distinct from the reserved **seller → customer** `date_reply`. F-008
  owns the home and reserves the key; **F-004 owns the copy and the CTA** and asserts it (F-004 AC-2…AC-4). The gold
  styling of that CTA is the exception in ADR-0032, not AC-A2.
- [x] **AC-B10 Encoding, new tab, no leak.** Every section-B link is encoded with `rawurlencode` exactly once,
  server-side; names and references containing `&`, `?`, `#`, `+`, quotes, emoji or non-Latin letters arrive intact.
  Customer → seller links open in a new tab (`target="_blank"`, `rel="noopener noreferrer"`); the seller → customer
  reminder goes through the `bookings.remind` redirect. No phone number or raw template is ever sent to the browser on
  its own — the client only follows a prebuilt URL or a server redirect.
- [x] **AC-B11 Tenant isolation (seller → customer).** A seller → customer link only ever carries that booking's own
  customer number. A two-seller test asserts seller B cannot obtain a reminder / cancelled / rejected URL for seller
  A's booking (booking scoping blocks it), and that the digits in any built URL equal the booking's own customer
  number — never another booking's and never the organization's own number.
- [ ] **AC-B12 Real-device review.** The seller → customer links open WhatsApp (app or web) with the right number and
  prefilled, editable message on Android Chrome, iOS Safari, and Instagram/TikTok in-app browsers. Checked by hand in
  the real-device review alongside AC-A11.

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
- **Copy.** The label `trip.ask_whatsapp` ("Tanya via WhatsApp") stays in `lang/id/public.php`; the two message
  bodies live in `lang/id/whatsapp.php` as `whatsapp.trip` and `whatsapp.trip_dated` (moved there with section B, D7).
  Two templates rather than one optional `:date`, so no empty placeholder can leak (AC-A3).
- **Tests** (`test_ac_a<n>_…`): PHPUnit in `tests/Feature/PublicPages` for AC-A1, A3, A4, A6, A7, A9, A10 and the
  exact keys; existing F-002 AC-11 and F-005 AC-33 tests keep passing with their texts unchanged and gain an
  invalid-number case; F-005 booking tests are the AC-A8 regression. Vitest covers link choice (AC-A5).

### Edge cases (section A)

- Request-a-date trip; scheduled trip with all departures full/closed or none listed → trip-only message.
- Preselected first bookable departure → dated message (D2).
- More departures than shown (F-002 AC-18): hidden rows can't be selected until shown; the link follows whichever is.
- Seller with an invalid stored number → no button on any public page (D3).
- Long trip name: the button label stays fixed; only the message carries the name.

### Section B

- **Template home (D4).** All prefilled WhatsApp **message bodies** move into one namespace, `lang/id/whatsapp.php`.
  Button **labels** stay with their page (they are that page's UI copy, not shared templates). The migration keeps the
  rendered text identical (AC-B2):

  | Message | Old location | New key (`whatsapp.*`) | Number | Built by | Placeholders |
  |---|---|---|---|---|---|
  | Chat WhatsApp (seller page) | `public.whatsapp.seller` | `seller` | org | F-002 AC-11 via `to()` | `:name` |
  | Kabari operator (booking page) | `booking.whatsapp.customer` | `customer` | org | F-005 AC-33 via `to()` | `:seller :trip :date :n :reference` |
  | Tanya via WhatsApp (trip-only) | — (new, §A) | `trip` | org | F-008 §A AC-A3 via `to()` | `:name :trip` |
  | Tanya via WhatsApp (dated) | — (new, §A) | `trip_dated` | org | F-008 §A AC-A4 via `to()` | `:name :trip :date` |
  | Ingatkan via WA (reminder) | `bookings.whatsapp.reminder` | `reminder` | customer | F-007 AC-25 via `toNumber()` | `:name :reference :trip :date :n :total :time :url` |
  | Reminder time fragment | `bookings.whatsapp.tomorrow` | `tomorrow` | — | F-007 AC-25 | `:time` |
  | Kabari pelanggan (cancelled) | `bookings.whatsapp.cancelled` | `cancelled` | customer | F-007 AC-29 via `toNumber()` | `:name :reference :trip :date` |
  | Kabari pelanggan (rejected) | `bookings.whatsapp.rejected` | `rejected` | customer | F-006 AC-18 via `toNumber()` | `:name :reference` |
  | Ajukan tanggal (handoff) | — (new) | `date_request` | org | F-004 via `to()` (AC-B9a) | `:name :trip` |
  | Balas permintaan tanggal | — (new) | `date_reply` (reserved) | customer | F-004 via `toNumber()` | owned by F-004 (QB3) |

  The section-A seller-page body (`seller`) moves here too (D7, decided by Rengga 2026-10-21): one real home for every
  body. The section-A trip bodies (`trip`, `trip_dated`) are new and created directly in `whatsapp.php`, not moved —
  they never existed in `public.php`. Section A's AC-A3/A4 key references point here accordingly (above).
- **Number source.** Customer → seller messages (`seller`, `customer`, `trip`, `trip_dated`) use the organization's
  number via `WhatsAppLink::to()`. Seller → customer messages (`reminder`, `cancelled`, `rejected`, `date_reply`) use
  the **booking's own customer number** via `WhatsAppLink::toNumber()`. Numbers are stored E.164; links use digits
  only (no `+`). Indonesian mobiles only in P0 (QB4, mirrors F-005 Q6).
- **Encoding.** One server-side `rawurlencode` of the fully-interpolated text, in the builder — never in the browser
  and never twice (AC-B10, same rule as AC-A7).
- **Tenant isolation.** Seller → customer links are built only from a booking the current organization owns (booking
  scope + `BookingPolicy`); the URL carries that booking's customer number and nothing of another seller or customer
  (AC-B11, parallel to AC-A10). The non-negotiable holds here too: a seller → customer link must never expose a number
  from another tenant's booking.
- **No new schema, no automation.** Section B is translation files + two builder methods. No WhatsApp Business API, no
  inbox/inquiry records, no automated sending, and no change to booking, capacity, payment or confirmation (Scope
  "Out"). The reminder/cancel/reject links remain manual, seller-initiated, editable before sending.
- **Tests** (`test_ac_b<n>_…`): PHPUnit asserts the single home and key set (AC-B1) and the unchanged text of each
  migrated template (AC-B2, reusing the F-002 / F-005 / F-006 / F-007 string assertions); `WhatsAppLink::toNumber`
  unit/feature tests for the guard and single encode (AC-B5, AC-B10); the `bookings.remind` redirect, cancellation and
  reject-response tests cover AC-B6/B7/B8 with their exact strings; a two-seller cross-tenant test covers AC-B11.
  AC-B9's copy is tested by F-004 when it ships; AC-B12 is manual device review.

### Edge cases (section B)

- Customer number invalid or backfilled `'-'` on an old booking → `toNumber()` (and the text-free `chatNumber()`)
  returns `null`; the seller → customer button — including the detail's "Chat WhatsApp" — is hidden rather than linking
  to a broken `https://wa.me/` or `https://wa.me/?text=…` (same guard as D3; D9). Normal bookings always have a valid
  number (validated at booking time, F-005 Q6), so this path only affects backfilled/imported rows.
- A booking whose hold releases after midnight → the reminder's `:time` uses the `tomorrow` fragment ("besok :time").
- `date_reply` is reserved but has no body until F-004; nothing renders it before then.
- `date_request` (customer → seller, org number) is added and rendered by F-004's "Ajukan tanggal →" handoff (AC-B9a,
  D10); its gold CTA styling is the ADR-0032 exception, not a change to AC-A2's ink "Tanya via WhatsApp".
- "Booking confirmed" seller → customer message is not shipped in P0 (D8); no key, no button.

## Decisions

- **D1** — Lives in F-008 (owns WhatsApp templates), not F-002. — Rengga · 2026-10-19
- **D2** — The preselected departure counts as selected; the customer can delete the date in WhatsApp. — Rengga ·
  2026-10-19
- **D3** — The shared helper's invalid-number guard also applies to the seller and booking pages. — Rengga ·
  2026-10-19
- **D4** — One template home: every prefilled WhatsApp message body lives in `lang/id/whatsapp.php`; button labels stay
  in their page's lang file. Satisfies the scope's "editable in one place". — Planner · 2026-10-21; confirmed by Rengga
  via D7
- **D5** — Seller → customer messages use a sibling builder `WhatsAppLink::toNumber(?string $e164, string $text):
  ?string` on the booking's own customer number, with the same invalid-number guard and single server-side
  `rawurlencode` as `to()`. — Planner · 2026-10-21 (recommended default in use)
- **D6** — Indonesian mobiles only for every WhatsApp link in P0 (`PhoneNumber::isMobile()`); mirrors F-005 Q6. —
  Planner · 2026-10-21 (recommended default in use)
- **D7** — The section-A public bodies (`seller`, `trip`, `trip_dated`) also move into `lang/id/whatsapp.php`, for one
  real home; section A's AC-A3/A4 key references move with them (text-identical). Resolves QB1 (yes). — Rengga ·
  2026-10-21
- **D8** — No seller → customer "booking confirmed" message in P0: no key, no button; deferred to P1 notifications or a
  later F-006 follow-up. Resolves QB2 (no). — Rengga · 2026-10-21
- **D10** — F-004's re-scope to a WhatsApp handoff (Rengga 2026-10-22, "Option B") adds a new **customer → seller** key
  `date_request` to this home for the "Ajukan tanggal →" CTA, built via `WhatsAppLink::to()`. The copy lives with F-004
  (AC-B9a); F-008 reserves the key and the exact-keys test is updated when F-004 lands. The gold styling of that CTA is a
  scoped exception to the one-gold-CTA rule recorded in ADR-0032, not a change to AC-A2 (which governs the ink "Tanya via
  WhatsApp" link). — Planner · 2026-10-22
- **D9** — The text-free "Chat WhatsApp" link (F-007 AC-25) builds through a third builder method
  `WhatsAppLink::chatNumber(?string $e164): ?string`, guarded like `to()`/`toNumber()`. So the seller booking detail's
  "Chat WhatsApp" button is hidden for an invalid / backfilled customer number instead of rendering a broken
  `https://wa.me/` link — a small behaviour change for those rows, consistent with D3. — Builder · 2026-10-21

## Open questions

Section A: none. QB1 and QB2 were decided by Rengga 2026-10-21 (see D7, D8). Remaining (recommended defaults in use;
none blocking):

- **QB3.** Finalize the F-004 "Balas permintaan tanggal" copy now, or let F-004 own it? **Recommended: F-004 owns the
  wording** — it depends on F-004's accept/decline + price flow, still a draft decision. Section B reserves only the
  `date_reply` key and the `toNumber()` path. Non-blocking (tracked in F-004's open questions).
- **QB4.** International (non-+62) numbers in WhatsApp links? **Recommended: Indonesian mobiles only in P0** — settled
  by D6, mirroring the already-decided F-005 Q6. Informational; not blocking.

## Changelog

- 2026-10-17 — created
- 2026-10-19 — F-007 hand-off noted (reminder and cancellation messages)
- 2026-10-19 — Planner: section A (Tanya via WhatsApp on the trip page) with AC-A1…AC-A11 from the contextual
  WhatsApp change request; D1–D3 approved by Rengga; section A `ready`, section B still draft
- 2026-10-21 — Planner: section B (template set) made ready — AC-B1…AC-B12; D4–D6 recorded; canonical template home
  `lang/id/whatsapp.php` with migration map; open questions QB1–QB4 with recommended defaults (none blocking)
- 2026-10-21 — Rengga: QB1 (yes — section-A public bodies move too) and QB2 (no — "booking confirmed" out of P0)
  decided (D7, D8); section-A AC-A3/A4 key references updated; spec `ready` as a whole with no open question
- 2026-10-21 — Builder: sections A and B built together — `App\WhatsApp\WhatsAppLink` (`to`/`toNumber`/`chatNumber`),
  `lang/id/whatsapp.php` template home (bodies moved out of `public.php`/`booking.php`/`bookings.php`), "Tanya via
  WhatsApp" on the trip page, and the `whatsappUrl` link-choice helper. 21/23 ACs covered by passing tests (AC-A11 and
  AC-B12 are manual real-device reviews); `php artisan test`, Pint, ESLint and Vitest green
- 2026-10-21 — Planner: recorded the builder's third method `chatNumber()` in AC-B5, the section-B edge cases and D9
  (hides the detail's "Chat WhatsApp" button for an invalid / backfilled customer number)
- 2026-10-22 — Planner: additive change for F-004's re-scope (Option B) — new customer → seller key `date_request`
  (AC-B9a, D10, template table, edge case, AC-B1 key list); copy owned by F-004; gold CTA styling cross-referenced to
  ADR-0032; `0032` added to decisions. No behaviour change to the built A/B templates
- 2026-10-22 — F-004 build: `date_request` body added to `lang/id/whatsapp.php` and the AC-B1 exact-keys test; AC-B9a
  ticked (the "Ajukan tanggal →" handoff is built and tested in F-004). `date_reply` stays reserved/empty
- 2026-10-22 — Rengga (after preview): the ink "Tanya via WhatsApp" link is dropped from `request_date` trips (gold
  "Ajukan tanggal →" handoff is their only WhatsApp link). AC-A2 reworded + "Request-date exception" note added; ADR-0032
  consequence records it. Scheduled-trip behaviour unchanged; `organization.whatsapp_url` prop still emitted (AC-A3)
