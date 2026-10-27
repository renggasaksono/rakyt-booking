<?php

namespace Tests\Feature\Admin;

use App\Bookings\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\PaymentRecord;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Organizations\OrganizationStatus;
use App\Organizations\OrganizationSuspension;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use LogicException;
use RuntimeException;
use Tests\Support\BuildsAdmins;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\SuspendsSellers;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-014: the platform admin suspends and reactivates a seller organization
 * from its detail page (`/admin/penjual/{id}`, "Akun"). Each action needs a
 * confirmation and a reason, is audited in the same transaction (F-015) and
 * is idempotent; neither touches trips, departures, bookings or payments.
 * The clock is Thursday 2024-10-10 10:00 WIB.
 */
class SellerSuspensionTest extends TestCase
{
    use BuildsAdmins, BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase, SuspendsSellers;

    private Tenant $a;

    private Tenant $b;

    private User $admin;

    private Trip $trip;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        Storage::fake(config('rakyt.media.private_disk'));
        $this->a = $this->seller('kencana', 'Kencana Trip');
        $this->b = $this->seller('bromo', 'Bromo Adventure');
        $this->admin = $this->platformAdmin(['name' => 'Rengga']);
        $this->trip = $this->bookableTrip($this->a);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
    }

    public function test_ac_1_suspending_records_who_when_and_why_and_one_audit_event(): void
    {
        $this->suspend($this->a, '  Laporan penipuan dari pelanggan  ')
            ->assertStatus(303)
            ->assertRedirect(route('admin.sellers.show', $this->a->id()))
            ->assertSessionHas('toast', 'Akun Kencana Trip ditangguhkan.');

        $organization = $this->a->organization->refresh();
        $this->assertSame(OrganizationStatus::Suspended, $organization->status);
        $this->assertTrue($organization->suspended_at->equalTo(CarbonImmutable::now()));
        $this->assertSame($this->admin->id, $organization->suspended_by);
        $this->assertSame('Laporan penipuan dari pelanggan', $organization->suspended_reason);

        $event = AuditEvent::acrossOrganizations()->sole();
        $this->assertSame('organization.suspended', $event->action);
        $this->assertSame('platform_admin', $event->actor_type);
        $this->assertSame((string) $this->admin->id, $event->actor_id);
        $this->assertSame($organization->getMorphClass(), $event->subject_type);
        $this->assertSame((string) $this->a->id(), $event->subject_id);
        $this->assertSame($this->a->id(), $event->organization_id);
        $this->assertSame('Laporan penipuan dari pelanggan', $event->reason);
        $this->assertSame(['status' => 'active'], $event->before);
        $this->assertSame(['status' => 'suspended'], $event->after);
        $this->assertNotNull($event->created_at);
        $this->assertSame('127.0.0.1', $event->ip);

        // Only the suspended organization changes.
        $this->assertSame(OrganizationStatus::Active, $this->b->organization->refresh()->status);
    }

    public function test_ac_1_ac_8_reactivating_clears_the_suspension_and_writes_one_audit_event(): void
    {
        $this->suspend($this->a);

        $this->reactivate($this->a, 'Masalah sudah selesai')
            ->assertStatus(303)
            ->assertRedirect(route('admin.sellers.show', $this->a->id()))
            ->assertSessionHas('toast', 'Akun Kencana Trip aktif kembali.');

        $organization = $this->a->organization->refresh();
        $this->assertSame(OrganizationStatus::Active, $organization->status);
        $this->assertNull($organization->suspended_at);
        $this->assertNull($organization->suspended_by);
        $this->assertNull($organization->suspended_reason);

        $event = AuditEvent::acrossOrganizations()->where('action', 'organization.reactivated')->sole();
        $this->assertSame('platform_admin', $event->actor_type);
        $this->assertSame((string) $this->admin->id, $event->actor_id);
        $this->assertSame($this->a->id(), $event->organization_id);
        $this->assertSame('Masalah sudah selesai', $event->reason);
        $this->assertSame(['status' => 'suspended'], $event->before);
        $this->assertSame(['status' => 'active'], $event->after);
        $this->assertSame(2, AuditEvent::acrossOrganizations()->count());
    }

    public function test_ac_1_both_actions_require_a_reason(): void
    {
        foreach ([[], ['alasan' => ''], ['alasan' => "  \n "], ['alasan' => ['x']]] as $payload) {
            $this->actingAsConfirmedAdmin($this->admin)
                ->from(route('admin.sellers.show', $this->a->id()))
                ->post(route('admin.sellers.suspend', $this->a->id()), $payload)
                ->assertRedirect(route('admin.sellers.show', $this->a->id()))
                ->assertSessionHasErrors('alasan');
        }

        $this->actingAsConfirmedAdmin($this->admin)
            ->post(route('admin.sellers.suspend', $this->a->id()), ['alasan' => str_repeat('a', 501)])
            ->assertSessionHasErrors(['alasan' => 'Alasan maksimal 500 karakter.']);

        $this->assertSame(OrganizationStatus::Active, $this->a->organization->refresh()->status);

        $this->suspendOrganization($this->a);
        $this->actingAsConfirmedAdmin($this->admin)
            ->post(route('admin.sellers.reactivate', $this->a->id()), ['alasan' => ' '])
            ->assertSessionHasErrors(['alasan' => 'Isi alasannya.']);

        $this->assertSame(OrganizationStatus::Suspended, $this->a->organization->refresh()->status);
        $this->assertSame(0, AuditEvent::acrossOrganizations()->count());

        // The service refuses a blank reason too, whatever the caller.
        $this->expectException(LogicException::class);
        app(OrganizationSuspension::class)->reactivate($this->a->organization, $this->admin, '   ');
    }

    public function test_ac_1_a_reason_of_exactly_500_characters_is_kept_whole(): void
    {
        $reason = str_repeat('a', 500);

        $this->suspend($this->a, $reason)->assertSessionHasNoErrors();

        $this->assertSame($reason, $this->a->organization->refresh()->suspended_reason);
        $this->assertSame($reason, AuditEvent::acrossOrganizations()->sole()->reason);
    }

    public function test_ac_1_the_status_change_does_not_survive_a_failed_audit_write(): void
    {
        AuditEvent::creating(fn () => throw new RuntimeException('audit store down'));

        try {
            app(OrganizationSuspension::class)->suspend($this->a->organization, $this->admin, 'Laporan penipuan');
            $this->fail('The suspension should fail when its audit event cannot be written.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit store down', $e->getMessage());
        }

        $row = DB::table('organizations')->where('id', $this->a->id())->first();
        $this->assertSame('active', $row->status);
        $this->assertNull($row->suspended_at);
        $this->assertNull($row->suspended_by);
        $this->assertNull($row->suspended_reason);
    }

    public function test_ac_1_the_page_asks_for_confirmation_and_a_reason_and_no_action_is_gold(): void
    {
        $this->showPage()->assertInertia(fn ($page) => $page
            ->component('Admin/Sellers/Show')
            ->where('suspension', null)
            ->where('urls.suspend', route('admin.sellers.suspend', $this->a->id()))
            ->where('urls.reactivate', route('admin.sellers.reactivate', $this->a->id()))
            ->where('copy.suspension.suspend', 'Tangguhkan akun')
            ->has('copy.suspension.suspend_effects', 5)
            ->where('reason_max', 500)
        );

        $vue = file_get_contents(resource_path('js/pages/Admin/Sellers/Show.vue'));
        // A sheet with the effects and a required reason confirms each action.
        $this->assertStringContainsString('<BottomSheet', $vue);
        $this->assertStringContainsString('v-model="form.alasan"', $vue);
        $this->assertStringContainsString(':disabled="!canSubmit"', $vue);
        // Suspending is destructive and reactivating is ink: neither is the gold CTA.
        $this->assertStringNotContainsString('bg-prada', $vue);
        $this->assertStringContainsString('bg-status-rejected', $vue);
    }

    public function test_ac_1_the_page_shows_who_suspended_when_and_why(): void
    {
        $this->suspend($this->a, 'Laporan penipuan dari pelanggan');

        $this->showPage()->assertInertia(fn ($page) => $page
            ->where('organization.status', 'suspended')
            ->where('suspension.at', '2024-10-10T03:00:00Z')
            ->where('suspension.at_label', '10 Okt 2024 10.00 WIB')
            ->where('suspension.by', 'Rengga')
            ->where('suspension.reason', 'Laporan penipuan dari pelanggan')
        );
    }

    public function test_suspending_twice_or_reactivating_an_active_seller_changes_nothing(): void
    {
        $this->suspend($this->a, 'Pertama');
        $first = $this->a->organization->refresh()->only(['status', 'suspended_at', 'suspended_by', 'suspended_reason', 'updated_at']);
        $this->travel(5)->minutes();

        // A second tab or a double tap: no change, no second event, and the admin is told.
        $this->suspend($this->a, 'Kedua')
            ->assertRedirect(route('admin.sellers.show', $this->a->id()))
            ->assertSessionHas('notice', 'Akun ini sudah ditangguhkan. Tidak ada yang berubah.')
            ->assertSessionMissing('toast');

        $this->assertEquals($first, $this->a->organization->refresh()->only(array_keys($first)));
        $this->assertSame(1, AuditEvent::acrossOrganizations()->count());

        $this->reactivate($this->b, 'Tidak perlu')
            ->assertSessionHas('notice', 'Akun ini sudah aktif. Tidak ada yang berubah.');
        $this->reactivate($this->a, 'Selesai');
        $this->reactivate($this->a, 'Selesai lagi')
            ->assertSessionHas('notice', 'Akun ini sudah aktif. Tidak ada yang berubah.');

        $this->assertSame(
            ['organization.suspended', 'organization.reactivated'],
            AuditEvent::acrossOrganizations()->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_ac_9_only_the_platform_admin_can_suspend_or_reactivate(): void
    {
        $routes = ['admin.sellers.suspend', 'admin.sellers.reactivate'];

        // Guest → sign-in.
        foreach ($routes as $route) {
            $this->post(route($route, $this->a->id()), ['alasan' => 'x'])->assertRedirect(route('login'));
        }

        foreach ($routes as $route) {
            $url = route($route, $this->a->id());

            // Seller (even of this organization) → 403, HTML or JSON; an admin
            // who has not passed TOTP → the challenge.
            $this->actingAsTenant($this->a)->post($url, ['alasan' => 'x'])->assertForbidden();
            $this->actingAsTenant($this->b)->postJson($url, ['alasan' => 'x'])->assertForbidden();
            $this->actingAs($this->admin)->post($url, ['alasan' => 'x'])->assertRedirect(route('admin.two-factor.challenge'));
        }

        $this->assertSame(OrganizationStatus::Active, $this->a->organization->refresh()->status);
        $this->assertSame(0, AuditEvent::acrossOrganizations()->count());
    }

    public function test_an_unknown_organization_is_not_found(): void
    {
        foreach (['999999', 'kencana', '1e3'] as $id) {
            $this->actingAsConfirmedAdmin($this->admin)
                ->post(route('admin.sellers.suspend', $id), ['alasan' => 'x'])
                ->assertNotFound();
        }

        $this->assertSame(0, AuditEvent::acrossOrganizations()->count());
    }

    public function test_the_status_columns_are_never_mass_assignable(): void
    {
        foreach (['status', 'suspended_at', 'suspended_by', 'suspended_reason'] as $column) {
            $this->assertTrue((new Organization)->isGuarded($column), "{$column} must be guarded (F-014).");
        }
    }

    public function test_ac_2_ac_7_suspending_and_reactivating_change_no_trip_departure_booking_or_payment(): void
    {
        Notification::fake();
        Mail::fake();

        $this->asTenant($this->a, fn () => PaymentMethod::factory()->create());
        $later = $this->departureFor($this->a, $this->trip, '2024-11-07');
        $holding = $this->bookingFor($this->a, $this->departure, 2, attributes: [
            'created_at' => CarbonImmutable::now(),
            'hold_expires_at' => CarbonImmutable::now()->addMinutes(120),
        ]);
        $review = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::NeedsReview);
        $paid = $this->bookingFor($this->a, $later, 3, BookingStatus::Paid);
        $this->bookingFor($this->a, $later, 1, BookingStatus::Cancelled);
        $this->asTenant($this->a, function () use ($review, $paid) {
            PaymentRecord::factory()->withEvidence('org/a/evidence/review.jpg')->create(['booking_id' => $review->id]);
            PaymentRecord::factory()->withEvidence('org/a/evidence/paid.jpg')->create(['booking_id' => $paid->id]);
        });

        $before = $this->snapshot();
        $organization = $this->organizationRow();

        $this->suspend($this->a)->assertSessionHasNoErrors();
        $this->travel(1)->hours();

        $this->assertEquals($before, $this->snapshot(), 'Suspending must not change any seller data.');
        // On the organization itself, only the status and its who / when / why move.
        $this->assertEquals($organization, $this->organizationRow());

        $this->reactivate($this->a)->assertSessionHasNoErrors();

        $this->assertEquals($before, $this->snapshot(), 'Reactivating must not change any seller data.');
        $this->assertEquals($organization, $this->organizationRow());

        // Paid and upcoming bookings are never cancelled and nobody is notified (AC-7).
        $this->assertSame(BookingStatus::Paid, Booking::acrossOrganizations()->findOrFail($paid->id)->status);
        $this->assertSame(BookingStatus::AwaitingPayment, Booking::acrossOrganizations()->findOrFail($holding->id)->status);
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertSame(
            ['organization.suspended', 'organization.reactivated'],
            AuditEvent::acrossOrganizations()->orderBy('id')->pluck('action')->all(),
        );
    }

    /**
     * Every seller-owned table, row by row.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        return collect(['trips', 'trip_departures', 'trip_photos', 'bookings', 'payment_records', 'payment_methods', 'organization_members', 'users'])
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all()])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationRow(): array
    {
        return collect((array) DB::table('organizations')->where('id', $this->a->id())->first())
            ->except(['status', 'suspended_at', 'suspended_by', 'suspended_reason', 'updated_at'])
            ->all();
    }

    private function suspend(Tenant $tenant, string $reason = 'Laporan penipuan dari pelanggan'): TestResponse
    {
        return $this->actingAsConfirmedAdmin($this->admin)
            ->post(route('admin.sellers.suspend', $tenant->id()), ['alasan' => $reason]);
    }

    private function reactivate(Tenant $tenant, string $reason = 'Masalah sudah selesai'): TestResponse
    {
        return $this->actingAsConfirmedAdmin($this->admin)
            ->post(route('admin.sellers.reactivate', $tenant->id()), ['alasan' => $reason]);
    }

    private function showPage(): TestResponse
    {
        return $this->actingAsConfirmedAdmin($this->admin)->get(route('admin.sellers.show', $this->a->id()));
    }
}
