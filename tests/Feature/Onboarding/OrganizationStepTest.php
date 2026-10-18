<?php

namespace Tests\Feature\Onboarding;

use App\Models\AuditEvent;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Onboarding\OnboardingService;
use App\Organizations\BookingSlug;
use App\Organizations\OrganizationRules;
use App\Tenancy\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use RuntimeException;
use Tests\Support\BuildsOnboarding;
use Tests\Support\InteractsWithTenants;
use Tests\TestCase;

/**
 * F-001 B1–B12: "Langkah 1 dari 4 · Usaha".
 */
class OrganizationStepTest extends TestCase
{
    use BuildsOnboarding, InteractsWithTenants, RefreshDatabase;

    public function test_ac_b1_step_1_shows_the_fields_and_no_verified_whatsapp_line(): void
    {
        $this->actingAs($this->signedUpUser())->get(route('onboarding.organization'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Onboarding/Organization')
                ->where('organization', null)
                ->where('header.step', 1)
                ->where('header.name', 'Usaha')
                ->where('copy.organization.name', 'Nama usaha')
                ->where('copy.organization.city', 'Kota basis')
                ->where('copy.organization.whatsapp', 'Nomor WhatsApp usaha')
                ->where('copy.organization.whatsapp_prefix', '+62')
                ->where('copy.organization.whatsapp_hint', 'Pelanggan akan chat Anda di nomor ini.')
                ->where('copy.organization.slug', 'Link booking')
                ->where('copy.organization.submit', 'Lanjut ke trip pertama')
                ->where('publicHost', 'rakyt.id'));

        $copy = json_encode(__('onboarding'));
        $this->assertStringNotContainsString('terverifikasi', $copy);
        $this->assertStringNotContainsString('Diambil dari profil WhatsApp Business', $copy);
        $this->assertStringContainsString('inputmode="numeric"', file_get_contents(resource_path('js/pages/Onboarding/Organization.vue')));
    }

    public function test_ac_b2_name_and_city_are_required_2_to_80_characters(): void
    {
        $user = $this->signedUpUser();

        $this->actingAs($user)->from(route('onboarding.organization'))
            ->post(route('onboarding.organization'), $this->organizationInput(['name' => ' A ', 'city' => '']))
            ->assertRedirect(route('onboarding.organization'))
            ->assertSessionHasErrors(['name', 'city'])
            ->assertSessionHasInput('slug', 'kencana');

        $this->actingAs($user)->post(route('onboarding.organization'), $this->organizationInput(['name' => str_repeat('a', 81), 'city' => str_repeat('b', 81)]))
            ->assertSessionHasErrors(['name', 'city']);

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_ac_b3_whatsapp_numbers_are_normalised_to_e164(): void
    {
        foreach (['0812 7788 1020', '812-7788-1020', '62812 7788 1020', '+62 812 7788 1020'] as $input) {
            $this->assertSame('+6281277881020', OrganizationRules::normalize(['whatsapp' => $input])['whatsapp'], $input);
        }

        $user = $this->signedUpUser();
        $this->actingAs($user)->post(route('onboarding.organization'), $this->organizationInput(['whatsapp' => '+62 812 7788 1020']))
            ->assertRedirect(route('onboarding.trip'));

        $this->assertDatabaseHas('organizations', ['slug' => 'kencana', 'whatsapp' => '+6281277881020']);
    }

    public function test_ac_b3_non_mobile_numbers_are_refused(): void
    {
        $user = $this->signedUpUser();

        foreach (['', '021 555 1234', '0812 77', '+1 415 555 0100', '0812 7788 1020 1234'] as $input) {
            $this->actingAs($user)->post(route('onboarding.organization'), $this->organizationInput(['whatsapp' => $input]))
                ->assertSessionHasErrors(['whatsapp' => 'Nomor WhatsApp tidak valid']);
        }

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_ac_b4_the_slug_suggestion_follows_the_name(): void
    {
        $this->assertSame('kencana-trip', BookingSlug::fromName('Kencana Trip'));
        $this->assertSame('cafe-bahari', BookingSlug::fromName('  Café   Bahari! '));
        $this->assertSame('trip-tour-1', BookingSlug::fromName('Trip & Tour #1'));
        $this->assertLessThanOrEqual(30, strlen(BookingSlug::fromName(str_repeat('Kencana ', 10))));
    }

    public function test_ac_b5_slug_format_rules(): void
    {
        foreach (['kencana', 'abc', 'kencana-2', str_repeat('a', 30)] as $slug) {
            $this->assertTrue(BookingSlug::isValidFormat($slug), $slug);
        }

        foreach (['ab', str_repeat('a', 31), '-kencana', 'kencana-', 'ken--cana', 'ken_cana', 'ken cana', 'kéncana'] as $slug) {
            $this->assertFalse(BookingSlug::isValidFormat($slug), $slug);
        }

        // Uppercase is lower-cased; uniqueness ignores case.
        $user = $this->signedUpUser();
        $this->actingAs($user)->post(route('onboarding.organization'), $this->organizationInput(['slug' => 'KenCana']))
            ->assertRedirect(route('onboarding.trip'));
        $this->assertDatabaseHas('organizations', ['slug' => 'kencana']);
    }

    public function test_ac_b6_b12_availability_check_returns_only_a_state_and_suggestions(): void
    {
        $taken = Organization::factory()->create(['slug' => 'kencana', 'name' => 'Rahasia Lain', 'whatsapp' => '+6281111111111']);
        $user = $this->signedUpUser();

        $check = fn (string $slug) => $this->actingAs($user)->getJson(route('onboarding.slug', ['slug' => $slug, 'name' => 'Kencana Trip', 'city' => 'Jepara, Jawa Tengah']));

        $check('kencana-baru')->assertOk()->assertExactJson(['state' => 'available', 'suggestions' => []]);
        $check('admin')->assertOk()->assertJsonPath('state', 'reserved');
        $check('ab')->assertOk()->assertExactJson(['state' => 'invalid', 'suggestions' => []]);

        $response = $check('KENCANA')->assertOk()->assertJsonPath('state', 'taken');
        $this->assertSame(['state', 'suggestions'], array_keys($response->json()));
        $this->assertSame(['kencana-trip', 'kencana-jepara', 'kencana2'], $response->json('suggestions'));
        $this->assertStringNotContainsString('Rahasia', $response->getContent());
        $this->assertStringNotContainsString((string) $taken->id, $response->getContent());
        $this->assertStringNotContainsString('811111', $response->getContent());
    }

    public function test_ac_b6_suggestions_skip_slugs_that_are_taken_too(): void
    {
        Organization::factory()->create(['slug' => 'kencana']);
        Organization::factory()->create(['slug' => 'kencana-trip']);

        $this->assertSame(['kencana-jepara', 'kencana2', 'kencana3'], BookingSlug::suggestions('kencana', 'Kencana Trip', 'Jepara, Jawa Tengah'));
    }

    public function test_ac_b12_the_check_needs_a_signed_in_user_and_is_throttled(): void
    {
        $this->getJson(route('onboarding.slug', ['slug' => 'kencana']))->assertUnauthorized();

        config(['rakyt.rate_limits.slug_check.per_ip' => [2, 1]]);
        $user = $this->signedUpUser();

        $this->actingAs($user)->getJson(route('onboarding.slug', ['slug' => 'a1b']))->assertOk();
        $this->actingAs($user)->getJson(route('onboarding.slug', ['slug' => 'a1c']))->assertOk();
        $this->actingAs($user)->getJson(route('onboarding.slug', ['slug' => 'a1d']))->assertTooManyRequests();
    }

    public function test_ac_b7_reserved_slugs_are_refused(): void
    {
        $user = $this->signedUpUser();

        foreach (['admin', 'dashboard', 'pengaturan', 'masuk', 'rakyt'] as $slug) {
            $this->actingAs($user)->post(route('onboarding.organization'), $this->organizationInput(['slug' => $slug]))
                ->assertSessionHasErrors(['slug' => '✕ Tidak bisa dipakai']);
        }

        $this->assertDatabaseCount('organizations', 0);
    }

    public function test_ac_b8_step_1_creates_the_organization_owner_membership_and_audit_event_together(): void
    {
        $user = $this->signedUpUser();

        $this->actingAs($user)->post(route('onboarding.organization'), $this->organizationInput())
            ->assertRedirect(route('onboarding.trip'));

        $organization = Organization::query()->sole();
        $this->assertSame('Kencana Trip', $organization->name);
        $this->assertSame('Jepara, Jawa Tengah', $organization->city);
        $this->assertSame('kencana', $organization->slug);
        $this->assertSame('free', $organization->plan);
        $this->assertSame('Asia/Jakarta', $organization->timezone);
        $this->assertSame('+6281277881020', $organization->whatsapp);

        $this->assertDatabaseHas('organization_members', ['organization_id' => $organization->id, 'user_id' => $user->id, 'role' => 'owner']);

        $event = AuditEvent::acrossOrganizations()->where('action', 'organization.created')->sole();
        $this->assertSame($organization->id, $event->organization_id);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame('kencana', $event->after['slug']);
        $this->assertArrayNotHasKey('whatsapp', $event->after);
    }

    public function test_ac_b8_if_any_insert_fails_nothing_is_kept(): void
    {
        $user = $this->signedUpUser();
        OrganizationMember::creating(fn () => throw new RuntimeException('member insert failed'));

        try {
            $this->withoutExceptionHandling()->actingAs($user)->post(route('onboarding.organization'), $this->organizationInput());
            $this->fail('Expected the membership insert to fail.');
        } catch (RuntimeException) {
        }

        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('organization_members', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_ac_b9_a_double_tap_or_retry_leaves_one_organization(): void
    {
        $user = $this->signedUpUser();

        $this->actingAs($user)->post(route('onboarding.organization'), $this->organizationInput())->assertRedirect(route('onboarding.trip'));
        $this->actingAs($user)->post(route('onboarding.organization'), $this->organizationInput(['name' => 'Kencana Trip Jepara']))->assertRedirect(route('onboarding.trip'));

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseCount('organization_members', 1);
        $this->assertDatabaseHas('organizations', ['name' => 'Kencana Trip Jepara', 'slug' => 'kencana']);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'organization.created')->count());
    }

    public function test_ac_b10_the_database_decides_a_slug_race_and_the_loser_keeps_their_input(): void
    {
        $user = $this->signedUpUser();

        // The other seller's insert lands after this request's availability check.
        $calls = 0;
        Organization::creating(function (Organization $organization) use (&$calls) {
            if ($calls++ === 0) {
                DB::table('organizations')->insert(['public_id' => (string) str()->uuid(), 'name' => 'Lain', 'slug' => 'kencana', 'city' => 'Bali', 'whatsapp' => '+6281200000000']);
            }
        });

        $this->actingAs($user)->from(route('onboarding.organization'))
            ->post(route('onboarding.organization'), $this->organizationInput())
            ->assertRedirect(route('onboarding.organization'))
            ->assertSessionHasErrors(['slug' => '✕ Sudah dipakai'])
            ->assertSessionHasInput('name', 'Kencana Trip')
            ->assertSessionHasInput('city', 'Jepara, Jawa Tengah');

        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseCount('organization_members', 0);
    }

    public function test_ac_b11_going_back_before_the_first_publish_edits_step_1(): void
    {
        $tenant = $this->onboardingTenant(['name' => 'Kencana', 'slug' => 'kencana', 'whatsapp' => '+6281277881020']);

        $this->actingAsTenant($tenant)->get(route('onboarding.organization'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('organization.name', 'Kencana')
                ->where('organization.whatsapp', '81277881020')
                ->where('organization.slug', 'kencana')
                ->where('slugLocked', false));

        $this->actingAsTenant($tenant)->post(route('onboarding.organization'), $this->organizationInput(['name' => 'Kencana Baru', 'slug' => 'kencana-baru', 'whatsapp' => 'x']))
            ->assertSessionHasErrors('whatsapp');

        $this->actingAsTenant($tenant)->post(route('onboarding.organization'), $this->organizationInput(['name' => 'Kencana Baru', 'slug' => 'kencana-baru']))
            ->assertRedirect(route('onboarding.trip'));

        $this->assertDatabaseHas('organizations', ['id' => $tenant->id(), 'name' => 'Kencana Baru', 'slug' => 'kencana-baru']);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'organization.slug_changed')->count());
    }

    public function test_ac_b11_the_seller_keeps_their_own_slug_when_resubmitting(): void
    {
        $tenant = $this->onboardingTenant(['slug' => 'kencana']);

        $this->actingAsTenant($tenant)->post(route('onboarding.organization'), $this->organizationInput(['slug' => 'kencana']))
            ->assertSessionHasNoErrors();
    }

    public function test_finished_sellers_do_not_see_step_1_again(): void
    {
        $tenant = $this->createTenant();

        $this->actingAsTenant($tenant)->get(route('onboarding.organization'))->assertRedirect(route('dashboard'));
        $this->actingAsTenant($tenant)->post(route('onboarding.organization'), $this->organizationInput(['slug' => 'lain']))->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('organizations', ['slug' => 'lain']);
    }

    public function test_the_service_refuses_a_slug_change_after_the_first_publish(): void
    {
        $tenant = $this->onboardingTenant(['slug' => 'kencana', 'first_published_at' => now()]);

        $this->expectException(ValidationException::class);
        app(CurrentOrganization::class)->runAs($tenant->organization, fn () => app(OnboardingService::class)
            ->saveOrganization($tenant->owner, ['name' => 'X', 'city' => 'Y', 'whatsapp' => '+6281277881020', 'slug' => 'baru']));
    }
}
