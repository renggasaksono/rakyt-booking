<?php

namespace Tests\Feature\PublicPages;

use Tests\TestCase;

/**
 * F-002 design and front-end rules checked in the source: AC-11, AC-20,
 * AC-27, AC-32, AC-33, AC-36–AC-38. The 360 px layout and Lighthouse (AC-35)
 * are checked on a device.
 */
class PublicPageDesignTest extends TestCase
{
    private const FILES = [
        'pages/Public/Seller', 'pages/Public/Trip',
        'components/public/TripCard', 'components/public/PhotoGallery', 'components/public/PublicFooter',
    ];

    public function test_ac_11_chat_whatsapp_opens_in_a_new_tab_without_referrer(): void
    {
        $this->assertMatchesRegularExpression(
            '/:href="organization\.whatsapp_url"\s+target="_blank"\s+rel="noopener noreferrer"/',
            $this->vue('pages/Public/Seller'),
        );
    }

    public function test_ac_20_unavailable_rows_use_no_opacity_or_lighter_text(): void
    {
        foreach (self::FILES as $file) {
            $vue = $this->vue($file);

            $this->assertStringNotContainsString('opacity', $vue, $file);
            $this->assertDoesNotMatchRegularExpression('/text-(?:secondary|ink)\/\d+/', $vue, "{$file} lightens text.");
        }

        $trip = $this->vue('pages/Public/Trip');
        $this->assertStringContainsString(':aria-disabled="departure.state === \'bookable\' ? undefined : \'true\'"', $trip);
        $this->assertStringContainsString('role="radiogroup"', $trip);
    }

    public function test_ac_27_no_v_html_and_map_links_open_safely(): void
    {
        foreach (self::FILES as $file) {
            $this->assertStringNotContainsString('v-html', $this->vue($file), $file);
        }

        $this->assertMatchesRegularExpression(
            '/:href="trip\.map_url"\s+target="_blank"\s+rel="noopener noreferrer nofollow"/',
            $this->vue('pages/Public/Trip'),
        );
    }

    public function test_ac_32_only_the_first_photo_and_first_two_cards_load_eagerly(): void
    {
        $this->assertStringContainsString(":loading=\"index === 0 ? 'eager' : 'lazy'\"", $this->vue('components/public/PhotoGallery'));
        $this->assertStringContainsString(":loading=\"eager ? 'eager' : 'lazy'\"", $this->vue('components/public/TripCard'));
        $this->assertStringContainsString(':eager="index < 2"', $this->vue('pages/Public/Seller'));

        foreach (['components/public/PhotoGallery', 'components/public/TripCard'] as $file) {
            $vue = $this->vue($file);
            $this->assertStringContainsString(':srcset=', $vue, $file);
            $this->assertStringContainsString('sizes=', $vue, $file);
            $this->assertMatchesRegularExpression('/width="\d+"\s+height="\d+"/', $vue, $file);
        }
    }

    public function test_ac_33_pages_are_resolved_lazily_with_a_budget_check(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString("import.meta.glob('./pages/**/*.vue')", $app);
        $this->assertStringNotContainsString('eager', $app);

        $package = json_decode(file_get_contents(base_path('package.json')), true);
        $this->assertSame('node scripts/check-public-bundle.mjs', $package['scripts']['check:bundle']);
        $this->assertStringContainsString('150 * 1024', file_get_contents(base_path('scripts/check-public-bundle.mjs')));
        $this->assertStringContainsString('npm run check:bundle', file_get_contents(base_path('.github/workflows/ci.yml')));
    }

    public function test_ac_36_phone_rules_type_floor_sticky_padding_and_gallery_controls(): void
    {
        foreach (self::FILES as $file) {
            $vue = $this->vue($file);

            $this->assertDoesNotMatchRegularExpression('/text-\[(?:[0-9]|10)px\]/', $vue, "{$file} uses text below 11 px.");

            // 11 px only for the uppercase kicker.
            preg_match_all('/class="[^"]*text-\[11px\][^"]*"/', $vue, $matches);
            foreach ($matches[0] as $class) {
                $this->assertStringContainsString('uppercase', $class, $file);
            }
        }

        // The sticky bar never covers the last section.
        $this->assertStringContainsString('pb-36', $this->vue('pages/Public/Trip'));

        $gallery = $this->vue('components/public/PhotoGallery');
        $this->assertStringContainsString('snap-x', $gallery);
        $this->assertStringContainsString("t(copy, 'trip.gallery.previous')", $gallery);
        $this->assertStringContainsString("t(copy, 'trip.gallery.next')", $gallery);
    }

    public function test_ac_37_exactly_one_gold_button_per_screen(): void
    {
        $this->assertSame(0, substr_count($this->vue('pages/Public/Seller'), 'bg-prada'));
        $this->assertSame(0, substr_count($this->vue('components/public/TripCard'), 'bg-prada'));

        // Trip page: "Pesan →" or "Ajukan tanggal →", one v-if / v-else pair in the sticky bar.
        $trip = $this->vue('pages/Public/Trip');
        $this->assertSame(2, substr_count($trip, 'bg-prada'));
        $bar = substr($trip, strpos($trip, '<StickyAction>'));
        $this->assertSame(2, substr_count($bar, 'bg-prada'));
        $this->assertMatchesRegularExpression('/<a\s+v-if="scheduled && selected"\s+:href="bookUrl\(urls\.book, selected\.public_id\)"\s+class="[^"]*bg-prada/', $bar);
        // F-004 / ADR-0032: the gold "Ajukan tanggal →" is the WhatsApp handoff (v-else-if,
        // opens a new tab); mutually exclusive with "Pesan →", so still one gold button.
        $this->assertMatchesRegularExpression('/<a\s+v-else-if="urls\.request_date"\s+:href="urls\.request_date"\s+target="_blank"\s+rel="noopener noreferrer"\s+class="[^"]*bg-prada/', $bar);

        // Chat WhatsApp and Bagikan are ink outline — drawn with a box-shadow inset,
        // not gold and not a heavy border (docs/design README → Shape & outlines).
        $this->assertStringContainsString('shadow-[inset_0_0_0_1.5px_#211710]', $this->vue('pages/Public/Seller'));
    }

    public function test_ac_38_every_public_page_ends_with_the_rakyt_footer(): void
    {
        foreach (['pages/Public/Seller', 'pages/Public/Trip'] as $file) {
            $this->assertMatchesRegularExpression('/<PublicFooter\s+:label="t\(copy, \'footer\'\)"\s+:href="urls\.home"/', $this->vue($file), $file);
        }

        $this->assertStringContainsString('text-xs text-secondary', $this->vue('components/public/PublicFooter'));
        $this->assertSame('Dibuat dengan Rakyt', __('public.footer'));
        $this->assertStringNotContainsString('plan', file_get_contents(app_path('PublicPages/PublicPagePresenter.php')));
    }

    private function vue(string $path): string
    {
        return file_get_contents(resource_path("js/{$path}.vue"));
    }
}
