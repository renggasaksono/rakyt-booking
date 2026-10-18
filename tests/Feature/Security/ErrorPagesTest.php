<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * F-009 A17: Bahasa error pages, phone width, ink-outline only, no stack traces.
 */
class ErrorPagesTest extends TestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function pages(): array
    {
        return [
            '404' => [404, 'Halaman tidak ditemukan'],
            '419' => [419, 'Sesi Anda sudah habis'],
            '429' => [429, 'Terlalu banyak percobaan'],
            '500' => [500, 'Ada yang tidak beres'],
            '503' => [503, 'Sedang ada perbaikan'],
        ];
    }

    #[DataProvider('pages')]
    public function test_ac_a17_error_page_is_bahasa_mobile_friendly_and_has_no_gold_cta(int $status, string $title): void
    {
        config(['app.debug' => false]);

        Route::get('/test/error', fn () => match ($status) {
            500 => throw new RuntimeException('SQLSTATE secret detail in /var/www/app.php'),
            default => throw new HttpException($status),
        });

        $response = $this->get('/test/error')->assertStatus($status);
        $html = $response->getContent();

        $response->assertSee($title)->assertSee('lang="id"', false);
        $this->assertStringContainsString('name="viewport" content="width=device-width, initial-scale=1"', $html);
        $this->assertStringNotContainsStringIgnoringCase('#E5A823', $html, 'No gold CTA on error pages.');
        $this->assertStringNotContainsString('SQLSTATE', $html);
        $this->assertStringNotContainsString('/var/www', $html);
        $this->assertDoesNotMatchRegularExpression('/(?<!max-)width:\s*(3[6-9]\d|[4-9]\d\d|\d{4,})px/', $html, 'No fixed width wider than 360px.');
    }

    public function test_ac_a17_expired_csrf_token_shows_the_419_page(): void
    {
        Route::middleware('web')->post('/test/form', fn () => 'ok');

        // The CSRF check is skipped in unit tests unless we opt back in.
        $this->app->instance('env', 'local');
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->post('/test/form')->assertStatus(419)->assertSee('Sesi Anda sudah habis');
    }

    public function test_back_links_never_point_to_another_site(): void
    {
        config(['app.debug' => false]);
        Route::get('/test/error', fn () => throw new HttpException(429));

        $this->withHeader('Referer', 'https://evil.example/phish')
            ->get('/test/error')
            ->assertStatus(429)
            ->assertDontSee('evil.example')
            ->assertSee('href="'.url('/').'"', false);
    }
}
