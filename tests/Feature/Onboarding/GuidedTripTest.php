<?php

namespace Tests\Feature\Onboarding;

use App\Models\PaymentMethod;
use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
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
 * F-001 C1–C13: steps 2–4 (mockups 4c–4e). The clock is Thursday 2024-10-10 10:00 WIB.
 */
class GuidedTripTest extends TestCase
{
    use BuildsOnboarding, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $seller;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('rakyt.media.public_disk'));
        $this->freezeClock();
        $this->seller = $this->onboardingTenant();
    }

    private function onboardingTrip(): ?Trip
    {
        return Trip::acrossOrganizations()->where('organization_id', $this->seller->id())->orderBy('id')->first();
    }

    private function saveTrip(array $overrides = []): Trip
    {
        $this->actingAsTenant($this->seller)->postJson(route('onboarding.trip'), $this->tripInput($overrides))->assertOk();

        return $this->onboardingTrip();
    }

    public function test_ac_c1_each_step_header_shows_the_step_and_minutes_left(): void
    {
        $this->assertSame([1 => 9, 2 => 8, 3 => 4, 4 => 2], config('rakyt.onboarding.minutes_left'));
        $this->assertSame('Langkah :n dari 4 · :name', __('onboarding.step'));
        $this->assertSame('±:m menit lagi', __('onboarding.minutes_left'));

        $this->actingAsTenant($this->seller)->get(route('onboarding.trip'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('header', ['step' => 2, 'name' => 'Trip', 'minutes_left' => 8]));

        $trip = $this->saveTrip();
        $this->actingAsTenant($this->seller)->get(route('onboarding.schedule'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('header', ['step' => 3, 'name' => 'Jadwal', 'minutes_left' => 4]));

        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput());
        $this->actingAsTenant($this->seller)->get(route('onboarding.payment'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('header', ['step' => 4, 'name' => 'Pembayaran', 'minutes_left' => 2]));

        // Sign-up / sign-in carry no step header.
        auth()->logout();
        $this->get(route('register'))->assertInertia(fn (AssertableInertia $page) => $page->missing('header'));
        $this->get(route('login'))->assertInertia(fn (AssertableInertia $page) => $page->missing('header'));
    }

    public function test_ac_c2_trip_types_preset_booking_mode_and_duration(): void
    {
        $this->actingAsTenant($this->seller)->get(route('onboarding.trip'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('copy.trip.types.open_trip.hint', 'Jadwal tetap, gabung')
                ->where('copy.trip.types.private.hint', 'Pelanggan ajukan tanggal')
                ->where('copy.trip.types.day_trip.hint', 'Beberapa jam / 1 hari')
                ->where('options.types.0', ['value' => 'open_trip', 'booking_mode' => 'scheduled', 'duration' => ['duration_label' => 'days', 'duration_days' => 3, 'duration_nights' => 2]])
                ->where('options.types.1', ['value' => 'private', 'booking_mode' => 'request_date', 'duration' => null])
                ->where('options.types.2', ['value' => 'day_trip', 'booking_mode' => 'scheduled', 'duration' => ['duration_label' => 'days', 'duration_days' => 1, 'duration_nights' => 0]]));

        $this->assertSame('scheduled', $this->saveTrip(['type' => 'open_trip'])->booking_mode->value);
        $this->assertSame('request_date', $this->saveTrip(['type' => 'private'])->booking_mode->value);
        $this->assertSame('scheduled', $this->saveTrip(['type' => 'day_trip', 'duration_days' => 1, 'duration_nights' => 0])->booking_mode->value);

        $this->actingAsTenant($this->seller)->postJson(route('onboarding.trip'), $this->tripInput(['type' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type' => 'Pilih jenis trip.']);
    }

    public function test_ac_c4_name_duration_and_inclusions_are_validated_and_saved_as_a_draft(): void
    {
        $this->actingAsTenant($this->seller)->postJson(route('onboarding.trip'), $this->tripInput(['name' => 'ab', 'duration_label' => null, 'inclusions' => [str_repeat('x', 41)]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'duration_label', 'inclusions.0']);
        $this->assertNull($this->onboardingTrip());

        $this->actingAsTenant($this->seller)->postJson(route('onboarding.trip'), $this->tripInput(['inclusions' => ['Transportasi', ' Snorkel  ', '']]))
            ->assertOk()
            ->assertJsonPath('urls.next', route('onboarding.schedule'));

        $trip = $this->onboardingTrip();
        $this->assertSame('draft', $trip->status->value);
        $this->assertSame('Open Trip Karimunjawa 3H2M', $trip->name);
        $this->assertSame(['Transportasi', 'Snorkel'], $trip->inclusions);
        $this->assertSame([3, 2], [$trip->duration_days, $trip->duration_nights]);
        $this->assertNull($trip->short_description);

        // A second "Lanjut" updates the same trip.
        $this->saveTrip(['name' => 'Karimunjawa Hemat']);
        $this->assertSame(1, Trip::acrossOrganizations()->count());
        $this->assertSame('Karimunjawa Hemat', $this->onboardingTrip()->name);

        // Q7: generic chips; no description field.
        $this->actingAsTenant($this->seller)->get(route('onboarding.trip'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('options.inclusion_suggestions', ['Transportasi', 'Penginapan', 'Makan', 'Guide', 'Tiket masuk', 'Dokumentasi'])
                ->where('trip.name', 'Karimunjawa Hemat')
                ->missing('trip.short_description'));
        $this->assertStringNotContainsString('short_description', file_get_contents(resource_path('js/pages/Onboarding/Trip.vue')));
    }

    public function test_ac_c3_photos_upload_to_the_draft_under_the_organization_prefix_first_is_the_cover(): void
    {
        $this->actingAsTenant($this->seller)->postJson(route('onboarding.trip'), $this->tripInput())
            ->assertOk()
            ->assertJsonStructure(['urls' => ['photos', 'photos_reorder', 'next']]);
        $trip = $this->onboardingTrip();

        $first = $this->actingAsTenant($this->seller)->postJson(route('trips.photos.store', $trip), ['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)])->assertCreated();
        $this->actingAsTenant($this->seller)->postJson(route('trips.photos.store', $trip), ['photo' => UploadedFile::fake()->image('b.jpg', 800, 600)])->assertCreated();

        $photos = $trip->photos()->get();
        $this->assertCount(2, $photos);
        $this->assertSame($first->json('photo_id'), $photos->first()->public_id);
        $this->assertStringStartsWith('org/'.$this->seller->organization->public_id.'/', $photos->first()->path);

        // F-003's type and size limits apply.
        $this->actingAsTenant($this->seller)->postJson(route('trips.photos.store', $trip), ['photo' => UploadedFile::fake()->create('menu.pdf', 10, 'application/pdf')])
            ->assertUnprocessable();

        $this->actingAsTenant($this->seller)->get(route('onboarding.trip'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('trip.photos', 2)
                ->where('urls.photos', route('trips.photos.store', $trip))
                ->where('tripCopy.photos.add', '+ Galeri'));
    }

    public function test_ac_c5_c7_scheduled_trips_get_a_price_and_one_departure(): void
    {
        $trip = $this->saveTrip();

        $this->actingAsTenant($this->seller)->get(route('onboarding.schedule'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Onboarding/Schedule')
                ->where('trip.booking_mode', 'scheduled')
                ->where('context.default_deadline_preset', 'h3')
                ->where('options.limits.seats_max', 500)
                ->where('copy.schedule.seats_hint', 'Jumlah peserta maksimal untuk keberangkatan ini.')
                ->where('copy.schedule.end_hint', 'Tanggal selesai otomatis dari durasi :duration.'));

        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput())
            ->assertRedirect(route('onboarding.payment'));

        $trip->refresh();
        $this->assertSame(1_250_000, $trip->base_price);
        $departure = TripDeparture::acrossOrganizations()->sole();
        $this->assertSame('2024-10-24', $departure->start_date->toDateString());
        $this->assertSame('2024-10-26', $departure->end_date->toDateString());
        $this->assertSame(16, $departure->capacity);
        $this->assertNull($departure->price_override);
        $this->assertSame(0, $departure->seats_taken);
        $this->assertSame('open', $departure->status->value);
        // H-3: Senin 21 Okt 23.59 WIB.
        $this->assertTrue($departure->booking_deadline_at->equalTo(CarbonImmutable::parse('2024-10-21 23:59:59', 'Asia/Jakarta')));

        // Going back and saving again edits the same departure.
        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput(['capacity' => 20]))->assertRedirect(route('onboarding.payment'));
        $this->assertSame(20, TripDeparture::acrossOrganizations()->sole()->capacity);
    }

    public function test_ac_c5_price_date_and_seat_limits(): void
    {
        $this->saveTrip();
        $post = fn (array $overrides) => $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput($overrides));

        $post(['base_price' => 999])->assertSessionHasErrors('base_price');
        $post(['base_price' => 100_000_001])->assertSessionHasErrors('base_price');
        $post(['capacity' => 0])->assertSessionHasErrors('capacity');
        $post(['capacity' => 501])->assertSessionHasErrors(['capacity' => 'Jumlah kursi 1–500.']);
        $post(['start_date' => '2024-10-09'])->assertSessionHasErrors('start_date');
        $post(['start_date' => ''])->assertSessionHasErrors('start_date');

        $this->assertSame(0, TripDeparture::acrossOrganizations()->count());

        $post(['base_price' => 1_000, 'capacity' => 500, 'start_date' => '2024-10-15', 'booking_deadline_preset' => 'h1'])->assertSessionHasNoErrors();
    }

    public function test_ac_c6_deadline_in_the_past_or_after_the_start_is_refused(): void
    {
        $this->saveTrip();
        $post = fn (array $overrides) => $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput($overrides));

        // Departure in 2 days: H-3 would be in the past.
        $post(['start_date' => '2024-10-12', 'booking_deadline_preset' => 'h3'])->assertSessionHasErrors('booking_deadline_preset');
        $post(['start_date' => '2024-10-24', 'booking_deadline_preset' => 'custom', 'booking_deadline_at' => '2024-10-25 10:00'])->assertSessionHasErrors('booking_deadline_at');
        $post(['start_date' => '2024-10-24', 'booking_deadline_preset' => 'custom', 'booking_deadline_at' => '2024-10-09 10:00'])->assertSessionHasErrors('booking_deadline_at');

        // The nearest valid preset is fine.
        $post(['start_date' => '2024-10-12', 'booking_deadline_preset' => 'h1'])->assertSessionHasNoErrors();
    }

    public function test_ac_c6_the_default_comes_from_the_organization(): void
    {
        $this->seller->organization->update(['default_booking_deadline_preset' => 'h7']);
        $this->saveTrip();

        $this->actingAsTenant($this->seller)->get(route('onboarding.schedule'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('context.default_deadline_preset', 'h7'));
    }

    public function test_ac_c8_request_date_trips_only_ask_for_a_price_and_get_no_departure(): void
    {
        $this->saveTrip(['type' => 'private', 'duration_label' => 'days', 'duration_days' => 2, 'duration_nights' => 1]);

        $this->actingAsTenant($this->seller)->get(route('onboarding.schedule'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('trip.booking_mode', 'request_date')
                ->where('header.name', 'Jadwal')
                ->where('copy.schedule.request_hint', 'Pelanggan mengajukan tanggal; Anda membalas dari dashboard.'));

        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), ['base_price' => 2_500_000])
            ->assertRedirect(route('onboarding.payment'));

        $this->assertSame(2_500_000, $this->onboardingTrip()->base_price);
        $this->assertSame(0, TripDeparture::acrossOrganizations()->count());
    }

    public function test_ac_c9_no_extra_dates_in_onboarding(): void
    {
        $this->assertStringNotContainsString('Tambah tanggal lain', file_get_contents(resource_path('js/pages/Onboarding/Schedule.vue')));
        $this->assertStringNotContainsString('Tambah tanggal lain', json_encode(__('onboarding')));
    }

    public function test_ac_c10_c11_payment_accounts_are_saved_for_the_organization(): void
    {
        $this->saveTrip();
        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput());

        $this->actingAsTenant($this->seller)->get(route('onboarding.payment'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Onboarding/Payment')
                ->where('copy.payment.title', 'Ke mana pelanggan transfer?')
                ->where('copy.payment.note', 'Uang langsung masuk ke rekening Anda. Rakyt tidak memegang dana dan tidak memotong biaya.')
                ->where('copy.payment.account_number_ewallet', 'Nomor HP / akun')
                ->where('copy.payment.account_holder_hint', 'Harus sama persis dengan buku tabungan. Ditampilkan ke pelanggan.')
                ->where('copy.payment.add', '+ Tambah rekening lain')
                ->where('limits.methods', 5)
                ->where('providers.0', ['value' => 'bca', 'label' => 'BCA', 'type' => 'bank', 'asks_name' => false])
                ->has('providers', 18));

        $this->withPaymentMethod(false);
        $this->actingAsTenant($this->seller)->post(route('onboarding.payment'), ['intent' => 'draft', 'methods' => [
            $this->bankBlock(),
            $this->bankBlock(['provider' => 'gopay', 'account_number' => '0812-7788-1020', 'account_holder' => 'Agus']),
            $this->bankBlock(['provider' => 'bpd', 'provider_name' => 'Bank Jateng', 'account_number' => '12345']),
        ]]);

        $methods = PaymentMethod::acrossOrganizations()->orderBy('position')->get();
        $this->assertCount(3, $methods);
        $this->assertSame(['bank', 'ewallet', 'bank'], $methods->map(fn ($m) => $m->type->value)->all());
        $this->assertSame('0123456789', $methods[0]->account_number);
        $this->assertSame('081277881020', $methods[1]->account_number);
        $this->assertSame('Bank Jateng', $methods[2]->providerLabel());
        $this->assertTrue($methods->every(fn ($m) => $m->organization_id === $this->seller->id()));
        $this->assertDatabaseMissing('payment_methods', ['organization_id' => null]);
    }

    public function test_ac_c10_account_fields_are_validated(): void
    {
        $this->saveTrip();
        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput());
        $post = fn (array $block) => $this->actingAsTenant($this->seller)->post(route('onboarding.payment'), ['intent' => 'publish', 'methods' => [$block]]);

        $post($this->bankBlock(['account_number' => '1234']))->assertSessionHasErrors(['methods.0.account_number' => 'Isi 5–20 angka.']);
        $post($this->bankBlock(['account_number' => str_repeat('1', 21)]))->assertSessionHasErrors('methods.0.account_number');
        $post($this->bankBlock(['account_number' => '12345abc']))->assertSessionHasErrors('methods.0.account_number');
        $post($this->bankBlock(['account_holder' => 'A']))->assertSessionHasErrors('methods.0.account_holder');
        $post($this->bankBlock(['account_holder' => str_repeat('a', 101)]))->assertSessionHasErrors('methods.0.account_holder');
        $post($this->bankBlock(['provider' => 'paypal']))->assertSessionHasErrors('methods.0.provider');
        $post($this->bankBlock(['provider' => 'other']))->assertSessionHasErrors('methods.0.provider_name');

        $this->actingAsTenant($this->seller)->post(route('onboarding.payment'), ['intent' => 'publish', 'methods' => array_fill(0, 6, $this->bankBlock())])
            ->assertSessionHasErrors('methods');

        $this->assertSame(0, PaymentMethod::acrossOrganizations()->count());
    }

    public function test_ac_c12_draft_needs_no_account_keeps_valid_ones_and_ends_the_guided_flow(): void
    {
        $this->saveTrip();
        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput());

        $this->actingAsTenant($this->seller)->post(route('onboarding.payment'), ['intent' => 'draft', 'methods' => [
            $this->bankBlock(),
            $this->bankBlock(['account_number' => '12']),
            ['provider' => '', 'account_number' => '', 'account_holder' => ''],
        ]])->assertRedirect(route('dashboard'))->assertSessionHas('toast');

        $this->assertSame(1, PaymentMethod::acrossOrganizations()->count());
        $this->assertSame('draft', $this->onboardingTrip()->status->value);
        $this->assertNotNull($this->seller->organization->refresh()->onboarding_skipped_at);
        $this->assertNull($this->seller->organization->first_published_at);

        // A draft with no account at all is fine too.
        $other = $this->onboardingTenant();
        $this->asTenant($other, fn () => Trip::factory()->create(['type' => 'private', 'booking_mode' => 'request_date']));
        $this->actingAsTenant($other)->post(route('onboarding.payment'), ['intent' => 'draft', 'methods' => []])->assertRedirect(route('dashboard'));
    }

    public function test_ac_c12_a_draft_never_removes_a_saved_account_whose_edit_is_invalid(): void
    {
        $this->saveTrip();
        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput());

        // "Terbitkan trip" saves the accounts, then bounces to step 2 for the missing cover (D1).
        $this->actingAsTenant($this->seller)->post(route('onboarding.payment'), ['intent' => 'publish', 'methods' => [
            $this->bankBlock(['account_number' => '1111111111']),
            $this->bankBlock(['provider' => 'bri', 'account_number' => '2222222222']),
        ]])->assertRedirect(route('onboarding.trip'));
        [$bca, $bri] = PaymentMethod::acrossOrganizations()->orderBy('position')->get()->all();

        // Back on step 4 the seller mistypes BCA's number (too short), fixes BRI, and saves a draft.
        $this->actingAsTenant($this->seller)->post(route('onboarding.payment'), ['intent' => 'draft', 'methods' => [
            ['id' => $bca->public_id, ...$this->bankBlock(['account_number' => '12'])],
            ['id' => $bri->public_id, ...$this->bankBlock(['provider' => 'bri', 'account_number' => '3333333333'])],
        ]])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('payment_methods', ['id' => $bca->id, 'account_number' => '1111111111']);
        $this->assertDatabaseHas('payment_methods', ['id' => $bri->id, 'account_number' => '3333333333']);
        $this->assertSame(2, PaymentMethod::acrossOrganizations()->count());
    }

    public function test_ac_c13_every_step_has_a_back_control_with_saved_values(): void
    {
        $this->saveTrip();
        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput());

        $this->actingAsTenant($this->seller)->get(route('onboarding.trip'))->assertInertia(fn (AssertableInertia $page) => $page->where('urls.back', route('onboarding.organization')));
        $this->actingAsTenant($this->seller)->get(route('onboarding.schedule'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('urls.back', route('onboarding.trip'))
            ->where('trip.base_price', 1_250_000)
            ->where('departure.start_date', '2024-10-24')
            ->where('departure.capacity', 16)
            ->where('departure.booking_deadline_preset', 'h3'));
        $this->actingAsTenant($this->seller)->get(route('onboarding.payment'))->assertInertia(fn (AssertableInertia $page) => $page->where('urls.back', route('onboarding.schedule')));

        $this->assertSame('Gagal menyimpan. Periksa koneksi lalu coba lagi.', __('onboarding.save_failed'));
        foreach (['Organization', 'Trip', 'Schedule', 'Payment'] as $page) {
            $vue = file_get_contents(resource_path("js/pages/Onboarding/{$page}.vue"));
            $this->assertStringContainsString("t(copy, 'save_failed')", $vue, $page);
            $this->assertMatchesRegularExpression('/:disabled="(form\.)?processing"/', $vue, $page);
        }
    }

    public function test_steps_cannot_be_skipped(): void
    {
        $this->actingAsTenant($this->seller)->get(route('onboarding.schedule'))->assertRedirect(route('onboarding.trip'));
        $this->actingAsTenant($this->seller)->get(route('onboarding.payment'))->assertRedirect(route('onboarding.trip'));
        $this->actingAsTenant($this->seller)->post(route('onboarding.schedule'), $this->scheduleInput())->assertRedirect(route('onboarding.trip'));

        $this->actingAs($this->signedUpUser())->get(route('onboarding.trip'))->assertRedirect(route('onboarding.organization'));
    }
}
