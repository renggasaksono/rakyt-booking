# Design

Reference: [`mockups/rakyt-booking-draft-v2.html`](mockups/rakyt-booking-draft-v2.html) — a self-contained canvas
(open in a browser). It holds three rounds of work; later rounds override earlier ones where they conflict.

Landing page (F-017) mockups:
[`mockups/rakyt-booking-landing-sogan-cerah.html`](mockups/rakyt-booking-landing-sogan-cerah.html) (desktop, **"Sogan Cerah"** — a
brighter, warmer reskin of the brand palette) and
[`mockups/rakyt-booking-landing-mobile.html`](mockups/rakyt-booking-landing-mobile.html) (mobile, current/darker palette plus a
sticky bottom CTA bar). **Where the two conflict on colour, Cerah wins.** These are proposals, not yet reflected in the tokens.

| Round | Contents |
|---|---|
| 1 (1a–1g) | Quick hi-fi: customer booking flow (mobile) + seller dashboard & trip upload |
| 2 (2a–2f) | Colour system, semantic statuses, contrast audit, CTA gold test |
| 3 (3a–3e) | Review fixes: structured inputs, trip detail contents, labelled share button, brighter CTA |
| 4 (4a–4f) | Seller onboarding: account → first trip live in 10 minutes (mobile) |

## Screen index

| ID | Screen | Feature |
|---|---|---|
| 1a, 3c | Seller public page (`rakyt.id/kencana`), share button | [F-002](../features/F-002-public-booking-page.md) |
| 1b, 2c/2d | Trip + departure picker | [F-002](../features/F-002-public-booking-page.md), [F-005](../features/F-005-customer-booking-and-seat-hold.md) |
| 3b | Trip detail (termasuk, tidak termasuk, kumpul & jadwal, ketentuan) | [F-002](../features/F-002-public-booking-page.md), [F-003](../features/F-003-trips-and-departures.md) |
| 1c | Booking form | [F-005](../features/F-005-customer-booking-and-seat-hold.md) |
| 1d, 2e, 3e | Booking reference + payment instructions | [F-006](../features/F-006-payment-instructions-and-records.md) |
| 1e, 2f | Seller "Hari ini" dashboard | [F-007](../features/F-007-seller-dashboard-and-bookings.md) |
| 1f, 3a | Trip upload (desktop), durasi & tutup pesan pickers | [F-003](../features/F-003-trips-and-departures.md) |
| 1g | Mobile quick edit | [F-003](../features/F-003-trips-and-departures.md) |
| 4a–4f | Onboarding | [F-001](../features/F-001-seller-onboarding.md) |

## Identity

"Rakyt Sogan": batik-inspired, warm. Headings/labels in **Poppins**, body in **Arial**.

### Tokens (WCAG 2.x ratios measured on Gading)

| Token | Hex | Use | Contrast |
|---|---|---|---|
| Sogan | `#6E4527` | Links, selected nav, brand accents — **brand & links only** | 7.2 : 1 |
| Ink | `#211710` | Headings, body, dark buttons, label on gold | 15.3 : 1 |
| Gading | `#F4EFE6` | Page & form ground | — |
| Surface | `#FBF8F2` | Cards, inputs, sticky action bars | ink 16.6 : 1 |
| Secondary | `#6F5B4B` | Helper text, metadata — don't go lighter | 5.6 : 1 |
| Prada (CTA) | `#E5A823` (option D) | The one primary action per screen | ink label 8.4 : 1 |

Prada alternatives tested: A `#B8862F` (dull), B `#C48A1E`, C `#D99A1E`. **D is confirmed** —
see [ADR-0008](../decisions/0008-brand-tokens-and-cta-rule.md).

### Ink ground (seller side nav, 1e/2f)

The seller nav is an ink panel — the desktop side nav and the phone bottom bar alike. Sogan fails on ink (2.1 : 1), so on this ground its "brand & links" role
moves to **gold-soft** — text and small marks only, never a button fill ([ADR-0036](../decisions/0036-ink-ground-side-nav-and-seat-bar-colours.md), proposed).

| Token | Hex | Use on ink | Contrast on ink |
|---|---|---|---|
| Sand | `#D8C9B6` | Resting nav items | 10.8 : 1 |
| Gading | `#F4EFE6` | Wordmark, current nav item (on a Sogan fill, 7.2 : 1) | 15.3 : 1 |
| Gold-soft | `#D9AE5C` | "Booking" kicker, monogram accent node, Perlu tindakan count (Pembayaran on desktop, Pesanan on the phone); gading instead on the Sogan-filled current item (gold-soft is 3.9 : 1 there) | 8.5 : 1 |

The side nav's foot carries the business name (Poppins 14px, gading) and the plan ("Paket Gratis", sand), as in 1e/2f.

### Product mark

**Rakyt | BOOKING** everywhere: the monogram, the "Rakyt" wordmark (Poppins 20px, `-0.03em`), a hairline, and
"Booking" as the 11px uppercase kicker (`.14em`). One component, `resources/js/components/ProductMark.vue`:
`tone="ink"` on light grounds (kicker in Sogan; the landing nav), `tone="reverse"` on ink (kicker in gold-soft; the
seller side nav). Don't rebuild the lockup by hand.

### Status set

Every status = tint + deep text + its own glyph + a word. Never colour alone; never brown or gold.

| Status | Glyph | Deep text |
|---|---|---|
| Menunggu pembayaran | ◷ | `#8F4A00` |
| Perlu dicek | ◐ | nila `#2C4A7A` |
| Lunas | ✓ | `#2F6B3A` |
| Ditolak | ✕ | `#A1281E` |
| Dibatalkan | – | `#4F4741` |
| Selesai | ☑ | `#2F6B3A` (ADR-0028 §10) |

The Hari ini **seat bar** (F-007 AC-12, mockup 2f) is a thin square bar of solid segments with 3 px gaps: Lunas
`status-paid`, ditahan `status-waiting-bar` `#D9963A`, kosong `ink/12`. It is decoration (`aria-hidden`); its text
summary ("12 lunas · **2 ditahan** · 2 kosong · tutup 21 Okt") carries the numbers.

Departure statuses (F-003 AC-27) follow the same rule with their own glyphs: Buka ● (Lunas green), Tutup ○
(`#4F4741`), Penuh ◼ (nila), Sudah lewat ◁ (`#4F4741`). Tints are tokens in `resources/css/app.css`
(`--color-status-*-tint`); never hard-code them.

### Shape & outlines

"Rakyt Sogan" is a **square** system — the mockup has no rounded corners on any UI element (the only radii
in it are the phone-frame bezel and dev-tooling chips). Keep it that way:

- **No border-radius.** Cards, buttons, inputs, departures, pills, the gold CTA — all sharp corners. The radius
  scale is zeroed at the token level (`--radius-*: 0` in `resources/css/app.css`), so existing `rounded-*`
  utilities render square; prefer not to add new ones. `rounded-full` is not token-driven — use it only for the
  few deliberate pills (toasts, status pills), never to soften a rectangle.
- **Outlines are thin, drawn with `box-shadow` insets, not heavy borders.** Weights: **1px** `rgba(33,23,16,.16–.25)`
  for a secondary/resting outline (e.g. the Share button, unselected departures), **1.5px** `#211710` (ink) for a
  primary outline (e.g. Chat WhatsApp), **2px** ink for the selected state (e.g. the chosen departure, a focused
  input uses 2px Sogan). Avoid `border-2` as the default outline — it reads too bold.
- **Section headers** are an uppercase kicker, not a sentence-case heading: Poppins 11px, `font-weight:600`,
  `letter-spacing:.16em`, uppercase, with a **2px `#211710` bottom border** (`padding-bottom:8px`). Used for
  "Trip tersedia", "Pilih keberangkatan", "Termasuk", "Tidak termasuk", "Kumpul & jadwal", "Ketentuan", and the
  seller Hari ini sections ("Perlu tindakan · n pembayaran", "Keberangkatan terdekat", "Pesanan terbaru"). Use the
  `section-header` utility; a "Semua …" link on the same line is plain Sogan body text, right-aligned.
- **Kickers/subtitles** above a title (e.g. "Open trip · 3 hari 2 malam") are Poppins 11px, `font-weight:600`,
  uppercase, `letter-spacing:.14em`, in **Sogan `#6E4527`** — not `secondary`, and not the tight `tracking-wide`.

## Rules

- **One gold button per screen**, for the step that moves the booking forward. Everything else is ink outline.
  Label is always ink, never cream. (On the payment screen, "Unggah bukti transfer" is gold; WhatsApp is secondary.)
- **The gold button is the biggest one.** On a screen with several buttons (e.g. Hari ini, 2f) the gold CTA is 44px
  tall, 14px Poppins; every other button is the smaller secondary size, 36px tall, 13px. Secondary buttons have three
  weights: ink fill (Konfirmasi), 1.5px ink outline (Ingatkan via WA, Salin link), and a quiet 1px `ink/25` outline in
  body text (Tolak, Bagikan). On a phone the 44px tap target wins, so the secondary buttons are 44px and the gold one
  grows to 48px to stay the biggest. In seller views, draw outlines as `ring-inset` rings on the ink token, not
  `border-2`.
- **Type floor:** 11 px for uppercase kickers, 12 px for helper text and metadata. Nothing smaller on a phone.
- **No opacity for disabled rows** (e.g. "Penuh" departures): full-strength secondary text, replace price with the word.
- **Structured inputs over free text** for durasi and tutup pesan: presets + "Atur sendiri"; show the deadline as a real date.
- **Share** is labelled ("↗ Bagikan"), opens the native share sheet, falls back to copy + "Link disalin".
- Seller dashboard is built around today's tasks (verify payments, seat counts, share link) — not a shrunk admin.

## Assumptions baked into the mockup (verify before building)

- 2-hour seat hold for unpaid bookings (placeholder) — [ADR-0006](../decisions/0006-seat-hold-and-capacity-locking.md)
- ~~WhatsApp OTP sign-up, no password~~ — rejected; sellers use email + password ([ADR-0012](../decisions/0012-email-password-authentication.md)). Mockups 4a–4b still show OTP; F-001 section A is the source of truth.
- "Kencana Trip" and all names/numbers are invented sample data.
