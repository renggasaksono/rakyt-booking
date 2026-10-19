<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteException;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/**
 * Headings and labels use Poppins (design README, ADR-0020). The Vite fonts
 * helper builds the font files, but only `@fonts` puts the @font-face styles
 * and preload links into the page; without it every heading falls back to Arial.
 */
class FontsTest extends TestCase
{
    use RefreshDatabase;

    private const MARKER = '<!-- vite-fonts -->';

    protected function setUp(): void
    {
        parent::setUp();

        // withoutVite() renders nothing for any Vite method; keep that, but make fonts() visible.
        $this->swap(Vite::class, new class(self::MARKER) extends Vite
        {
            public function __construct(private string $marker) {}

            public function __invoke($entrypoints, $buildDirectory = null)
            {
                return new HtmlString('');
            }

            public function fonts($aliases = null)
            {
                return new HtmlString($this->marker);
            }
        });
    }

    public function test_app_pages_load_the_heading_font_in_the_head(): void
    {
        $html = $this->get('/masuk')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<head>.*'.preg_quote(self::MARKER, '/').'.*<\/head>/s', $html);
    }

    public function test_error_pages_load_the_heading_font_in_the_head(): void
    {
        $html = $this->get('/pengaturan/tidak-ada/sama-sekali')->assertNotFound()->getContent();

        $this->assertMatchesRegularExpression('/<head>.*'.preg_quote(self::MARKER, '/').'.*<\/head>/s', $html);
    }

    public function test_error_pages_still_render_when_the_font_manifest_is_missing(): void
    {
        $this->swap(Vite::class, new class extends Vite
        {
            public function fonts($aliases = null)
            {
                throw new ViteException('Vite fonts manifest not found.');
            }
        });

        $this->get('/pengaturan/tidak-ada/sama-sekali')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan');
    }
}
