<?php

namespace Tests\Feature\PublicPages;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-002 section A (routing) and E (caching, errors).
 */
class PublicRoutingTest extends TestCase
{
    use BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
    }

    public function test_ac_1_the_seller_page_is_live_from_onboarding_step_1(): void
    {
        $tenant = $this->createTenant();
        $tenant->organization->update(['slug' => 'kencana', 'onboarding_skipped_at' => null, 'first_published_at' => null]);
        $this->assertTrue($tenant->organization->fresh()->isOnboarding());

        $this->get('/kencana')->assertOk();
        $this->assertSame('Kencana Trip', $this->seller('bahari', 'Kencana Trip')->organization->name);
        $this->get('/bahari')->assertOk();
    }

    public function test_ac_2_other_cases_redirect_permanently_to_the_lower_case_url(): void
    {
        $a = $this->seller();
        $this->tripFor($a, ['name' => 'Karimunjawa 3H2M'], published: true);

        $this->get('/Kencana')->assertStatus(301)->assertRedirect('/kencana');
        $this->get('/KENCANA')->assertStatus(301)->assertRedirect('/kencana');
        $this->get('/Kencana/Karimunjawa-3H2M')->assertStatus(301)->assertRedirect('/kencana/karimunjawa-3h2m');
        $this->get('/kencana/KARIMUNJAWA-3h2m?ref=ig')->assertStatus(301)->assertRedirect('/kencana/karimunjawa-3h2m?ref=ig');
        $this->get('/kencana/karimunjawa-3h2m')->assertOk();
    }

    public function test_ac_3_unknown_reserved_and_malformed_slugs_are_the_same_404(): void
    {
        $this->seller();

        $paths = [
            '/tidak-ada',              // unknown
            '/admin',                  // reserved, no app route
            '/demo',                   // reserved
            '/ke--ncana',              // fails the B5 format
            '/-kencana',
            '/kencana_trip',
            '/ab',                     // too short
            '/'.str_repeat('a', 31),   // over 30 characters
            '/kenc%00ana',             // NUL byte
        ];

        $responses = array_map(fn (string $path) => $this->get($path), $paths);
        $first = $responses[0]->assertNotFound();

        foreach ($responses as $index => $response) {
            $response->assertNotFound();
            $this->assertSame($first->getContent(), $response->getContent(), "Body differs for {$paths[$index]}");
            $this->assertSame($this->headerNames($first), $this->headerNames($response), "Headers differ for {$paths[$index]}");
            $this->assertSame($first->headers->get('Cache-Control'), $response->headers->get('Cache-Control'));
        }

        // The same 404 for trip paths under any of them, and for non-UTF-8 bytes.
        $this->get('/tidak-ada/karimunjawa')->assertNotFound();
        $this->get('/admin/karimunjawa')->assertNotFound();
        $this->get('/kencana/kari%00munjawa')->assertNotFound();
        $this->get('/kencana/'.str_repeat('a', 101))->assertNotFound();
        $this->get('/kencana/kari%FFmunjawa')->assertNotFound();
        $this->get('/%FF%FE')->assertNotFound();
    }

    public function test_ac_3_reserved_slugs_never_reach_the_database_lookup(): void
    {
        // Even if a row slipped past the sign-up check (F-001 B7), a reserved slug is a 404.
        $tenant = $this->createTenant();
        Organization::query()->whereKey($tenant->id())->update(['slug' => 'demo']);

        $this->get('/demo')->assertNotFound();
    }

    public function test_ac_3_app_routes_are_not_shadowed(): void
    {
        $this->get('/masuk')->assertOk();
        $this->get('/daftar')->assertOk();
        $this->get('/up')->assertOk();
        $this->get('/syarat')->assertOk();
    }

    public function test_ac_4_a_published_trip_renders_on_its_url(): void
    {
        $a = $this->seller();
        $trip = $this->tripFor($a, ['name' => 'Karimunjawa 3H2M'], published: true);

        $this->assertSame('karimunjawa-3h2m', $trip->slug);
        $this->assertSame('Karimunjawa 3H2M', $this->tripProps('kencana', 'karimunjawa-3h2m')['trip']['name']);
    }

    public function test_ac_9_public_pages_use_the_public_page_limiter(): void
    {
        $this->seller();
        config(['rakyt.rate_limits.public_page.per_ip' => [2, 1]]);

        $this->get('/kencana')->assertOk();
        $this->get('/tidak-ada')->assertNotFound();

        $response = $this->get('/kencana')->assertStatus(429);
        $response->assertSee('Terlalu banyak');
        $response->assertDontSee('Kencana Trip');

        $route = app('router')->getRoutes()->getByName('public.trip');
        $this->assertContains('throttle:public-page', $route->gatherMiddleware());
        $this->assertSame([120, 1], (require config_path('rakyt.php'))['rate_limits']['public_page']['per_ip']);
    }

    public function test_ac_34_public_responses_are_never_cached(): void
    {
        $a = $this->seller();
        $this->tripFor($a, ['name' => 'Karimunjawa'], published: true);

        $this->get('/kencana')->assertHeader('Cache-Control', 'no-cache, private');
        $this->get('/kencana/karimunjawa')->assertHeader('Cache-Control', 'no-cache, private');
        $this->get('/Kencana')->assertHeader('Cache-Control', 'no-cache, private');

        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->inertiaVersion()])
            ->get('/kencana/karimunjawa')
            ->assertOk()
            ->assertHeader('X-Inertia', 'true')
            ->assertHeader('Cache-Control', 'no-cache, private');
    }

    public function test_ac_39_errors_are_the_bahasa_pages_with_a_home_link_and_no_seller_data(): void
    {
        $this->seller();

        $this->get('/kencana/tidak-ada')
            ->assertNotFound()
            ->assertSee(__('errors.404.title'))
            ->assertSee(__('errors.home'))
            ->assertSee('href="'.url('/').'"', false)
            ->assertDontSee('Kencana Trip')
            ->assertDontSee('og:', false);

        // Inertia visits get the Inertia error page (F-009 R7).
        $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->inertiaVersion()])
            ->get('/tidak-ada')
            ->assertNotFound()
            ->assertJsonPath('component', 'Error');
        $this->flushHeaders();

        // The two 404s above used both allowed requests.
        config(['rakyt.rate_limits.public_page.per_ip' => [2, 1]]);
        $this->get('/kencana')
            ->assertStatus(429)
            ->assertSee(__('errors.home'))
            ->assertDontSee('Kencana Trip');

    }

    /**
     * @return list<string>
     */
    private function headerNames(TestResponse $response): array
    {
        $names = array_diff(array_keys($response->headers->all()), ['date']);
        sort($names);

        return array_values($names);
    }

    private function inertiaVersion(): string
    {
        return (string) app(HandleInertiaRequests::class)->version(Request::create('/'));
    }
}
