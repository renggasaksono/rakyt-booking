<?php

/*
| Pengaturan and Pengaturan → Profil usaha (F-001 section F).
*/

return [
    'settings' => [
        'title' => 'Pengaturan',
        'profile' => 'Profil usaha',
        'profile_hint' => 'Nama, kota, WhatsApp, logo, bio, zona waktu',
        'account' => 'Akun',
        'account_hint' => 'Email, password, keluar',
        'booking' => 'Pemesanan',
        'booking_hint' => 'Batas pemesanan bawaan',
    ],

    'title' => 'Profil usaha',
    'kicker' => 'Pengaturan',
    'back' => '← Pengaturan',
    'name' => 'Nama usaha',
    'city' => 'Kota basis',
    'whatsapp' => 'Nomor WhatsApp usaha',
    'whatsapp_hint' => 'Pelanggan akan chat Anda di nomor ini.',
    'slug' => 'Link booking',
    'slug_locked' => 'Hubungi Rakyt untuk mengganti link.',
    'logo' => 'Logo',
    'logo_hint' => 'JPG, PNG atau WebP, maks. 2 MB. Ditampilkan persegi.',
    'logo_pick' => 'Pilih logo',
    'logo_change' => 'Ganti logo',
    'logo_remove' => 'Hapus logo',
    'logo_type' => 'Logo harus JPG, PNG atau WebP.',
    'logo_size' => 'Logo maksimal 2 MB.',
    'logo_saved' => 'Logo disimpan.',
    'logo_removed' => 'Logo dihapus.',
    'bio' => 'Bio singkat',
    'bio_hint' => 'Maks. 160 karakter. Tampil di halaman booking Anda.',
    'timezone' => 'Zona waktu',
    'timezone_hint' => 'Dipakai untuk tanggal keberangkatan dan batas pemesanan.',
    'timezones' => [
        'Asia/Jakarta' => 'WIB — Waktu Indonesia Barat',
        'Asia/Makassar' => 'WITA — Waktu Indonesia Tengah',
        'Asia/Jayapura' => 'WIT — Waktu Indonesia Timur',
    ],
    'timezone_notice' => ':n batas pemesanan dihitung ulang dengan zona waktu baru.',
    'save' => 'Simpan profil',
    'saved' => 'Profil usaha disimpan.',
];
