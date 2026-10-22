<?php

/*
| Rakyt product configuration. Values here are tunable without touching
| application code (F-009 "All limits live in config").
*/

return [

    /*
    | Code that may query seller data without the organization scope (F-009
    | A8): Eloquent bypasses (acrossOrganizations, withoutGlobalScope) and raw
    | query builder calls (DB::table, DB::select, …). Only reviewed system jobs
    | belong here: hold expiry and deadline auto-close (F-005, F-003). Admin
    | read queries live under app/Admin/Queries instead (A8a, ADR-0011). A test
    | scans app/ and routes/ and asserts this list is exactly what it finds.
    */
    'cross_organization_allowlist' => [
        // F-003 AC-37: lists due departure ids, then closes each as its organization.
        'app/Trips/Jobs/CloseDeparturesPastDeadline.php',
        // F-005 AC-24: lists expired hold ids, then releases each as its organization.
        'app/Bookings/Jobs/ReleaseExpiredHolds.php',
    ],

    'admin_query_path' => 'app/Admin/Queries',

    /*
    | Rate limits (F-009 Data & rules). Each entry is [max attempts, decay minutes].
    */
    'rate_limits' => [
        'seller_login' => [
            'per_email_ip' => [5, 1],
            'per_ip' => [30, 10],
        ],
        'password_reset' => [
            'per_email' => [3, 15],
            'per_ip' => [20, 60],
        ],
        'signup' => [
            'per_ip' => [10, 60],
        ],
        'public_booking' => [
            'per_phone' => [5, 60],
            'per_ip' => [30, 10],
        ],
        'evidence_upload' => [
            'per_token' => [10, 60],
        ],
        'slug_check' => [
            'per_ip' => [60, 1],
        ],
        // F-001 A10: "Kirim ulang" for the verification email.
        'verification_resend' => [
            'per_user' => [3, 15],
        ],
        // F-001 A11/A12: account changes that ask for the current password.
        'account_change' => [
            'per_user' => [10, 15],
        ],
        // F-001 F5: Pengaturan → Rekening saves.
        'payment_change' => [
            'per_user' => [10, 15],
        ],
        // F-002 AC-9: public seller and trip pages (GET), per IP.
        'public_page' => [
            'per_ip' => [120, 1],
        ],
    ],

    /*
    | Booking-link slugs sellers may not take (F-001 B7, Data & rules). Every
    | first path segment of a registered web route must be listed here; a test
    | checks it. Extend freely.
    */
    'reserved_slugs' => [
        'admin', 'api', 'app', 'auth', 'login', 'logout', 'masuk', 'keluar', 'daftar', 'register', 'signup',
        'password', 'reset', 'lupa-password', 'verify', 'verifikasi', 'email', 'dashboard', 'hari-ini', 'pesanan',
        'pembayaran', 'rekening', 'trip', 'trips', 'keberangkatan', 'pengaturan', 'settings', 'akun', 'account', 'profil',
        'profile', 'onboarding', 'mulai', 'help', 'bantuan', 'support', 'cs', 'kontak', 'contact', 'about',
        'tentang', 'blog', 'docs', 'status', 'legal', 'syarat', 'privasi', 'terms', 'privacy', 'pricing', 'harga',
        'paket', 'pro', 'business', 'enterprise', 'rakyt', 'rakytbooking', 'booking', 'book', 'pesan', 'www',
        'mail', 'static', 'assets', 'storage', 'media', 'cdn', 'img', 'images', 'public', 'null', 'undefined',
        'new', 'edit', 'test', 'demo',
        // Framework routes: health check, Vite dev assets.
        'up', 'build',
    ],

    // Public host shown in booking links (rakyt.id/<slug>). Override locally
    // (e.g. RAKYT_PUBLIC_HOST=rakyt-booking.test) so copied links open in dev.
    'public_host' => env('RAKYT_PUBLIC_HOST', 'rakyt.id'),

    // Scheme for those links. Production serves over HTTPS; local plain-HTTP
    // dev sets RAKYT_PUBLIC_SCHEME=http so copied links don't hit a TLS error.
    'public_scheme' => env('RAKYT_PUBLIC_SCHEME', 'https'),

    /*
    | Public booking pages (F-002 Data & rules).
    */
    'public' => [
        // AC-19: "Tinggal n dari m kursi" when seats left are at most this (Q4).
        'low_seats' => 5,
        // AC-19a: a closing date within this many days is always shown on its row.
        'deadline_urgent_days' => 7,
        // AC-18: departures shown before "Lihat n tanggal lainnya".
        'departures_shown' => 10,
        // AC-29: og:description length.
        'description_max' => 200,
    ],

    /*
    | Customer bookings (F-005 Data & rules).
    */
    'booking' => [
        // ADR-0006: the seat hold for an unpaid booking. A constant, not a seller setting.
        'hold_minutes' => 120,
        'name_min' => 2,
        'name_max' => 80,
        'notes_max' => 500,
        // AC-4: the notes counter appears once this many characters are typed.
        'notes_counter_from' => 400,
    ],

    /*
    | Onboarding (F-001 C1, Q12): minutes left shown in each step header,
    | from the budget Akun 1' · Usaha 1' · Trip 4' · Jadwal 2' · Rekening 2'.
    | Retune after the week-1 user tests.
    */
    'onboarding' => [
        'minutes_left' => [1 => 9, 2 => 8, 3 => 4, 4 => 2],
        // D4: the "selesai dalam" time is shown only up to this many minutes (Q5).
        'show_elapsed_up_to_minutes' => 60,
        // C11: bank / e-wallet blocks in step 4.
        'max_payment_methods' => 5,
        // C5
        'max_seats' => 500,
    ],

    /*
    | "Bank / e-wallet" picker (F-001 C10, Data & rules). `name` asks for a
    | typed name (BPD, Lainnya).
    */
    'payment_providers' => [
        'bca' => ['label' => 'BCA', 'type' => 'bank'],
        'bri' => ['label' => 'BRI', 'type' => 'bank'],
        'bni' => ['label' => 'BNI', 'type' => 'bank'],
        'mandiri' => ['label' => 'Mandiri', 'type' => 'bank'],
        'bsi' => ['label' => 'BSI', 'type' => 'bank'],
        'cimb_niaga' => ['label' => 'CIMB Niaga', 'type' => 'bank'],
        'permata' => ['label' => 'Permata', 'type' => 'bank'],
        'btn' => ['label' => 'BTN', 'type' => 'bank'],
        'danamon' => ['label' => 'Danamon', 'type' => 'bank'],
        'jago' => ['label' => 'Bank Jago', 'type' => 'bank'],
        'seabank' => ['label' => 'SeaBank', 'type' => 'bank'],
        'bpd' => ['label' => 'BPD (isi nama)', 'type' => 'bank', 'name' => true],
        'gopay' => ['label' => 'GoPay', 'type' => 'ewallet'],
        'ovo' => ['label' => 'OVO', 'type' => 'ewallet'],
        'dana' => ['label' => 'DANA', 'type' => 'ewallet'],
        'shopeepay' => ['label' => 'ShopeePay', 'type' => 'ewallet'],
        'linkaja' => ['label' => 'LinkAja', 'type' => 'ewallet'],
        'other' => ['label' => 'Lainnya', 'type' => 'bank', 'name' => true],
    ],

    'media' => [
        // ADR-0015: public trip photos; served publicly.
        'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),
        // F-009 A20: payment evidence; never publicly readable.
        'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),
        // Signed URL lifetime for private media, in minutes (A20: ≤ 5).
        'private_url_ttl' => 5,
        // ADR-0018 / F-006 AC-25: payment-instruction changes are blocked for
        // this many hours after a password reset or sign-in-email change.
        'payment_change_block_hours' => 24,
        'trip_photo' => [
            'mimetypes' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_kb' => 10 * 1024,
            // F-009 R3 / ADR-0022 §5: checked from the file header before GD decodes it.
            // The megapixel cap is derived from this budget (App\Media\PhotoBudget);
            // the upload and the variants job raise memory_limit to it.
            'memory_limit_mb' => 320,
            'memory_headroom_mb' => 96,
            'bytes_per_pixel' => 8,
            'max_side_px' => 8000,
            // The stored original is downscaled to this longest side (EXIF stripped).
            'stored_max_side_px' => 2560,
            // ADR-0015: at most this many photos per trip.
            'max_per_trip' => 10,
            // ADR-0015: resized variants for F-002, generated on the queue.
            'variant_widths' => [400, 800, 1600],
        ],
        // F-001 F1: organization logo, shown square.
        'logo' => [
            'mimetypes' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_kb' => 2 * 1024,
            'stored_side_px' => 512,
        ],
        'payment_evidence' => [
            'mimetypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
            'max_kb' => 5 * 1024,
        ],
    ],

];
