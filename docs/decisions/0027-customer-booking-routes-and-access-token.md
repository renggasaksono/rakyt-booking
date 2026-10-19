---
id: 0027
title: Customer booking routes — URL shape, token storage, session
status: proposed
date: 2026-10-19
deciders: [Rengga]
related: [F-005, F-006, F-008, F-009, F-014, ADR-0017, ADR-0021, ADR-0025]
---

# 0027. Customer booking routes — URL shape, token storage, session

*Proposed by the planner agent on 2026-10-19 while making F-005 `ready`.*

## Context

ADR-0017 (accepted) says customers reach their booking through an unguessable token, compared safely, on pages that
send `Referrer-Policy: no-referrer`. It leaves three things open:

- **Where the token page lives.** F-009 R20 assumed `/b/{token}`. Such a path names no seller, so finding the booking
  needs a query across organizations — ADR-0021 allows that only on a reviewed allowlist. F-002's pages already resolve
  the organization from the slug (`ResolvePublicOrganization`).
- **How the token is stored.** "Hashed" means the seller app can never show the link again; but the seller is the one
  who resends a lost link (ADR-0017), and F-008's "Ingatkan pembayaran" message should carry it.
- **Session.** F-002's pages run without a session or CSRF (ADR-0025 amendment) because they are GET-only. The booking
  form is a POST whose validation errors, kept input and rate-limit message (F-009 A14) use the session flash, as every
  other Inertia form in the app does.

## Decision

1. **Routes** (all under the seller slug, resolved by `ResolvePublicOrganization`, registered so they never shadow an
   app route — fallback routes as in ADR-0025 §1):
   - `GET /{slug}/{trip}/pesan?keberangkatan={departure public_id}` — booking form;
   - `POST /{slug}/{trip}/pesan` — create the booking;
   - `GET /{slug}/pesanan/{token}` — the customer's booking page (F-006 adds its upload route under the same prefix).
   The token is looked up **within the slug's organization** (normal scope), so another seller's token under this
   slug is a 404 by construction and no allowlist entry is needed.
2. **Token:** 32 characters from a cryptographically secure source (≈ 190 bits, URL-safe). Stored twice: a SHA-256
   `access_token_hash` (unique, used for lookup — an equality match on a hash of a high-entropy secret, so timing
   reveals nothing useful) and the token itself under Laravel's `encrypted` cast (APP_KEY) so the seller app can build
   the link for "Salin link pesanan" / WhatsApp messages (F-007, F-008). The plain token is never logged, never put in
   an audit event, and never sent to the seller's browser except inside that link.
3. **Session on the booking routes only.** The form and the booking page run the session, `ShareErrorsFromSession`
   and CSRF, but not `ResolveCurrentOrganization` or `AuthenticateSession`: the organization always comes from the slug,
   and a signed-in seller is never read. F-002's seller and trip pages stay session-less. Shared Inertia seller props
   are dropped on these routes too (`ResolvePublicOrganization::isPublicPage`).
4. **Headers:** form and booking page send `Cache-Control: no-store, private`; the booking page also sends
   `Referrer-Policy: no-referrer` and `X-Robots-Tag: noindex` and carries no customer data in `<title>` or Open Graph
   tags (WhatsApp builds a preview when the customer forwards the link).
5. **Logs:** the token segment of `/…/pesanan/{token}` is redacted from every logged path and URL (F-009 A24, R20).

## Consequences

- Good: tenancy by construction (no cross-organization lookup); the link shows the seller's name, which customers
  trust; the seller can resend the same link; F-014's suspension rule covers booking pages through `PublicVisibility`.
- Bad / costs: a session row per customer who opens the booking form (far fewer than page views); the token is
  recoverable by anyone holding APP_KEY and the database — the same exposure as the customer data on the row; a slug
  change (support-only after publish, F-001 Q3) breaks existing booking links unless support re-sends them.
- Follow-ups: F-006 registers its evidence upload under `/{slug}/pesanan/{token}/…` with `throttle:evidence-upload`
  (route parameter named `token`, F-009 R1); F-014 decides what a suspended seller's booking page shows.

## Alternatives considered

- **`/b/{token}`** — shorter link, but needs a cross-organization lookup on the allowlist and shows no seller.
- **Hash only** — the seller could not resend the link; a "new link" action would invalidate the one in the customer's
  chat history.
- **No session on the booking form** — would need a custom JSON form flow outside Inertia's error handling and the
  A14 rate-limit behaviour every other form uses.
