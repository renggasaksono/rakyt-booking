<?php

namespace Tests\Feature\WhatsApp;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * F-008 section B: one template home (AC-B1) and the text-identical move of
 * every migrated body (AC-B2). The page-level string assertions live with
 * F-002/F-005/F-006/F-007; this pins the home itself.
 */
class WhatsAppTemplateTest extends TestCase
{
    public function test_ac_b1_whatsapp_php_holds_exactly_the_expected_body_keys(): void
    {
        $keys = array_keys(Lang::get('whatsapp'));
        sort($keys);

        $this->assertSame(
            ['cancelled', 'customer', 'date_reply', 'rejected', 'reminder', 'seller', 'tomorrow', 'trip', 'trip_dated'],
            $keys,
        );
    }

    public function test_ac_b1_the_old_body_locations_are_gone(): void
    {
        // Message bodies no longer live in the page lang files (only labels remain).
        $this->assertFalse(Lang::has('public.whatsapp'));
        $this->assertFalse(Lang::has('booking.whatsapp'));
        $this->assertFalse(Lang::has('bookings.whatsapp'));

        // Button labels stay in their page's lang file.
        $this->assertTrue(Lang::has('public.seller.chat_whatsapp'));
        $this->assertTrue(Lang::has('public.trip.ask_whatsapp'));
        $this->assertTrue(Lang::has('booking.page.whatsapp'));
        $this->assertTrue(Lang::has('bookings.show.remind'));
    }

    public function test_ac_b9_date_reply_is_reserved_but_empty(): void
    {
        $this->assertTrue(Lang::has('whatsapp.date_reply'));
        $this->assertSame('', __('whatsapp.date_reply'));
    }

    public function test_ac_b2_each_migrated_body_is_text_identical(): void
    {
        $this->assertSame('Halo :name, saya lihat halaman booking Anda di Rakyt. Mau tanya…', Lang::get('whatsapp.seller'));
        $this->assertSame('Halo :seller, saya sudah pesan :trip tanggal :date untuk :n orang. Kode pesanan :reference.', Lang::get('whatsapp.customer'));
        $this->assertSame('Halo :name, saya tertarik dengan :trip. Saya mau tanya beberapa detail sebelum memesan.', Lang::get('whatsapp.trip'));
        $this->assertSame('Halo :name, saya tertarik dengan :trip keberangkatan :date. Saya mau tanya beberapa detail sebelum memesan.', Lang::get('whatsapp.trip_dated'));
        $this->assertSame('Halo :name, pesanan :reference untuk :trip tanggal :date (:n orang) menunggu pembayaran :total. Kursi ditahan sampai :time. Detail pesanan: :url', Lang::get('whatsapp.reminder'));
        $this->assertSame('besok :time', Lang::get('whatsapp.tomorrow'));
        $this->assertSame('Halo :name, pesanan :reference untuk :trip tanggal :date dibatalkan. Hubungi kami jika ada pertanyaan.', Lang::get('whatsapp.cancelled'));
        $this->assertSame('Halo :name, bukti transfer untuk pesanan :reference belum bisa kami terima. Silakan hubungi kami.', Lang::get('whatsapp.rejected'));
    }
}
