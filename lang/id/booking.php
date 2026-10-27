<?php

/*
| Customer booking form and booking page (F-005, mockups 1c, 1d/2e top half).
*/

return [

    'currency' => 'Rp',

    'footer' => 'Dibuat dengan Rakyt',

    'head' => [
        // No customer data in <head> (AC-36).
        'form_title' => 'Pesan :trip · :name',
        'booking_title' => 'Pesanan Anda · :name',
    ],

    'form' => [
        'back' => 'Kembali ke :trip',
        'kicker' => 'Langkah 2 dari 3',
        'title' => 'Data pemesan',
        'name' => 'Nama lengkap',
        'phone' => 'Nomor WhatsApp',
        'phone_prefix' => '+62',
        'phone_help' => 'Konfirmasi & info keberangkatan dikirim ke sini.',
        'participants' => 'Jumlah peserta',
        'participants_help' => 'Sisa kuota :n',
        'decrease' => 'Kurangi jumlah peserta',
        'increase' => 'Tambah jumlah peserta',
        'notes' => 'Catatan (opsional)',
        'notes_counter' => ':n/:max',
        'line' => ':n × :price',
        'total' => 'Total',
        // Q11: shortened to one line on mobile; the hours come from config('rakyt.booking.hold_minutes') (AC-7).
        'hold' => 'Kuota ditahan :hours jam sampai dibayar, lalu dilepas.',
        'submit' => 'Kirim pesanan',
        'sending' => 'Mengirim…',
        'network_error' => 'Belum terkirim. Periksa koneksi lalu coba lagi.',
        // Q13: one line + the existing /privasi page.
        'privacy' => 'Data Anda hanya diteruskan ke :name untuk mengurus pesanan ini.',
        'privacy_link' => 'Privasi',
        'unavailable' => 'Tanggal ini sudah tidak bisa dipesan.',
        'reasons' => [
            'full' => 'Penuh',
            'closed' => 'Ditutup',
        ],
        'other_date' => 'Pilih tanggal lain →',
    ],

    'errors' => [
        'name' => 'Isi nama lengkap (2–80 huruf).',
        'phone' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.',
        'participants' => 'Minimal 1 peserta.',
        'notes' => 'Catatan maksimal :max karakter.',
        'price_changed' => 'Harga untuk tanggal ini berubah menjadi :price per orang. Periksa total lalu kirim lagi.',
        'seats_left' => 'Sisa kuota tinggal :n. Kurangi jumlah peserta.',
    ],

    'page' => [
        'sent' => 'Pesanan terkirim',
        'reference' => 'Kode pesanan',
        'countdown_label' => 'Sisa waktu bayar',
        'countdown' => [
            'hours' => ':h j :m m',
            'minutes' => ':m m',
            'under_minute' => '< 1 m',
        ],
        'hold_until' => 'Kuota ditahan s.d. :time. Setelah itu kuota dilepas otomatis.',
        'hold_until_tomorrow' => 'Kuota ditahan s.d. besok :time. Setelah itu kuota dilepas otomatis.',
        'expired' => 'Waktu pembayaran habis. Kuota sudah dilepas.',
        'book_again' => 'Pesan lagi',
        'summary' => 'Ringkasan pesanan',
        'meeting' => 'Titik kumpul',
        'name' => 'Nama',
        'phone' => 'Nomor WhatsApp',
        'line' => ':n peserta × :price',
        'total' => 'Total :amount',
        'whatsapp' => 'Kabari operator via WhatsApp',
        // F-016: keep + save the private booking link (there is no account or email).
        'copy_link' => 'Salin tautan pesanan',
        'link_copied' => 'Tautan disalin',
        'link_copy_failed' => 'Gagal menyalin. Salin tautan dari bilah alamat.',
        'link_hint' => 'Simpan tautan ini untuk membuka pesanan Anda lagi — tidak perlu akun.',
        'save_proof' => 'Simpan bukti pesanan',
    ],

    // F-006 customer payment screen (mockups 1d/2e/3e bottom half).
    'payment' => [
        'title' => 'Cara pembayaran',
        'direct_notice' => 'Pembayaran langsung ke :seller. Rakyt tidak memotong biaya transaksi.',
        'no_methods' => 'Operator belum memasang instruksi pembayaran. Hubungi via WhatsApp.',
        'amount_label' => 'Jumlah transfer',
        'account_number_label' => 'Nomor rekening',
        'account_holder_label' => 'Atas nama',
        'account_holder_hint' => 'Harus sama persis dengan buku tabungan.',
        'qris_label' => 'Scan QRIS',
        'qris_alt' => 'Kode QRIS',
        'copy' => 'Salin',
        'copy_number' => 'Salin nomor rekening',
        'copy_amount' => 'Salin jumlah',
        'copied_number' => 'Nomor disalin',
        'copied_amount' => 'Jumlah disalin',
        'steps' => [
            'transfer' => 'Transfer',
            'review' => 'Dicek operator',
            'done' => 'Lunas',
        ],
        'upload_cta' => 'Unggah bukti transfer',
        'replace_cta' => 'Ganti bukti',
        'uploading' => 'Mengunggah…',
        'evidence_label' => 'Bukti transfer',
        'evidence_open' => 'Lihat bukti',
        'under_review' => 'Bukti sudah dikirim. Menunggu operator mengecek.',
        'paid' => 'Pembayaran lunas. Sampai jumpa di :trip.',
        'rejected' => 'Bukti transfer ditolak.',
        'rejected_reason' => 'Alasan: :reason',
        // F-014 AC-6, Q1: the seller is suspended — no payment instructions, no upload. Neutral.
        'paused' => 'Pembayaran sedang ditutup karena penjual ini tidak tersedia di Rakyt untuk sementara. Jangan transfer dulu sampai penjual aktif kembali.',
        'errors' => [
            'evidence' => 'Unggah foto/berkas gambar (JPG/PNG), maks. 5 MB.',
            'expired' => 'Waktu pembayaran sudah habis.',
            'seller_unavailable' => 'Bukti bayar belum bisa diunggah: penjual ini tidak tersedia di Rakyt untuk sementara.',
        ],
    ],

    // Glossary status set; the page adds the glyph (ADR-0008).
    'status' => [
        'awaiting_payment' => 'Menunggu pembayaran',
        'needs_review' => 'Perlu dicek',
        'paid' => 'Lunas',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
        'completed' => 'Selesai',
    ],

];
