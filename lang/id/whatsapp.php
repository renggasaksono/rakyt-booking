<?php

/*
| One home for every prefilled WhatsApp message body (F-008 AC-B1, D4). Button
| labels stay in their page's lang file; only the message bodies live here, so
| wording is editable in one place. The builders in App\WhatsApp\WhatsAppLink
| read bodies only from this namespace. Each move is byte-for-byte identical to
| its pre-move text (AC-B2).
|
| Customer → seller (organization number, via WhatsAppLink::to):
|   seller, customer, trip, trip_dated
| Seller → customer (booking's own customer number, via WhatsAppLink::toNumber):
|   reminder (+ tomorrow fragment), cancelled, rejected, date_reply (reserved).
*/

return [

    // Chat WhatsApp — seller page (F-002 AC-11). Placeholders: :name.
    'seller' => 'Halo :name, saya lihat halaman booking Anda di Rakyt. Mau tanya…',

    // Kabari operator — booking page (F-005 AC-33). Placeholders: :seller :trip :date :n :reference.
    'customer' => 'Halo :seller, saya sudah pesan :trip tanggal :date untuk :n orang. Kode pesanan :reference.',

    // Tanya via WhatsApp — trip page, no departure selected (F-008 AC-A3). Placeholders: :name :trip.
    'trip' => 'Halo :name, saya tertarik dengan :trip. Saya mau tanya beberapa detail sebelum memesan.',

    // Tanya via WhatsApp — trip page, departure selected (F-008 AC-A4). Placeholders: :name :trip :date.
    'trip_dated' => 'Halo :name, saya tertarik dengan :trip keberangkatan :date. Saya mau tanya beberapa detail sebelum memesan.',

    // Ingatkan via WA — reminder (F-007 AC-25). Placeholders: :name :reference :trip :date :n :total :time :url.
    'reminder' => 'Halo :name, pesanan :reference untuk :trip tanggal :date (:n orang) menunggu pembayaran :total. Kuota ditahan sampai :time. Detail pesanan: :url',

    // Reminder :time fragment when the hold releases tomorrow (F-007 AC-25). Placeholder: :time.
    'tomorrow' => 'besok :time',

    // Kabari pelanggan — cancellation (F-007 AC-29). Placeholders: :name :reference :trip :date.
    'cancelled' => 'Halo :name, pesanan :reference untuk :trip tanggal :date dibatalkan. Hubungi kami jika ada pertanyaan.',

    // Kabari pelanggan — rejected proof (F-006 AC-18). Placeholders: :name :reference.
    'rejected' => 'Halo :name, bukti transfer untuk pesanan :reference belum bisa kami terima. Silakan hubungi kami.',

    // Ajukan tanggal — handoff CTA, customer → seller (F-004 AC-3, F-008 AC-B9a). Placeholders: :name :trip.
    'date_request' => 'Halo :name, saya mau ajukan tanggal untuk :trip. Boleh tahu tanggal yang tersedia?',

    // Balas permintaan tanggal — F-004 owns the wording (AC-B9/QB3); reserved only.
    'date_reply' => '',

];
