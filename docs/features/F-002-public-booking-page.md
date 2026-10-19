---
id: F-002
title: Public booking page
status: ready
priority: P0
roadmap: mvp-weeks-2-3
mockups: [1a, 1b, 2c, 3b, 3c]
decisions: ["0004", "0005", "0007", "0008", "0014", "0015", "0019", "0021", "0022", "0024"]
updated: 2026-10-19
---

# F-002 · Public booking page

## Problem

Sellers already have demand on IG/WA/TikTok but answer the same questions in chat ("masih ada kursi?", "berapa
harganya?", "termasuk apa saja?"). They need one link that shows their trips, dates, seats and prices — with no
marketplace chrome — and that customers open from a WhatsApp chat or an Instagram bio, on a mid-range Android phone,
often on a slow connection.

## Scope

**In:**
- **Seller page** `rakyt.id/<slug>` (mockups 1a, 3c): header (logo or initials, Nama usaha, Kota basis, Bio singkat),
  "Chat WhatsApp", "↗ Bagikan", "Trip tersedia · n trip" list of published trips as cards.
- **Trip page** `rakyt.id/<slug>/<trip-slug>` (mockups 1b, 2c, 3b): swipeable photo gallery, type & duration kicker,
  name, short description, departure picker (scheduled trips), "Tanggal lain? Ajukan tanggal ke operator", detail
  sections (Termasuk, Tidak termasuk, Kumpul & jadwal, Ketentuan), sticky price bar with the gold CTA.
- The links that hand over to other features: "Pesan →" (F-005 booking form), "Ajukan tanggal" (F-004 request form),
  "Chat WhatsApp" (F-008 owns the message templates later).
- Link previews: page title and Open Graph tags rendered by the server for WhatsApp / Instagram / Facebook crawlers.
- Public tenancy context: the organization is resolved from the slug (ADR-0021), never from a signed-in user.
- Performance for a mid-range Android on a slow connection: resized, lazy-loaded photos; public pages load only their
  own JavaScript (ADR-0024).
- A small "Dibuat dengan Rakyt" footer on every public page (Q2).

**Out (explicitly):**
- Search or discovery across sellers, marketplace listings, "trip lain dari operator lain" (P2).
- Reviews and ratings; SEO landing pages, sitemaps, structured data (later).
- Custom domains and removing the Rakyt footer on paid plans (P1, blueprint §3).
- Server-side rendering of the page body (Inertia SSR) — only `<head>` tags are server-rendered (ADR-0024).
- The booking form, seat holds and booking pages (F-005); the request-a-date form (F-004); payment instructions
  (F-006); WhatsApp message templates beyond the one "Chat WhatsApp" text (F-008).
- A share button on the trip page (not in the mockups; the browser's own share works) — Q12.
- Showing a draft trip to its own seller on the public URL (the F-003 editor has a live preview).
- Multiple languages; UI is Bahasa Indonesia only (ADR-0007).
- Suspension behaviour (F-014 decides; this feature provides the hook — Q11).

## User stories

- As a customer, I want to open the seller's link and see all their trips with prices and the next date, so I can
  pick one without asking in chat.
- As a customer, I want to see which dates still have seats and what each date costs, so I know what I can book.
- As a customer, I want to read what is included, where we meet and the terms before I book.
- As a customer, I want to chat the seller on WhatsApp from the page when I have a question.
- As a customer, I want to share the seller's page with friends in a WhatsApp group, with a preview that shows what it is.
- As a seller, I want my link preview in WhatsApp to show my trip's photo and name, so my link looks trustworthy.
- As a seller, I want my drafts and my business details to stay private until I publish.

## Acceptance criteria

UI copy is Bahasa Indonesia from `lang/id/public.php` (ADR-0007). Dates and "today" are in the organization's time
zone (ADR-0014). *Bookable* is the single server-side rule from F-003 (`TripDeparture::isBookable()` /
`scopeBookable()`); this feature never re-implements it.

### A. Routing & visibility

- [ ] **AC-1 Seller page URL.** Given an organization with slug `kencana` — from the moment onboarding step 1 saves it,
  whether or not it has ever published — when anyone requests `/kencana`, then the seller page renders with status 200
  (Q3, decided 2026-10-19).
- [ ] **AC-2 Slug case.** Given `/Kencana` or `/KENCANA`, then the response is a 301 to `/kencana` (same for the trip
  path, e.g. `/Kencana/karimunjawa-3h2m` → `/kencana/karimunjawa-3h2m`).
- [ ] **AC-3 Unknown, reserved and not-yet-public.** Given a slug that does not exist, is on
  `config/rakyt.php → reserved_slugs`, is malformed (fails the F-001 B5 format, over 30 chars, contains NUL or
  non-UTF-8 bytes), then the response is the same Bahasa 404 page, with no difference in body or headers that tells
  these cases apart.
- [ ] **AC-4 Trip page URL.** Given a published trip of `kencana` with slug `karimunjawa-3h2m`, when anyone requests
  `/kencana/karimunjawa-3h2m`, then the trip page renders with status 200.
- [ ] **AC-5 Drafts never public.** Given a draft trip (never published, or unpublished with F-003 AC-15), when anyone
  — a guest, a seller of another organization, or a signed-in member of the owning organization — requests its trip
  URL, then the response is the 404 page; the trip appears on no seller page, in no props, and in no Open Graph tag.
  (Closes F-001 D7 and the public half of F-001 T3.)
- [ ] **AC-6 Cross-tenant trip URL.** Given organizations A (`kencana`) and B (`bahari`) and a published trip of B with
  slug `menjangan`, when anyone requests `/kencana/menjangan`, then the response is the 404 page — never B's trip under
  A's header. Given A also has a trip slug `menjangan`, then `/kencana/menjangan` shows A's trip and `/bahari/menjangan`
  shows B's. (F-009 A5.)
- [ ] **AC-7 Context from the slug, not the session.** Given a signed-in seller of A, when they open B's seller page
  and B's trip page, then they see B's published content exactly as a guest does, and the response contains no data of
  A (names, trips, payment methods, ids). The public routes do not run the seller `organization` middleware; the
  current organization is set from the slug (ADR-0021).
- [ ] **AC-8 Only public fields leave the server.** Given the seller and trip page responses (Inertia props and
  `<head>` tags), then they contain only the fields listed in Data & rules → *Public fields*. A test asserts the exact
  prop keys and that no numeric `id`, `organization_id`, `public_id` of the organization, email, member, payment
  method, `seats_taken`, audit data or draft trip is present. Departures are identified by their `public_id` only.
- [ ] **AC-9 Rate limit.** Public page GETs use the named limiter `throttle:public-page` (per IP; value in
  `config/rakyt.php`, default 120 per minute). Over the limit, the response is the Bahasa 429 page.

### B. Seller page (1a, 3c)

- [ ] **AC-10 Header.** Given the seller page, then the header shows the logo (square) or, without a logo, the
  initials of Nama usaha (up to 2 letters, e.g. "Kencana Trip" → "KT") on a Sogan square; Nama usaha; Kota basis; and
  Bio singkat below when filled (Q9). Changes saved in Pengaturan → Profil usaha show on the next page load (closes
  F-001 F1's public half).
- [ ] **AC-11 Chat WhatsApp.** Given the header, then "Chat WhatsApp" (ink outline) links to
  `https://wa.me/<digits>?text=<encoded>` where digits are the organization's E.164 WhatsApp number without "+", and
  the text is `lang/id/public.php → whatsapp.seller` ("Halo :name, saya lihat halaman booking Anda di Rakyt. Mau
  tanya…"), URL-encoded. It opens in a new tab/app (`target="_blank"`, `rel="noopener noreferrer"`).
- [ ] **AC-12 Bagikan.** Given "↗ Bagikan" (labelled, ink outline), when tapped on a browser with the Web Share API,
  then the native share sheet opens with the title Nama usaha, a short text and the URL `https://rakyt.id/<slug>`
  (same link builder as F-001 D5). Without Web Share, or if share throws anything but a user cancel, the link is
  copied and a toast "Link disalin" shows for about 2 s. A user cancelling the share sheet shows nothing.
- [ ] **AC-13 Trip list.** Given the seller page, then "Trip tersedia" shows the count "n trip" of published trips and
  one card per published trip, ordered: trips with a bookable departure first, by their earliest bookable start date
  (ties by name); then `request_date` trips; then scheduled trips without a bookable departure; within each of the last
  two groups newest `published_at` first.
- [ ] **AC-14 Trip card.** Each card shows the cover photo (400 px variant, `srcset` with 800), trip name,
  "Mulai Rp1.150.000 /orang" and one status line:
  - scheduled trip with a bookable departure → "Sisa n kursi · 24 Okt" for the earliest bookable departure;
  - `request_date` trip → "Ajukan tanggal";
  - scheduled trip with no bookable departure → "Belum ada tanggal".
  The whole card links to the trip page; tap target ≥ 44 px.
- [ ] **AC-15 "Mulai" price.** "Mulai Rp…" is the lowest effective price (`price_override ?? base_price`) among the
  trip's **bookable** departures; when there is none (or the trip is `request_date`), it is the base price. The F-003
  editor preview (F-003 AC-13, `TripPreview`) uses the same function, so preview and public card always agree (Q7).
- [ ] **AC-16 Empty seller page.** Given an organization with no published trip — one that has not published yet
  (still in onboarding, or "Simpan sebagai draf") or one that unpublished every trip — then the header, Chat WhatsApp
  and Bagikan still show, "Trip tersedia" has no count, and in place of the list the page reads "Belum ada trip yang
  bisa dipesan saat ini." with "Chat operator untuk info jadwal." — no 404, and no draft trip is hinted at (Q3).

### C. Trip page (1b, 2c, 3b)

- [ ] **AC-17 Top of page.** Given the trip page, then it shows a back link "←" to the seller page, a swipeable
  gallery of the trip's photos in F-003 order (cover first) with a counter "1/6", the kicker "Open trip · 3 hari 2
  malam" (type label from `lang/id/trips.php`; duration label as in F-003 — "Beberapa jam", "1 hari", "3 hari 2
  malam"; no duration → type label only), the trip name (h1), and the short description when filled.
- [ ] **AC-18 Departure list.** Given a `scheduled` trip, then "Pilih keberangkatan" lists every departure whose start
  date is today or later (organization time zone), sorted by start date then creation order. Departures that have
  started are not listed. The first 10 are shown; "Lihat n tanggal lainnya" reveals the rest on the page.
- [ ] **AC-19 Bookable row.** A bookable departure row shows the dates ("24–26 Okt"; "31 Okt – 2 Nov" across months;
  one date "24 Okt" for 1-day trips; the year is added when it is not the current year, e.g. "2–4 Jan 2027"), the seats
  line and the effective price "Rp1.250.000". The seats line is "Tinggal n dari m kursi" when seats left ≤ 5, else
  "Sisa n kursi" (Q4), followed by the closing date when AC-19a asks for it. Seat counts carry no status colour (2c).
- [ ] **AC-19a Closing date without repetition (Q5).** Given the listed bookable departures:
  - when they all use the **same relative preset** (`h1`, `h3`, `h7` or `same_day`), then one line under the list says
    it once — "Pemesanan ditutup 3 hari sebelum keberangkatan." (`h1`: "1 hari sebelum keberangkatan"; `h7`: "7 hari
    …"; `same_day`: "Pemesanan ditutup 1 jam sebelum jam kumpul.") — and rows do not repeat it;
  - otherwise (mixed presets, or any `custom` deadline), each bookable row shows " · tutup 21 Okt";
  - in both cases, a row whose deadline is within the next 7 days always shows its own date, worded "· tutup hari ini
    23.59", "· tutup besok 23.59" or "· tutup 21 Okt", because that one is urgent.
  E.g. three H-3 departures on 24 Okt, 31 Okt, 14 Nov viewed on 19 Okt: the summary line, plus "· tutup 21 Okt" on the
  first row only.
- [ ] **AC-20 Full and closed rows stay readable.** A departure that is open but full shows its dates, "Tidak bisa
  dipesan" and "– Penuh" in place of the price. A departure that is closed (manually or by deadline) or past its
  deadline shows its dates, "Tidak bisa dipesan" and "– Ditutup" in place of the price. Neither uses opacity or a
  lighter colour than Secondary `#6F5B4B`, neither shows a price, and neither can be selected (`aria-disabled`, tapping
  does nothing). (Design rule "No opacity for disabled rows".)
- [ ] **AC-21 Selection.** The list is a radio group. On load, the earliest bookable departure is selected. Tapping
  another bookable row selects it. The selected row has a visible non-colour marker (border + ● glyph).
- [ ] **AC-22 Sticky bar — scheduled.** Given a selected departure, then a sticky bottom bar (Surface background) shows
  "24–26 Okt · per orang", its effective price, and the one gold CTA "Pesan →" linking to
  `/<slug>/<trip-slug>/pesan?keberangkatan=<departure public_id>` (route owned by F-005, which re-checks *Bookable*
  server-side). Changing the selection updates the bar without a page load.
- [ ] **AC-23 No bookable date.** Given a `scheduled` trip with no bookable departure (none listed, or all full/closed),
  then the list shows the rows that exist (AC-20) or "Belum ada tanggal yang bisa dipesan.", and the sticky bar shows
  "Mulai Rp… / orang" with the gold CTA "Ajukan tanggal →" (the F-004 form). There is still exactly one gold button.
- [ ] **AC-24 Request-date trip.** Given a `request_date` trip, then no departure list is shown; the sticky bar shows
  "Mulai Rp… / orang" (base price) and the gold CTA "Ajukan tanggal →" linking to `/<slug>/<trip-slug>/ajukan-tanggal`
  (route owned by F-004).
- [ ] **AC-25 Other date link.** Given a `scheduled` trip, then below the list an ink link "Tanggal lain? Ajukan
  tanggal ke operator" points to the same F-004 URL (it is a link, not a second gold button).
- [ ] **AC-26 Detail sections.** Each section renders only when it has content, in this order:
  - **Termasuk** — each item prefixed "✓";
  - **Tidak termasuk** — each item prefixed "–";
  - **Kumpul & jadwal** — "Pelabuhan Kartini, Jepara · 06.00" (meeting point, then meeting time when set), "Buka peta"
    when a map link exists, then one line per filled itinerary day "Hari n · …" (empty days are skipped, numbering
    keeps the real day number);
  - **Ketentuan** — the terms text with its line breaks kept.
  A trip with none of these shows no empty headings.
- [ ] **AC-27 Seller text is text.** Given seller-entered text containing HTML or script (`<script>`, `<img onerror>`,
  `javascript:`), then it is shown literally on both pages and in `<head>` tags and nothing executes; no `v-html` is
  used for seller content. "Buka peta" is rendered only for an `http`/`https` URL and opens with
  `target="_blank" rel="noopener noreferrer nofollow"`.
- [ ] **AC-28 Prices.** Every price is integer rupiah shown as "Rp1.250.000" (dots, no decimals), the same formatter
  as F-003.

### D. Link previews & page head (ADR-0024)

- [ ] **AC-29 Trip page head.** Given a trip page response, then the server-rendered HTML (before any JavaScript runs)
  contains `<title>Karimunjawa 3H2M Open Trip · Kencana Trip</title>`, `og:title` (trip name), `og:description` (the
  short description, else "Mulai Rp1.150.000/orang · 3 hari 2 malam · Kencana Trip"; at most 200 chars), `og:image`
  (absolute URL of the cover's 800 px variant, else the original), `og:url` (canonical lower-case URL), `og:type`
  `website`, `og:site_name` `Rakyt`, `og:locale` `id_ID`, and `<link rel="canonical">`.
- [ ] **AC-30 Seller page head.** Given a seller page response, then the server-rendered HTML contains
  `<title>Kencana Trip · Rakyt</title>`, `og:title` (Nama usaha), `og:description` (Bio singkat, else "n trip ·
  Kota basis", else just Kota basis when there is no published trip), `og:image` (the logo; else the cover of the first listed trip; else omitted), `og:url` and
  `<link rel="canonical">`.
- [ ] **AC-31 Head tags follow visibility.** A draft trip's name, description or photo never appears in any head tag,
  and 404 pages carry no Open Graph tags.

### E. Performance, caching, phone width

- [ ] **AC-32 Images.** Every trip photo is served from its resized variant with `srcset` (400/800/1600) and `sizes`,
  with `width`/`height` (or an aspect-ratio box) so the layout does not jump. Only the first gallery image and the
  first two cards load eagerly; the rest use `loading="lazy"`. When a variant is not generated yet, the original is used
  (`TripPhoto::url()` fallback).
- [ ] **AC-33 JavaScript split.** Public pages load only the shared runtime and their own page chunk — not the seller
  app, onboarding or settings pages (Inertia pages resolved lazily; ADR-0024). The built public-page JavaScript is at
  most 150 KB gzip in total (checked from the Vite manifest in CI or by a script the builder adds).
- [ ] **AC-34 Fresh seat counts.** Public HTML and Inertia responses send `Cache-Control: no-cache, private`, so a
  back/refresh never shows stale seats; nothing about seats is cached server-side. Photo files are served with a long
  `max-age` (their UUID paths never change; ADR-0015).
- [ ] **AC-35 Speed target (manual).** Given Lighthouse mobile (default "Slow 4G" throttling, Moto G-class CPU) on a
  production build with real photos, then the seller page and a trip page with 6 photos score LCP ≤ 3.0 s and CLS ≤ 0.1.
  Checked before the pilot and recorded in this spec; not in CI.
- [ ] **AC-36 Phone width.** Both pages work at 360 px wide without horizontal scroll; tap targets ≥ 44 px; text
  ≥ 12 px (11 px only for uppercase kickers); the sticky bar never covers the last section (bottom padding); the gallery
  swipes with touch and has prev/next controls for keyboard users.
- [ ] **AC-37 Design rules.** Exactly one gold (Prada) button per screen ("Pesan →" or "Ajukan tanggal →"); Chat
  WhatsApp and Bagikan are ink outline; label colours are ink; status words always carry a glyph or word, never colour
  alone (ADR-0008).
- [ ] **AC-38 Footer.** Every public page ends with "Dibuat dengan Rakyt" (Secondary text, 12 px) linking to the Rakyt
  home page. It is shown for every plan; `plan` is not read (ADR-0019, Q2).
- [ ] **AC-39 Errors.** The 404 and 429 pages for public routes are the Bahasa error pages from F-009, with a link to
  the Rakyt home page and no seller data.

## Data & rules

### Routes

| Method & path | Name | Notes |
|---|---|---|
| `GET /{organization:slug}` | `public.seller` | Registered **last** in `routes/web.php`, constrained to the slug format (F-001 B5) so it never shadows app routes; reserved slugs are 404 (they are also refused at sign-up, F-001 B7). |
| `GET /{organization:slug}/{trip:slug}` | `public.trip` | Trip resolved through the organization scope **and** `status = published`. |
| `GET /{slug}/{trip}/pesan?keberangkatan=` | — | Owned by F-005; F-002 only builds the link. |
| `GET /{slug}/{trip}/ajukan-tanggal` | — | Owned by F-004; F-002 only builds the link. |

Middleware for public routes: `web`, `throttle:public-page`, a new `public-organization` resolver (below). **Not**
`auth`, `organization` or `onboarded`.

### Tenancy (ADR-0004, ADR-0021)

- A `ResolvePublicOrganization` middleware looks the organization up by lower-cased slug (organizations are not
  tenant-scoped, ADR-0021), 404s an unknown slug (AC-3), and sets
  `CurrentOrganization` to it **before** route model binding — replacing anything a session might have set. Trips and
  departures are then read through the normal organization scope; no `acrossOrganizations()` and no allowlist entry.
- Visibility goes through one method (e.g. `PublicVisibility::organizationIsPublic(Organization)`) so F-014 can add
  its suspension rule in one place (Q11).
- Trip lookup: `slug` within the current organization ∧ `status = published`; anything else is a 404 (F-009 A5).
  Non-UTF-8, NUL or over-long values are a 404 before reaching PostgreSQL (`App\Tenancy\RouteKey`, ADR-0022 §4).
- Cross-tenant tests use `tests/Support/InteractsWithTenants` (AC-6, AC-7) plus an F-009 A5 test.

### Public fields

Only these leave the server (AC-8); a presenter (e.g. `App\Public\PublicTripPresenter`) builds them explicitly — no
`toArray()` of models.

- **Organization:** `name`, `slug`, `city`, `bio`, logo URL, WhatsApp link (built server-side), share URL.
- **Trip (card):** `slug`, `name`, cover URLs (variants), from-price, status line data (`next_start_date`,
  `seats_left`, or the `request_date` / no-date state).
- **Trip (page):** the card fields + `type`, duration label, `short_description`, photo URLs (variants, order),
  `inclusions[]`, `exclusions[]`, `meeting_point`, `meeting_time`, `map_url`, `itinerary[]`, `terms`, `booking_mode`.
- **Departure:** `public_id`, `start_date`, `end_date`, `seats_left`, `capacity`, effective price (bookable rows only),
  `booking_deadline_at` (local date), state `bookable | full | closed`.

### Rules

- **Departure state for display:** `bookable` = F-003 *Bookable*; else `full` when open and `seats_taken ≥ capacity`;
  else `closed` (closed manually, closed by deadline, or deadline passed before the auto-close job ran — F-003 AC-36).
- **Listed departures:** `start_date ≥ today` (organization time zone), any state; started ones are hidden.
- **From-price:** lowest effective price over bookable departures, else `base_price` (AC-15). One function shared with
  `TripPreview` (F-003 AC-13).
- **Low-seat wording:** seats left ≤ 5 → "Tinggal n dari m kursi" (config `rakyt.public.low_seats`, default 5).
- **Closing date:** shared-preset summary line vs per-row dates as in AC-19a; "urgent" = deadline within
  `rakyt.public.deadline_urgent_days` (default 7) of now, in the organization's time zone.
- **Seat counts are read at request time**; no page or query cache (AC-34). A customer can still lose the last seat
  between viewing and booking — F-005 rejects that booking with a clear message.
- **Share URL** `https://{rakyt.public_host}/{slug}`, the same builder F-001 D5 uses.
- **WhatsApp link:** `https://wa.me/{E.164 digits}?text={rawurlencode(text)}`; F-008 later moves all templates to one
  place — this one text lives in `lang/id/public.php` until then.
- **Queries:** the seller page runs a bounded number of queries regardless of trip count (eager-load cover photos and
  bookable-departure aggregates); a test asserts no N+1 (e.g. query count with 1 vs 10 trips is equal).

### Edge cases covered

- Slug in a different case (AC-2); reserved, unknown or malformed slug (AC-3) — indistinguishable.
- Organization that has never published: page live with the empty state (AC-16).
- Draft, unpublished, or another seller's trip under this seller's slug (AC-5, AC-6); signed-in seller of another
  organization (AC-7).
- Organization that unpublished every trip (AC-16); scheduled trip with no bookable date (AC-23); request-date trip
  (AC-24); trip without photos variants yet (AC-32); trip without detail sections (AC-26).
- Deadline passed but auto-close not run yet (Rules → display state); last seat taken while the page is open (Rules).
- Departure today with a same-day deadline still open (listed, bookable until the deadline).
- Seller text with HTML/script; non-http map links (AC-27).
- Share sheet missing or cancelled (AC-12). Slow 3G/4G, phone width (AC-32–AC-36).
- Many departures (AC-18: 10 shown, rest on demand); many trips (no N+1).

### Dependencies

- **F-003** (built): trips, departures, photos and variants, `Bookable`, `TripPreview` (to share the from-price),
  duration and money formatters, type labels.
- **F-001** (built): organization profile fields (`name`, `slug`, `city`, `whatsapp`, `logo_path`, `bio`),
  the share-link builder (D5), reserved slugs. F-002 closes F-001 D7, the public half of F1 and
  of T3.
- **F-009**: tenancy primitives, error pages, limiter registry (add `public-page`), headers; closes **A5**.
- **F-005 / F-004**: own the "Pesan →" and "Ajukan tanggal" targets (Q8). **F-008**: WhatsApp templates.
- **F-014**: suspension rule plugs into `PublicVisibility` (Q11).
- **ADR-0024** (accepted 2026-10-19): rendering, head tags, code splitting, caching.

## Open questions

All decided by Rengga on 2026-10-19: Q3 rejected (page live immediately), Q5 refined, the rest as recommended.

- [x] **Q1 Rendering & link previews.** No Inertia SSR; the controller passes title and Open Graph values to the Blade
  root view, so crawlers (which run no JavaScript) get them; public pages load lazily split JavaScript; seat data is
  never cached. [ADR-0024](../decisions/0024-public-page-rendering-and-caching.md) accepted. — Rengga
- [x] **Q2 Rakyt branding.** A small "Dibuat dengan Rakyt" footer on every public page, every plan, linking to the Rakyt
  home page; no header badge. Removal on paid plans is P1 (ADR-0019: no plan logic in P0). — Rengga
- [x] **Q3 Seller page before the first publish.** *Recommended was:* 404 until the first publish. **Rejected:** the
  seller page is live as soon as the organization exists (onboarding step 1), published or not; with no published trip
  it shows the header and the empty-state message (AC-1, AC-16). — Rengga
- [x] **Q4 "Tinggal n dari m kursi" threshold.** Seats left ≤ 5; configurable. — Rengga
- [x] **Q5 When to show "tutup <tanggal>".** Agreed to show closing dates, with Rengga's concern that the same date
  pattern on every row tells customers nothing. Refined in AC-19a: one summary line when all bookable departures share a
  relative preset; per-row dates only when presets differ or the deadline is within 7 days. — Rengga
- [x] **Q6 Closed departures.** Upcoming closed departures show as "Tidak bisa dipesan · – Ditutup"; started ones are
  hidden. — Rengga
- [x] **Q7 "Mulai Rp…" counts only bookable departures.** `TripPreview` and the wording of F-003 AC-13 change to match
  when F-002 is built. — Rengga
- [x] **Q8 CTAs before F-005 / F-004.** Link to their agreed URLs (AC-22, AC-24); no interim fallback. — Rengga
- [x] **Q9 Header subline.** Kota basis as the subline; Bio singkat below; no generated type summary. — Rengga
- [x] **Q10 Search engines.** Indexable, canonical URL; no sitemap or structured data in P0. — Rengga
- [x] **Q11 Suspended sellers.** F-002 builds the single `PublicVisibility` check; until F-014 decides, every
  organization is public. The suspension behaviour itself stays open in F-014. — Rengga
- [x] **Q12 Share button on the trip page.** Not in P0; revisit with pilot evidence. — Rengga

## Changelog

- 2026-10-17 — created
- 2026-10-19 — Planner: full acceptance criteria (AC-1…AC-39: routing & visibility, seller page, trip page, link
  previews, performance & phone width), public fields, tenancy via slug context, rules, edge cases, dependencies;
  open questions Q1–Q12 given defaults; ADR-0024 proposed. Status → `ready`
- 2026-10-19 — Rengga: Q3 rejected — the seller page is live from onboarding step 1 with an empty-state message
  (AC-1, AC-3, AC-16, AC-30 updated); Q5 refined — closing date shown once when every departure shares a preset,
  per row otherwise or when urgent (AC-19a); Q1, Q2, Q4, Q6–Q12 accepted as recommended; ADR-0024 accepted
