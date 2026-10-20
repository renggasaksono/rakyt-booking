<?php

/*
| F-006 Pengaturan → Pembayaran (seller payment instructions + QRIS, ADR-0018).
| Supersedes F-001's interim "Rekening". Bank / e-wallet field labels still come
| from lang/id/onboarding.php → payment (shared PaymentMethodFields).
*/

return [

    'nav_label' => 'Pembayaran',
    'title' => 'Pembayaran',
    'kicker' => 'Pengaturan',
    'note' => 'Uang langsung masuk ke rekening Anda. Rakyt tidak memegang dana dan tidak memotong biaya.',
    'back' => '← Pengaturan',

    'bank_title' => 'Rekening bank / e-wallet',
    'qris_title' => 'QRIS',
    'qris_hint' => 'Unggah gambar QR dari aplikasi bank atau e-wallet Anda. Pelanggan tinggal memindainya.',
    'qris_upload' => 'Unggah gambar QRIS',
    'qris_replace' => 'Ganti gambar QRIS',
    'qris_remove' => 'Hapus QRIS',
    'qris_preview_alt' => 'Gambar QRIS',

    'save' => 'Simpan',
    'saved' => 'Instruksi pembayaran disimpan.',
    'saving' => 'Menyimpan…',
    'save_failed' => 'Belum tersimpan. Periksa koneksi lalu coba lagi.',

    // ADR-0018 / AC-25: the 24-hour block after account recovery.
    'locked' => 'Demi keamanan, instruksi pembayaran terkunci 24 jam setelah perubahan kata sandi atau email. Coba lagi setelah :time.',

    'errors' => [
        'qris_required' => 'Unggah gambar QRIS.',
        'qris_image' => 'Unggah gambar QRIS (JPG/PNG), maks. 5 MB.',
    ],

    'mail' => [
        'changed_subject' => 'Instruksi pembayaran usaha Anda diubah.',
        'changed_line' => 'Instruksi pembayaran untuk :name baru saja diubah.',
        'changed_warning' => 'Jika ini bukan Anda, segera amankan akun Anda dan hubungi kami.',
    ],

];
