<?php

namespace Tests\Unit\WhatsApp;

use App\WhatsApp\WhatsAppLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * F-008 AC-B5/AC-B10 (and AC-A6/A7/A9 for the shared guard and single encode):
 * the seller → customer builder toNumber(), the invalid-number guard, and the
 * single server-side rawurlencode. The organization-number method to() is
 * covered end-to-end by the seller/trip/booking page feature tests.
 */
class WhatsAppLinkTest extends TestCase
{
    public function test_ac_b5_to_number_builds_a_digits_only_wa_me_link(): void
    {
        $url = WhatsAppLink::toNumber('+6281234567890', 'Halo');

        $this->assertSame('https://wa.me/6281234567890?text='.rawurlencode('Halo'), $url);
        // Digits only — the leading "+" is dropped.
        $this->assertStringContainsString('wa.me/6281234567890?', $url);
        $this->assertStringNotContainsString('+62', $url);
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function invalidNumbers(): array
    {
        return [
            'null' => [null],
            'backfill dash' => ['-'],
            'empty' => [''],
            'landline' => ['+62217654321'],
            'too short' => ['+62812345'],
            'not indonesian' => ['+14155552671'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_ac_b5_to_number_returns_null_for_an_invalid_number(?string $number): void
    {
        $this->assertNull(WhatsAppLink::toNumber($number, 'Halo'));
    }

    #[DataProvider('invalidNumbers')]
    public function test_ac_b5_chat_number_returns_null_for_an_invalid_number(?string $number): void
    {
        $this->assertNull(WhatsAppLink::chatNumber($number));
    }

    public function test_ac_b5_chat_number_has_no_text_query(): void
    {
        $this->assertSame('https://wa.me/6281234567890', WhatsAppLink::chatNumber('+6281234567890'));
    }

    public function test_ac_b10_the_text_is_rawurlencoded_exactly_once(): void
    {
        // Every reserved and non-Latin character the templates might carry.
        $text = 'Halo A&B? #1 +62 "Trip" é 😀 Kode RKB-KNC/0001';
        $url = WhatsAppLink::toNumber('+6281234567890', $text);
        $encoded = substr($url, strpos($url, '?text=') + 6);

        // Encoded once: decoding once restores the original exactly.
        $this->assertSame($text, rawurldecode($encoded));
        // Reserved characters are escaped (so WhatsApp does not re-parse them)...
        $this->assertStringContainsString('%26', $encoded); // &
        $this->assertStringContainsString('%23', $encoded); // #
        $this->assertStringContainsString('%3F', $encoded); // ?
        // ...and not double-encoded (a double encode would leave a literal "%25").
        $this->assertStringNotContainsString('%25', $encoded);
    }
}
