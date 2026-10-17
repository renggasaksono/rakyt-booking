---
id: 0014
title: Store times in UTC; compute deadlines in the organization's time zone
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: [F-001, F-003, F-005, F-009]
---

# 0014. Store times in UTC; compute deadlines in the organization's time zone

## Context

Booking deadlines ("H-3 pukul 23.59", "Sampai hari keberangkatan") are wall-clock times where the trip runs. Indonesia has three zones (WIB, WITA, WIT) and many target operators are in WITA (Bali, Lombok, Labuan Bajo). The auto-close scheduler and the bookability check need an exact instant.

## Decision

- All timestamps are stored in UTC.
- Each organization has `timezone` ∈ {`Asia/Jakarta`, `Asia/Makassar`, `Asia/Jayapura`}, default `Asia/Jakarta`, changeable in Pengaturan → Profil usaha (F-001).
- Departure `start_date` is a local calendar date; `booking_deadline_at` is computed from the preset in the organization's zone and stored in UTC. Display uses the organization's zone with Indonesian formatting.
- Changing an organization's time zone recomputes preset-based deadlines on future departures; custom deadlines keep their local wall-clock time.

## Consequences

- Good: correct deadlines across Indonesia; one rule for the scheduler and the booking check.
- Bad / costs: one more organization field; tests must cover a non-WIB organization.

## Alternatives considered

- Everything in WIB — simpler, but wrong by 1–2 h for WITA/WIT sellers.
- Time zone per trip — more precise, more fields; not needed for single-region operators.
