---
id: 0006
title: Seat holds with automatic expiry; transactional capacity
status: proposed
date: 2026-10-17
deciders: [Rengga]
related: [F-005, F-006]
---

# 0006. Seat holds with automatic expiry; transactional capacity

## Context

Blueprint §4 "Capacity and booking reliability": prevent overbooking; if unpaid bookings hold seats, make the expiry explicit and release automatically; never hold capacity because someone opened a page. The mockup uses a **2-hour** hold as a placeholder (1c, 2e: "Kursi ditahan 2 jam… dilepas otomatis").

## Decision

- Creating a booking takes seats inside a DB transaction with a row lock on the departure (`SELECT … FOR UPDATE`) or an atomic conditional update (`seats_taken + n <= capacity`).
- An unpaid booking holds its seats until `hold_expires_at`. A scheduled job releases expired holds (status → cancelled/expired) and writes an audit event.
- Uploading payment evidence before expiry **[open: does it extend/freeze the hold?]**.
- Seats count as taken when held or paid. Viewing a page never holds seats.

## To confirm (why this is `proposed`)

1. Hold length — 2 hours? Configurable per seller?
2. Does uploading proof stop the clock until the seller reviews?
3. Should the seller be able to extend a hold manually?

## Consequences

- Good: no overbooking; predictable for customers.
- Costs: scheduler must run reliably in production; concurrency tests required.
