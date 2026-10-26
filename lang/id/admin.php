<?php

/*
| Admin panel copy (F-010, ADR-0011). Internal panel, but per ADR-0007 the UI
| language stays Bahasa Indonesia.
*/

return [
    'brand' => 'Rakyt Admin',

    'nav' => [
        'menu' => 'Menu admin',
        'overview' => 'Beranda',
        'audit' => 'Log audit',
        'logout' => 'Keluar',
        'signed_in_as' => 'Masuk sebagai :name',
    ],

    'dashboard' => [
        'title' => 'Beranda Admin',
        'heading' => 'Panel Admin Rakyt',
        'intro' => 'Area internal untuk mengelola platform selama masa pilot.',
        'empty' => 'Menu lainnya akan muncul di sini seiring fitur admin ditambahkan.',
    ],

    // F-015: the admin audit view. Read-only; admin events stay platform-internal.
    'audit' => [
        'title' => 'Log audit',
        'heading' => 'Log audit',
        'intro' => 'Semua perubahan penting di platform: siapa, apa, kapan, dan alasannya. Log ini tidak bisa diubah atau dihapus.',
        'at' => ':date :time WIB',

        'filters' => [
            'title' => 'Filter',
            'organization' => 'Organisasi',
            'all_organizations' => 'Semua organisasi',
            'platform' => 'Platform (tanpa organisasi)',
            'actor' => 'Pelaku',
            'all_actors' => 'Semua pelaku',
            'actor_id' => 'ID pelaku',
            'actor_id_hint' => 'Opsional. Pilih jenis pelaku dulu.',
            'action' => 'Aksi',
            'all_actions' => 'Semua aksi',
            'apply' => 'Terapkan',
            'reset' => 'Hapus filter',
        ],

        'event' => [
            'organization' => 'Organisasi',
            'no_organization' => 'Platform',
            'actor' => 'Pelaku',
            'subject' => 'Target',
            'reason' => 'Alasan',
            'changes' => 'Perubahan',
            'before' => 'Sebelum',
            'after' => 'Sesudah',
            'empty_value' => '—',
            'ip' => 'IP',
            'filter_by' => 'Tampilkan hanya :name',
        ],

        'empty' => 'Belum ada catatan audit.',
        'empty_filtered' => 'Tidak ada catatan yang cocok dengan filter ini.',
        'prev' => 'Sebelumnya',
        'next' => 'Berikutnya',
        'page' => 'Halaman :page',

        'actor_types' => [
            'user' => 'Penjual',
            'customer' => 'Pelanggan',
            'system' => 'Sistem',
            'rakyt_staff' => 'Staf Rakyt',
            'platform_admin' => 'Admin platform',
        ],

        'subject_types' => [
            'Booking' => 'Pesanan',
            'Organization' => 'Organisasi',
            'User' => 'Pengguna',
            'Trip' => 'Trip',
            'TripDeparture' => 'Keberangkatan',
            'PaymentRecord' => 'Pembayaran',
            'PaymentMethod' => 'Metode pembayaran',
        ],

        // One label per App\Audit\AuditAction (a test checks every case).
        'actions' => [
            'booking' => [
                'created' => 'Pesanan dibuat',
                'status_changed' => 'Status pesanan berubah',
                'hold_expired' => 'Tahanan kuota kedaluwarsa',
                'cancelled' => 'Pesanan dibatalkan',
            ],
            'payment' => [
                'evidence_uploaded' => 'Bukti bayar diunggah',
                'confirmed' => 'Pembayaran dikonfirmasi',
                'rejected' => 'Pembayaran ditolak',
            ],
            'departure' => [
                'created' => 'Keberangkatan dibuat',
                'price_changed' => 'Harga keberangkatan berubah',
                'capacity_changed' => 'Kuota keberangkatan berubah',
                'status_changed' => 'Status keberangkatan berubah',
                'deadline_changed' => 'Batas pemesanan berubah',
                'deleted' => 'Keberangkatan dihapus',
            ],
            'trip' => [
                'base_price_changed' => 'Harga dasar trip berubah',
                'published' => 'Trip diterbitkan',
                'unpublished' => 'Trip disembunyikan',
            ],
            'payment_instructions' => [
                'changed' => 'Instruksi pembayaran berubah',
            ],
            'organization' => [
                'created' => 'Organisasi dibuat',
                'slug_changed' => 'Link booking berubah',
                'member_added' => 'Anggota ditambahkan',
                'member_removed' => 'Anggota dihapus',
            ],
            'auth' => [
                'password_reset' => 'Kata sandi direset',
                'password_changed' => 'Kata sandi diganti',
                'email_changed' => 'Email diganti',
                'account_recovered' => 'Akun dipulihkan',
            ],
            'platform_admin' => [
                'granted' => 'Akses admin platform diberikan',
                'revoked' => 'Akses admin platform dicabut',
            ],
        ],
    ],

    'two_factor' => [
        'title' => 'Verifikasi dua langkah',
        'heading' => 'Masukkan kode autentikator',
        'intro' => 'Buka aplikasi autentikator Anda, lalu masukkan kode 6 digit untuk melanjutkan.',
        'code' => 'Kode 6 digit',
        'submit' => 'Verifikasi',
        'invalid' => 'Kode tidak valid. Coba lagi.',
        'logout' => 'Keluar',
    ],
];
