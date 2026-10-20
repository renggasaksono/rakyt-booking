<?php

namespace Tests\Feature\Settings;

use App\Models\PaymentMethod;
use App\Payments\PaymentMethodType;
use App\Trips\Contracts\PaymentReadiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-006 section D: QRIS in Pengaturan → Pembayaran (ADR-0030 §4).
 */
class QrisMethodTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $seller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        Storage::fake(config('rakyt.media.private_disk'));
        $this->seller = $this->createTenant();
    }

    private function save(array $data)
    {
        return $this->actingAsTenant($this->seller)->post(route('settings.payment.update'), $data);
    }

    public function test_ac_21_a_qris_image_can_be_added_without_an_account(): void
    {
        $this->save(['methods' => [], 'qris' => UploadedFile::fake()->image('qris.png')])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $qris = PaymentMethod::acrossOrganizations()->where('type', 'qris')->sole();
        $this->assertSame($this->seller->id(), $qris->organization_id);
        $this->assertNull($qris->account_number);
        $this->assertNotNull($qris->qris_image_path);
        Storage::disk(config('rakyt.media.private_disk'))->assertExists($qris->qris_image_path);
    }

    public function test_ac_21_qris_counts_as_an_active_payment_method_for_publishing(): void
    {
        $this->save(['methods' => [], 'qris' => UploadedFile::fake()->image('qris.png')])->assertSessionHasNoErrors();

        $this->assertTrue(app(PaymentReadiness::class)->hasActivePaymentMethod($this->seller->organization->fresh()));
    }

    public function test_ac_22_the_seller_previews_the_qris_through_a_gated_stream(): void
    {
        $this->save(['methods' => [], 'qris' => UploadedFile::fake()->image('qris.png')])->assertSessionHasNoErrors();

        $this->actingAsTenant($this->seller)->get(route('settings.payment.qris'))->assertOk();
        // Another seller never reaches it.
        $other = $this->createTenant();
        $this->actingAsTenant($other)->get(route('settings.payment.qris'))->assertNotFound();
    }

    public function test_ac_23_a_qris_upload_must_be_an_image(): void
    {
        $this->save(['methods' => [], 'qris' => UploadedFile::fake()->create('qris.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('qris');

        $this->assertDatabaseMissing('payment_methods', ['organization_id' => $this->seller->id(), 'type' => 'qris']);
    }

    public function test_ac_23_bank_rules_still_apply_and_qris_coexists_with_a_bank(): void
    {
        // A bad account number is still refused.
        $this->save(['methods' => [$this->block(['account_number' => '12'])]])
            ->assertSessionHasErrors('methods.0.account_number');

        // A bank and a QRIS coexist, QRIS last.
        $this->save([
            'methods' => [$this->block(['account_number' => '1234567890'])],
            'qris' => UploadedFile::fake()->image('qris.png'),
        ])->assertSessionHasNoErrors();

        $types = PaymentMethod::acrossOrganizations()->orderBy('position')->pluck('type')->all();
        $this->assertSame([PaymentMethodType::Bank, PaymentMethodType::Qris], $types);
    }

    public function test_ac_23_removing_the_qris_keeps_a_bank_and_deletes_the_image(): void
    {
        $this->save([
            'methods' => [$this->block(['account_number' => '1234567890'])],
            'qris' => UploadedFile::fake()->image('qris.png'),
        ])->assertSessionHasNoErrors();
        $path = PaymentMethod::acrossOrganizations()->where('type', 'qris')->value('qris_image_path');

        $this->save(['methods' => [$this->block(['account_number' => '1234567890'])], 'remove_qris' => 1])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('payment_methods', ['organization_id' => $this->seller->id(), 'type' => 'qris']);
        Storage::disk(config('rakyt.media.private_disk'))->assertMissing($path);
    }

    public function test_removing_the_only_method_is_refused(): void
    {
        $this->save(['methods' => [], 'qris' => UploadedFile::fake()->image('qris.png')])->assertSessionHasNoErrors();

        $this->save(['methods' => [], 'remove_qris' => 1])->assertSessionHasErrors('methods');
        $this->assertDatabaseHas('payment_methods', ['organization_id' => $this->seller->id(), 'type' => 'qris']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function block(array $overrides = []): array
    {
        return ['provider' => 'bca', 'account_number' => '1234567890', 'account_holder' => 'Agus Santoso', ...$overrides];
    }
}
