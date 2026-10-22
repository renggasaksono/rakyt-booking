---
id: F-004
title: Request-a-date mode (WhatsApp handoff)
status: in-progress
priority: P0
roadmap: mvp-weeks-2-3
mockups: [1e, 4c]
decisions: ["0005", "0008", "0032"]
updated: 2026-10-22
---

# F-004 · Request-a-date mode (WhatsApp handoff)

> **Build state:** built 2026-10-22 — all 10 acceptance criteria (AC-1…AC-10) covered by passing tests. The handoff
> URL is built by the shared `App\WhatsApp\WhatsAppLink::to()` from the new `whatsapp.date_request` body; the dead
> `/ajukan-tanggal` route is gone. `php artisan test`, Pint, ESLint and Vitest green. Awaiting human review/merge.

> **Scope note (2026-10-22, "Option B").** Rengga re-scoped this feature from a full in-app date-request flow to a
> **WhatsApp handoff**. What stays: the `request_date` booking mode and dateless publish (a Private trip publishes with
> no departure, F-003 checklist n/4). What changes: the public trip page's "Ajukan tanggal →" CTA now opens WhatsApp
> with a prefilled Bahasa message instead of an in-app form. The in-app date-request **entity, inbox, "Balas"
> accept/decline, and dashboard "Permintaan tanggal" rows are deferred to P1** and are explicitly Out below so no
> builder reintroduces them (consistent with F-008's Out list).

## Problem

Private tours and some one-off trips have no fixed schedule; the operator confirms availability by chatting with the
customer. Today a seller can publish such a trip without dates (F-003, checklist n/4), but the public trip page's gold
"Ajukan tanggal →" CTA points at `/<slug>/<trip>/ajukan-tanggal` — a route that was never built (it was this feature's
planned in-app entry point). So the one primary action on every request-date trip is a dead link, and customers have no
way to reach the seller about a date without leaving the page and finding the WhatsApp number themselves.

WhatsApp is where these conversations already happen (see F-008). Rakyt should hand off to it cleanly rather than build
an inbox.

## Scope

**In:**
- **Dateless publish (unchanged, F-003).** A `request_date` (Private) trip publishes with no departure: the publish
  checklist drops the departure item and counts n/4 (F-003 AC-9). The trip is discoverable on the seller page and has
  its own public trip page.
- **WhatsApp "Ajukan tanggal →" handoff.** On the public trip page (`/<slug>/<trip>`, F-002), the gold "Ajukan tanggal →"
  CTA opens WhatsApp via `App\WhatsApp\WhatsAppLink::to($organization, trans('whatsapp.date_request', ['name' => …,
  'trip' => …]))` — a new **customer → seller** template in F-008's template home (`lang/id/whatsapp.php`). The same
  handoff backs the two other places F-002 links a date request: the ink "Tanggal lain? Ajukan tanggal ke operator"
  link on scheduled trips (F-002 AC-25) and the gold "Ajukan tanggal →" shown on a scheduled trip with no bookable
  departure (F-002 AC-23).
- **Invalid-number guard.** Reusing `WhatsAppLink::to()`'s guard (F-008 AC-A6/D3): when the seller's `whatsapp` is not a
  valid Indonesian mobile, the handoff returns `null` and the button is hidden, exactly like every other WhatsApp link.
- **Seller fulfillment path (documented, no new code).** After agreeing a date on WhatsApp, the seller turns the
  conversation into a booking using features that already exist — see Data & rules → *Seller fulfillment path*.

**Out (explicitly) — deferred to P1:**
- The in-app `date_requests` entity / table, and any request record, status, or lifecycle.
- A customer-facing in-app date-request form (preferred date + participants + contact submitted to Rakyt).
- The seller "Balas" accept/decline action and its WhatsApp reply (F-008 reserves the `date_reply` key and the
  `toNumber()` path for that future work only).
- "Permintaan tanggal" rows in Pesanan terbaru / the booking list, a "◐ Permintaan tanggal" pill, and any count toward
  "Perlu tindakan" or the nav badge (the earlier F-007 hand-off is withdrawn).
- Automatic availability calendars.

These stay P1; promote them only with pilot evidence (roadmap). This feature must not reintroduce any inbox or record.

## User stories

- As a customer on a Private trip's page, I want to ask the seller about a date on WhatsApp in one tap, with the trip
  already named, so I don't have to leave the page and explain which trip I mean.
- As a seller, I want date requests to land in my WhatsApp already naming the trip, so I can answer and agree a date in
  the chat I already use.
- As a seller, I want to keep my Private trip published and discoverable with no fixed dates, so customers keep finding
  it between departures.

## Acceptance criteria

UI copy is Bahasa Indonesia (ADR-0007). "Digits" = the seller's E.164 WhatsApp number without "+".

- [x] **AC-1 Dateless publish (regression).** Given a `request_date` (Private) trip with a cover photo, name, base
  price and an active payment method, when the seller publishes, then it publishes with the checklist at **n/4** (no
  departure item, F-003 AC-9) and appears on the seller page and its own trip page.
- [x] **AC-2 Handoff CTA.** Given a published `request_date` trip of a seller with a valid WhatsApp number, then the
  public trip page shows exactly one gold CTA "Ajukan tanggal →" (`public.trip.request_date`) whose `href` is
  `https://wa.me/<digits>?text=<encoded>`, opening in a new tab (`target="_blank"`, `rel="noopener noreferrer"`).
- [x] **AC-3 Message content.** The `?text=` body is `whatsapp.date_request` (new key, Data & rules): "Halo :name, saya
  mau ajukan tanggal untuk :trip. Boleh tahu tanggal yang tersedia?" with `:name` = the seller's Nama usaha and `:trip`
  = the trip name. It is `rawurlencode`d exactly once, server-side (names with `&`, `?`, `#`, `+`, quotes, emoji or
  non-Latin letters arrive intact); no placeholder, empty bracket or stray space remains.
- [x] **AC-4 Built through the shared builder.** The URL is produced by `WhatsAppLink::to($organization,
  __('whatsapp.date_request', ['name' => $organization->name, 'trip' => $trip->name]))` — the one customer → seller
  builder (F-008 AC-A9). No second link builder, no body defined outside `lang/id/whatsapp.php`.
- [x] **AC-5 Invalid-number guard.** Given the seller's `whatsapp` is not a valid Indonesian mobile
  (`PhoneNumber::isMobile()` false — e.g. the `'-'` backfill), then `WhatsAppLink::to()` returns `null`, the
  "Ajukan tanggal →" CTA is **not rendered** and no `https://wa.me/?text=…` is emitted; the rest of the page is
  unchanged. (On a request-date trip this leaves the page with no primary CTA — acceptable, matching F-008 AC-A6/D3;
  normal sellers always have a valid number from onboarding B3.)
- [x] **AC-6 Scheduled-trip reuse.** Given a `scheduled` trip, then the ink "Tanggal lain? Ajukan tanggal ke operator"
  link (F-002 AC-25) and — when the trip has no bookable departure — the gold "Ajukan tanggal →" (F-002 AC-23) use the
  **same** `date_request` handoff URL, open in a new tab, and are hidden under the same invalid-number guard. The ink
  link stays an ink link (never gold); only the no-bookable-date CTA is gold.
- [x] **AC-7 Placeholder route removed.** The dead `url($base.'/ajukan-tanggal')` is gone from
  `PublicPagePresenter` and from the page; `urls.request_date` now holds the `wa.me` handoff URL (or `null`). No
  `/ajukan-tanggal` route, link or reference remains anywhere. `PublicTenancyTest`'s exact-keys assertion is updated if
  the shape of `urls.request_date` (string-or-null) changes.
- [x] **AC-8 One gold button (ADR-0032).** Every trip page keeps exactly one gold (Prada) button (F-002 AC-37,
  ADR-0008). On a `request_date` trip and on a `scheduled` trip with no bookable departure, that single gold button is
  the WhatsApp "Ajukan tanggal →" handoff — the deliberate, scoped exception to F-008 AC-A2 ("WhatsApp links are never
  gold") recorded in ADR-0032. The ink "Tanya via WhatsApp" general-inquiry link (F-008 §A) is shown only on scheduled
  trips, so the two WhatsApp links never both appear gold.
- [x] **AC-9 Tenant isolation.** A request-date trip page only ever carries its own seller's number. A two-seller test
  asserts seller B's trip page contains no `wa.me` URL with seller A's digits (parallel to F-008 AC-A10).
- [x] **AC-10 No in-app request entity (Out guard).** There is no `date_requests` table or model, no customer
  date-request form route, no seller "Balas" action, and no "Permintaan tanggal" row/pill/count in Pesanan terbaru, the
  booking list, "Perlu tindakan" or the nav badge. A test asserts the absence of any such route (the handoff is a plain
  `wa.me` link, not an app endpoint).

## Data & rules

- **No schema change.** Uses `trips.booking_mode = request_date` and `organizations.whatsapp` (E.164). No
  `date_requests` table is created (Out).
- **New template (owned by F-008, whose spec is updated in the same change set).** Add to `lang/id/whatsapp.php`:

  | Message | Key (`whatsapp.*`) | Direction | Number | Built by | Placeholders |
  |---|---|---|---|---|---|
  | Ajukan tanggal (handoff) | `date_request` | customer → seller | org | F-004 via `to()` | `:name :trip` |

  Body: `'date_request' => 'Halo :name, saya mau ajukan tanggal untuk :trip. Boleh tahu tanggal yang tersedia?'`
  This is **distinct** from F-008's reserved `date_reply` (seller → customer, built via `toNumber()`, still unused). The
  button **label** "Ajukan tanggal →" stays in `lang/id/public.php` (`trip.request_date`); only the message body lives
  in `whatsapp.php` (F-008 AC-B1).
- **Link construction (`PublicPagePresenter`).** `urls.request_date` changes from `url($base.'/ajukan-tanggal')` to
  `WhatsAppLink::to($organization, __('whatsapp.date_request', ['name' => $organization->name, 'trip' => $trip->name]))`,
  which is a `wa.me` URL or `null`. The Vue `Trip.vue` renders the three request-date links (sticky gold CTA, no-date
  gold CTA, ink "other date" link) only when `urls.request_date` is non-null, each with `target="_blank"
  rel="noopener noreferrer"`. Encoding is the builder's single server-side `rawurlencode` (F-008 AC-A7/B10).
- **One gold button (ADR-0032).** The page still renders exactly one gold button. See ADR-0032 for the exception that
  lets that button be a WhatsApp handoff on request-date trips and scheduled trips with no bookable departure. ADR-0032
  is `proposed`; **the builder implements it as the default** (the gold "Ajukan tanggal →" handoff) unless Rengga
  rejects it — this spec authorises building against it (D4, Q2).
- **Seller fulfillment path (no new code).** After agreeing a date in the WhatsApp chat, the seller turns it into a
  booking with existing features: **keep the Private trip published as the discoverable dateless listing**, and create a
  scheduled trip (or add a departure to an existing scheduled trip) for the agreed date, then send the customer that
  trip's "Pesan →" link so they book and pay through the normal F-005/F-006 flow. (The F-003 booking-mode-change guard
  also lets the seller convert the *same* trip to `scheduled` and add a departure when no seats are taken; this mutates
  the shared offering, so it is only the path for a genuinely one-off private trip — not the default, which preserves
  discoverability.) Decision D5.
- **Tenant isolation.** The handoff is built only from the trip's own organization number; no cross-tenant number can
  appear (AC-9, parallel to F-008 AC-A10).
- **No automation.** The link is prefilled only; the customer edits and sends it in WhatsApp. No WhatsApp Business API,
  no records, no booking created from the chat (Out).

### Edge cases

- Seller with an invalid / backfilled `'-'` number → no handoff button on the request-date trip (AC-5); the page still
  renders without a CTA.
- Scheduled trip whose departures are all full/closed → the gold "Ajukan tanggal →" uses the handoff (AC-6).
- Long trip or seller name → the button label is fixed; only the message carries the names (F-008 AC-A7 encoding).

### Dependencies

- **F-008** — owns `lang/id/whatsapp.php` and `App\WhatsApp\WhatsAppLink`. F-008's spec is updated in this change set to
  add the `date_request` key (its Out list already excludes an inbox). F-008 is merged, so adding the key and building
  the handoff is additive.
- **F-002** — renders the public trip page and currently builds `urls.request_date`; this feature changes what that URL
  points to and updates `PublicTenancyTest`'s exact-keys assertion if needed (AC-7).
- **F-003** — dateless publish (checklist n/4) and the booking-mode-change guard used by the fulfillment path.
- **F-005 / F-006** — the normal booking and payment flow the seller sends the customer to after agreeing a date.

## Decisions

- **D1** — "Option B": the WhatsApp-handoff model replaces the in-app date-request flow; the in-app `date_requests`
  entity, "Balas" accept/decline, dashboard "Permintaan tanggal" rows and any request lifecycle/audit are deferred to
  P1 and listed under Out. — Rengga · 2026-10-22
- **D2** — The handoff reads a new customer → seller template `whatsapp.date_request`, built through the shared
  `WhatsAppLink::to()`; distinct from F-008's reserved seller → customer `date_reply`. — Planner · 2026-10-22
- **D3** — Ajukan copy: "Halo :name, saya mau ajukan tanggal untuk :trip. Boleh tahu tanggal yang tersedia?" —
  Planner · 2026-10-22 (recommended default in use; Rengga may tweak wording)
- **D4** — Gold-CTA exception: the single gold "Ajukan tanggal →" is a WhatsApp handoff on request-date trips and on
  scheduled trips with no bookable departure. Recorded as ADR-0032, **accepted by Rengga 2026-10-22**. — Rengga ·
  2026-10-22
- **D5** — Seller fulfillment path: keep the Private trip as the discoverable dateless listing and create a scheduled
  trip / departure for an agreed date (send the customer its "Pesan →" link); converting the same trip in place is
  available but not the default. No new code. — Planner · 2026-10-22 (recommended default in use)

## Open questions

None open — all resolved by Rengga 2026-10-22.

- [x] ~~Does accepting create a public or private departure; base price or a quote on accept?~~ **N/A in P0** — there is
  no in-app "accept". The seller books via the fulfillment path (D5). Revisit if the P1 in-app date-request inbox is
  built.
- [x] ~~Q1 — Confirm the ajukan copy (D3) and the fulfillment path (D5).~~ **Decided: keep both** (Rengga 2026-10-22).
- [x] ~~Q2 — Accept ADR-0032 (gold WhatsApp "Ajukan tanggal →" exception).~~ **Accepted** (Rengga 2026-10-22; see D4).

## Changelog

- 2026-10-17 — created
- 2026-10-19 — F-007 hand-off noted (list and Pesanan terbaru rows)
- 2026-10-22 — Planner: re-scoped to the WhatsApp-handoff model ("Option B", Rengga 2026-10-22). In-app date-request
  entity/inbox/"Balas"/dashboard rows moved to Out (P1). New AC-1…AC-10; `whatsapp.date_request` template defined (also
  added to F-008's spec); `urls.request_date` points at the WhatsApp handoff and the dead `/ajukan-tanggal` URL is
  removed; gold-CTA exception recorded as ADR-0032 (`proposed`); D1–D5 recorded; status `draft` → `ready`
- 2026-10-22 — Rengga: accepted ADR-0032 (gold CTA may be the WhatsApp handoff); confirmed the ajukan copy and the
  fulfillment path (Q1); Q1/Q2 closed, no open questions remain
- 2026-10-22 — Builder: built the handoff — `whatsapp.date_request` body added to `lang/id/whatsapp.php`;
  `PublicPagePresenter` builds `urls.request_date` via `WhatsAppLink::to()` (wa.me or null); `Public/Trip.vue` opens the
  gold "Ajukan tanggal →" and ink "other date" links in a new tab and hides them when the URL is null; the ink "Tanya
  via WhatsApp" link now shows only on scheduled trips (ADR-0032). New `RequestDateHandoffTest` covers AC-1…AC-10
  (AC-8 also via `PublicPageDesignTest`; F-008 exact-keys test updated to include `date_request`). Status `ready` →
  `in-progress`; all gates green
