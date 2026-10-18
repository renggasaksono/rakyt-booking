<?php

namespace Tests\Feature\Settings;

use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\Support\BuildsOnboarding;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-001 F1, F2, F4: Pengaturan → Profil usaha.
 */
class ProfileTest extends TestCase
{
    use BuildsOnboarding, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $seller;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('rakyt.media.public_disk'));
        $this->freezeClock();
        $this->seller = $this->createTenant();
        $this->seller->organization->update(['name' => 'Kencana Trip', 'slug' => 'kencana', 'city' => 'Jepara', 'whatsapp' => '+6281277881020']);
    }

    /**
     * @return array<string, mixed>
     */
    private function input(array $overrides = []): array
    {
        return ['name' => 'Kencana Trip', 'city' => 'Jepara', 'whatsapp' => '0812 7788 1020', 'slug' => 'kencana', 'bio' => '', 'timezone' => 'Asia/Jakarta', ...$overrides];
    }

    private function organization(): Organization
    {
        return Organization::query()->findOrFail($this->seller->id());
    }

    public function test_ac_f1_profil_usaha_shows_the_editable_fields(): void
    {
        $this->actingAsTenant($this->seller)->get(route('settings.profile'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Profile')
                ->where('profile.name', 'Kencana Trip')
                ->where('profile.whatsapp', '81277881020')
                ->where('profile.timezone', 'Asia/Jakarta')
                ->where('limits.bio', 160)
                ->where('limits.logo_kb', 2048)
                ->where('timezones', ['Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura'])
                ->where('copy.logo_hint', 'JPG, PNG atau WebP, maks. 2 MB. Ditampilkan persegi.')
                ->where('urls.back', route('settings.index'))
                ->where('backLabel', '← Pengaturan'));
    }

    public function test_ac_f1_name_city_whatsapp_and_bio_are_saved_with_the_onboarding_rules(): void
    {
        $this->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input([
            'name' => 'Kencana Trip Jepara',
            'city' => 'Karimunjawa',
            'whatsapp' => '+62 813 1111 2222',
            'bio' => 'Open trip Karimunjawa sejak 2015.',
        ]))->assertSessionHasNoErrors()->assertSessionHas('toast', 'Profil usaha disimpan.');

        $organization = $this->organization();
        $this->assertSame('Kencana Trip Jepara', $organization->name);
        $this->assertSame('Karimunjawa', $organization->city);
        $this->assertSame('+6281311112222', $organization->whatsapp);
        $this->assertSame('Open trip Karimunjawa sejak 2015.', $organization->bio);

        $this->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input(['whatsapp' => '021 555 1234', 'bio' => str_repeat('a', 161), 'name' => 'A', 'timezone' => 'Europe/Amsterdam']))
            ->assertSessionHasErrors(['whatsapp' => 'Nomor WhatsApp tidak valid', 'bio', 'name', 'timezone']);

        $this->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input(['bio' => '  ']))->assertSessionHasNoErrors();
        $this->assertNull($this->organization()->bio);
    }

    public function test_ac_f1_logo_is_jpg_png_or_webp_up_to_2_mb_stored_square_ready(): void
    {
        $this->actingAsTenant($this->seller)->post(route('settings.profile.logo'), ['logo' => UploadedFile::fake()->image('logo.png', 1200, 1200)])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('toast', 'Logo disimpan.');

        $path = $this->organization()->logo_path;
        $disk = Storage::disk(config('rakyt.media.public_disk'));
        $disk->assertExists($path);
        [$width, $height] = getimagesizefromstring($disk->get($path));
        $this->assertLessThanOrEqual(512, max($width, $height));
        $this->assertNotNull($this->organization()->logoUrl());

        // Replacing removes the old file.
        $this->actingAsTenant($this->seller)->post(route('settings.profile.logo'), ['logo' => UploadedFile::fake()->image('baru.webp', 300, 300)]);
        $disk->assertMissing($path);

        $this->actingAsTenant($this->seller)->post(route('settings.profile.logo'), ['logo' => UploadedFile::fake()->create('logo.gif', 10, 'image/gif')])
            ->assertSessionHasErrors(['logo' => 'Logo harus JPG, PNG atau WebP.']);
        $this->actingAsTenant($this->seller)->post(route('settings.profile.logo'), ['logo' => UploadedFile::fake()->image('besar.jpg')->size(2 * 1024 + 1)])
            ->assertSessionHasErrors(['logo' => 'Logo maksimal 2 MB.']);

        $current = $this->organization()->logo_path;
        $this->actingAsTenant($this->seller)->delete(route('settings.profile.logo'))->assertSessionHas('toast', 'Logo dihapus.');
        $this->assertNull($this->organization()->logo_path);
        $disk->assertMissing($current);
    }

    public function test_ac_f2_the_slug_is_read_only_after_the_first_publish(): void
    {
        $this->seller->organization->update(['first_published_at' => now()]);

        $this->actingAsTenant($this->seller)->get(route('settings.profile'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('slugLocked', true)->where('copy.slug_locked', 'Hubungi Rakyt untuk mengganti link.'));

        $this->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input(['slug' => 'baru']))->assertSessionHasNoErrors();
        $this->assertSame('kencana', $this->organization()->slug);
    }

    public function test_ac_f2_before_the_first_publish_the_slug_follows_the_b_rules(): void
    {
        $this->assertNull($this->organization()->first_published_at);
        Organization::factory()->create(['slug' => 'dipakai']);

        $this->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input(['slug' => 'admin']))->assertSessionHasErrors(['slug' => '✕ Tidak bisa dipakai']);
        $this->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input(['slug' => 'DIPAKAI']))->assertSessionHasErrors(['slug' => '✕ Sudah dipakai']);
        $this->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input(['slug' => 'ke--ncana']))->assertSessionHasErrors('slug');

        $this->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input(['slug' => 'kencana-baru']))->assertSessionHasNoErrors();
        $this->assertSame('kencana-baru', $this->organization()->slug);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'organization.slug_changed')->count());
    }

    public function test_ac_f4_a_time_zone_change_recomputes_preset_deadlines_and_keeps_custom_ones(): void
    {
        $trip = $this->tripFor($this->seller, ['meeting_time' => '06:00']);
        $h3 = $this->departureFor($this->seller, $trip, '2024-10-24');
        $sameDay = $this->departureFor($this->seller, $trip, '2024-10-25', [
            'booking_deadline_preset' => 'same_day',
            'booking_deadline_at' => CarbonImmutable::parse('2024-10-25 05:00', 'Asia/Jakarta')->utc(),
        ]);
        $customAt = CarbonImmutable::parse('2024-10-20 12:00', 'Asia/Jakarta')->utc();
        $custom = $this->departureFor($this->seller, $trip, '2024-10-26', ['booking_deadline_preset' => 'custom', 'booking_deadline_at' => $customAt]);
        $past = $this->departureFor($this->seller, $trip, '2024-10-01', ['booking_deadline_at' => CarbonImmutable::parse('2024-09-28 23:59:59', 'Asia/Jakarta')->utc()]);
        $pastBefore = $this->freshDeparture($past)->booking_deadline_at;

        $this->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input(['timezone' => 'Asia/Jayapura']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('notice', '2 batas pemesanan dihitung ulang dengan zona waktu baru.');

        $this->assertSame('Asia/Jayapura', $this->organization()->timezone);
        $this->assertTrue($this->freshDeparture($h3)->booking_deadline_at->equalTo(CarbonImmutable::parse('2024-10-21 23:59:59', 'Asia/Jayapura')));
        $this->assertTrue($this->freshDeparture($sameDay)->booking_deadline_at->equalTo(CarbonImmutable::parse('2024-10-25 05:00', 'Asia/Jayapura')));
        $this->assertTrue($this->freshDeparture($custom)->booking_deadline_at->equalTo($customAt));
        $this->assertTrue($this->freshDeparture($past)->booking_deadline_at->equalTo($pastBefore));

        $events = AuditEvent::acrossOrganizations()->where('action', 'departure.deadline_changed')->get();
        $this->assertCount(2, $events);
        $this->assertEqualsCanonicalizing([(string) $h3->id, (string) $sameDay->id], $events->pluck('subject_id')->all());
    }

    public function test_ac_f4_a_deadline_that_moves_into_the_past_closes_the_departure(): void
    {
        // Monday 21 Oct 23:30 WIB: the H-3 deadline (23:59 WIB) is still ahead, 23:59 WIT is not.
        $this->travelTo(CarbonImmutable::parse('2024-10-21 23:30', 'Asia/Jakarta'));
        $trip = $this->tripFor($this->seller);
        $departure = $this->departureFor($this->seller, $trip, '2024-10-24');

        $this->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input(['timezone' => 'Asia/Jayapura']))->assertSessionHasNoErrors();

        $fresh = $this->freshDeparture($departure);
        $this->assertSame('closed', $fresh->status->value);
        $this->assertSame('deadline', $fresh->closed_reason->value);
    }

    public function test_ac_f4_rolls_back_with_the_time_zone(): void
    {
        $trip = $this->tripFor($this->seller);
        $departure = $this->departureFor($this->seller, $trip, '2024-10-24');
        $before = $this->freshDeparture($departure)->booking_deadline_at;

        TripDeparture::saving(fn () => throw new RuntimeException('fail'));

        try {
            $this->withoutExceptionHandling()->actingAsTenant($this->seller)->put(route('settings.profile'), $this->input(['timezone' => 'Asia/Makassar']));
            $this->fail('Expected the departure save to fail.');
        } catch (RuntimeException) {
        }

        $this->assertSame('Asia/Jakarta', $this->organization()->timezone);
        $this->assertTrue($this->freshDeparture($departure)->booking_deadline_at->equalTo($before));
    }

    public function test_settings_pages_are_for_signed_in_sellers_only(): void
    {
        $this->get(route('settings.profile'))->assertRedirect(route('login'));
        $this->putJson(route('settings.profile'), $this->input())->assertUnauthorized();
        $this->postJson(route('settings.profile.logo'))->assertUnauthorized();
    }
}
