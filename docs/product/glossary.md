# Glossary

Use these words in code (English column) and UI copy (Bahasa column) consistently.
Source of UI wording: [design mockup](../design/README.md).

| Code / English | UI (Bahasa Indonesia) | Meaning |
|---|---|---|
| organization | usaha | The seller's business. Tenant boundary — every seller-owned record belongs to one. |
| seller, operator | operator | Person running the organization. |
| customer | pelanggan, pemesan | Person booking a trip. No account required. |
| trip | trip | Reusable offering (description, photos, base price, booking mode). E.g. "Karimunjawa 3H2M Open Trip". |
| trip type | jenis trip | Preset that fills defaults: `open_trip` (jadwal tetap, gabung), `private` (pelanggan ajukan tanggal), `day_trip`. |
| booking mode | cara pesan | `scheduled` (jadwal tetap) or `request_date` (pelanggan ajukan tanggal). |
| departure | keberangkatan, jadwal | A specific bookable occurrence of a trip: dates, seats, optional price override, booking deadline, open/closed. |
| duration | durasi | Days + nights, e.g. 3H2M = 3 hari 2 malam. End date is derived from it. |
| capacity / seats | kapasitas / kursi | Total seats on a departure. |
| booking deadline | tutup pemesanan / tutup pesan | When a departure stops accepting bookings, e.g. H-3 = 3 days before, 23.59. |
| H-n | H-n | n days before departure date. |
| date request | permintaan tanggal / ajukan tanggal | Customer proposes a date for a request-date trip; seller replies. |
| booking | pesanan | A customer's reservation on a departure. |
| booking reference | kode pesanan | Human-readable ID, e.g. `RKB-1024-0142`. |
| seat hold | kursi ditahan | Seats reserved for an unpaid booking until the hold expires. |
| payment record | pembayaran | Amount due/paid, evidence, verification for a booking. |
| payment evidence | bukti transfer | Photo/screenshot uploaded by the customer. |
| payment instructions | instruksi pembayaran, rekening | Seller's bank / e-wallet / QRIS details shown to customers. |
| deposit | DP (uang muka) | Partial payment (P1). |
| public page | halaman publik | `rakyt.id/<slug>` — the seller's shareable booking page. |
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
