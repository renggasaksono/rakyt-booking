<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * F-009 R7 (done with F-001): Inertia visits that end in 404/409/429/… get an
 * Inertia error page instead of HTML in Inertia's modal; an expired form goes back.
 */
class InertiaErrorResponseTest extends TestCase
{
    public function test_r7_an_inertia_visit_gets_an_inertia_error_page_with_the_status(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/test/inertia-error/{status}', fn (int $status) => throw new HttpException($status));

        foreach ([404 => 'Halaman tidak ditemukan', 409 => 'Akun perlu dicek', 500 => 'Ada yang tidak beres'] as $status => $title) {
            $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/'))])
                ->get("/test/inertia-error/{$status}")
                ->assertStatus($status)
                ->assertHeader('X-Inertia', 'true')
                ->assertJsonPath('component', 'Error')
                ->assertJsonPath('props.title', $title);
        }
    }

    public function test_r7_plain_requests_keep_the_blade_error_pages(): void
    {
        Route::middleware('web')->get('/test/inertia-error', fn () => throw new HttpException(404));

        $this->get('/test/inertia-error')->assertNotFound()->assertHeaderMissing('X-Inertia')->assertSee('Halaman tidak ditemukan');
    }

    public function test_r7_an_expired_inertia_form_goes_back_with_a_notice(): void
    {
        Route::middleware('web')->post('/test/inertia-form', fn () => 'ok');

        $this->app->instance('env', 'local');
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->withHeaders(['X-Inertia' => 'true'])
            ->from('/masuk')
            ->post('/test/inertia-form', ['_token' => 'expired'])
            ->assertRedirect('/masuk')
            ->assertSessionHas('notice', __('errors.419.message'));
    }

    public function test_r7_the_error_page_keeps_the_rules_of_the_blade_pages(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Error.vue'));

        $this->assertStringNotContainsString('bg-prada', $vue, 'No gold CTA on error pages.');
        $this->assertStringContainsString('max-w-md', $vue);
    }
}
