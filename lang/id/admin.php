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
        'logout' => 'Keluar',
        'signed_in_as' => 'Masuk sebagai :name',
    ],

    'dashboard' => [
        'title' => 'Beranda Admin',
        'heading' => 'Panel Admin Rakyt',
        'intro' => 'Area internal untuk mengelola platform selama masa pilot.',
        'empty' => 'Menu lainnya akan muncul di sini seiring fitur admin ditambahkan.',
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
