---
id: 0005
title: Trip (offering) vs departure (bookable occurrence)
status: accepted
date: 2026-10-15
deciders: [Rengga]
related: [F-003, F-005]
---

# 0005. Trip (offering) vs departure (bookable occurrence)

## Context

Blueprint §3 "Core domain model". Generic ecommerce product/variant config confused Liburdulu sellers.

## Decision

- **Trip**: reusable offering — name, description, photos, duration, inclusions, base price, booking mode (`scheduled` | `request_date`).
- **Departure** (`trip_departures`): a dated occurrence — start date (end derived from duration), capacity, optional price override, booking deadline, open/closed.
- Bookings attach to a departure. Request-date bookings create a date request that the seller turns into a departure (or declines).

Core entities: users, organizations, organization_members, trips, trip_departures, bookings, payment_records, notifications, subscriptions, audit_events.

## Consequences

- Good: matches how operators think ("Karimunjawa, 24 Okt, 16 kursi").
- Price changes on a departure apply to *new* bookings only; existing bookings keep their price (mockup 1g).
- Capacity cannot be reduced below seats already taken (mockup 1g).
