<?php

/*
| Error pages (F-009 A17). Ink-outline actions only; nothing here moves a booking forward.
*/

return [
    'home' => 'Ke beranda',
    'back' => 'Kembali',
    'reload' => 'Muat ulang',

    '403' => ['title' => 'Akses ditolak', 'message' => 'Anda tidak punya akses ke halaman ini.'],
    '404' => ['title' => 'Halaman tidak ditemukan', 'message' => 'Link mungkin salah ketik atau halaman sudah tidak tersedia.'],
    '409' => ['title' => 'Akun perlu dicek', 'message' => 'Akun Anda terhubung ke lebih dari satu usaha. Hubungi Rakyt agar kami bantu rapikan.'],
    '419' => ['title' => 'Sesi Anda sudah habis', 'message' => 'Demi keamanan, halaman ini kedaluwarsa. Muat ulang halaman lalu coba lagi.'],
    '429' => ['title' => 'Terlalu banyak percobaan'],
    '500' => ['title' => 'Ada yang tidak beres', 'message' => 'Maaf, terjadi kesalahan di sistem kami. Coba lagi beberapa saat lagi.'],
    '503' => ['title' => 'Sedang ada perbaikan', 'message' => 'Rakyt sedang diperbarui dan akan kembali sebentar lagi.'],
];
