---
id: 0032
title: The one gold CTA may be a WhatsApp handoff on request-date trips
status: accepted
date: 2026-10-22
deciders: [Rengga]
related: [ADR-0008, F-004, F-008, F-002]
---

# 0032. The one gold CTA may be a WhatsApp handoff on request-date trips

## Context

Two accepted rules meet on the public trip page:

- **One gold CTA per screen** (ADR-0008): each screen has exactly one gold (Prada) button — the next step — and
  everything else is ink outline. On the trip page that button is "Pesan →" (scheduled trips) or "Ajukan tanggal →"
  (request-date trips), per F-002 AC-37.
- **WhatsApp links are never gold** (F-008 AC-A2): the contextual "Tanya via WhatsApp" general-inquiry link is an
  ink-outline button, never gold and never in the sticky bar.

F-004 was re-scoped on 2026-10-22 (Rengga, "Option B") so that "Ajukan tanggal →" is a **WhatsApp handoff** rather than
an in-app form (the in-app flow is deferred to P1). On a dateless request-date trip there is no "Pesan →": the WhatsApp
"Ajukan tanggal →" handoff is the page's only primary action. The same is true of a scheduled trip whose departures are
all full/closed (F-002 AC-23), where "Ajukan tanggal →" is also the single CTA. Making that one button ink to honour
F-008 AC-A2 literally would leave those pages with no gold "next step" at all — the opposite of what ADR-0008 intends.

F-008 AC-A2's "never gold" was written about the *ink* "Tanya via WhatsApp" general-inquiry link, which coexists with a
separate gold primary CTA. It did not contemplate the primary CTA itself becoming a WhatsApp link.

## Decision

We will allow the page's **single** gold (Prada) CTA to be a WhatsApp handoff in exactly one situation: when
"Ajukan tanggal →" is the only primary action on the page — i.e. on `request_date` trips, and on `scheduled` trips with
no bookable departure (F-002 AC-23). In that case:

- "Ajukan tanggal →" stays gold and opens WhatsApp via `WhatsAppLink::to()` with the `whatsapp.date_request` body
  (F-004).
- The page still has **exactly one** gold button; the one-gold-CTA rule of ADR-0008 is unchanged.
- F-008 AC-A2's "WhatsApp links are never gold" continues to govern the ink "Tanya via WhatsApp" general-inquiry link,
  which only appears on scheduled trips. The two WhatsApp links therefore never both appear, and never both gold.

This refines ADR-0008 for one case; it does not supersede it. The one-gold-CTA invariant and the status/token rules of
ADR-0008 are untouched.

## Consequences

- Good: every trip page keeps a single, learnable gold "next step", including dateless Private trips whose only action
  is to ask for a date.
- Good: no second link builder or styling special-case beyond the handoff already shared with F-008.
- Bad / costs: one deliberate exception to F-008 AC-A2's phrasing — documented here and cross-referenced from F-004
  AC-8 and F-008 so a future reader does not read it as a regression.
- Follow-ups: if the P1 in-app date-request flow is built, revisit whether "Ajukan tanggal →" returns to an in-app
  (non-WhatsApp) gold CTA, which would retire this exception.

## Alternatives considered

- **Make "Ajukan tanggal →" an ink button to satisfy F-008 AC-A2 literally.** Rejected: it leaves request-date trips
  with no gold primary CTA, breaking the "gold = next step" learning ADR-0008 is built on.
- **A spec note in F-004/F-008 only, no ADR.** Rejected: the choice refines an *accepted* foundational design ADR
  (0008) and creates an explicit exception to a *built* spec AC (F-008 AC-A2) — exactly the kind of cross-cutting "why?"
  that belongs in an ADR, and it needs Rengga's accept/reject rather than a silent planner default.
