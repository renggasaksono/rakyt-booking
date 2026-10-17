---
id: 0014
title: Trip photos — public, unguessable paths with queued resizing
status: accepted
date: 2026-10-17
deciders: [Rengga]
related: [F-002, F-003, F-009, ADR-0004]
---

# 0014. Trip photos — public, unguessable paths with queued resizing

## Context

Public pages must load fast over 3G on mid-range Android (F-002). Trip photos are meant to be public, unlike payment evidence (private, signed URLs; F-006, F-009). ADR-0004 requires organization-prefixed media paths.

## Decision

- Store photos in S3-compatible storage at `org/{organization_public_id}/trips/{trip_public_id}/{uuid}.{ext}`, publicly readable; names are random UUIDs.
- Accept JPEG/PNG/WebP up to 10 MB, at most 10 per trip (plan-based limits later).
- Strip EXIF (incl. GPS) from stored public images.
- A queued job generates resized variants (~400/800/1600 px wide); the public page serves the right size.
- Deleting a photo or trip deletes its files.

## Consequences

- Good: cheap, cacheable, fast public pages; no signed URLs for public content.
- Bad / costs: photos of a draft trip are reachable by anyone holding the exact URL (acceptable — no sensitive data). Payment evidence must never use this path.

## Alternatives considered

- Signed URLs for all media — slower, uncacheable public pages for no privacy gain.
