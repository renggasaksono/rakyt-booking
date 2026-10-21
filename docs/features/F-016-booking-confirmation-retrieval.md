---
id: F-016
title: Booking confirmation — copy link & print-friendly proof
status: ready
priority: P0
roadmap: mvp-week-4
mockups: [1d, 2e, 3e]
decisions: ["0007", "0008", "0017", "0025", "0027"]
updated: 2026-10-21
---

# F-016 · Booking confirmation — copy link & print-friendly proof

## Problem

A customer who books today lands on `/{slug}/pesanan/{token}` (F-005 section E + F-006), sees their reference, status,
summary and payment instructions, and can reach the seller on WhatsApp. There are no customer accounts and no automated
notifications (ADR-0017), so that link **is** their only way back to the booking. Two gaps remain:

- **Keeping the link.** The private URL already works — a customer can copy it from the address bar and reopen it in a
  fresh browser, and the seller can resend it (F-007 "Salin link pesanan"). But the customer's own confirmation page
  offers no one-tap way to copy it, so most customers will lose it when they close the tab.
- **Keeping a proof.** On a flaky connection or after the hold expires, a customer wants an offline record of what they
  booked — a receipt they can save or show at check-in — without us building PDF generation or storage.

This feature adds a customer-side **"Salin tautan pesanan"** button and a browser-native **"Simpan bukti pesanan"**
(print / Save as PDF) to the existing confirmation page. It does **not** change booking creation, payment, statuses, the
token scheme, or the seller flows.

## Scope

**In:**
- A secondary **"Salin tautan pesanan"** button on the customer booking page (`/{slug}/pesanan/{token}`) that copies the
  current private booking URL, with clear Bahasa success / failure feedback.
- A short, non-intrusive hint that this link is how the customer returns to the booking (there is no account or email).
- A secondary **"Simpan bukti pesanan"** button that opens the browser print dialog (`window.print()`), and a
  **print-only snapshot** layout (`@media print`) that shows reference, trip name, departure date, participant count,
  booking status, payment status and total amount, and hides nav, every interactive control, the countdown, the payment
  instruction inputs and the upload control.
- Exposing the booking page's own URL to the page as `urls.self` (the one server-side change), and updating the
  exact-keys test (F-005 AC-37) accordingly.
- Confirming the existing confirmation content (A) and WhatsApp CTA (D) meet the change request, as a regression —
  no behaviour change.

**Out (explicitly):**
- **Token revoke / regenerate — deferred to P1** (Rengga, 2026-10-21). The change request asks for a way to revoke or
  rotate the token; it is not built in this feature because a customer-facing self-revoke would lock the customer out of
  their only route back (no account recovery), and a seller-side rotation invalidates the link already in the customer's
  WhatsApp history (ADR-0027 Consequences). Tracked as a P1 follow-up with a recommended seller-owned design (see Open
  questions Q1). This CR line is intentionally unmet for MVP.
- Server-side PDF generation, PDF storage, or any PDF dependency (MVP uses browser-native print — change request §C).
- Automated email / WhatsApp notifications, scheduled reminders, customer accounts or order history (out per the CR; P1).
- Any change to booking creation, seat holds, payment records, statuses, the access-token scheme (ADR-0017/0027), routes,
  rate limits, or seller dashboard / booking-management workflows.
- New customer personal data on the page or in the print layout beyond what F-005/F-006 already show.

## User stories

- As a customer, I want to copy my private booking link with one tap and know it was copied, so that I can save it in my
  notes or chat and reopen my booking later without an account.
- As a customer, I want to save or print a clean proof of my booking, so that I have an offline record to show at
  check-in or keep if I lose the link.
- As a customer, I want the printed proof to show only my booking details — not buttons, upload boxes or the live
  countdown — so that it reads like a receipt on paper or in a saved PDF.
- As a seller, I want none of my booking, payment or dashboard flows to change, so that this is a safe additive change.

## Acceptance criteria

UI copy is Bahasa Indonesia from `lang/id/booking.php` (ADR-0007). The page keeps **one gold button** — F-006's
"Unggah bukti transfer"; the new "Salin tautan pesanan", "Simpan bukti pesanan" and the existing WhatsApp CTA are all
ink-outline secondary (ADR-0008). Test names: `test_ac_<n>_…`.

### A. Confirmation content (regression — already built by F-005/F-006)

- [ ] **AC-1 Required fields present.** Given a booking opened at `/{slug}/pesanan/{token}`, the page shows: the booking
  reference in large type; the status pill (glyph + word, from the glossary status set); the trip name and departure
  date line; the customer name and participant count; the total amount; and — while relevant — the payment status /
  progress and next steps (countdown + release time while `awaiting_payment`, payment instructions, or the expired /
  paid / rejected state). No new booking step or required field is added. *Covered by existing F-005/F-006 page tests;
  this AC asserts the set is complete for the CR.*
- [ ] **AC-2 No unnecessary UI change.** The visual design, layout and existing CTAs are unchanged except for the two
  new secondary buttons and the link hint; the gold "Unggah bukti transfer" stays the only gold button.

### B. Copy the private booking link

- [ ] **AC-3 Copy button.** The page shows a secondary ink-outline **"Salin tautan pesanan"** button
  (`booking.page.copy_link`). Tapping it copies the page's own private URL (`/{slug}/pesanan/{token}`, carrying the
  token) to the clipboard via the existing `copyLink` helper (`resources/js/lib/onboarding.js`).
- [ ] **AC-4 Success feedback.** On a successful copy, an `aria-live="polite"` message shows **"Tautan disalin"**
  (`booking.page.link_copied`); it clears after a short delay, following the same pattern as the existing amount /
  account-number copy feedback on this page.
- [ ] **AC-5 Failure feedback.** When the clipboard write fails (or the API is unavailable), the page shows a Bahasa
  fallback (`booking.page.link_copy_failed`, e.g. "Gagal menyalin. Salin tautan dari bilah alamat.") instead of a silent
  no-op. The button never throws.
- [ ] **AC-6 Link hint.** Near the button a one-line secondary hint explains the link is how the customer returns to
  the booking without an account (`booking.page.link_hint`), making the private link easy to find without overwhelming
  the screen.
- [ ] **AC-7 URL exposed explicitly.** The page URL reaches the client as `urls.self` from `BookingPresenter::page()`
  (built with `route('public.booking.show', …)`), added to the exact-keys list in F-005 AC-37 / `BookingPageTest`. No
  other new prop key is introduced; no token hash, numeric id or seller-private field is added.

### C. Print-friendly proof (browser-native)

- [ ] **AC-8 Print button.** The page shows a secondary ink-outline **"Simpan bukti pesanan"** button
  (`booking.page.save_proof`) that calls `window.print()`, letting the customer choose "Save as PDF" or a printer. No
  PDF library or server route is added.
- [ ] **AC-9 Print snapshot content.** When printing, the output shows: the booking reference, trip name, departure
  date, participant count, booking status, payment status and total amount — a snapshot of the information as displayed
  when printed (status reflects the values loaded on the page, not live).
- [ ] **AC-10 Print hides interactive and irrelevant UI.** The print output hides: the top nav / seller bar, all buttons
  (copy, print, WhatsApp, upload CTA), the file input, the live countdown block, the payment-instruction account/QRIS
  blocks and their copy buttons, the progress bar, the page footer, and any error messages — using Tailwind's `print:`
  variant / an `@media print` block. Only the receipt-style summary prints.
- [ ] **AC-11 Print layout fits A4 and reads on mobile.** The printed page fits A4 without clipping, uses readable
  black-on-white type (no reliance on brand tints that may drop in print), and the two new buttons and the print output
  render correctly on a 360 px mobile browser. *Verified in the device review with the other P0 screens.*

### D. WhatsApp CTA (regression — already built by F-005 AC-33)

- [ ] **AC-12 WhatsApp prefilled, no change.** The existing "Kabari operator via WhatsApp" CTA still opens
  `https://wa.me/<seller digits>?text=…` in a new tab (`rel="noopener noreferrer"`) with a prefilled, URL-encoded
  message naming the booking reference, trip and date (`booking.whatsapp.customer`), using the seller's configured
  number. It sends nothing automatically and does not change booking status. *Existing AC-33 test is the regression; a
  test asserts tapping the link triggers no state change and no request.*

### E. Security, privacy & logs (regression)

- [ ] **AC-13 No token leak.** The private URL is only rendered client-side in the copy button and print layout; the
  token is not written to any new log line, audit event or analytics payload. The booking page keeps
  `Cache-Control: no-store, private`, `Referrer-Policy: no-referrer` and `X-Robots-Tag: noindex` (F-005 AC-36); the
  logged path still redacts the `/pesanan/{token}` segment (F-005 AC-40 / F-009 R20). No change to these; asserted as a
  regression.
- [ ] **AC-14 Tenancy unchanged.** Access is still by `access_token_hash` within the slug's organization; another
  seller's token under this slug is a 404 by construction (F-005 AC-35/AC-38). No new route or lookup is added.
- [ ] **AC-15 No extra PII.** The print layout and the page add no customer personal data beyond what F-005/F-006
  already display (name, participant count, the customer's own phone as today). Per-participant identities, ID numbers
  and seller-private data never appear.

### F. Mobile-first & accessibility

- [ ] **AC-16 Mobile & desktop.** Both new buttons work at 360 px and on desktop without horizontal scroll; tap targets
  ≥ 44 px; copy feedback is announced via `aria-live`; text ≥ 12 px (11 px only for uppercase kickers). Exactly one gold
  button remains. *Device review with F-002/F-003/F-005 screens.*

## Data & rules

### No schema change

Nothing is added to `bookings` or any table. The token, its hash and encrypted copy already exist (ADR-0027). Token
rotation is deferred (Q1), so no `revoked_at` / rotation columns are added now.

### Server change (one)

`App\Bookings\BookingPresenter::page()` adds `urls.self = route('public.booking.show', ['organization' => $slug,
'token' => $booking->access_token])` — the same builder `BookingController::bookingUrl()` already uses for redirects.
`urls` becomes `['home', 'evidence_upload', 'evidence_show', 'self']`; `BookingPageTest` (the F-005 AC-37 exact-keys
assertion, currently line ~248) is updated to match. No other presenter, controller, route, middleware or policy changes.

### Frontend (`resources/js/pages/Public/Booking.vue`)

- Reuse `copyLink` from `resources/js/lib/onboarding.js` (returns a state the seller page already consumes) for the link
  copy; reuse the page's existing `copied` / `aria-live` feedback pattern for success / failure.
- Add a `window.print()` handler for "Simpan bukti pesanan".
- Add a print-only receipt block and `print:hidden` on nav, buttons, upload, countdown, payment-instruction blocks,
  progress and footer (or an `@media print` block in `resources/css/app.css`). Keep the square, brand-neutral print
  style — black on white, no dependence on status tints that may not print.

### Copy (`lang/id/booking.php → page.*`, ADR-0007)

New keys (Bahasa, placeholders where noted):
`copy_link` ("Salin tautan pesanan"), `link_copied` ("Tautan disalin"),
`link_copy_failed` ("Gagal menyalin. Salin tautan dari bilah alamat."),
`link_hint` (one line, e.g. "Simpan tautan ini untuk membuka pesanan Anda lagi — tidak perlu akun."),
`save_proof` ("Simpan bukti pesanan"). Print labels reuse existing `page.*` and `status.*` keys. No Bahasa string is
hard-coded in the Vue file.

### Public fields (F-005 AC-37)

The booking page props gain exactly `urls.self`. The exact-keys test asserts the full `urls` set and that no numeric id,
`organization_id`, `access_token_hash`, other bookings, `seats_taken` or seller-private data is present (unchanged
otherwise).

### Edge cases covered

- Clipboard API unavailable / blocked (insecure context, permission denied) → AC-5 fallback, no throw.
- Printing an expired / cancelled / paid / rejected booking → the snapshot shows that status (AC-9); it is a snapshot,
  not live, and the page tells the customer to reopen the link for later changes (CR §E).
- Long trip / seller names in the print layout → wrap (`overflow-wrap`), no clipping on A4 (AC-11).
- Opening the link in a fresh browser / incognito → already works (confirmed); unchanged.
- Token still never appears in logs, referrer, `<head>`, Open Graph or audit (AC-13).

### Dependencies

- **F-005** (done): the booking page, presenter, token scheme, headers, AC-37 exact-keys test, `lang/id/booking.php`.
- **F-006** (done): payment instructions / progress / evidence on the same page — the print layout hides these inputs.
- **F-007** (done): the seller already has "Salin link pesanan"; this feature adds the customer-side equivalent only.
- **F-008 / ADR-0027**: WhatsApp link builder and token routing — reused, unchanged.
- No new ADR: browser-native print with no PDF dependency is mandated by the CR; token rotation (which would need an
  ADR superseding/extending ADR-0017/0027) is deferred to P1.

## Test plan

| Area | Tests |
|---|---|
| Presenter / props (B) | `BookingPageTest`: `urls.self` present and correct; exact-keys list updated (AC-7); no token hash / id leaked |
| Confirmation content (A) | Assert the AC-1 field set renders for a representative status (regression over F-005/F-006) |
| WhatsApp (D) | AC-33 regression: correct `wa.me` URL + prefilled text; tapping triggers no request and no status change (AC-12) |
| Security / logs (E) | Regression: `no-store` / `no-referrer` / `noindex` headers; token redacted from logs; 404 on another seller's token (reuse F-005 AC-35/36/38/40) |
| Frontend (B, C) | Vitest in `tests/js`: link-copy success + failure feedback state; `window.print()` invoked by the print button; print-only block contains the AC-9 fields |
| Manual | 360 px browser check; print preview to A4 and "Save as PDF" on mobile + desktop; device review with the other P0 screens (AC-11, AC-16) |

## Open questions

- [ ] **Q1 Token revoke / regenerate (deferred to P1).** The CR asks for a way to revoke or rotate a booking's access
  token. *Recommended P1 design:* seller-owned — a "Buat tautan baru" action on the seller booking detail
  (F-007 `Bookings/Show`) that rotates the token (new 32-char secret → new `access_token_hash` + encrypted copy), 404s
  the old link, writes an audit event, and prompts the seller to resend the new link via WhatsApp. No customer-facing
  self-revoke (lockout risk; no account recovery). Needs an ADR extending ADR-0017/0027 and, if a revoked-but-not-rotated
  state is wanted, a `revoked_at` column. — Rengga · *deferred, not blocking MVP*
- [ ] **Q2 "Save as PDF" naming.** Browser-native print offers "Save as PDF" on most mobile/desktop browsers but not
  all (older Android print services vary). *Recommended:* label the action "Simpan bukti pesanan" (not "Unduh PDF") so
  it reads correctly whether the customer saves a PDF or prints; confirm wording in the device review. — Rengga ·
  *not blocking*

## Changelog

- 2026-10-21 — created `ready` from the "Improve Customer Booking Confirmation & Retrieval" change request. Scoped to a
  thin delta over F-005/F-006: customer-side "Salin tautan pesanan" (copy link + feedback), browser-native
  "Simpan bukti pesanan" (print snapshot), and `urls.self` exposure. Confirmation content (A), the token retrieval
  mechanism (B core), the WhatsApp CTA (D) and status-reflects-DB (E) are already built and are covered here as
  regressions. Token revoke/regenerate deferred to P1 (Q1); print = snapshot of key fields; both per Rengga 2026-10-21.
