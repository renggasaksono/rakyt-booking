<?php

namespace Tests\Feature\Settings;

use App\Models\AuditEvent;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsOnboarding;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * Pengaturan → Pembayaran (F-006, superseding F-001's Rekening): the bank /
 * e-wallet accounts. Same rules and audit as onboarding step 4 (C10–C11). QRIS
 * and the ADR-0018 block live in QrisMethodTest / PaymentInstructionBlockTest.
 */
class PaymentMethodsTest extends TestCase
{
    use BuildsOnboarding, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->seller = $this->createTenant();
    }

    private function save(array $methods, ?Tenant $as = null)
    {
        return $this->actingAsTenant($as ?? $this->seller)->post(route('settings.payment.update'), ['methods' => $methods]);
    }

    public function test_ac_f5_pembayaran_lists_the_accounts_and_is_on_the_settings_index(): void
    {
        $method = $this->asTenant($this->seller, fn () => PaymentMethod::factory()->create(['account_number' => '1111111111']));

        $this->actingAsTenant($this->seller)->get(route('settings.payment'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Settings/Payment')
                ->where('methods', [[
                    'id' => $method->public_id,
                    'provider' => 'bca',
                    'provider_name' => null,
                    'account_number' => '1111111111',
                    'account_holder' => 'Agus Santoso',
                ]])
                ->where('qris.exists', false)
                ->where('providers.0', ['value' => 'bca', 'label' => 'BCA', 'type' => 'bank', 'asks_name' => false])
                ->where('limits.methods', 5)
                ->where('urls.update', route('settings.payment.update'))
                ->where('urls.back', route('settings.index'))
                ->where('lockedUntil', null)
                ->where('copy.title', 'Pembayaran')
                ->where('fields.add', '+ Tambah rekening lain'));

        $this->actingAsTenant($this->seller)->get(route('settings.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('items.3.key', 'payment_methods')
                ->where('items.3.url', route('settings.payment'))
                ->where('copy.payment_methods', 'Pembayaran'));
    }

    public function test_ac_f5_accounts_are_added_updated_and_removed_with_one_audit_row(): void
    {
        [$bca, $bri] = $this->asTenant($this->seller, fn () => [
            PaymentMethod::factory()->create(['account_number' => '1111111111', 'position' => 0]),
            PaymentMethod::factory()->create(['provider' => 'bri', 'account_number' => '2222222222', 'position' => 1]),
        ]);

        $this->save([
            ['id' => $bca->public_id, ...$this->bankBlock(['account_number' => '3333 3333 33'])],
            $this->bankBlock(['provider' => 'gopay', 'account_number' => '0812-7788-1020', 'account_holder' => 'Agus']),
        ])->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('toast', 'Instruksi pembayaran disimpan.');

        $this->assertDatabaseHas('payment_methods', ['id' => $bca->id, 'account_number' => '3333333333', 'position' => 0]);
        $this->assertDatabaseMissing('payment_methods', ['id' => $bri->id]);
        $this->assertDatabaseHas('payment_methods', ['organization_id' => $this->seller->id(), 'provider' => 'gopay', 'type' => 'ewallet', 'account_number' => '081277881020']);

        $event = AuditEvent::acrossOrganizations()->where('action', 'payment_instructions.changed')->sole();
        $this->assertSame($this->seller->id(), $event->organization_id);
        $this->assertSame('user', $event->actor_type);
        $this->assertSame(['1111111111', '2222222222'], array_column($event->before['methods'], 'account_number'));
        $this->assertSame(['3333333333', '081277881020'], array_column($event->after['methods'], 'account_number'));

        // Saving the same accounts again changes nothing and is not audited.
        $saved = PaymentMethod::acrossOrganizations()->orderBy('position')->get();
        $this->save($saved->map(fn (PaymentMethod $m) => ['id' => $m->public_id, 'provider' => $m->provider, 'account_number' => $m->account_number, 'account_holder' => $m->account_holder])->all())
            ->assertSessionHasNoErrors();
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'payment_instructions.changed')->count());
    }

    public function test_ac_f5_the_onboarding_rules_apply_and_one_account_is_needed(): void
    {
        $this->save([$this->bankBlock(['account_number' => '1234'])])->assertSessionHasErrors(['methods.0.account_number' => 'Isi 5–20 angka.']);
        $this->save([$this->bankBlock(['account_holder' => 'A'])])->assertSessionHasErrors('methods.0.account_holder');
        $this->save([$this->bankBlock(['provider' => 'other'])])->assertSessionHasErrors('methods.0.provider_name');
        $this->save(array_fill(0, 6, $this->bankBlock()))->assertSessionHasErrors('methods');
        $this->save([])->assertSessionHasErrors('methods');

        $this->assertSame(0, PaymentMethod::acrossOrganizations()->count());
        $this->assertSame(0, AuditEvent::acrossOrganizations()->where('action', 'payment_instructions.changed')->count());
    }

    public function test_ac_f5_t1_a_seller_never_sees_or_changes_another_sellers_accounts(): void
    {
        $other = $this->createTenant();
        $theirs = $this->asTenant($other, fn () => PaymentMethod::factory()->create(['account_number' => '9999999999']));
        $mine = $this->asTenant($this->seller, fn () => PaymentMethod::factory()->create(['account_number' => '1111111111']));

        $this->actingAsTenant($this->seller)->get(route('settings.payment'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('methods', 1)->where('methods.0.id', $mine->public_id));

        $this->assertCrossTenantPayloadRejected($this->seller, 'POST', route('settings.payment.update'),
            ['methods' => [['id' => $theirs->public_id, ...$this->bankBlock(['account_number' => '5555555555'])]]], 'methods.0.id',
            function () use ($theirs, $mine) {
                $this->assertDatabaseHas('payment_methods', ['id' => $theirs->id, 'account_number' => '9999999999']);
                $this->assertDatabaseHas('payment_methods', ['id' => $mine->id, 'account_number' => '1111111111']);
            });

        // A payload organization_id is ignored; the rows go to the signed-in seller.
        $this->actingAsTenant($this->seller)->post(route('settings.payment.update'), ['organization_id' => $other->id(), 'methods' => [$this->bankBlock(['account_number' => '7777777777'])]])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payment_methods', ['organization_id' => $this->seller->id(), 'account_number' => '7777777777']);
        $this->assertDatabaseHas('payment_methods', ['id' => $theirs->id, 'organization_id' => $other->id(), 'account_number' => '9999999999']);
        $this->assertSame(0, AuditEvent::acrossOrganizations()->where('organization_id', $other->id())->count());
    }

    public function test_ac_f5_guests_sign_in_and_sellers_in_the_guided_flow_resume_it(): void
    {
        $this->get(route('settings.payment'))->assertRedirect(route('login'));
        $this->postJson(route('settings.payment.update'), ['methods' => [$this->bankBlock()]])->assertUnauthorized();

        // E2: page visits resume the guided flow (writes pass, as for every seller route).
        $onboarding = $this->onboardingTenant();
        $this->actingAsTenant($onboarding)->get(route('settings.payment'))->assertRedirect(route('onboarding.trip'));
        $this->assertSame(0, PaymentMethod::acrossOrganizations()->count());
    }

    public function test_ac_f5_saves_have_their_own_limiter_apart_from_email_and_password_changes(): void
    {
        config(['rakyt.rate_limits.payment_change.per_user' => [2, 15]]);

        $this->save([$this->bankBlock()])->assertSessionHasNoErrors();
        $this->save([$this->bankBlock()])->assertSessionHasNoErrors();
        $this->actingAsTenant($this->seller)->postJson(route('settings.payment.update'), ['methods' => [$this->bankBlock()]])->assertTooManyRequests();

        // Email and password changes keep their own budget.
        $this->actingAsTenant($this->seller)->putJson(route('settings.account.email'), ['email' => 'baru@example.com', 'current_password' => 'salah'])
            ->assertUnprocessable();
    }

    public function test_ac_f5_the_trip_editor_links_its_payment_checklist_item_here(): void
    {
        $trip = $this->tripFor($this->seller);

        $this->actingAsTenant($this->seller)->get(route('trips.edit', $trip))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('urls.payment_setup', route('settings.payment')));

        // Phone width: the checklist under "Pratinjau" carries the link too, not only the desktop sidebar.
        $editor = file_get_contents(resource_path('js/pages/Trips/Edit.vue'));
        $this->assertStringContainsString(':href="urlState.payment_setup"', $editor);
    }
}
