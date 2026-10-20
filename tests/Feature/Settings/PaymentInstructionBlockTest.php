<?php

namespace Tests\Feature\Settings;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\AuditEvent;
use App\Models\PaymentMethod;
use App\Notifications\Payments\PaymentInstructionsChangedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-006 section E: payment-instruction change protection (ADR-0018).
 */
class PaymentInstructionBlockTest extends TestCase
{
    use BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $seller;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = $this->freezeClock();
        $this->seller = $this->createTenant();
    }

    private function save(array $methods)
    {
        return $this->actingAsTenant($this->seller)->post(route('settings.payment.update'), ['methods' => $methods]);
    }

    private function block(array $overrides = []): array
    {
        return ['provider' => 'bca', 'account_number' => '1234567890', 'account_holder' => 'Agus Santoso', ...$overrides];
    }

    private function recover(AuditAction $action, ?CarbonImmutable $at = null): void
    {
        // audit_events is append-only (DB trigger), so set the clock to stamp created_at.
        $this->travelTo(($at ?? $this->now)->toDateTime());

        $this->asTenant($this->seller, function () use ($action) {
            DB::transaction(fn () => app(AuditLog::class)->record($action, $this->seller->owner, actor: Actor::user($this->seller->owner)));
        });

        $this->travelTo($this->now->toDateTime());
    }

    public function test_ac_24_a_change_is_audited_and_the_sign_in_email_is_notified(): void
    {
        Notification::fake();

        $this->save([$this->block()])->assertSessionHasNoErrors();

        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'payment_instructions.changed')->count());
        Notification::assertSentTo($this->seller->owner, PaymentInstructionsChangedNotification::class);
    }

    public function test_ac_24_no_change_sends_no_audit_and_no_email(): void
    {
        Notification::fake();
        $this->save([$this->block()])->assertSessionHasNoErrors();
        Notification::fake(); // reset

        $saved = PaymentMethod::acrossOrganizations()->where('type', 'bank')->first();
        $this->save([['id' => $saved->public_id, ...$this->block()]])->assertSessionHasNoErrors();

        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'payment_instructions.changed')->count());
        Notification::assertNothingSent();
    }

    public function test_ac_25_a_recent_recovery_locks_the_form_and_refuses_a_post(): void
    {
        $this->save([$this->block()])->assertSessionHasNoErrors();
        $this->recover(AuditAction::AuthPasswordReset);

        // The page reports the lock.
        $this->actingAsTenant($this->seller)->get(route('settings.payment'))
            ->assertInertia(fn ($page) => $page->whereNot('lockedUntil', null));

        // A POST in the window is refused server-side; nothing changes.
        $this->save([$this->block(['account_number' => '5555555555'])])
            ->assertSessionHas('notice');
        $this->assertDatabaseMissing('payment_methods', ['organization_id' => $this->seller->id(), 'account_number' => '5555555555']);
    }

    public function test_ac_25_an_email_change_also_locks(): void
    {
        $this->save([$this->block()])->assertSessionHasNoErrors();
        $this->recover(AuditAction::AuthEmailChanged);

        $this->save([$this->block(['account_number' => '5555555555'])])->assertSessionHas('notice');
        $this->assertDatabaseMissing('payment_methods', ['organization_id' => $this->seller->id(), 'account_number' => '5555555555']);
    }

    public function test_ac_26_the_block_lifts_after_the_window(): void
    {
        $this->save([$this->block()])->assertSessionHasNoErrors();
        $this->recover(AuditAction::AuthPasswordReset, CarbonImmutable::now()->subHours(25));

        $this->actingAsTenant($this->seller)->get(route('settings.payment'))
            ->assertInertia(fn ($page) => $page->where('lockedUntil', null));

        $this->save([$this->block(['account_number' => '5555555555'])])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payment_methods', ['organization_id' => $this->seller->id(), 'account_number' => '5555555555']);
    }

    public function test_ac_26_an_ordinary_password_change_does_not_lock(): void
    {
        $this->save([$this->block()])->assertSessionHasNoErrors();
        $this->recover(AuditAction::AuthPasswordChanged);

        $this->save([$this->block(['account_number' => '5555555555'])])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payment_methods', ['organization_id' => $this->seller->id(), 'account_number' => '5555555555']);
    }
}
