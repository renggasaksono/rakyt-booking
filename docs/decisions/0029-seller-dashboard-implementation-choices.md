---
id: 0029
title: Implementation choices for the seller dashboard and booking list (F-007)
status: accepted
date: 2026-10-19
deciders: [Rengga, Builder agent]
related: [F-007, ADR-0004, ADR-0006, ADR-0021, ADR-0026, ADR-0027]
---

# 0029. Implementation choices for the seller dashboard and booking list (F-007)

*Accepted by Rengga on 2026-10-19.*

## Context

F-007 (Hari ini, `/pesanan`, the booking detail and seller cancellation) left a few technical choices open, and one
spec conflict: AC-25 puts "Ingatkan via WA" — whose message contains the customer's booking link with its access
token — on Hari ini's Perlu tindakan rows, while AC-24 and AC-36 say the token is in the detail's props only, never
in Hari ini.

## Decision

1. **Reminder from Hari ini goes through a seller route.** Hari ini rows carry `whatsapp_reminder_url` =
   `GET /pesanan/{public_id}/ingatkan` (`bookings.remind`), which checks the policy and the active hold, then
   302-redirects to `https://wa.me/<digits>?text=…` with the customer link (`no-store`, `no-referrer`). Without an active
   hold it redirects to the detail with "Pesanan ini sudah tidak aktif.". The detail page itself links straight to
   WhatsApp (the token is allowed there). Nothing is recorded (AC-25).
2. **List filters travel with the detail link.** List rows link to `/pesanan/{id}?<the list's filters>`; the detail
   parses them with the same `BookingFilters` (invalid values dropped) to build its "←" link and the cancel redirect,
   so back, reload and a cancel all return to the same list (AC-19). `cari` is redacted from logged URLs (AC-37).
3. **Filter ids are public ids.** `trip` and `keberangkatan` take the trip's / departure's `public_id`; unknown ids,
   numeric ids and another seller's ids are all dropped the same way through the tenant-scoped lookup (AC-20, AC-34).
4. **Search.** One OR over `customer_name ILIKE`, `reference ILIKE` (both with `\`, `%`, `_` escaped) and — only when
   the term is digits/spaces/`+-().` with ≥ 4 digits — `customer_phone LIKE '%digits%'`, where a whole number (≥ 9
   digits) first loses its `0062` / `62` / `0` prefix like `PhoneNumber::normalize()`. Terms of 2–100 characters; no new
   index in P0.
5. **Pagination.** `offset/limit 21` on `created_at desc, id desc`, with `halaman` (1–999999) and prev/next URLs built
   from the applied filters only; no total count query.
6. **Extra props beyond the spec's list:** `context` on Hari ini (F-003's quick-edit context + `now`) and on the list
   (`now`, `timezone`, `today`), `urls.trips` on Hari ini, `has_any` on the list (AC-21's two empty states), and
   `whatsapp.cancelled_url` on the detail (AC-29's "Kabari pelanggan via WA", shown while the booking is cancelled by
   the seller). The exact-keys tests list them.
7. **Hari ini is `no-store` too**, like the list and detail: it shows customer names.
8. **Nav counts** are one `count()` of `Booking::actionNeeded()` inside the `seller` shared-prop closure, so they run
   only on seller pages and are skipped by partial reloads.

## Consequences

- Good: the token never sits in Hari ini's props, HTML or Inertia history; one parser decides every filter.
- Bad / costs: one more seller route (`/pesanan/{id}/ingatkan`); the list's filters appear in detail URLs (`cari` and
  the WhatsApp `text` are redacted in logs).
- Follow-ups: F-008 moves `bookings.whatsapp.*` into its template set; F-006 may give Pembayaran its own page.

## Alternatives considered

- Reminder text without the link on Hari ini — contradicts Q7 (the reminder includes the link).
- Token in Hari ini's props — contradicts AC-24/AC-36.
- `history.back()` for "←" — breaks on a shared or reloaded detail URL.
