<?php

/*
| Public booking pages rakyt.id/<slug> and rakyt.id/<slug>/<trip> (F-002).
*/

return [

    'currency' => 'Rp',

    'seller' => [
        'logo_alt' => 'Logo :name',
        'chat_whatsapp' => 'Chat WhatsApp',
        'share' => 'Bagikan',
        // Web Share API text, next to the title (Nama usaha) and the link (AC-12).
        'share_text' => 'Lihat trip dan jadwal :name di sini.',
        'copied' => 'Link disalin',
        'copy_failed' => 'Salin link ini: :url',
        'trips_title' => 'Trip tersedia',
        'trips_count' => ':n trip',
        'empty' => 'Belum ada trip yang bisa dipesan saat ini.',
        'empty_hint' => 'Chat operator untuk info jadwal.',
    ],

    'card' => [
        'from' => 'Mulai',
        'per_person' => '/orang',
        'seats' => 'Sisa :n kursi',
        'request_date' => 'Ajukan tanggal',
        'no_date' => 'Belum ada tanggal',
        'no_photo' => 'Belum ada foto',
    ],

    'trip' => [
        'back' => 'Kembali ke :name',
        'gallery' => [
            'label' => 'Foto trip',
            'previous' => 'Foto sebelumnya',
            'next' => 'Foto berikutnya',
            'counter' => ':current/:total',
            'alt' => ':name, foto :n dari :total',
        ],
        'departures_title' => 'Pilih keberangkatan',
        'show_more' => 'Lihat :n tanggal lainnya',
        'seats' => 'Sisa :n kursi',
        'seats_low' => 'Tinggal :n dari :m kursi',
        'unavailable' => 'Tidak bisa dipesan',
        'full' => '– Penuh',
        'closed' => '– Ditutup',
        'selected' => 'Dipilih',
        'closing' => [
            // AC-19a: said once when every bookable departure shares the preset.
            'h1' => 'Pemesanan ditutup 1 hari sebelum keberangkatan.',
            'h3' => 'Pemesanan ditutup 3 hari sebelum keberangkatan.',
            'h7' => 'Pemesanan ditutup 7 hari sebelum keberangkatan.',
            'same_day' => 'Pemesanan ditutup 1 jam sebelum jam kumpul.',
            // Per row: mixed presets, a custom deadline, or an urgent one.
            'row' => '· tutup :date',
            'row_today' => '· tutup hari ini :time',
            'row_tomorrow' => '· tutup besok :time',
        ],
        'no_departures' => 'Belum ada tanggal yang bisa dipesan.',
        'other_date' => 'Tanggal lain? Ajukan tanggal ke operator',
        'bar_dates' => ':dates · per orang',
        'bar_from' => 'Mulai',
        'bar_per_person' => '/ orang',
        'book' => 'Pesan →',
        'request_date' => 'Ajukan tanggal →',
        // F-008 AC-A1/A2 button label; the message bodies live in lang/id/whatsapp.php.
        'ask_whatsapp' => 'Tanya via WhatsApp',
        'sections' => [
            'inclusions' => 'Termasuk',
            'exclusions' => 'Tidak termasuk',
            'meeting' => 'Kumpul & jadwal',
            'map' => 'Buka peta',
            'day' => 'Hari :n',
            'terms' => 'Ketentuan',
        ],
    ],

    'footer' => 'Dibuat dengan Rakyt',

    // Server-rendered <head> (AC-29, AC-30).
    'head' => [
        'seller_title' => ':name · Rakyt',
        'seller_description' => ':n trip · :city',
        'trip_title' => ':trip · :name',
        'trip_from' => 'Mulai :price/orang',
    ],

];
