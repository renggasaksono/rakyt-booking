<?php

/*
| F-007 Seller booking list `/pesanan` and booking detail — seller UI copy (ADR-0007).
| Status words come from lang/id/booking.php (the customer set).
*/

return [

    'currency' => 'Rp',

    'index' => [
        'title' => 'Pesanan',
        'search' => 'Cari nama, nomor WA, atau kode pesanan',
        'search_submit' => 'Cari',
        'filter' => 'Filter',
        'filter_count' => 'Filter (:n)',
        'filters_title' => 'Filter pesanan',
        'apply' => 'Terapkan',
        'clear' => 'Hapus filter',
        'close' => 'Tutup',
        'remove_filter' => 'Hapus filter :label',
        'status' => 'Status',
        'trip' => 'Trip',
        'all' => 'Semua',
        'all_trips' => 'Semua trip',
        'date' => 'Tanggal berangkat',
        'from' => 'Dari',
        'to' => 'Sampai',
        'departure' => 'Keberangkatan',
        'participants' => ':n org',
        // AC-7: Perlu tindakan rows.
        'hold_until' => 'ditahan s.d. :time',
        'hold_until_tomorrow' => 'ditahan s.d. besok :time',
        'next' => 'Berikutnya',
        'prev' => 'Sebelumnya',
        'empty' => 'Belum ada pesanan.',
        'no_match' => 'Tidak ada pesanan yang cocok.',
    ],

    // AC-18: `completed` is not offered (Q5).
    'filters' => [
        'perlu_tindakan' => 'Perlu tindakan',
        'awaiting_payment' => 'Menunggu pembayaran',
        'needs_review' => 'Perlu dicek',
        'paid' => 'Lunas',
        'rejected' => 'Ditolak',
        'cancelled' => 'Dibatalkan',
    ],

    'time' => [
        'yesterday' => 'Kemarin',
    ],

    'show' => [
        'title' => 'Pesanan :reference',
        'back' => '← Pesanan',
        'back_label' => 'Kembali ke daftar pesanan',
        'created' => 'Dibuat :date',
        'hold_until' => 'Kursi ditahan s.d. :time',
        'hold_until_tomorrow' => 'Kursi ditahan s.d. besok :time',
        'cancel_reasons' => [
            'hold_expired' => 'Waktu pembayaran habis, kursi dilepas otomatis.',
            'seller' => 'Dibatalkan oleh Anda.',
        ],
        'customer' => 'Pemesan',
        'name' => 'Nama',
        'phone' => 'Nomor WhatsApp',
        'notes' => 'Catatan',
        'none' => '—',
        'trip' => 'Trip',
        'meeting' => 'Titik kumpul',
        'payment' => 'Pembayaran',
        'line' => ':n peserta × :price',
        'total' => 'Total :amount',
        'copy_link' => 'Salin link pesanan pelanggan',
        'copied' => 'Link disalin',
        'copy_failed' => 'Link belum tersalin. Coba lagi.',
        'chat' => 'Chat WhatsApp',
        'remind' => 'Ingatkan via WA',
        'notify_cancelled' => 'Kabari pelanggan via WA',
        'inactive' => 'Pesanan ini sudah tidak aktif.',
    ],

    'cancel' => [
        'action' => 'Batalkan pesanan',
        'title' => 'Batalkan pesanan :reference?',
        'seats' => ':n kursi di keberangkatan :date akan dibuka lagi.',
        'no_notice' => 'Pelanggan tidak diberi tahu otomatis. Kabari lewat WhatsApp.',
        'paid' => 'Pesanan ini sudah lunas. Pengembalian dana diurus langsung antara Anda dan pelanggan.',
        'confirm' => 'Batalkan pesanan',
        'back' => 'Kembali',
        'close' => 'Tutup',
        'sending' => 'Membatalkan…',
        'failed' => 'Belum tersimpan. Periksa koneksi lalu coba lagi.',
        'done' => 'Pesanan dibatalkan. :n kursi dibuka lagi.',
    ],

    'whatsapp' => [
        // F-008 moves these into its template set.
        'reminder' => 'Halo :name, pesanan :reference untuk :trip tanggal :date (:n orang) menunggu pembayaran :total. Kursi ditahan sampai :time. Detail pesanan: :url',
        'cancelled' => 'Halo :name, pesanan :reference untuk :trip tanggal :date dibatalkan. Hubungi kami jika ada pertanyaan.',
        'tomorrow' => 'besok :time',
    ],

];
