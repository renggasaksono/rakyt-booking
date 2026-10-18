<?php

/*
| Seller account: sign-up, sign-in, password reset, email verification and
| Pengaturan → Akun (F-001 section A, ADR-0012).
*/

return [
    'brand' => 'Rakyt',

    'register' => [
        'title' => 'Daftar',
        'headline' => 'Trip pertama Anda online dalam 10 menit.',
        'subline' => 'Buat halaman booking, atur jadwal, dan terima pesanan lewat satu link.',
        'name' => 'Nama Anda',
        'name_placeholder' => 'mis. Agus Santoso',
        'email' => 'Email',
        'email_placeholder' => 'nama@email.com',
        'password' => 'Password',
        'password_hint' => 'Minimal 8 karakter',
        'terms_before' => 'Saya setuju dengan',
        'terms' => 'Syarat',
        'terms_and' => '&',
        'privacy' => 'Privasi',
        'free' => 'Gratis untuk mulai.',
        'submit' => 'Daftar',
        'have_account' => 'Sudah punya akun?',
        'login' => 'Masuk',
        'email_taken' => 'Email ini sudah terdaftar. Masuk atau atur ulang password.',
        'email_taken_login' => 'Masuk',
        'email_taken_reset' => 'Atur ulang password',
        'terms_required' => 'Centang persetujuan Syarat & Privasi untuk lanjut.',
    ],

    'login' => [
        'title' => 'Masuk',
        'headline' => 'Masuk ke Rakyt',
        'email' => 'Email',
        'password' => 'Password',
        'remember' => 'Ingat saya',
        'submit' => 'Masuk',
        'forgot' => 'Lupa password?',
        'no_account' => 'Belum punya akun?',
        'register' => 'Daftar',
    ],

    'password_toggle' => [
        'show' => 'Lihat',
        'hide' => 'Sembunyikan',
        'show_label' => 'Tampilkan password',
        'hide_label' => 'Sembunyikan password',
    ],

    'forgot' => [
        'title' => 'Lupa password',
        'headline' => 'Atur ulang password',
        'intro' => 'Masukkan email akun Anda. Kami kirim link untuk membuat password baru.',
        'email' => 'Email',
        'submit' => 'Kirim link',
        'sent' => 'Jika email ini terdaftar, kami kirim link untuk atur ulang password.',
        'back' => 'Kembali ke halaman masuk',
    ],

    'reset' => [
        'title' => 'Password baru',
        'headline' => 'Buat password baru',
        'email' => 'Email',
        'password' => 'Password baru',
        'password_hint' => 'Minimal 8 karakter',
        'submit' => 'Simpan password',
        'invalid' => 'Link sudah tidak berlaku. Minta link baru.',
        'request_new' => 'Minta link baru',
        'done' => 'Password Anda sudah diganti.',
    ],

    'verify' => [
        'banner' => 'Cek email Anda untuk verifikasi alamat email.',
        'resend' => 'Kirim ulang',
        'resent' => 'Email verifikasi sudah dikirim ulang.',
        'dismiss' => 'Tutup',
        'title' => 'Verifikasi email',
        'verified' => 'Email Anda sudah terverifikasi.',
        'invalid' => 'Link verifikasi sudah tidak berlaku. Masuk lalu pilih "Kirim ulang" untuk link baru.',
        'continue' => 'Lanjut ke Rakyt',
    ],

    'settings' => [
        'title' => 'Akun',
        'kicker' => 'Pengaturan',
        'back' => '← Kembali',
        'email_section' => 'Email untuk masuk',
        'current_email' => 'Email sekarang',
        'unverified' => 'Belum terverifikasi',
        'verified' => '✓ Terverifikasi',
        'new_email' => 'Email baru',
        'current_password' => 'Password sekarang',
        'save_email' => 'Ganti email',
        'email_changed' => 'Email diganti. Cek email baru Anda untuk verifikasi.',
        'password_section' => 'Ganti password',
        'new_password' => 'Password baru',
        'save_password' => 'Simpan password',
        'password_changed' => 'Password diganti. Perangkat lain sudah dikeluarkan.',
        'logout_section' => 'Keluar',
        'logout' => 'Keluar',
        'wrong_password' => 'Password salah.',
        'same_email' => 'Email baru sama dengan email sekarang.',
    ],

    'menu' => [
        'account' => 'Akun',
        'logout' => 'Keluar',
    ],

    'mail' => [
        'greeting' => 'Halo :name,',
        'salutation' => "Salam,\nTim Rakyt",
        'verify_subject' => 'Verifikasi email Anda',
        'verify_line' => 'Klik tombol di bawah untuk memastikan email ini milik Anda.',
        'verify_action' => 'Verifikasi email',
        'verify_expire' => 'Link berlaku :minutes menit.',
        'verify_ignore' => 'Jika Anda tidak mendaftar di Rakyt, abaikan email ini.',
        'reset_subject' => 'Atur ulang password Rakyt',
        'reset_line' => 'Kami menerima permintaan untuk mengatur ulang password akun Rakyt Anda.',
        'reset_action' => 'Buat password baru',
        'reset_expire' => 'Link berlaku :minutes menit dan hanya bisa dipakai sekali.',
        'reset_ignore' => 'Jika Anda tidak meminta ini, abaikan email ini. Password Anda tidak berubah.',
        'email_changed_subject' => 'Email akun Rakyt Anda diganti',
        'email_changed_line' => 'Email akun Rakyt Anda diganti ke :email.',
        'email_changed_warning' => 'Jika bukan Anda yang menggantinya, segera hubungi Rakyt lewat WhatsApp.',
    ],

    'legal' => [
        'terms_title' => 'Syarat penggunaan',
        'privacy_title' => 'Kebijakan privasi',
        'placeholder' => 'Teks lengkap sedang disiapkan dan akan tersedia sebelum Rakyt dibuka untuk umum.',
        'back' => 'Kembali',
    ],
];
