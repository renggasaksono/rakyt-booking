<?php

namespace Tests\Feature\Landing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsOnboarding;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-017: the public marketing landing page at `/`. It is the `home` route, not
 * a `/<slug>` fallback; it resolves no tenant and drives the open self-serve
 * sign-up funnel. Analytics is Google Analytics 4, config-gated.
 */
class LandingPageTest extends TestCase
{
    use BuildsOnboarding, InteractsWithTenants, RefreshDatabase;

    public function test_ac_1_a_guest_sees_the_landing_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Home'));
    }

    public function test_ac_2_no_signed_in_seller_data_reaches_the_landing(): void
    {
        // A slug/name that cannot collide with the demo copy on the page.
        $seller = $this->createTenant(['slug' => 'operator-rahasia-9z']);
        $seller->organization->update(['name' => 'Operator Rahasia 9Z']);

        $response = $this->actingAsTenant($seller)->get('/');

        $response
            ->assertOk()
            // The seller navigation and account menu shared props never load here.
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Home')
                ->missing('seller')
                ->missing('account')
                ->where('cta.url', route('dashboard')))
            // Nothing of the organization (name or slug) leaks into the payload.
            ->assertDontSee('Operator Rahasia 9Z')
            ->assertDontSee('operator-rahasia-9z');
    }

    public function test_ac_3_a_signed_in_seller_gets_a_dashboard_cta(): void
    {
        $seller = $this->createTenant();

        $this->actingAsTenant($seller)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('authenticated', true)
                ->where('cta.url', route('dashboard'))
                ->where('cta.label', __('landing.nav.cta_authenticated')));
    }

    public function test_ac_3_an_onboarding_seller_gets_a_resume_cta(): void
    {
        $tenant = $this->onboardingTenant();

        $this->actingAs($tenant->owner)->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('authenticated', true)
                ->where('cta.url', fn (string $url) => str_starts_with(parse_url($url, PHP_URL_PATH), '/mulai')));
    }

    public function test_ac_4_every_primary_cta_points_at_the_open_sign_up(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('authenticated', false)
                ->where('cta.url', route('register'))
                ->where('cta.label', __('landing.nav.cta'))
                ->where('urls.register', route('register'))
                ->where('urls.login', route('login'))
                ->where('urls.terms', route('legal.terms'))
                ->where('urls.privacy', route('legal.privacy')));
    }

    public function test_ac_7_all_copy_comes_from_the_lang_file(): void
    {
        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('copy', __('landing'))
                ->where('copy.hero.headline', 'Trip Anda. Pelanggan Anda. Booking jadi rapi.'));
    }

    public function test_ac_8_and_ac_11_copy_is_honest_about_pricing_and_unbuilt_features(): void
    {
        $copy = __('landing');

        // No named free-tier limit or price (ADR-0019).
        $json = json_encode($copy, JSON_UNESCAPED_UNICODE);
        $this->assertStringNotContainsString('tanpa batas', mb_strtolower((string) $json));
        $this->assertStringContainsString('Gratis untuk mulai', $copy['faq']['items'][0]['a']);

        // No testimonials (AC-10): there is no such section in the copy.
        $this->assertArrayNotHasKey('testimonials', $copy);

        // Custom domain / logo removal are marked as not available yet (AC-11).
        $this->assertStringContainsString('Belum', $copy['faq']['items'][5]['a']);
    }

    public function test_ac_15_the_head_is_server_rendered_for_crawlers(): void
    {
        $this->get('/')
            ->assertSee('<title>'.__('landing.head.title').'</title>', false)
            ->assertSee('<meta property="og:title" content="'.__('landing.head.og_title').'">', false)
            ->assertSee('<meta property="og:description" content="'.__('landing.head.description').'">', false)
            ->assertSee('<meta property="og:url" content="'.route('home').'">', false)
            ->assertSee('<link rel="canonical" href="'.route('home').'">', false);
    }

    public function test_ac_19_analytics_is_off_without_a_measurement_id(): void
    {
        config(['services.google_analytics.measurement_id' => null]);

        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('analytics.measurementId', null))
            ->assertDontSee('googletagmanager');
    }

    public function test_ac_19_analytics_turns_on_when_configured(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('analytics.measurementId', 'G-TEST123'));
    }

    public function test_analytics_stays_off_across_the_signed_in_seller_app(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);
        $seller = $this->createTenant();

        // The funnel ends at onboarding; the dashboard is outside it, so the
        // analytics prop is not even shared there.
        $this->actingAsTenant($seller)->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page->missing('analytics'));
    }

    public function test_ac_21_a_flashed_funnel_event_surfaces_on_a_funnel_page(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $this->withSession(['analytics_event' => 'onboarding_publish'])->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('analytics.event', 'onboarding_publish'));
    }

    public function test_ac_21_registration_flashes_the_sign_up_event(): void
    {
        $this->post(route('register'), ['name' => 'Agus', 'email' => 'agus@example.com', 'password' => 'rahasia123', 'terms' => true])
            ->assertRedirect(route('onboarding.organization'))
            ->assertSessionHas('analytics_event', 'sign_up');
    }

    public function test_ac_21_finishing_the_organization_step_flashes_onboarding_start(): void
    {
        $user = $this->signedUpUser();

        $this->actingAs($user)
            ->post(route('onboarding.organization'), $this->organizationInput())
            ->assertRedirect(route('onboarding.trip'))
            ->assertSessionHas('analytics_event', 'onboarding_start');
    }

    public function test_the_home_route_is_not_a_public_slug_fallback(): void
    {
        // A `/<slug>` page is served by the public controller; `/` is the landing.
        $this->get('/')->assertInertia(fn (AssertableInertia $page) => $page->component('Home'));
        $this->assertSame(route('home'), url('/'));
    }
}
