---
id: 0042
title: Admin overview — live grouped counts, a trips status index, published trips open the sellers that publish one
status: accepted
date: 2026-10-28
deciders: [Rengga]
related: [F-011, F-012, F-013, ADR-0011, ADR-0039, ADR-0041]
---

# 0042. Admin overview — live grouped counts, a trips status index, published trips open the sellers that publish one

## Context

F-011 puts five counts on `/admin`: seller organizations (suspended apart from active), published trips, total
bookings, `awaiting_payment` and `needs_review` bookings, each linking to "the matching filtered list in F-012 or
F-013". Three details were open:

1. **How to count** — live or cached, and with what queries, without "duplicated counters" (AC-1).
2. **Indexes.** The counts name no organization. PostgreSQL 17 cannot skip a leading `organization_id`, and the only
   trips status index is `(organization_id, status)`.
3. **Where "published trips" links.** F-012 lists organizations and F-013 bookings; there is no admin trip list, and
   F-012's detail lists one seller's trips.

## Decision

- **Live, one grouped query per table**, in `App\Admin\Queries\AdminOverviewQueries`: organizations
  `GROUP BY status`, trips `count(*) WHERE status = 'published'` (across organizations), bookings `GROUP BY status`.
  The seller total and the booking total are the sums of their groups, so a total can never disagree with its parts.
  No cache: three index scans are cheap at pilot scale, and a stale count would send the admin to a list that
  disagrees with it. The page keeps `Cache-Control: no-store, private`.
- **One new index, `trips (status)`** (migration `2026_10_28_000001`). Organizations already have
  `(status, created_at)` (F-012) and bookings `(status, hold_expires_at)` (F-005); both lead with `status`.
- **Published trips count trips, and link to the sellers that publish one:** a new seller-directory filter
  `trip=published` ("Punya trip tampil") — a correlated `EXISTS` on trips' `(organization_id, status)` index inside
  `SellerDirectoryQueries`, so each seller appears once. From a seller the admin opens the detail's trip list (F-012).
  The count includes trips of suspended sellers (their status is still `published`; F-014 hides them publicly
  without changing it), and so does the filtered list unless "Status akun" narrows it.
- Suspended sellers are counted and shown apart from active ones (AC-4), each linking to the directory's existing
  status filter; bookings link to F-013's single status filter (ADR-0041).

## Consequences

- Good: no new table or counter to keep in step; every count matches the list it opens (a test follows each link and
  compares); one more small index.
- Costs: the published-trips link shows sellers, not trips — "42 trip tampil" may open fewer rows. The card's hint
  says so ("Lihat penjual yang punya trip tampil"). The booking total is a full index scan of `bookings`; fine for the
  pilot, worth a short cache if bookings reach the millions.
- Follow-ups: a cross-seller trip list if the admin needs to browse trips directly (not in P0).

## Alternatives considered

- A short cache (e.g. 60 s) — allowed by the spec, but the counts are cheap and a cached number could contradict the
  list one tap away.
- Link published trips to the unfiltered directory — no extra filter, but not "the matching filtered list" (AC-3).
- An admin trip list — a new screen outside F-011's scope.
- One `count(*) FILTER (WHERE …)` per table — the same scan, but harder to keep in step with the enums than grouping.
