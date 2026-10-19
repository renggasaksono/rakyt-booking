<?php

/*
| F-007 Seller navigation and the "Hari ini" home (mockups 1e/2f) — seller UI copy (ADR-0007).
*/

return [

    'nav' => [
        'today' => 'Hari ini',
        'bookings' => 'Pesanan',
        'trips' => 'Trip & keberangkatan',
        'trips_short' => 'Trip',
        'payments' => 'Pembayaran',
        'public_page' => 'Halaman publik',
        'settings' => 'Pengaturan',
        'external' => '↗',
        'new_tab' => '(tab baru)',
        // AC-4: the badge's accessible label.
        'badge' => ':n pesanan perlu tindakan',
    ],

    'title' => 'Hari ini',

    'greeting' => [
        'pagi' => 'Selamat pagi, :name.',
        'siang' => 'Selamat siang, :name.',
        'sore' => 'Selamat sore, :name.',
        'malam' => 'Selamat malam, :name.',
    ],

    // Q3: no Pro plan yet.
    'plan' => [
        'free' => 'Paket Gratis',
    ],

    'new_trip' => '+ Trip baru',

    'action_needed' => [
        'title' => 'Perlu tindakan · :n pembayaran',
        'all' => 'Semua',
        'empty' => 'Semua beres. Tidak ada pembayaran yang perlu ditindak.',
    ],

    'recent' => [
        'title' => 'Pesanan terbaru',
        'all' => 'Semua pesanan',
        'empty' => 'Belum ada pesanan. Bagikan link booking Anda supaya pelanggan bisa memesan.',
    ],

    'departures' => [
        'title' => 'Keberangkatan terdekat',
        'all' => 'Semua keberangkatan',
        'empty' => 'Belum ada keberangkatan terjadwal.',
        'add' => '+ Tambah keberangkatan',
        'quick_edit' => 'Edit cepat',
        'legend' => [
            'paid' => 'Lunas',
            'held' => 'Ditahan',
            'empty' => 'Kosong',
        ],
        // AC-12: "12 lunas · 2 ditahan · 2 kosong · tutup 21 Okt"
        'seats' => [
            'paid' => ':n lunas',
            'held' => ':n ditahan',
            'empty' => ':n kosong',
            'closes' => 'tutup :date',
        ],
    ],

    'link' => [
        'title' => 'Link booking Anda',
        'copy' => 'Salin link',
        'share' => '↗ Bagikan ke WA / IG',
        'copied' => 'Link disalin',
        'failed' => 'Link belum tersalin. Salin manual dari kotak di atas.',
        'view' => 'Lihat halaman',
        'share_text' => 'Pesan trip kami di sini:',
    ],

];
