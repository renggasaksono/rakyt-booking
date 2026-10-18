<?php

namespace Tests\Feature\Onboarding;

use App\Models\AuditEvent;
use App\Models\Trip;
use App\Models\TripPhoto;
use App\Payments\ActivePaymentMethods;
use App\Trips\Contracts\PaymentReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsOnboarding;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-001 D1–D6, E4: "Terbitkan trip" and mockup 4f.
 */
class PublishTest extends TestCase
{
    use BuildsOnboarding, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $seller;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('rakyt.media.public_disk'));
        $this->freezeClock();
        $this->seller = $this->onboardingTenant(['slug' => 'kencana']);
        $this->seller->owner->forceFill(['signup_started_at' => now()->subMinutes(7)->subSeconds(30)])->save();
    }

    private function trip(): Trip
    {
        return Trip::acrossOrganizations()->where('organization_id', $this->seller->id())->orderBy('id')->firstOrFail();
    }

    private function completeSteps2And3(bool $photo = true, array $trip = [], ?array $schedule = null): void
    {
        $this->actingAsTenant($this->seller)->postJson(route('onboarding.trip'), $this->tripInput($trip))->assertOk();

        if ($photo) {
            $this->actingAsTenant($this->seller)->postJson(route('trips.photos.store', $this->trip()), ['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)])->assertCreated();
        }

        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $schedule ?? $this->scheduleInput())->assertRedirect(route('onboarding.payment'));
    }

    private function publish(?array $methods = null)
    {
        return $this->actingAsTenant($this->seller)->post(route('onboarding.payment'), ['intent' => 'publish', 'methods' => $methods ?? [$this->bankBlock()]]);
    }

    public function test_the_real_payment_readiness_is_bound(): void
    {
        $this->assertInstanceOf(ActivePaymentMethods::class, app(PaymentReadiness::class));
    }

    public function test_ac_d2_publishing_sets_the_trip_first_published_at_and_audits_once(): void
    {
        $this->completeSteps2And3();

        $this->publish()->assertRedirect(route('onboarding.done'));

        $this->assertSame('published', $this->trip()->status->value);
        $organization = $this->seller->organization->refresh();
        $this->assertNotNull($organization->first_published_at);
        $this->assertFalse($organization->isOnboarding());

        $event = AuditEvent::acrossOrganizations()->where('action', 'trip.published')->sole();
        $this->assertSame('user', $event->actor_type);
        $this->assertEquals(['status' => 'draft'], $event->before);
        $this->assertEquals(['status' => 'published'], $event->after);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'payment_instructions.changed')->count());

        // A double tap publishes once and keeps the first publish time.
        $first = $organization->first_published_at;
        $this->travel(5)->seconds();
        $this->publish()->assertRedirect();
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'trip.published')->count());
        $this->assertTrue($first->equalTo($this->seller->organization->refresh()->first_published_at));
    }

    public function test_ac_d1_publishing_needs_at_least_one_account(): void
    {
        $this->completeSteps2And3();

        $this->publish([])->assertSessionHasErrors(['methods' => 'Tambahkan minimal satu rekening untuk menerbitkan trip.']);
        $this->assertSame('draft', $this->trip()->status->value);
    }

    public function test_ac_d1_a_missing_cover_photo_sends_the_seller_back_to_step_2_highlighted(): void
    {
        $this->completeSteps2And3(photo: false);

        $this->publish()
            ->assertRedirect(route('onboarding.trip'))
            ->assertSessionHas('highlight', 'cover_photo')
            ->assertSessionHasErrors(['publish' => 'Tambahkan minimal satu foto untuk sampul.']);

        $this->assertSame('draft', $this->trip()->status->value);
        $this->assertNull($this->seller->organization->refresh()->first_published_at);
        $this->assertSame(0, AuditEvent::acrossOrganizations()->where('action', 'trip.published')->count());

        // The account was kept, so after adding a photo publishing works.
        $this->actingAsTenant($this->seller)->get(route('onboarding.trip'))->assertInertia(fn (AssertableInertia $page) => $page->where('highlight', 'cover_photo'));
        $this->actingAsTenant($this->seller)->postJson(route('trips.photos.store', $this->trip()), ['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)])->assertCreated();
        $this->publish()->assertRedirect(route('onboarding.done'));
    }

    public function test_ac_d1_description_is_not_required(): void
    {
        $this->completeSteps2And3();
        $this->assertNull($this->trip()->short_description);

        $this->publish()->assertRedirect(route('onboarding.done'));
    }

    public function test_ac_e4_a_stale_draft_with_a_past_date_is_sent_to_step_3(): void
    {
        $this->completeSteps2And3();
        $this->travelTo(now()->addDays(20));

        $this->publish()
            ->assertRedirect(route('onboarding.schedule'))
            ->assertSessionHas('highlight', 'departure')
            ->assertSessionHasErrors(['publish' => 'Tanggal keberangkatan sudah lewat — pilih tanggal baru.']);

        // Choosing a new date makes a new departure; the past one stays read-only.
        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput(['start_date' => now('Asia/Jakarta')->addDays(10)->toDateString()]))
            ->assertRedirect(route('onboarding.payment'));
        $this->publish()->assertRedirect(route('onboarding.done'));
    }

    public function test_ac_d3_the_free_plan_does_not_limit_publishing(): void
    {
        $this->assertSame('free', $this->seller->organization->plan);
        $this->asTenant($this->seller, fn () => Trip::factory()->count(3)->published()->create());

        $this->completeSteps2And3();
        $this->publish()->assertRedirect(route('onboarding.done'));
    }

    public function test_ac_d4_d6_the_published_screen(): void
    {
        $this->completeSteps2And3();
        $this->publish();

        $this->actingAsTenant($this->seller)->get(route('onboarding.done'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Onboarding/Done')
                ->where('elapsed', ['minutes' => 7, 'seconds' => 30])
                ->where('copy.done.published_in', '✓ Trip terbit · :minutes menit :seconds detik')
                ->where('copy.done.ready', 'Siap terima pesanan.')
                ->where('trip.name', 'Open Trip Karimunjawa 3H2M')
                ->where('trip.from_price', 1_250_000)
                ->where('trip.request_date', false)
                ->where('trip.next_departure', ['start_date' => '2024-10-24', 'seats_left' => 16])
                ->where('link.display', 'rakyt.id/kencana')
                ->where('link.url', 'https://rakyt.id/kencana')
                ->where('copy.done.copy', 'Salin')
                ->where('copy.done.copied', 'Link disalin')
                ->where('copy.done.share', '↗ Bagikan ke WA / IG')
                ->where('copy.done.dashboard', 'Ke dashboard')
                ->where('copy.done.later_details', 'Deskripsi, itinerary per hari & ketentuan')
                ->where('copy.done.later_profile', 'Logo & bio usaha')
                ->where('urls.trip', route('trips.edit', $this->trip()))
                ->where('urls.profile', route('settings.profile'))
                ->where('urls.dashboard', route('dashboard'))
                ->etc());

        $this->assertNotNull(TripPhoto::acrossOrganizations()->first());
    }

    public function test_ac_d4_the_time_is_hidden_after_60_minutes(): void
    {
        $this->seller->owner->forceFill(['signup_started_at' => now()->subMinutes(61)])->save();
        $this->completeSteps2And3();
        $this->publish();

        $this->actingAsTenant($this->seller)->get(route('onboarding.done'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('elapsed', null));
    }

    public function test_ac_d4_request_date_trips_show_ajukan_tanggal(): void
    {
        $this->completeSteps2And3(trip: ['type' => 'private'], schedule: ['base_price' => 2_000_000]);
        $this->publish()->assertRedirect(route('onboarding.done'));

        $this->actingAsTenant($this->seller)->get(route('onboarding.done'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('trip.request_date', true)->where('copy.done.request_date', 'Ajukan tanggal'));
    }

    public function test_ac_d5_share_uses_the_web_share_api_with_a_copy_fallback(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Onboarding/Done.vue'));

        $this->assertStringContainsString('shareOrCopy', $vue);
        $this->assertSame(1, substr_count($vue, 'bg-prada'), 'One gold CTA on 4f.');
    }

    public function test_the_published_screen_needs_a_publish(): void
    {
        $this->actingAsTenant($this->seller)->get(route('onboarding.done'))->assertRedirect(route('onboarding.trip'));
    }
}
