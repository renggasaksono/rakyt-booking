<?php

// F-017 public landing page. All visible marketing copy (ADR-0007); passed to
// the Home page as the `copy` prop. Nothing Bahasa is hard-coded in the views.
// Adapted from the approved "Sogan Cerah" mockup, with the pilot-waitlist
// framing, fabricated testimonials and specific free-tier limits removed
// (decided 2026-10-23; ADR-0019 — "gratis untuk mulai", no named limits).

return [
    // Server-rendered <head> (AC-15, ADR-0024).
    'head' => [
        'title' => 'Rakyt — booking rapi untuk operator trip',
        'og_title' => 'Rakyt Booking',
        'description' => 'Satu link booking untuk pesanan dari Instagram dan WhatsApp. Atur tanggal dan kursi, '
            .'pantau pembayaran — uangnya langsung masuk ke rekening Anda. Gratis untuk mulai.',
    ],

    'nav' => [
        'brand' => 'Rakyt',
        // The product lockup: "Rakyt | Booking" (AC-6). This defines the product;
        // it sits beside the monogram in the nav and must not be dropped.
        'brand_suffix' => 'Booking',
        'anchors' => [
            ['id' => 'cara-kerja', 'label' => 'Cara kerja'],
            ['id' => 'fitur', 'label' => 'Fitur'],
            ['id' => 'tanya-jawab', 'label' => 'Tanya jawab'],
        ],
        'login' => 'Masuk',
        'cta' => 'Coba gratis',
        // Shown instead of "Coba gratis" to a signed-in seller (AC-3, Q1).
        'cta_authenticated' => 'Buka dashboard',
    ],

    'hero' => [
        'headline' => 'Trip Anda. Pelanggan Anda. Booking jadi rapi.',
        'subheadline' => 'Satu link booking untuk pesanan dari Instagram dan WhatsApp. Atur tanggal dan kursi, '
            .'pantau pembayaran — uangnya langsung masuk ke rekening Anda.',
        'cta' => 'Buat akun gratis',
        'cta_authenticated' => 'Buka dashboard',
        'bullets' => [
            'Trip pertama online ±10 menit',
            'Tanpa potongan per transaksi',
            'Pelanggan tak perlu daftar akun',
        ],
    ],

    // Interactive 3-step product demo (AC-16, AC-17). Logic in
    // resources/js/lib/landingDemo.js; labels here.
    'demo' => [
        'aria' => 'Contoh alur pemesanan',
        'tabs' => ['Lihat trip', 'Pesan', 'Bayar'],
        'replay' => 'Putar ulang',
        'pause' => 'Jeda',
        'play' => 'Putar',
        'seller' => [
            'name' => 'Kencana Trip',
            'tagline' => 'Open trip & private tour · Jepara',
            'link' => 'rakyt.id/kencana',
        ],
        'step1' => [
            'trip_name' => 'Karimunjawa 3H2M',
            'trip_tag' => 'Open Trip',
            'includes' => 'Kapal · Homestay · Makan 6× · Snorkeling',
            'pick' => 'Pilih keberangkatan',
            'departures' => [
                ['date' => 'Kam, 24–26 Okt', 'seats' => 'Sisa 6 kursi', 'state' => 'open'],
                ['date' => 'Kam, 31 Okt – 2 Nov', 'seats' => 'Sisa 12 kursi', 'state' => 'open'],
                ['date' => 'Kam, 7–9 Nov', 'seats' => 'Penuh', 'state' => 'full'],
            ],
            'price' => 'Rp1.250.000',
            'price_note' => '24–26 Okt · per orang',
            'cta' => 'Pesan →',
        ],
        'step2' => [
            'title' => 'Karimunjawa 3H2M · Kam, 24–26 Okt',
            'section' => 'Data pemesan',
            'name_label' => 'Nama lengkap',
            'name_value' => 'Sari Wulandari',
            'wa_label' => 'Nomor WhatsApp',
            'wa_value' => '0812-3456-7890',
            'participants_label' => 'Peserta',
            'participants_value' => '2 orang',
            'note_label' => 'Catatan · opsional',
            'note_value' => 'Vegetarian 1 orang',
            'hold' => 'Kursi ditahan 2 jam setelah pesanan dikirim. Selesaikan transfer dalam waktu itu.',
            'total_label' => 'Total',
            'total_value' => 'Rp2.500.000',
            'cta' => 'Kirim pesanan',
        ],
        'step3' => [
            'title' => 'Pesanan terkirim · RKB-1024-0142',
            'subtitle' => 'Transfer ke operator',
            'status' => 'Menunggu pembayaran',
            'steps' => ['Transfer', 'Dicek', 'Lunas'],
            'bank_label' => 'Bank',
            'bank_value' => 'BCA · 1234567890',
            'holder_label' => 'Atas nama',
            'holder_value' => 'CV Kencana Wisata',
            'amount_label' => 'Jumlah',
            'amount_value' => 'Rp2.500.000',
            'note' => 'Pembayaran langsung ke Kencana Trip. Rakyt tidak memotong biaya transaksi.',
            'countdown_label' => 'Sisa waktu tahan kursi',
            'upload' => 'Unggah bukti transfer',
            'whatsapp' => 'Kabari operator via WhatsApp',
        ],
    ],

    'value' => [
        'kicker' => 'Kenapa Rakyt',
        'headline' => 'Dibuat untuk operator yang sudah punya pelanggan sendiri.',
        'items' => [
            [
                'myth' => 'Dashboard toko online yang ribet',
                'title' => 'Cukup trip, tanggal, dan kursi.',
                'body' => 'Tanpa pelatihan. Ubah harga atau sisa kursi dari HP dalam hitungan detik.',
            ],
            [
                'myth' => 'Potongan per transaksi & biaya tarik dana',
                'title' => 'Pelanggan transfer langsung ke Anda.',
                'body' => 'Rakyt mencatat statusnya, bukan memegang uangnya. Tidak ada saldo yang harus dicairkan.',
            ],
            [
                'myth' => 'Janji trafik marketplace',
                'title' => 'Link Anda, audiens Anda.',
                'body' => 'Taruh di bio IG, status WA, atau balasan chat. Rakyt berguna meski tidak mendatangkan satu pengunjung pun.',
            ],
        ],
    ],

    'how' => [
        'kicker' => 'Cara kerja',
        'headline' => 'Dari daftar sampai link siap dibagikan, ±10 menit.',
        'lede' => 'Hanya isian yang wajib untuk terbit. Itinerary, ketentuan, dan foto tambahan bisa dilengkapi nanti.',
        'steps' => [
            ['n' => '1', 'title' => 'Buat akun', 'body' => 'Daftar dengan email. Gratis, tanpa kartu kredit.'],
            ['n' => '2', 'title' => 'Isi usaha', 'body' => 'Nama usaha, kota, dan link booking Anda.'],
            ['n' => '3', 'title' => 'Tambah trip', 'body' => 'Judul, harga, dan yang termasuk.'],
            ['n' => '4', 'title' => 'Atur jadwal', 'body' => 'Tanggal keberangkatan dan jumlah kursi.'],
            ['n' => '5', 'title' => 'Isi rekening', 'body' => 'Rekening atau e-wallet tujuan transfer pelanggan.'],
        ],
        'loop_kicker' => 'Lalu berputar',
        'loop' => [
            'Bagikan link',
            'Pelanggan pesan',
            'Anda konfirmasi bayar',
            'Buka tanggal berikutnya',
        ],
    ],

    'features' => [
        'kicker' => 'Yang sudah bisa dipakai',
        'seller' => [
            'title' => 'Untuk Anda, operator',
            'items' => [
                [
                    'title' => 'Semua pesanan di satu daftar',
                    'body' => 'Bukan di riwayat chat. Status selalu ditulis dengan kata dan tanda — tidak cuma warna.',
                ],
                [
                    'title' => 'Konfirmasi bayar sekali ketuk',
                    'body' => 'Cek bukti transfer, tandai lunas, selesai. Semua dari HP.',
                ],
                [
                    'title' => 'Ubah harga & kursi kapan saja',
                    'body' => 'Buka atau tutup tanggal, atur sisa kursi tanpa menunggu siapa pun.',
                ],
            ],
        ],
        'customer' => [
            'title' => 'Untuk pelanggan Anda',
            'items' => [
                [
                    'title' => 'Pesan tanpa buat akun',
                    'body' => 'Cukup nama, nomor WhatsApp, dan jumlah peserta.',
                ],
                [
                    'title' => 'Link pesanan sendiri',
                    'body' => 'Untuk melihat status dan mengunggah bukti transfer kapan saja.',
                ],
                [
                    'title' => 'Tetap bisa chat WhatsApp',
                    'body' => 'Tiap pesanan punya tombol WhatsApp dengan pesan yang sudah terisi.',
                ],
            ],
        ],
    ],

    // Use cases (AC-11): shipped trip types; "Guide lokal" is not built yet and
    // is clearly marked "Belum".
    'usecases' => [
        'title' => 'Cocok untuk',
        'items' => [
            ['title' => 'Open trip', 'body' => 'Jadwal tetap, peserta gabung.', 'soon' => false],
            ['title' => 'Private tour', 'body' => 'Pelanggan ajukan tanggal lewat WhatsApp.', 'soon' => false],
            ['title' => 'Day trip & aktivitas', 'body' => 'Snorkeling, hiking, kelas.', 'soon' => false],
            ['title' => 'Guide lokal', 'body' => 'Kuota terbatas per tanggal. Belum ada, menyusul.', 'soon' => true],
        ],
        'soon_label' => 'Belum',
    ],

    'payment' => [
        'kicker' => 'Pembayaran',
        'headline' => 'Uangnya langsung ke rekening Anda.',
        'body' => 'Pelanggan melihat rekening dan nama pemiliknya, transfer, lalu mengunggah bukti. Anda cek, '
            .'tandai lunas, selesai. Rakyt tidak memegang dana dan tidak memotong biaya transaksi.',
        'points' => [
            [
                'title' => 'Kursi ditahan 2 jam',
                'body' => 'Kalau tidak dibayar, kursi kembali terbuka otomatis. Unggah bukti menghentikan jam.',
            ],
            [
                'title' => 'Bukti transfer tersimpan di pesanan',
                'body' => 'Tidak perlu scroll chat untuk mencari foto struk.',
            ],
            [
                'title' => 'Tidak pernah dobel jual kursi',
                'body' => 'Dua pelanggan memesan kursi terakhir bersamaan? Hanya satu yang masuk.',
            ],
        ],
    ],

    'faq' => [
        'kicker' => 'Tanya jawab',
        'headline' => 'Yang sering ditanyakan.',
        'items' => [
            [
                'q' => 'Berapa biayanya?',
                'a' => 'Gratis untuk mulai. Rakyt tidak memungut komisi per pesanan. Paket berbayar akan ada '
                    .'nanti; harganya kami tentukan bersama operator awal.',
            ],
            [
                'q' => 'Uang pelanggan masuk ke mana?',
                'a' => 'Langsung ke rekening bank atau e-wallet Anda. Rakyt hanya mencatat jumlah tagihan, bukti '
                    .'transfer, dan status lunas. Biaya dari bank atau e-wallet Anda tetap mengikuti ketentuan mereka.',
            ],
            [
                'q' => 'Apakah pelanggan harus punya akun?',
                'a' => 'Tidak. Pelanggan cukup isi nama, nomor WhatsApp, dan jumlah peserta. Mereka menerima link '
                    .'pesanan untuk melihat status dan mengunggah bukti transfer.',
            ],
            [
                'q' => 'Bagaimana kalau trip saya private, tanggalnya bebas?',
                'a' => 'Pakai mode ajukan tanggal: pelanggan menghubungi Anda lewat WhatsApp untuk mengusulkan '
                    .'tanggal, dan Anda yang memutuskan menerima atau tidak.',
            ],
            [
                'q' => 'Apakah saya masih bisa pakai WhatsApp?',
                'a' => 'Tentu. Setiap pesanan punya tombol WhatsApp dengan pesan yang sudah terisi, jadi percakapan '
                    .'tetap di WA — tapi datanya rapi di Rakyt.',
            ],
            [
                'q' => 'Bisa pakai domain sendiri atau hapus logo Rakyt?',
                'a' => 'Belum. Domain sendiri dan menghapus logo Rakyt direncanakan untuk paket berbayar nanti.',
            ],
        ],
    ],

    'closing' => [
        'kicker' => 'Mulai sekarang',
        'headline' => 'Trip pertama Anda online minggu ini.',
        'body' => 'Gratis, tanpa kartu kredit. Buat akun, isi usaha, trip, jadwal, dan rekening — lalu bagikan link-nya.',
        'cta' => 'Buat akun gratis',
        'cta_authenticated' => 'Buka dashboard',
    ],

    'footer' => [
        'terms' => 'Syarat',
        'privacy' => 'Privasi',
        // Rakyt's own site carries the brand wordmark, not the "Dibuat dengan
        // Rakyt" attribution badge (that belongs on seller public pages, F-002).
        'brand' => 'Rakyt',
        'company' => 'PT Rakyt Teknologi · Jakarta',
        'email' => 'hai@rakyt.id',
    ],

    // Lightweight opt-in prompt for Google Analytics (AC-20; Consent Mode v2,
    // default-denied). Only shown when analytics is configured and the visitor
    // has not chosen yet.
    'consent' => [
        'message' => 'Kami memakai Google Analytics untuk memahami kunjungan ke halaman ini. Boleh?',
        'accept' => 'Boleh',
        'decline' => 'Tidak',
        'learn' => 'Selengkapnya di Privasi',
    ],
];
