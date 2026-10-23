---
id: F-001
title: Seller onboarding & organization profile
status: in-progress
priority: P0
roadmap: mvp-weeks-2-3
mockups: [4a, 4b, 4c, 4d, 4e, 4f]
decisions: ["0003", "0004", "0005", "0007", "0008", "0010", "0012", "0013", "0018", "0019", "0023"]
updated: 2026-10-22
---

# F-001 · Seller onboarding & organization profile

## Problem

Small operators won't sit through training, and today they run bookings out of WhatsApp chats and notebooks.
If the first session doesn't end with a live booking link, they don't come back. The promise on the sign-up
screen is **"Trip pertama Anda online dalam 10 menit."** (blueprint §9: time to first published trip < 10 min;
roadmap gate "Continue / pivot" measures exactly this).

## Readiness

**`in-progress`** (built 2026-10-18 on `feat/F-001-seller-onboarding`; D7, F1 and T3 completed by F-002 on
2026-10-19; C3 and P1 are partly covered — see each). Implementation choices: ADR-0023 (accepted 2026-10-19).

The blocking product decisions are made: sign-in is email + password (ADR-0012, replaces the rejected
ADR-0009); the database is PostgreSQL (ADR-0013); a trip description is optional to publish (F-003 checklist).
The remaining open questions have defaults this spec already uses and can be overridden without blocking the build.
Build order still depends on F-009 and F-003 (see Dependencies).

**Differences from the mockup:** screens 4a and 4b show WhatsApp code sign-up ("Kirim kode", "✓ WhatsApp …
terverifikasi"). Under ADR-0012 the sign-up / sign-in screens differ from 4a (email + password, see section A),
and step 1 asks for the WhatsApp number as an unverified contact field instead of showing it as verified. Keep 4a's
headline, layout, tokens and one-gold-CTA rule. The time budget ("±m menit lagi") was set for code sign-in and
must be rechecked in pilot (Q12).

## Scope

**In:**
- **A. Account** (replaces the 4a code flow; no mockup for the new screens): sign-up with email + password,
  sign-in, sign-out, password reset by expiring email link, non-blocking email verification, change of email.
  Laravel's standard auth stack (Fortify/Breeze-style) per ADR-0012.
- **B. Organization** (4b, "Langkah 1 dari 4 · Usaha"): business name, base city, business WhatsApp number
  (contact for customers, not verified), booking link slug with live availability check. Creates the
  organization and the owner membership atomically.
- **C. Guided first trip** (4c–4e): Trip (type, photos, name, duration, inclusions) → Jadwal (price per person,
  first departure date, seats, booking deadline) → Pembayaran (one or more bank / e-wallet accounts).
  Each step header shows the step number and the minutes left in the 10-minute budget.
- **D. Publish & share** (4f): publish, elapsed time, booking link with Salin and Bagikan, "Nanti, dari dashboard" list.
- **E. Save and resume**: every "Lanjut" persists the step; "Simpan sebagai draf" on step 4; returning sellers
  resume where they left off.
- **F. Organization profile in Pengaturan** (no mockup yet): edit business name, base city, WhatsApp contact
  number, logo and short bio after onboarding (blueprint P0 "organization profile, contact details, basic
  branding"; 4f lists "Logo & bio usaha" as "Nanti, dari dashboard"), and Pengaturan → Rekening for the bank /
  e-wallet accounts after onboarding (F5, the F-006 subset).

**Out (explicitly):**
- WhatsApp / SMS code sign-in, magic links, social login, two-factor authentication (ADR-0012).
- Verifying the business WhatsApp number.
- Making email verification a requirement for onboarding or publishing (P0 only reminds).
- Staff invites, multiple members, roles beyond `owner` (P1 "staff accounts & permissions").
- One user owning or switching between several organizations (P1 / enterprise "multi-brand").
- Changing the slug after first publish from the seller UI, and redirects from old slugs (see Q3).
- Account deletion / data export (handled by support in P0).
- Trip description, itinerary per day, terms, meeting point & time, more departures beyond the first
  ("+ Tambah tanggal lain" hands off to the F-003 editor after publish — see C9).
- QRIS image upload and cash / pay-on-arrival setup in onboarding (F-006, from Pengaturan → Pembayaran) — see Q10.
- Subscription billing / upgrade flow (P1); only the `free` plan value and its limit check are in scope.
- Custom domains (P1), plan-based branding removal (P1).

## User stories

- As a new seller, I sign up with my email and a password in under a minute, so I can start building my page straight away.
- As a returning seller, I sign in and land where I left off.
- As a seller who forgot my password, I get a reset link by email and set a new one.
- As a seller, I give the WhatsApp number my customers should chat to, so the public page can link to it.
- As a seller, I pick my booking link (`rakyt.id/<slug>`) and see instantly whether it's available, so that I don't hit an error at the end.
- As a seller, I create my first trip, set its price and first departure, add where customers should transfer, and publish — in one guided flow on my phone.
- As a seller, I can stop before adding bank details, save a draft, and come back later without re-typing anything.
- As a seller who has just published, I copy or share my link straight to WhatsApp / Instagram.
- As a seller, I can later change my business name, city, WhatsApp number, logo and bio from Pengaturan.

## Acceptance criteria

IDs are stable so tests can reference them (e.g. `test_AC_B4_...`). Rate limits referenced as "F-009 limits" come
from the rate-limit table in F-009; tests read the configured values rather than hard-coding them.

### A. Account — email + password (ADR-0012)

- [x] **A1** Given the sign-up screen ("Daftar") at 360 px width, then it shows the headline "Trip pertama Anda online dalam 10 menit.", the sub-line from 4a, fields "Nama Anda" (Q13), "Email" (`type="email"`, `autocomplete="email"`) and "Password" (`autocomplete="new-password"`, "Lihat" show/hide toggle, helper "Minimal 8 karakter"), a required checkbox "Saya setuju dengan Syarat & Privasi" with both words linking to their pages, "Gratis untuk mulai." (no specific limit promised, ADR-0019), the gold CTA "Daftar", and the link "Sudah punya akun? Masuk". No password-confirmation field (the show/hide toggle replaces it).
- [x] **A2** Validation on sign-up: email required, valid format, stored trimmed and lowercased, unique case-insensitively; password required, ≥ 8 characters (no composition rules); terms checkbox must be ticked; "Nama Anda" required, 2–80 chars. Errors appear inline in Bahasa Indonesia and keep all input except the password. Given the email is already registered, then "Email ini sudah terdaftar. Masuk atau atur ulang password." with both actions linked.
- [x] **A3** Given valid sign-up, then exactly one `users` row is created with the password hashed (Laravel default hasher), `terms_accepted_at` and `signup_started_at` set, `email_verified_at` null; the seller is signed in, a verification email is queued, and they land on step 1 (Usaha). A double tap creates one user.
- [x] **A4** Given the sign-in screen ("Masuk"): email, password (show/hide toggle), "Ingat saya" (checked by default), gold CTA "Masuk", link "Lupa password?", link "Belum punya akun? Daftar". Given wrong email or wrong password, then the same message "Email atau password salah." is shown for both. Sign-in attempts are throttled per email + IP per the F-009 limits, with "Terlalu banyak percobaan. Coba lagi dalam n menit."
- [x] **A5** Given a successful sign-in, then the session is regenerated and the seller lands per E1–E3. With "Ingat saya", the seller stays signed in across browser restarts (remember-me cookie); without it, the session ends with the browser session or after the configured idle timeout.
- [x] **A6** Given "Keluar" (in Pengaturan and the account menu), then the session is invalidated, the remember-me token is cleared, and the seller sees the sign-in screen.
- [x] **A7** Given "Lupa password?", when any email is submitted, then the screen always shows "Jika email ini terdaftar, kami kirim link untuk atur ulang password." — identical response and comparable timing whether or not the email exists. Requests are throttled per the F-009 limits.
- [x] **A8** Given a registered email, then a reset email (Bahasa) with a single-use link is sent; the link expires after 60 minutes. Given an expired, used or tampered link, then "Link sudah tidak berlaku. Minta link baru." with a button to request one.
- [x] **A9** Given a valid reset link, when a new password (≥ 8 chars) is set, then the password is replaced, the reset token is consumed, all other sessions and remember-me tokens for that user are invalidated, the seller is signed in, and an `auth.password_reset` audit event is written.
- [x] **A10** Email verification is **non-blocking in P0**: given an unverified seller, then onboarding, publishing and every seller feature work normally, and seller screens (not the onboarding steps' sticky CTA area) show a dismissible-for-the-session banner "Cek email Anda untuk verifikasi alamat email. Kirim ulang". "Kirim ulang" re-sends (throttled per the F-009 limits). The verification link is signed and expires after 60 minutes; opening it sets `email_verified_at` and hides the banner.
- [x] **A11** Given Pengaturan → Akun, when the seller changes their email, then the current password is required; the new email is validated as in A2; on success the email is updated, `email_verified_at` is reset to null, a verification email goes to the new address, a notification "Email akun Rakyt Anda diganti ke <new, masked>" goes to the **old** address, and an `auth.email_changed` audit event is written. Wrong current password → "Password salah." and nothing changes.
- [x] **A12** Given Pengaturan → Akun, the seller can change the password by entering the current password and a new one (≥ 8 chars); other sessions are invalidated.
- [x] **A13** In local and testing environments mail uses the `log` / array mailer; no provider credentials are needed. Passwords, reset tokens and verification URLs are never written to application logs.

### B. Organization — "Langkah 1 dari 4 · Usaha" (4b, adapted)

- [x] **B1** Given a signed-in seller without an organization, when step 1 renders, then it shows fields "Nama usaha", "Kota basis", "Nomor WhatsApp usaha" (fixed "+62" prefix, numeric keyboard, helper "Pelanggan akan chat Anda di nomor ini."), "Link booking" (`rakyt.id/` prefix + slug input), and the gold CTA "Lanjut ke trip pertama". The 4b line "✓ WhatsApp … terverifikasi" and the helper "Diambil dari profil WhatsApp Business" are **not** shown.
- [x] **B2** "Nama usaha" is required, 2–80 characters after trimming (empty field with placeholder, no prefill). "Kota basis" is required, 2–80 characters, free text (placeholder e.g. "Jepara, Jawa Tengah"). Errors appear inline in Bahasa Indonesia; input is kept.
- [x] **B3** "Nomor WhatsApp usaha" is required. Given `0812 7788 1020`, `812-7788-1020`, `62812 7788 1020` or `+62 812 7788 1020`, then it is normalised to E.164 `+6281277881020`. Given a number that is not an Indonesian mobile number (`+62 8` followed by 8–11 digits), then "Nomor WhatsApp tidak valid". The number is not verified (no code sent).
- [x] **B4** Given the seller types a business name and has not edited the slug by hand, then the slug is suggested from the name (lowercased, accents stripped, spaces → `-`, invalid characters removed). Once the seller edits the slug, it no longer follows the name.
- [x] **B5** Slug format: 3–30 characters, `a-z`, `0-9` and `-`; must start and end with a letter or digit; no `--`. Uppercase input is lowercased as typed. Uniqueness is case-insensitive.
- [x] **B6** Given the seller stops typing for ~400 ms, then availability is checked and the field shows exactly one of: "Memeriksa…", "✓ Tersedia" (Lunas green), "✕ Sudah dipakai" with up to 3 available suggestions (e.g. `kencana-trip`, `kencana-jepara`, `kencana2`), "✕ Tidak bisa dipakai" (reserved), or the format error. The CTA stays enabled; the server re-validates on submit.
- [x] **B7** Given a slug on the reserved list (`config/rakyt.php` → `reserved_slugs`, initial list in Data & rules), then it is rejected as "Tidak bisa dipakai". An automated test asserts that every first path segment of every registered web route is on the reserved list.
- [x] **B8** Given step 1 is submitted, then the organization (with `plan = free`, `timezone = Asia/Jakarta`, `whatsapp` = the entered number) and an `organization_members` row (`role = owner`) are created in **one DB transaction**, plus an `organization.created` audit event; if any insert fails, none exists.
- [x] **B9** Given the seller double-taps "Lanjut ke trip pertama" or retries after a timeout, then exactly one organization exists for that user (idempotent: a second submit updates the existing organization).
- [x] **B10** Given two sellers submit the same available slug at the same moment, then exactly one succeeds (PostgreSQL unique index on the lowercased slug); the other sees "✕ Sudah dipakai" with suggestions and keeps all other input.
- [x] **B11** Given the seller goes back to step 1 before the first publish, then all step-1 fields are editable and saved on "Lanjut" (same validation).
- [x] **B12** The slug-availability endpoint requires a signed-in user, is throttled per the F-009 limits, and returns only a state (`available | taken | reserved | invalid`) plus suggestions — never any data about the organization that owns a slug.

### C. Guided first trip — steps 2–4 (4c, 4d, 4e)

- [x] **C1** Each step header shows "Langkah n dari 4 · <nama langkah>" and a static "±m menit lagi" from the budget Akun 1′ · Usaha 1′ · Trip 4′ · Jadwal 2′ · Rekening 2′: step 1 → ±9, step 2 (Trip) → ±8, step 3 (Jadwal) → ±4, step 4 (Pembayaran) → ±2. Step names: Usaha, Trip, Jadwal, Pembayaran. Sign-up / sign-in screens carry no step header. Minute values live in one config / translation entry so they can be retuned (Q12).
- [x] **C2** Step 2 (Trip): "Jenis trip" offers **two** types — Open trip ("Jadwal tetap, gabung — 1 hari atau lebih") and Private ("Pelanggan ajukan tanggal"). Choosing one presets (aligned with F-003 AC-1): Open trip → booking mode `scheduled`, duration 3H2M; Private → `request_date`, no duration preset. There is no Day trip — a single-day trip is an Open trip with a 1-day duration, set in the Durasi picker. The seller can change the duration. Jenis trip is required. *Re-scoped 2026-10-22 (Rengga): Day Trip dropped; coupled with F-003 Q17. The Open-trip hint tweak ("— 1 hari atau lebih") is a Planner default; Rengga may keep "Jadwal tetap, gabung".*
- [ ] **C3** Step 2 photos: "+ Galeri" opens the phone's picker (multiple select); the first photo is the cover ("sampul"); each photo shows upload progress and a retry on failure; the seller can remove a photo. File type and size limits follow F-003. Photos are stored under the organization's media prefix (ADR-0004).
  *Partly covered:* upload to the draft, the organization prefix, cover order and F-003 limits are tested; per-photo progress and "Coba lagi" were checked in the browser (2026-10-18), not in CI.
- [x] **C4** Step 2 fields: "Nama trip" required (3–80 chars, F-003 `TripRules`; F-003 Q11); "Durasi" required, using the F-003 duration picker (presets + "Atur sendiri", nights ≤ days); "Termasuk apa saja?" optional multi-select chips plus "+ Lainnya" (free text, max 40 chars per item). **No description field** (optional to publish; added later from the dashboard). Gold CTA "Lanjut ke harga & jadwal". On submit the trip is saved as **draft** in the seller's organization.
- [x] **C5** Step 3 (Jadwal), scheduled trips: "Harga per orang" (required integer rupiah, 1.000–100.000.000, shown with thousand separators "1.250.000", numeric keyboard); "Tanggal berangkat pertama" (required, today or later in the organization's timezone; end date shown read-only from duration: "s.d. Sab 26 Okt" + "Tanggal selesai otomatis dari durasi 3H2M."); "Jumlah kursi" (required integer 1–500, helper "Jumlah peserta maksimal untuk keberangkatan ini."); "Tutup pemesanan" via the F-003 deadline picker, shown as a real date.
- [x] **C6** Step 3 deadline default: the organization's default deadline (initially H-3, per mockup 4d). Given the default would fall in the past (e.g. departure in 2 days), then past presets are hidden from the picker (not shown struck through), "Pilih tanggal & jam…" always stays, and the nearest valid preset is preselected. A deadline in the past or after the departure start is rejected. The rule "Hanya boleh sebelum jam kumpul…" is shown only when the departure has a meeting time or "Pilih tanggal & jam…" is chosen (onboarding asks for no meeting time). *Tested:* past-preset fallback and hiding, and when the rule shows, in Vitest (`tests/js/deadline.test.js`, `onboarding.test.js`).
- [x] **C7** Step 3 shows a plain-words summary that updates live: "16 kursi · Rp1.250.000/orang · pesanan ditutup Senin 21 Okt 23.59". Gold CTA "Lanjut ke pembayaran". On submit, the base price is saved on the trip and one departure is created (price override empty, `seats_taken = 0`, status open).
- [x] **C8** Step 3, request-date trips (Private, or any trip with booking mode `request_date`): only "Harga per orang" is asked, with helper "Pelanggan mengajukan tanggal; Anda membalas dari dashboard." No departure is created. Header still reads "Langkah 3 dari 4 · Jadwal".
- [x] **C9** "+ Tambah tanggal lain" on step 3 is not shown in onboarding; after publish, extra departures are added in the F-003 editor. (Keeps the step inside its 2-minute budget.)
- [x] **C10** Step 4 (Pembayaran): title "Ke mana pelanggan transfer?", note "Uang langsung masuk ke rekening Anda. Rakyt tidak memegang dana dan tidak memotong biaya." Fields: "Bank / e-wallet" (picker, list in Data & rules, plus "Lainnya" with a name field), "Nomor rekening" (digits only, 5–20, spaces ignored; for e-wallets the label reads "Nomor HP / akun"), "Atas nama" (required, 2–100 chars, helper "Harus sama persis dengan buku tabungan. Ditampilkan ke pelanggan.").
- [x] **C11** "+ Tambah rekening lain" in onboarding adds another bank / e-wallet block (max 5 in onboarding); Below it: "QRIS bisa ditambahkan nanti." — QRIS comes with F-006, and the copy names no menu until that screen exists. Payment methods belong to the organization, not to the trip.
- [x] **C12** Gold CTA on step 4 is "Terbitkan trip"; below it, "Belum siap? Simpan sebagai draf — trip belum bisa dipesan." Saving as draft does not require any payment method and keeps whatever valid payment fields were entered. A seller who saved a draft without an account adds one later in Pengaturan → Rekening (F5) — the F-003 editor's checklist item "atur rekening" links there — and then publishes from the editor (tested end to end).
- [x] **C13** Every step has a back control that returns to the previous step with saved values shown. A failed save keeps all input and shows "Gagal menyimpan. Periksa koneksi lalu coba lagi."; the CTA shows a loading state and ignores repeat taps while saving.

### D. Publish & share (4f)

- [x] **D1** Given "Terbitkan trip", when the trip fails the shared publish checklist (owned by F-003: cover photo, name, base price, ≥1 open future departure for `scheduled` trips, ≥1 active payment method in the organization — description **not** required), then nothing is published and the seller is sent to the first failing step with the missing item highlighted.
- [x] **D2** Given the checklist passes, then in one transaction the trip becomes `published`, `organizations.first_published_at` is set if empty, and an audit event (`trip.published`, actor, before/after status) is written. A double tap publishes once.
- [x] **D3** Given an organization on the `free` plan, then publishing is not limited by plan in P0 (ADR-0019); `plan` is stored but not enforced.
- [x] **D4** Given a successful first publish, then 4f shows "✓ Trip terbit · m menit s detik" where the time is `first_published_at − users.signup_started_at`, **only if** that is ≤ 60 minutes; otherwise "✓ Trip terbit". Then "Siap terima pesanan.", the trip card (cover, name, "Mulai Rp…", "24 Okt · 16 kursi" or "Ajukan tanggal" for request-date trips), "Link booking Anda" `rakyt.id/<slug>` with "Salin".
- [x] **D5** "Salin" copies `https://rakyt.id/<slug>` and shows "Link disalin". The gold CTA "↗ Bagikan ke WA / IG" opens the native share sheet (Web Share API) with the link and a short Bahasa text; without Web Share it copies the link and shows "Link disalin". "Ke dashboard" is an ink-outline button.
- [x] **D6** 4f lists "Nanti, dari dashboard": "Deskripsi, itinerary per hari & ketentuan" (links to the F-003 trip editor) and "Logo & bio usaha ±1 menit" (links to Pengaturan → Profil usaha, section F).
- [x] **D7** Given the published trip, then `rakyt.id/<slug>` shows it (F-002). Given a draft trip, then it is not reachable publicly by any URL (F-002 AC).
  *Covered by F-002* AC-5, AC-13 and AC-16 tests (`tests/Feature/PublicPages`).

### E. Save and resume

- [x] **E1** Given a signed-in seller with no organization, then any seller route redirects to step 1.
- [x] **E2** Given a seller with an organization that has never published and has not chosen "Simpan sebagai draf", when they sign in or open any seller route, then they resume at the first incomplete step, derived from data (no organization → 1; no draft trip → 2; scheduled trip without departure or trip without base price → 3; no payment method → 4; otherwise 4), with all saved values filled in.
- [x] **E3** Given the seller chose "Simpan sebagai draf" (sets `organizations.onboarding_skipped_at`) or has published at least once, then they land on the dashboard (F-007), and the draft trip is available in Trip & keberangkatan for completion in the F-003 editor.
- [x] **E4** Given a draft saved days ago whose departure date or deadline has since passed, when the seller tries to publish, then D1 blocks it with "Tanggal keberangkatan sudah lewat — pilih tanggal baru."

### F. Organization profile — Pengaturan → Profil usaha (no mockup yet)

- [x] **F1** The owner can edit "Nama usaha", "Kota basis", "Nomor WhatsApp usaha" (same validation as B3, not verified), "Logo" (JPG/PNG/WebP, ≤ 2 MB, shown square) and "Bio singkat" (≤ 160 chars). Changes appear on the public page (F-002) immediately.
  *Covered:* editing, validation and the logo are tested here; the public page shows changes on the next load (F-002 AC-10).
- [x] **F2** The slug is shown read-only after first publish with "Hubungi Rakyt untuk mengganti link." (Q3). Before first publish it is editable with the B5–B7, B10 rules.
- [x] **F3** Account settings (email, password) live in Pengaturan → Akun (A11, A12), separate from the business profile. Past onboarding Akun sits in the seller layout like the other Pengaturan pages; "Keluar" (A6) is on Pengaturan itself and in the onboarding header, not repeated on Akun.
- [x] **F4** Given an organization with future departures that use a relative deadline preset (`same_day`, `h1`, `h3`, `h7`), when the owner changes the time zone in Profil usaha, then those deadlines are recomputed in the new zone with F-003's `DeadlineCalculator`, inside the same transaction, each change audited as `departure.deadline_changed`; custom deadlines keep their instant (ADR-0014, F-003 Q15).

- [x] **F5** Pengaturan → Rekening (`/pengaturan/rekening`, route `payment-methods.index`) lists the organization's bank / e-wallet accounts with the step 4 fields and rules (C10, max 5, at least one kept) and saves them with the same service (own limiter `throttle:payment-change`, 10 per 15 min per user): one `payment_instructions.changed` audit event with before/after values per real change, none when nothing changed. It is on the Pengaturan index and linked from the F-003 editor's publish checklist, at phone width too (the list under "Pratinjau"). Page visits during the guided flow resume it (E2). QRIS and cash stay in F-006. No mockup; built from the 4e block.
- [x] **F6** Every Pengaturan sub-page (Profil usaha, Akun, Pemesanan, Rekening) links back to the Pengaturan index with the same label "← Pengaturan". Akun opened before step 1 links back to onboarding ("← Kembali").

### Tenant isolation (ADR-0004, F-009) — tests required

- [x] **T1** All onboarding and profile endpoints resolve the organization from the signed-in user's membership; none accepts an `organization_id` from the request.
- [x] **T2** Given sellers A and B, when B requests A's draft trip, departure, payment method (onboarding or Pengaturan → Rekening) or organization profile by ID through any onboarding / profile / publish route (read, update, publish, delete photo), then the response is 404 (or the F-009 convention) and nothing changes. One test per resource.
- [x] **T3** Given A's draft trip, then it never appears on B's public page or any public URL, and A's payment-method details are never returned to B.
  *Covered:* A's draft and payment details never reach B's onboarding pages (tested here) or any public page (F-002 AC-5, AC-7, AC-8, `PublicTenancyTest`).
- [x] **T4** Uploaded trip photos and logos are stored under A's organization prefix; B cannot delete or replace them.
- [x] **T5** Given a user with no membership in an organization, policies deny every seller action on it, even if UI links are crafted by hand.
- [x] **T6** Account endpoints (email change, password change) act only on the signed-in user; a user ID in the request is ignored.

### Overbooking

Not applicable beyond setup: onboarding creates a departure with `seats_taken = 0` and capacity 1–500; no
booking or seat hold happens here. Capacity changes after publish go through F-003 quick edit (ADR-0006).

### Phone width & design rules

- [ ] **P1** Every screen in this feature works at 360 × 640 with no horizontal scroll; the gold CTA is sticky at the bottom; all tap targets ≥ 44 px; the on-screen keyboard does not cover the focused field.
  *Partly verified:* browser smoke test at 375 × 812 on 2026-10-18 (sign-up → steps 1–4 → 4f → Profil usaha): no horizontal scroll, sticky gold CTA, 44 px targets. Not in CI; device review at 360 × 640 still to do.
- [x] **P2** One gold button per screen ("Daftar", "Masuk", "Kirim link", "Simpan password", 4b–4e "Lanjut…"/"Terbitkan trip", 4f "↗ Bagikan ke WA / IG"); everything else ink outline or link (ADR-0008). Helper text ≥ 12 px; kickers ≥ 11 px.
- [x] **P3** Correct keyboards and autofill: `type="email"` for email, `autocomplete="current-password"` / `new-password` for passwords (password managers work), `inputmode="numeric"` for phone, price, seats and account number.
- [x] **P4** All UI strings, including auth emails, come from `lang/id/` (ADR-0007); money shown as `Rp1.250.000`, dates as `Kam, 24 Okt 2026`, times as `23.59`.

## Data & rules

**Entities** (names per blueprint §3; F-003 owns trips/departures, F-006 owns payment methods; PostgreSQL per ADR-0013):

| Table | Fields used by F-001 | Notes |
|---|---|---|
| `users` | `name`, `email` (unique, lowercase), `password` (hash), `email_verified_at`, `remember_token`, `terms_accepted_at`, `signup_started_at` | Laravel standard plus two timestamps. Not tenant-owned. |
| `password_reset_tokens`, `sessions` | Laravel standard | Reset tokens expire after 60 min. |
| `organizations` | `name`, `slug` (unique, lowercase), `city`, `whatsapp` (E.164), `timezone` (default `Asia/Jakarta`), `plan` (default `free`), `logo_path`, `bio`, `onboarding_skipped_at`, `first_published_at` | Tenant boundary. |
| `organization_members` | `organization_id`, `user_id`, `role` (`owner`) | Unique (`organization_id`, `user_id`). P0: one organization per user, enforced in the service, not by a DB constraint (keeps P1 multi-org open). |
| `trips` | name, trip type, duration, inclusions, base price, booking mode, status (`draft`/`published`), photos | Per F-003. Recommend storing `trip_type` for analytics. |
| `trip_departures` | start date, capacity, price override (null), booking deadline, open/closed, `seats_taken` | Per F-003 / ADR-0005. |
| `payment_methods` | `organization_id`, `type` (`bank`/`ewallet`), `provider`, `account_number`, `account_holder`, `position`, `active` | Per F-006; F-001 needs the bank/e-wallet subset early. |
| `audit_events` | `organization.created`, `trip.published`, `auth.password_reset`, `auth.email_changed` | Per F-009. |

**Rules**
- Money is integer rupiah. Phone numbers are stored E.164 and rendered `+62 812 7788 1020`.
- `signup_started_at` = the moment the sign-up form is submitted successfully (user created).
- No plan limits are enforced in P0 (ADR-0019); `plan` is stored (default `free`) for later. Mockup copy "Gratis untuk 1 trip aktif" is sample text.
- Slugs are lowercase, 3–30 chars, `[a-z0-9-]`, no leading/trailing/double hyphen, unique, not reserved.
- **Initial reserved slugs** (config, extend freely): `admin, api, app, auth, login, logout, masuk, keluar, daftar, register, signup, password, reset, lupa-password, verify, verifikasi, email, dashboard, hari-ini, pesanan, pembayaran, rekening, trip, trips, keberangkatan, pengaturan, settings, akun, account, profil, profile, onboarding, mulai, help, bantuan, support, cs, kontak, contact, about, tentang, blog, docs, status, legal, syarat, privasi, terms, privacy, pricing, harga, paket, pro, business, enterprise, rakyt, rakytbooking, booking, book, pesan, www, mail, static, assets, storage, media, cdn, img, images, public, null, undefined, new, edit, test, demo`. Plus every first route segment (B7).
- Bank / e-wallet picker (initial list, config): BCA, BRI, BNI, Mandiri, BSI, CIMB Niaga, Permata, BTN, Danamon, Bank Jago, SeaBank, BPD (isi nama), GoPay, OVO, DANA, ShopeePay, LinkAja, Lainnya.
- Elapsed-time metric: `signup_started_at` → `first_published_at`; both kept for the "time to first published trip" metric (roadmap gate, blueprint §9).
- Timezone: every date rule (departure ≥ today, deadline) uses the organization's timezone (ADR-0007).

**Edge cases covered above:** duplicate email (A2); credential stuffing / brute force (A4); reset-link email
enumeration (A7); expired or reused reset / verification links (A8, A10); hijacked-session email change (A11);
malformed / foreign WhatsApp numbers (B3); double submit (A3, B9, C13, D2); slug race (B10); reserved slug vs.
route collision (B7); deadline default in the past (C6); draft saved without any account (C12, F5); request-date trip with no departure (C8); slow / failed
photo upload (C3); lost connection mid-step (C13); stale draft with past dates (E4); cross-tenant ID guessing (T2).

## Dependencies

- **F-009** — tenancy base (organization scope, policies, 404 vs 403 convention), audit events, the rate-limit table used by A4, A7, A10 and B12. F-001 creates the first tenant, so B8 and T1–T6 need F-009's scoping in place.
- **F-003** — trip & departure models, duration picker, deadline picker, photo limits, publish checklist (name required, description optional), trip-type presets (C2 must match). F-001 steps 2–3 reuse these; they must not fork them.
- **F-003 is built first (2026-10-18)** — it adds `organizations.timezone` and `default_booking_deadline_preset`, a minimal seller layout, and a `PaymentReadiness` interface for checklist item 5 whose default answer is "no method". F-001 adds the `payment_methods` table and form, binds the real `PaymentReadiness`, adds the time-zone field to Profil usaha, and reuses F-003's R3 pixel cap. See F-003 → *Build order*.
- **F-006** — payment-method model. Onboarding (weeks 2–3) needs bank / e-wallet methods before F-006 is scheduled (week 4): the payment-method model and its form must be pulled forward into weeks 2–3.
- **F-002** — public page renders the published trip and enforces "drafts never public".
- **F-007** — dashboard landing after E3; Pengaturan nav entry that hosts sections A (Akun) and F (Profil usaha); the dashboard greeting uses `users.name`.
- **F-009 hand-offs (2026-10-18)** — built and ready: `BelongsToOrganization`, `OrganizationOwnedPolicy`, `OrganizationPolicy`, `ExistsInCurrentOrganization`, the cross-tenant harness (`tests/Support/InteractsWithTenants`), `AuditLog`, the named limiters (`throttle:seller-login`, `password-reset`, `signup`, `slug-check`) and Bahasa validation messages in `lang/id`. F-001 owns: **F-009 A14b** (show the `rate_limit` error on the form, input kept), **A15a** (reset response identical for unknown emails), **R4** (`AuditLog` must resolve a `User` subject through its membership so `auth.password_reset` works for a guest; throw on organization mismatch), **R7** (Inertia-aware rendering of 409/419/429 instead of Inertia's error modal) and **R3** (pixel-dimension cap on photo uploads, shared with F-003). Do not re-add an unscoped `Organization::users()` relation (ADR-0021).
- **Ops / F-009** — a transactional email provider (with SPF/DKIM on the sending domain) is needed before pilot for reset and verification emails; `log` mailer is enough to build.
- **Legal** — "Syarat" and "Privasi" pages need content before pilot (A1 links); placeholder pages are fine for building.
- **Design** — sign-up, sign-in, reset screens and Pengaturan → Akun / Profil usaha are not mocked (4a/4b show the rejected WhatsApp-code flow); build from tokens and the 4a/4b patterns.

## Open questions

None block building. The spec uses the recommended default for each; override any time.

- [x] **Q1 — Sign-in method.** Decided 2026-10-17: email + password (ADR-0012); ADR-0009 rejected.
- [x] ~~**Q2 — Prefill business name from the WhatsApp Business profile?**~~ Withdrawn: no WhatsApp sign-in, so nothing to prefill; manual field.
- [ ] **Q3 — Can sellers change their slug later?** *Recommend: editable until first publish; afterwards only via Rakyt support, no redirects in P0.* Shared links on IG bios and WA statuses break on change; support-only keeps it rare and deliberate. Revisit (self-serve + redirect from old slug) if pilots ask.
- [x] **Q4 — "Akun" outside the 4 numbered steps?** Resolved from mockup 4a–4e: sign-up has no step header; Usaha, Trip, Jadwal, Pembayaran are steps 1–4.
- [ ] **Q5 — 4f timer for sellers who come back days later.** *Recommend:* from sign-up to first publish, shown only if ≤ 60 min (D4). Showing "3 hari 2 jam" would undercut the promise.
- [x] **Q6 — Trip type presets.** Aligned with F-003 AC-1: Open trip → scheduled + 3H2M; Private → request_date, no duration preset. *Updated 2026-10-22 (Rengga): Day Trip dropped — two types only; a day trip is an Open trip with a 1-day duration (F-003 Q17).*
- [ ] **Q7 — Inclusion chips.** Mockup 4c shows Karimunjawa-specific chips (Kapal, Homestay, Makan, Alat snorkel, Guide). *Recommend a generic set:* Transportasi, Penginapan, Makan, Guide, Tiket masuk, Dokumentasi, + Lainnya.
- [x] **Q8 — Timezone for operators in WITA/WIT (Bali, NTT, Sulawesi, Papua).** Decided 2026-10-17: ADR-0014 accepted. *Recommend:* default WIB, selectable WIB/WITA/WIT in Pengaturan → Profil usaha; don't add a field to step 1 (time budget).
- [ ] **Q9 — Who owns logo / bio / contact editing?** *Recommend: F-001 section F* (blueprint P0 lists it; nothing else owns it). Confirm, or move it to F-007.
- [ ] **Q10 — Sellers who only take QRIS or cash.** *Recommend:* onboarding asks for bank / e-wallet only; QRIS-only or cash-only sellers save as draft, add the method in Pengaturan → Pembayaran (F-006), then publish. Revisit if pilots hit it. *Update 2026-10-19:* a seller who saved a draft without any account was stuck until F-006 (manual test). Pengaturan → Rekening (F5) now lets them add a bank / e-wallet account and publish from the editor; QRIS-only and cash-only sellers still wait on F-006.
- [x] **Q11 — Is a description required to publish?** Decided 2026-10-17: no; F-003 checklist item is "Nama". Onboarding has no description field.
- [ ] **Q12 — Recheck the time budget.** The ±9/±8/±4/±2 values assumed a 1-minute code sign-in. *Recommend:* keep them for build (config, C1), time 3–5 target users in week 1 prototype tests, and retune before pilot.
- [ ] **Q13 — Ask "Nama Anda" at sign-up?** *Recommend: yes* — the dashboard greets by name ("Selamat pagi, Agus.") and support needs a person's name; it is one short field. Alternative: drop it and greet with the business name.
- [ ] **Q14 — Sign-up form: password confirmation field?** *Recommend: no*, use a show/hide toggle (A1) — faster on a phone, same error protection. Reset is always available.
- [x] **Q15 — Email change: now, or after verifying the new address?** *Decided 2026-10-19 (Rengga): at once, as built (A11); F-009 B12 reworded.* A11 (built) changes the email at once and resets verification; F-009 B12 says the change takes effect only after the new address is verified. *Recommend:* keep A11 for P0 (the current password is required, the old address is told, other devices are signed out) and reword F-009 B12; switch to verify-first if pilots show takeover attempts. Raised by the builder 2026-10-18.
- [ ] **Q16 — ADR-0018's 24-hour block and change notice for payment instructions.** Not built in F-001: after a reset or email change, step 4 / later edits are not blocked, and the sign-in email is not notified of an account change (every change *is* audited as `payment_instructions.changed`). *Recommend:* build both with F-006's Pengaturan → Pembayaran. Pengaturan → Rekening (F5, 2026-10-19) is a second write path through the same `PaymentMethodService::sync`, so the block and notice must cover it too. Recorded as a pre-pilot gate in the roadmap (blocks "Pilot go"). Raised by the builder 2026-10-18.
- [ ] **Q17 — Auth events before step 1.** A seller with no organization yet (e.g. resetting the password before step 1) is not audited, because `audit_events` needs an organization. *Recommend:* accept for P0; nothing seller-owned exists to protect yet. Raised by the builder 2026-10-18.

## Changelog

- 2026-10-17 — created from blueprint §3 and mockup round 4
- 2026-10-17 — planner pass: testable ACs (A–F, tenancy, phone), data model, reserved slugs, edge cases, dependencies; resolved step count from mockup
- 2026-10-17 — section A rewritten for email + password (ADR-0012, ADR-0009 rejected); WhatsApp number moved to step 1 as unverified contact; DB = PostgreSQL (ADR-0013); description optional (Q11); trip-type presets aligned with F-003; mockup 4a/4b divergence and time-budget recheck noted; status → `ready`
- 2026-10-17 — Free-plan trip limit removed from P0 (ADR-0019): sign-up copy "Gratis untuk mulai.", D3 asserts no limit
- 2026-10-17 — ADR-0014 accepted; Q8 closed
- 2026-10-18 — F-009 slice A hand-offs listed under Dependencies (A14b, A15a, R3, R4, R7)
- 2026-10-18 — F-003 now builds first; hand-back (payment_methods + real PaymentReadiness, time-zone field) noted under Dependencies
- 2026-10-18 — Trip name 3–80 chars (F-003 Q11); F4 added: time-zone change recomputes preset deadlines (F-003 Q15)
- 2026-10-18 — Built (builder) on `feat/F-001-seller-onboarding`: sections A–F, `payment_methods` + the real `PaymentReadiness`, F-009 A14b/A15a/R4/R7. 58/63 ACs ticked; C3, D7, F1, T3, P1 partly covered. ADR-0023 proposed; Q15–Q17 raised. Status → `in-progress`
- 2026-10-18 — Review fix (blocker): `first_published_at` is now set in `TripService::publish`, so a publish from the F-003 editor after "Simpan sebagai draf" also locks the slug (F2) and records the first publish (D2); tested
- 2026-10-18 — Review fix (C12): "Simpan sebagai draf" leaves a saved account whose edit is not valid unchanged instead of removing it; tested
- 2026-10-19 — Rengga: ADR-0023 accepted; Q15 decided (email change takes effect at once; F-009 B12 reworded)
- 2026-10-19 — Manual-test fixes: Pengaturan → Rekening added (F5) so a seller who saved a draft without an account can add one and publish (C12, Q10); seats helper made generic (C5); past deadline presets hidden and the meeting-time rule shown only when it applies (C6, F-003 AC-21–23); "+ Tambah rekening lain" (C11); every Pengaturan sub-page links back to Pengaturan (F6)
- 2026-10-19 — Review follow-up: step 4's QRIS note no longer names a Pengaturan → Pembayaran menu that does not exist ("QRIS bisa ditambahkan nanti.", C11); Pengaturan → Rekening saves have their own limiter (`payment-change`, 10 per 15 min per user)
- 2026-10-19 — F-002 build: D7, F1 (public half) and T3 (public half) covered by the public-page tests; ticked
- 2026-10-22 — Rengga: Day Trip trip type dropped (coupled with F-003 Q17). C2 reworded to two types (Open trip,
  Private) and **unticked** for the builder; Q6 updated; `lang/id/onboarding.php → trip.types` loses `day_trip` and the
  Open-trip hint gains "— 1 hari atau lebih" (Planner default). Type presets and the enum change live in F-003; this
  spec tracks the onboarding picker delta only. Status stays `in-progress`
- 2026-10-22 — Builder: onboarding picker now offers the two types only (`day_trip` removed from
  `lang/id/onboarding.php → trip.types`, Open-trip hint updated); `GuidedTripTest::test_ac_c2_...` asserts the two-type
  set and the new hint. C2 re-ticked. All gates green
- 2026-10-23 — F3: Akun uses the seller layout once onboarding is done and no longer repeats "Keluar" (it stays on
  Pengaturan and in the onboarding header). Status unchanged
