<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * F-009 A16.
 */
class SecurityHeadersTest extends TestCase
{
    public function test_ac_a16_baseline_headers_are_sent(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_ac_a16_hsts_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->get('/up')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_ac_a16_hsts_on_any_https_response(): void
    {
        $this->get('https://localhost/up')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_ac_a16_customer_pages_send_no_referrer(): void
    {
        Route::middleware(['web', 'no-referrer'])->get('/test/pesanan/{token}', fn () => 'ok');

        $this->get('/test/pesanan/abc')->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_ac_a16_session_cookie_is_secure_httponly_and_lax(): void
    {
        $cookie = collect($this->get('/')->headers->getCookies())
            ->firstWhere(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_every_response_carries_a_request_id(): void
    {
        $this->get('/')->assertHeader('X-Request-Id');
        $this->withHeader('X-Request-Id', 'abc12345-trace')->get('/')->assertHeader('X-Request-Id', 'abc12345-trace');
        $this->withHeader('X-Request-Id', '<script>')->get('/')->assertHeader('X-Request-Id');
    }
}
