# Design

Reference: [`mockups/rakyt-booking-draft-v2.html`](mockups/rakyt-booking-draft-v2.html) — a self-contained canvas
(open in a browser). It holds three rounds of work; later rounds override earlier ones where they conflict.

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

Prada alternatives tested: A `#B8862F` (dull), B `#C48A1E`, C `#D99A1E`. **D is recommended but not yet confirmed** —
see [ADR-0008](../decisions/0008-brand-tokens-and-cta-rule.md).

### Status set

Every status = tint + deep text + its own glyph + a word. Never colour alone; never brown or gold.

| Status | Glyph | Deep text |
|---|---|---|
| Menunggu pembayaran | ◷ | `#8F4A00` |
| Perlu dicek | ◐ | nila `#2C4A7A` |
| Lunas | ✓ | `#2F6B3A` |
| Ditolak | ✕ | `#A1281E` |
| Dibatalkan | – | `#4F4741` |

## Rules

- **One gold button per screen**, for the step that moves the booking forward. Everything else is ink outline.
  Label is always ink, never cream. (On the payment screen, "Unggah bukti transfer" is gold; WhatsApp is secondary.)
- **Type floor:** 11 px for uppercase kickers, 12 px for helper text and metadata. Nothing smaller on a phone.
- **No opacity for disabled rows** (e.g. "Penuh" departures): full-strength secondary text, replace price with the word.
- **Structured inputs over free text** for durasi and tutup pesan: presets + "Atur sendiri"; show the deadline as a real date.
- **Share** is labelled ("↗ Bagikan"), opens the native share sheet, falls back to copy + "Link disalin".
- Seller dashboard is built around today's tasks (verify payments, seat counts, share link) — not a shrunk admin.

## Assumptions baked into the mockup (verify before building)

- 2-hour seat hold for unpaid bookings (placeholder) — [ADR-0006](../decisions/0006-seat-hold-and-capacity-locking.md)
- ~~WhatsApp OTP sign-up, no password~~ — rejected; sellers use email + password ([ADR-0011](../decisions/0011-email-password-authentication.md)). Mockups 4a–4b still show OTP; F-001 section A is the source of truth.
- "Kencana Trip" and all names/numbers are invented sample data.
