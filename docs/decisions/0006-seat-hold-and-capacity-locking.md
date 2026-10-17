---
id: 0006
title: Seat holds with automatic expiry; transactional capacity
status: accepted
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
- **Hold length: 2 hours** from booking creation, fixed for every seller in the MVP (not configurable). `hold_expires_at = created_at + 2 h`.
- **Uploading payment evidence before expiry stops the clock.** The booking moves to `needs_review` and its seats stay held with no expiry until the seller verifies (→ `paid`) or rejects it. In this phase the seller is responsible for reviewing promptly; Rakyt does not auto-release a booking under review.
- Evidence cannot be uploaded after the hold has expired (the booking is already cancelled).
- **Sellers cannot extend a hold manually.**
- Seats count as taken when held (incl. under review) or paid. Viewing a page never holds seats.

## Decided 2026-10-17

1. Hold length: 2 hours, not configurable in the MVP.
2. Uploading proof stops the clock until the seller reviews it.
3. No manual hold extension by the seller.

Still open in F-006: what happens to the hold after the seller rejects evidence (re-upload allowed? clock restarts?).

## Consequences

- Good: no overbooking; predictable for customers.
- Costs: scheduler must run reliably in production; concurrency tests required.
- Costs: a seller who never reviews evidence keeps those seats blocked indefinitely; the dashboard (F-007) must surface bookings waiting for review.
- Follow-ups: revisit configurable hold length and a review deadline after the pilot.
