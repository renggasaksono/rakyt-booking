<?php

namespace Tests\Feature\Dashboard;

use Tests\TestCase;

/**
 * F-007 AC-38 design rules checked in the source. The 360 px layout was
 * checked in a browser (scrollWidth = 360 on Hari ini, the list with its
 * filter sheet, and the detail with its cancel sheet); the real-device review
 * happens with F-002/F-003/F-005's.
 */
class DashboardDesignTest extends TestCase
{
    private const FILES = [
        'pages/Dashboard/Today', 'pages/Bookings/Index', 'pages/Bookings/Show',
        'components/BookingRow', 'components/BookingStatusPill', 'components/LinkCard', 'components/SeatBar',
        'layouts/SellerLayout',
    ];

    public function test_ac_38_type_floor_and_token_colours(): void
    {
        foreach (self::FILES as $file) {
            $vue = $this->vue($file);

            $this->assertDoesNotMatchRegularExpression('/text-\[(?:[0-9]|10)px\]/', $vue, "{$file} uses text below 11 px.");
            preg_match_all('/class="[^"]*text-\[11px\][^"]*"/', $vue, $matches);
            foreach ($matches[0] as $class) {
                $this->assertStringContainsString('uppercase', $class, "{$file}: 11 px only for uppercase kickers.");
            }

            // Tints and colours come from tokens, never hex values.
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,6}\b/', $vue, "{$file} hard-codes a colour.");
            $this->assertStringNotContainsString('opacity', $vue, $file);
        }
    }

    public function test_ac_38_one_gold_button_per_screen(): void
    {
        $this->assertSame(1, substr_count($this->vue('pages/Dashboard/Today'), 'bg-prada'));
        $this->assertSame(0, substr_count($this->vue('pages/Bookings/Index'), 'bg-prada'));
        $this->assertSame(0, substr_count($this->vue('pages/Bookings/Show'), 'bg-prada'));

        foreach (['components/BookingRow', 'components/LinkCard', 'components/SeatBar', 'layouts/SellerLayout'] as $file) {
            $this->assertSame(0, substr_count($this->vue($file), 'bg-prada'), $file);
        }

        // 2f: the gold button is the biggest — 48 px on a phone, 44 px on desktop; every other
        // button on Hari ini is 44 px on a phone (tap target) and the 36 px secondary size on desktop.
        $this->assertMatchesRegularExpression('/class="[^"]*\bmin-h-12\b[^"]*bg-prada[^"]*lg:min-h-11/', $this->vue('pages/Dashboard/Today'));
        foreach (['components/BookingRow', 'components/LinkCard'] as $file) {
            preg_match_all('/<(?:button|a)\b[^>]*?\s(?<!:)class="([^"]*(?:ring-inset|bg-ink )[^"]*)"/s', $this->vue($file), $matches);
            $this->assertNotEmpty($matches[1], $file);
            foreach ($matches[1] as $class) {
                $this->assertMatchesRegularExpression('/\bmin-h-11\b.*\blg:min-h-9\b/', $class, "{$file}: a secondary button bigger than 36 px on desktop [{$class}].");
            }
        }

        // AC-13: while open, the quick-edit sheet's "Simpan" is the gold one.
        $this->assertSame(1, substr_count($this->vue('components/QuickEditSheet'), ' bg-prada '));
    }

    public function test_ac_38_tap_targets_status_glyphs_and_copy(): void
    {
        foreach (['pages/Dashboard/Today', 'pages/Bookings/Index', 'pages/Bookings/Show', 'components/LinkCard', 'components/BookingRow'] as $file) {
            $vue = $this->vue($file);

            preg_match_all('/<(?:button|a|Link)\b[^>]*?\s(?<!:)class="([^"]*)"/s', $vue, $matches);
            foreach ($matches[1] as $class) {
                $this->assertMatchesRegularExpression('/\bmin-h-(?:11|12|14)\b/', $class, "{$file}: a tap target below 44 px [{$class}].");
            }

            // No hard-coded Bahasa: copy comes through t() from lang/id.
            foreach (['Batalkan', 'Pesanan', 'Salin', 'Bagikan', 'Kembali', 'Semua'] as $word) {
                $this->assertDoesNotMatchRegularExpression('/>\s*'.$word.'\b/', $vue, "{$file} hard-codes [{$word}].");
            }
        }

        // Every booking status carries its glyph (ADR-0008).
        $this->assertStringContainsString('style.glyph', $this->vue('components/BookingStatusPill'));
        $this->assertStringContainsString('<BookingStatusPill', $this->vue('components/BookingRow'));
        $this->assertStringContainsString('<BookingStatusPill', $this->vue('pages/Bookings/Show'));
    }

    public function test_ac_38_loading_states_and_a_failed_cancel_keeps_the_sheet_open(): void
    {
        $this->assertSame(2, substr_count($this->vue('components/LinkCard'), ':aria-busy='));

        $show = $this->vue('pages/Bookings/Show');
        $this->assertStringContainsString(":aria-busy=\"cancelling ? 'true' : 'false'\"", $show);
        $this->assertStringContainsString('onNetworkError', $show);
        $this->assertStringContainsString("t(copy, 'cancel.failed')", $show);
        $this->assertSame('Belum tersimpan. Periksa koneksi lalu coba lagi.', __('bookings.cancel.failed'));
        // The sheet only closes on success.
        $this->assertStringContainsString('onSuccess: () => (confirmOpen.value = false)', $show);
    }

    private function vue(string $path): string
    {
        return file_get_contents(resource_path("js/{$path}.vue"));
    }
}
