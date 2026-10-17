<?php

/*
| Rakyt product configuration. Values here are tunable without touching
| application code (F-009 "All limits live in config").
*/

return [

    /*
    | Code that may query seller data across organizations without a signed-in
    | seller (F-009 A8). Only reviewed system jobs belong here: hold expiry and
    | deadline auto-close (F-005, F-003). Admin read queries live under
    | app/Admin/Queries instead (A8a, ADR-0011). A test asserts this list is
    | exactly the set of files that bypass the organization scope.
    */
    'cross_organization_allowlist' => [
        // e.g. 'app/Bookings/Jobs/ExpireSeatHolds.php',
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
    ],

    'media' => [
        // ADR-0015: public trip photos; served publicly.
        'public_disk' => env('MEDIA_PUBLIC_DISK', 'public'),
        // F-009 A20: payment evidence; never publicly readable.
        'private_disk' => env('MEDIA_PRIVATE_DISK', 'local'),
        // Signed URL lifetime for private media, in minutes (A20: ≤ 5).
        'private_url_ttl' => 5,
        'trip_photo' => [
            'mimetypes' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_kb' => 10 * 1024,
        ],
        'payment_evidence' => [
            'mimetypes' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'],
            'max_kb' => 5 * 1024,
        ],
    ],

];
