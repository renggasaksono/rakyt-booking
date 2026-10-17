# Glossary

Use these words in code (English column) and UI copy (Bahasa column) consistently.
Source of UI wording: [design mockup](../design/README.md).

| Code / English | UI (Bahasa Indonesia) | Meaning |
|---|---|---|
| organization | usaha | The seller's business. Tenant boundary — every seller-owned record belongs to one. |
| organization time zone | zona waktu (WIB/WITA/WIT) | Used to compute and show deadlines. All storage in UTC ([ADR-0014](../decisions/0014-timezones-utc-storage.md)). |
| plan | paket | `free` / `pro` / `business`; limits live in config (ADR-0010). No limits enforced in P0; free-tier limits not decided yet (ADR-0019). |
| email verification | verifikasi email | Confirms the seller's email; reminder only in P0, never blocks onboarding or publishing ([ADR-0012](../decisions/0012-email-password-authentication.md)). |
| onboarding | — (Langkah n dari 4) | Guided first run: Usaha → Trip → Jadwal → Pembayaran → publish. |
| seller, operator | operator | Person running the organization. |
| customer | pelanggan, pemesan | Person booking a trip. No account required. |
| trip | trip | Reusable offering (description, photos, base price, booking mode). E.g. "Karimunjawa 3H2M Open Trip". |
| trip status | draf / terbit | `draft` (not public) or `published` (on the public page). |
| publish / unpublish | terbitkan / sembunyikan | Make a trip public, or hide it again. |
| publish checklist | siap terbit n/5 | What a trip needs before it can be published. |
| inclusions / exclusions / terms | termasuk / tidak termasuk / ketentuan | Trip detail sections. |
| meeting point | kumpul & jadwal | Where and when participants gather. "Sampai hari keberangkatan" closes 1 h before this time. |
| base price | harga dasar | A trip's default price per person; used by departures without a price override. |
| price override | harga (per keberangkatan) | Price set on one departure; empty = base price. |
| effective price | harga / orang | `price_override ?? base_price`. Copied onto a booking when it is created (ADR-0005). |
| trip type | jenis trip | Preset that fills defaults: `open_trip` (jadwal tetap, gabung), `private` (pelanggan ajukan tanggal), `day_trip`. |
| booking mode | cara pesan | `scheduled` (jadwal tetap) or `request_date` (pelanggan ajukan tanggal). |
| departure | keberangkatan, jadwal | A specific bookable occurrence of a trip: dates, seats, optional price override, booking deadline, open/closed. |
| duration | durasi | Days + nights, e.g. 3H2M = 3 hari 2 malam. End date is derived from it. |
| capacity / seats | kapasitas / kursi | Total seats on a departure. |
| seats taken | terisi | Seats held + paid on a departure. |
| seats left | sisa kursi | capacity − seats taken. |
| full | penuh | Departure is open but seats taken = capacity. |
| departure status | buka / tutup pemesanan | `open` or `closed` (`closed_reason`: `manual` or `deadline`). |
| booking deadline | tutup pemesanan / tutup pesan | When a departure stops accepting bookings, e.g. H-3 = 3 days before, 23.59. |
| H-n | H-n | n days before departure date. |
| booking deadline preset | tutup pesan | `same_day`, `h1`, `h3`, `h7`, `custom`. |
| date request | permintaan tanggal / ajukan tanggal | Customer proposes a date for a request-date trip; seller replies. |
| booking | pesanan | A customer's reservation on a departure. |
| booking reference | kode pesanan | Human-readable ID, e.g. `RKB-1024-0142`. Display only — never grants access. |
| booking access token | — (in link) | Unguessable token in the customer's booking/payment link ([ADR-0017](../decisions/0017-customer-booking-access-token.md)). |
| seat hold | kursi ditahan | Seats reserved for an unpaid booking for 2 hours; uploading payment evidence stops the clock until the seller reviews it (ADR-0006). |
| payment record | pembayaran | Amount due/paid, evidence, verification for a booking. |
| payment evidence | bukti transfer | Photo/screenshot uploaded by the customer. |
| payment instructions | instruksi pembayaran, rekening | Seller's bank / e-wallet / QRIS details shown to customers. |
| payment method | rekening / metode pembayaran | One bank or e-wallet account (QRIS, cash in F-006) in the payment instructions; belongs to the organization. |
| deposit | DP (uang muka) | Partial payment (P1). |
| public page | halaman publik | `rakyt.id/<slug>` — the seller's shareable booking page. |
| booking link / slug | link booking | The `<slug>` in `rakyt.id/<slug>`; lowercase, 3–30 chars; locked after first publish in P0. |
| audit event | riwayat perubahan | Append-only record of an important change (who, what, before/after, when). Not shown to sellers in P0. |
| quick edit | edit cepat | Mobile sheet to change price, seats, open/closed in < 30 s. |
| today | hari ini | Seller dashboard home. |
| platform admin | admin | Rakyt operator (the product owner in the MVP) with access to `/admin` across organizations. Not a member of any organization. ADR-0011. |
| suspended (organization) | ditangguhkan | Organization blocked from new activity by the platform admin; data is kept. F-014. |

## Booking & payment statuses

| Code | UI | Glyph | Meaning |
|---|---|---|---|
| `awaiting_payment` | Menunggu pembayaran | ◷ | Seat held, no proof yet |
| `needs_review` | Perlu dicek | ◐ | Proof uploaded, seller to verify |
| `paid` | Lunas | ✓ | Paid and confirmed |
| `rejected` | Ditolak | ✕ | Proof rejected |
| `cancelled` | Dibatalkan | – | Cancelled or hold expired |
| `completed` | Selesai | | Trip has happened |

> The blueprint names booking statuses *pending, confirmed, cancelled, completed*; the mockup refines payment states.
> How the two map onto each other is an open question in [F-006](../features/F-006-payment-instructions-and-records.md).
