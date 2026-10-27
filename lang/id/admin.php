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
        'sellers' => 'Penjual',
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
            'organization_search' => 'Cari nama, link, atau nomor WhatsApp',
            'organization_hint' => 'Ketik minimal 2 huruf.',
            'organization_none' => 'Tidak ada organisasi yang cocok.',
            'organization_error' => 'Gagal memuat organisasi. Coba lagi.',
            'organization_change' => 'Ganti',
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
            'OrganizationMember' => 'Anggota organisasi',
            'User' => 'Pengguna',
            'Trip' => 'Trip',
            'TripPhoto' => 'Foto trip',
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
                'suspended' => 'Akun ditangguhkan',
                'reactivated' => 'Akun diaktifkan kembali',
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

    // F-012: the seller directory and organization detail. Read-only.
    'sellers' => [
        'title' => 'Penjual',
        'heading' => 'Penjual',
        'intro' => 'Semua organisasi penjual di Rakyt. Cari untuk membantu onboarding atau menelusuri masalah.',

        'filters' => [
            'title' => 'Cari penjual',
            'search' => 'Cari',
            'search_placeholder' => 'Nama, link booking, atau nomor WhatsApp',
            'status' => 'Status akun',
            'all_statuses' => 'Semua status',
            'apply' => 'Cari',
            'reset' => 'Hapus filter',
        ],

        'empty' => 'Belum ada penjual.',
        'empty_filtered' => 'Tidak ada penjual yang cocok dengan pencarian ini.',
        'registered' => 'Terdaftar :date',
        'prev' => 'Sebelumnya',
        'next' => 'Berikutnya',
        'page' => 'Halaman :page',
        'back' => 'Semua penjual',

        'statuses' => [
            'active' => 'Aktif',
            'suspended' => 'Ditangguhkan',
        ],
        'plans' => [
            'free' => 'Gratis',
        ],
        'roles' => [
            'owner' => 'Pemilik',
        ],
        'trip_statuses' => [
            'draft' => 'Draf',
            'published' => 'Tampil',
        ],
        'departure_statuses' => [
            'open' => 'Buka',
            'closed' => 'Tutup',
        ],
        'never_signed_in' => 'Belum pernah masuk',

        'profile' => [
            'title' => 'Profil',
            'public_url' => 'Link booking',
            'booking_code' => 'Kode pesanan',
            'whatsapp' => 'WhatsApp',
            'city' => 'Kota',
            'bio' => 'Bio',
        ],
        'account' => [
            'title' => 'Akun',
            'status' => 'Status',
            'plan' => 'Paket',
            'registered' => 'Terdaftar',
            'last_activity' => 'Aktivitas terakhir',
            'last_activity_hint' => 'Waktu pemilik terakhir berhasil masuk.',
        ],
        'members' => [
            'title' => 'Pengguna',
            'last_login' => 'Terakhir masuk',
        ],
        'trips' => [
            'title' => 'Trip',
            'empty' => 'Belum ada trip.',
            'departures_count' => ':count keberangkatan',
            'more' => 'Menampilkan :count trip pertama.',
        ],
        'departures' => [
            'title' => 'Keberangkatan mendatang',
            'empty' => 'Tidak ada keberangkatan mendatang.',
            'seats' => ':paid lunas · :held ditahan · :empty kosong dari :capacity',
            'more' => 'Menampilkan :count keberangkatan terdekat.',
        ],
        'bookings' => [
            'title' => 'Pesanan terbaru',
            'empty' => 'Belum ada pesanan.',
            'participants' => ':count orang',
            'evidence' => 'Lihat bukti bayar',
        ],
        'audit_events' => [
            'title' => 'Log audit',
            'empty' => 'Belum ada catatan audit untuk organisasi ini.',
            'all' => 'Lihat semua di Log audit',
        ],
        'read_only' => 'Data penjual tidak bisa diubah dari sini. Yang bisa diubah hanya status akun (bagian Akun).',

        // F-014: suspend / reactivate on the "Akun" section. Both need a reason (audited).
        'suspension' => [
            'since' => 'Ditangguhkan',
            'by' => 'Oleh',
            'reason' => 'Alasan',
            'unknown_admin' => 'Admin (tidak diketahui)',
            'suspend' => 'Tangguhkan akun',
            'reactivate' => 'Aktifkan kembali',
            'suspend_title' => 'Tangguhkan :name?',
            'suspend_effects' => [
                'Link booking menampilkan "tidak tersedia": tanpa trip, profil, atau tombol pesan.',
                'Pesanan baru dan unggah bukti bayar ditolak.',
                'Pelanggan dengan pesanan yang ada tidak lagi melihat instruksi pembayaran, sisa waktu bayar, atau tombol WhatsApp penjual.',
                'Penjual tidak bisa masuk; sesi yang sedang terbuka langsung keluar.',
                'Pesanan yang ada tidak dibatalkan dan pelanggan tidak diberi tahu otomatis. Tahanan kuota yang belum dibayar tetap kedaluwarsa seperti biasa.',
            ],
            'reactivate_title' => 'Aktifkan kembali :name?',
            'reactivate_effects' => [
                'Link booking, pesanan baru, unggah bukti bayar, dan akses masuk penjual kembali normal.',
                'Tidak ada data yang diubah.',
            ],
            'reason_label' => 'Alasan (wajib)',
            'reason_hint' => 'Tercatat di log audit. Maksimal :max karakter.',
            'suspend_placeholder' => 'Contoh: laporan penipuan dari pelanggan',
            'reactivate_placeholder' => 'Contoh: masalah sudah selesai dengan penjual',
            'confirm_suspend' => 'Ya, tangguhkan',
            'confirm_reactivate' => 'Ya, aktifkan kembali',
            'cancel' => 'Batal',
            'close' => 'Tutup',
            'sending' => 'Menyimpan…',
            'failed' => 'Gagal menyimpan. Periksa koneksi lalu coba lagi.',
            'suspended' => 'Akun :name ditangguhkan.',
            'reactivated' => 'Akun :name aktif kembali.',
            'already_suspended' => 'Akun ini sudah ditangguhkan. Tidak ada yang berubah.',
            'already_active' => 'Akun ini sudah aktif. Tidak ada yang berubah.',
            'reason_required' => 'Isi alasannya.',
            'reason_max' => 'Alasan maksimal :max karakter.',
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
