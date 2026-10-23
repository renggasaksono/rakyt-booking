---
id: 0037
title: UI term "kuota" instead of "kursi" for departure capacity
status: accepted
date: 2026-10-23
deciders: [Rengga]
related: [ADR-0007, ADR-0006, F-001, F-002, F-003, F-005, F-006, F-007, F-008, F-017]
---

# 0037. UI term "kuota" instead of "kursi" for departure capacity

## Context

The UI calls a departure's capacity unit **kursi** (seat), e.g. "Sisa 6 kursi", "Kursi ditahan 2 jam", "Total kursi".
That word suits trips with a vehicle seat per person (open trips by minibus or boat). Rakyt also targets **activity
providers** (blueprint): snorkeling, hiking, workshops, local guides. A snorkeling spot or a guided hike has no seats. The
landing already says "Kuota terbatas per tanggal" for guides. Rengga asked on 2026-10-23 to rename every "kursi" label
to **kuota** so the copy fits all of these.

"Kursi" appears only in UI copy: 64 strings in `lang/id/{trips,booking,bookings,public,onboarding,whatsapp,landing}.php`,
plus one comment in `config/rakyt.php`. No Vue page, JS helper or PHP class has the word hard-coded (ADR-0007 keeps copy
in `lang/id`). Twelve test files assert exact strings that contain it.

## Decision

We will use **kuota** wherever UI copy means a departure's capacity, its remaining places, or the places a booking takes
or releases. This covers seller pages, public pages, the booking form and booking page, WhatsApp templates and the
landing page.

Phrasing rules, so the word reads naturally in Bahasa:

1. **Counts put the number after "kuota"**: "Sisa kuota 6", "Kuota 16", not "6 kuota".
2. **Places a booking releases name the people**: "Kuota 2 orang dibuka lagi."
3. **Holds**: "Kuota ditahan …" / "kuota dilepas otomatis".
4. **Form fields** use "Kuota peserta". Short column headers and chips use "Kuota".

Out of scope, left unchanged:

- Code identifiers and data: `capacity`, `seats_taken`, `seats` lang keys, `seat bar`, `SeatHold`… The English glossary
  terms stay "seats" and "capacity". Only the Bahasa column changes.
- Accepted ADRs (0005, 0006, 0024) quote "kursi" as history. We do not rewrite them.
- Mockups in `docs/design/mockups/` are kept as they are. `docs/design/README.md` gets a note that the app says "kuota".

### Copy map

| File : key | Now | New |
|---|---|---|
| `trips.php` departures `subtitle` (l. 35) | Ketuk untuk ubah harga, kursi, atau buka/tutup. | Ketuk untuk ubah harga, kuota, atau buka/tutup. |
| `trips.php` departures `seats` (l. 140) | Kursi | Kuota |
| `trips.php` preview `seats` (l. 221) | :n kursi | Kuota :n |
| `trips.php` quick_edit `capacity` (l. 232) | Total kursi | Kuota peserta |
| `trips.php` validation `capacity_range` (l. 260) | Jumlah kursi 1–999. | Kuota peserta 1–999. |
| `trips.php` attributes `capacity` (l. 329) | jumlah kursi | kuota peserta |
| `onboarding.php` `seats` (l. 77) | Jumlah kursi | Kuota peserta |
| `onboarding.php` `seats_range` (l. 79) | Jumlah kursi 1–:max. | Kuota peserta 1–:max. |
| `onboarding.php` `seats_decrease` (l. 80) | Kurangi kursi | Kurangi kuota |
| `onboarding.php` `seats_increase` (l. 81) | Tambah kursi | Tambah kuota |
| `onboarding.php` `summary` (l. 83) | :seats kursi · :price/orang · pesanan ditutup :deadline | Kuota :seats · :price/orang · pesanan ditutup :deadline |
| `onboarding.php` `departure` (l. 129) | :date · :seats kursi | :date · kuota :seats |
| `public.php` card `seats` (l. 28) | Sisa :n kursi | Sisa kuota :n |
| `public.php` trip `seats` (l. 45) | Sisa :n kursi | Sisa kuota :n |
| `public.php` trip `seats_low` (l. 46) | Tinggal :n dari :m kursi | Kuota tinggal :n dari :m |
| `booking.php` `participants_help` (l. 28) | Sisa :n kursi | Sisa kuota :n |
| `booking.php` `hold` (l. 36) | Kursi ditahan :hours jam sampai dibayar, lalu dilepas. | Kuota ditahan :hours jam sampai dibayar, lalu dilepas. |
| `booking.php` `seats_left` (l. 57) | Sisa kursi tinggal :n. Kurangi jumlah peserta. | Sisa kuota tinggal :n. Kurangi jumlah peserta. |
| `booking.php` page `hold_until` (l. 69) | Kursi ditahan s.d. :time. Setelah itu kursi dilepas otomatis. | Kuota ditahan s.d. :time. Setelah itu kuota dilepas otomatis. |
| `booking.php` page `hold_until_tomorrow` (l. 70) | Kursi ditahan s.d. besok :time. Setelah itu kursi dilepas otomatis. | Kuota ditahan s.d. besok :time. Setelah itu kuota dilepas otomatis. |
| `booking.php` page `expired` (l. 71) | Waktu pembayaran habis. Kursi sudah dilepas. | Waktu pembayaran habis. Kuota sudah dilepas. |
| `bookings.php` show `hold_until` (l. 60) | Kursi ditahan s.d. :time | Kuota ditahan s.d. :time |
| `bookings.php` show `hold_until_tomorrow` (l. 61) | Kursi ditahan s.d. besok :time | Kuota ditahan s.d. besok :time |
| `bookings.php` reasons `hold_expired` (l. 63) | Waktu pembayaran habis, kursi dilepas otomatis. | Waktu pembayaran habis, kuota dilepas otomatis. |
| `bookings.php` `confirm_body` (l. 93) | Pesanan ini jadi Lunas dan kursi tetap dipakai. | Pesanan ini jadi Lunas dan kuota tetap dipakai. |
| `bookings.php` `reject_body` (l. 96) | :n kursi akan dibuka lagi. Pelanggan harus memesan ulang. | Kuota :n orang akan dibuka lagi. Pelanggan harus memesan ulang. |
| `bookings.php` `rejected` (l. 105) | Bukti ditolak. :n kursi dibuka lagi. | Bukti ditolak. Kuota :n orang dibuka lagi. |
| `bookings.php` cancel `seats` (l. 114) | :n kursi di keberangkatan :date akan dibuka lagi. | Kuota :n orang di keberangkatan :date akan dibuka lagi. |
| `bookings.php` cancel `done` (l. 122) | Pesanan dibatalkan. :n kursi dibuka lagi. | Pesanan dibatalkan. Kuota :n orang dibuka lagi. |
| `whatsapp.php` `reminder` (l. 31) | … Kursi ditahan sampai :time. … | … Kuota ditahan sampai :time. … |
| `landing.php` meta `description` (l. 14) + hero `subheadline` (l. 37) | … Atur tanggal dan kursi, … | … Atur tanggal dan kuota, … |
| `landing.php` demo dates (l. 67–68) | Sisa 6 kursi · Sisa 12 kursi | Sisa kuota 6 · Sisa kuota 12 |
| `landing.php` demo `hold` (l. 86) | Kursi ditahan 2 jam setelah pesanan dikirim. … | Kuota ditahan 2 jam setelah pesanan dikirim. … |
| `landing.php` `countdown_label` (l. 103) | Sisa waktu tahan kursi | Sisa waktu tahan kuota |
| `landing.php` (l. 115) | Cukup trip, tanggal, dan kursi. | Cukup trip, tanggal, dan kuota. |
| `landing.php` (l. 116) | … Ubah harga atau sisa kursi dari HP … | … Ubah harga atau sisa kuota dari HP … |
| `landing.php` step 4 (l. 139) | Tanggal keberangkatan dan jumlah kursi. | Tanggal keberangkatan dan kuota peserta. |
| `landing.php` (l. 165) | Ubah harga & kursi kapan saja | Ubah harga & kuota kapan saja |
| `landing.php` (l. 166) | … atur sisa kursi tanpa menunggu siapa pun. | … atur sisa kuota tanpa menunggu siapa pun. |
| `landing.php` (l. 209) | Kursi ditahan 2 jam | Kuota ditahan 2 jam |
| `landing.php` (l. 210) | Kalau tidak dibayar, kursi kembali terbuka otomatis. … | Kalau tidak dibayar, kuota kembali terbuka otomatis. … |
| `landing.php` (l. 217) | Tidak pernah dobel jual kursi | Kuota tidak pernah terjual dobel |
| `landing.php` (l. 218) | Dua pelanggan memesan kursi terakhir bersamaan? … | Dua pelanggan memesan kuota terakhir bersamaan? … |

Line numbers are from `main` at 7317493. After the change, `grep -rni kursi lang resources app config` returns nothing (checked 2026-10-23).

## Consequences

- Good: one word fits trips, activities and guides, and it matches the landing's "Kuota terbatas" line. "Kuota" is
  already common in Indonesian tour promotions ("kuota terbatas", "sisa kuota 4").
- Good: the change touches copy only, with no schema, logic or route changes, because ADR-0007 keeps all copy in `lang/id`.
- Bad / costs: "Kuota ditahan" reads a little less concrete to customers than "Kursi ditahan". Pilot feedback may ask
  for a different word on the hold line (Q1).
- Bad / costs: specs that quote UI strings must be updated in the same PR, or the docs drift from the code.
- Follow-ups (same PR as the copy change, per AGENTS.md "definition of done"):
  - Update the exact-string assertions in `tests/Feature/{Bookings/BookingPageTest,Bookings/BookingFormTest,
    Bookings/CreateBookingTest,Bookings/BookingConcurrencyTest,Dashboard/BookingDetailTest,Dashboard/CancelBookingTest,
    Payments/VerifyPaymentTest,WhatsApp/WhatsAppTemplateTest,Onboarding/GuidedTripTest}.php`. Also check the trip,
    public-page and landing tests for strings the grep did not catch (for example a rendered `Sisa 6 kursi`).
  - Update the comment in `config/rakyt.php` (the `low_seats` threshold) to "Kuota tinggal n dari m".
  - Update quoted copy in the specs: F-001 C7; F-002 AC-14, AC-19, the "Low-seat wording" rule and Q4; F-003 AC-31;
    F-005 AC-4, AC-7, AC-14, AC-20, AC-29, AC-31 and Q11; F-006 AC-18; F-007 AC-13, AC-23, AC-25, AC-28, AC-29; F-008
    AC-B6. Add a changelog line to each spec citing this ADR. Problem-statement prose that quotes what customers type
    in chat ("masih ada kursi?") stays as it is.
  - Glossary: change the Bahasa column of "capacity / seats" to "kapasitas / kuota", "seats left" to "sisa kuota",
    "seat hold" to "kuota ditahan" and "hold expiry" to "waktu pembayaran habis, kuota dilepas".
  - `docs/design/README.md`: add a note that the mockups say "kursi" and the app says "kuota" (ADR-0037).
  - Test at phone width that no changed string wraps badly. Check "Kuota tinggal 2 dari 16" on the departure picker
    and the "Sisa kuota n" chip on trip cards.

## Open questions

- [x] **Q1 Hold wording.** Should it be "Kuota ditahan 2 jam" or "Tempat Anda ditahan 2 jam"? *Recommended:* "Kuota
  ditahan", for one consistent term. Revisit if pilot customers find it unclear.
- [x] **Q2 Count order.** Should it be "Sisa kuota 6" or "Sisa 6 kuota"? *Recommended:* "Sisa kuota 6". "Kuota" is a
  total, not a countable unit like "kursi".
- [x] **Q3 Released places.** Should it be "Kuota 2 orang dibuka lagi" or "2 kuota dibuka lagi"? *Recommended:* "Kuota 2
  orang". It follows Q2 and says who the places were for.

Q1–Q3 decided 2026-10-23: the recommended answers. ADR accepted the same day, and the copy map was built with it. — Rengga

## Alternatives considered

- **Keep "kursi"**: does not fit activities and guides, which the blueprint targets.
- **"Tempat"** ("Sisa 6 tempat", "Tempat ditahan"): natural, but vague ("tempat" also means location, as in "Titik
  kumpul"). It is not the word Rengga asked for.
- **"Slot"**: common in apps, but English-flavoured. It reads as a time slot, which clashes with departures.
- **"Peserta"** ("Sisa 6 peserta"): wrong meaning. It reads as six participants already booked, not six places free.
