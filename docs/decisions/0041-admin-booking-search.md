---
id: 0041
title: Admin booking search — one search box, a joined cross-organization list, indexes, phone only on the detail
status: proposed
date: 2026-10-27
deciders: [Rengga]
related: [F-013, F-012, F-015, F-005, F-006, F-007, ADR-0011, ADR-0021, ADR-0026, ADR-0039]
---

# 0041. Admin booking search — one search box, a joined cross-organization list, indexes, phone only on the detail

## Context

F-013 lets the platform admin find any booking across sellers (reference, customer name, customer phone, seller name or
slug), filter by booking status, payment status, seller and departure date, and open a read-only detail with seller,
trip, departure and seats, amounts, payment and evidence, hold expiry and audit history. The spec leaves these
technical details open:

1. **How one search box reads a term** — a reference, a phone number and a name look different, and the spec asks for
   an *exact* reference match and E.164-normalised phones (AC-1, AC-2).
2. **How the list reads across organizations.** ADR-0039 fixed "one organization per admin query" for F-012's detail.
   The search, by design, spans every organization in one page.
3. **Indexes.** `bookings` grows fastest, and every existing `bookings` / `trip_departures` index leads with
   `organization_id`; PostgreSQL 17 cannot skip that leading column, so cross-organization lookups would scan the table.
4. **"Payment status"** as a filter next to booking status.
5. **Where the customer's phone may appear** (AC-5: "only on detail pages, not in list exports or logs").

## Decision

- **One search box (`cari`), read as exactly one kind** (`App\Admin\AdminBookingFilters`), stated under the box:
  - a booking reference (`RKB-<code>-<MMDD>-<NNNN…>`, any case) → `bookings.reference = ?`, exact (AC-1);
  - a whole Indonesian mobile number, only digits and phone punctuation, in any F-005 format (`0812…`, `+62 812-…`,
    `62812…`, `812…`) → `PhoneNumber::normalize()` to E.164 → `bookings.customer_phone = ?`, exact (AC-2). A partial
    number is not a phone search (unlike the seller's own list, which matches trailing digits within one organization);
  - anything else → part of the customer's name, or of the seller's name or slug (`ILIKE`, wildcards escaped).
  Terms are validated like F-012's (2–100 characters, UTF-8, no control characters); invalid filters are dropped.
- **The list is one joined query** in `App\Admin\Queries\AdminBookingQueries`: `bookings` joined to `trip_departures`,
  `trips` and `organizations` on the composite `(organization_id, id)` keys (`bookings.organization_id =
  trip_departures.organization_id AND bookings.trip_departure_id = trip_departures.id`, and so on), so a row can never
  pair a booking with another seller's departure or trip. No eager load drops the scope across organizations, and the
  page costs one query whatever its rows. Newest first (`created_at DESC, id DESC`), 25 rows + 1, pages 1–10 000.
  The **detail** follows ADR-0039: the booking by its public id, then its departure, trip, payment record and seat
  sums each read with `organization_id` = the booking's organization.
- **Four indexes** (migration `2026_10_27_000005`): `bookings (reference)`, `bookings (customer_phone)`,
  `bookings (created_at, id)` and `trip_departures (start_date)`, for the exact lookups, the newest-first list and the
  departure-date filter. Name search is a substring `ILIKE` with no index: it walks `(created_at, id)` backwards and
  stops at 26 matches, which is fast for names that occur and degrades to a scan for one that does not. A `pg_trgm`
  GIN index is the follow-up when bookings reach the low millions — it needs the extension on the managed database,
  so it is not added now.
- **One status filter.** ADR-0026 §1 keeps one status column on `bookings` for both booking and payment state
  (`awaiting_payment`, `needs_review`, `paid`, `rejected`, `cancelled`, `completed`); evidence and the seller's
  verification live in `payment_records`, not in a second status. The spec's "booking status, payment status" filters
  are therefore one select over that set. The detail shows the payment record (amount due, evidence and upload time,
  the seller's decision, verification time and reject reason) beside the status.
- **The customer's phone is on the detail only.** The list's query never selects `customer_phone`, and a test pins the
  row's exact keys. What the admin typed stays in the filters and URLs (AC-3 keeps filters in the URL, and detail links
  carry them so "←" restores the list); the app's log redaction already strips `cari=` from URLs and masks phone
  numbers (F-009 A24). Web-server access logs are outside the app and follow the hosting setup. There is no export.
- **Read-only, GET only:** `admin.bookings.index` and `admin.bookings.show` behind `auth` + `platform-admin`; no write
  route, no form on the detail. Evidence opens through F-012's issuing route and short-lived signed URL (ADR-0039) —
  never a second evidence route. Notes, access tokens, the customer link and storage paths are never props.
- **Times:** created, hold expiry, upload and verification times in WIB with "WIB" in the label (as F-012/F-015);
  departure dates are the seller's local dates; seat counts come from `SeatBreakdown`, as on "Hari ini".

## Consequences

- Good: support finds a booking from a reference or the customer's number in one indexed lookup; a row's seller,
  trip and departure always belong to the booking's organization; list and detail run a fixed number of queries.
- Costs: four more indexes to maintain on write; a misspelled name scans; a partial phone number finds nothing
  (the hint under the search box says references and numbers must be whole).
- Follow-ups: `pg_trgm` for name search at scale; keyset pagination if deep pages become slow.

## Alternatives considered

- Resolve the organization from the reference's booking code, then use the existing `(organization_id, reference)`
  index — no migration, but test and imported bookings whose code differs would never be found.
- Eager-load departures, trips and organizations across the page's organizations with the scope dropped — correct
  thanks to the composite foreign keys, but it contradicts ADR-0039's one-organization rule and still needs joins or
  subqueries for the date and seller-name filters.
- Partial phone matching (`LIKE '%digits%'`) as in the seller list — a scan across all sellers, and AC-2 asks for
  normalisation, not substrings.
- Separate payment-status filter on `payment_records` (evidence / decision) — a second status ADR-0026 rejected;
  `needs_review` already means "evidence uploaded, not yet checked".
