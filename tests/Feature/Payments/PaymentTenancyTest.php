<?php

namespace Tests\Feature\Payments;

use App\Bookings\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentRecord;
use App\Policies\PaymentRecordPolicy;
use App\Tenancy\OrganizationOwnedPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-006 section F (AC-27): seller B never reaches A's payment data on any route,
 * and the seat invariant / policy hold (checked after every test via BuildsBookings).
 */
class PaymentTenancyTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        Storage::fake(config('rakyt.media.private_disk'));
        $this->a = $this->seller('kencana', 'Kencana Trip');
        $this->b = $this->seller('bahari', 'Bahari Trip');

        $departure = $this->departureFor($this->a, $this->bookableTrip($this->a), '2024-10-24');
        $this->booking = $this->bookingFor($this->a, $departure, 2, BookingStatus::NeedsReview);
        $path = 'org/'.$this->a->organization->public_id.'/evidence/'.$this->booking->public_id.'/bukti.jpg';
        Storage::disk(config('rakyt.media.private_disk'))->put($path, 'bukti');
        $this->asTenant($this->a, fn () => PaymentRecord::factory()->for($this->booking)->withEvidence($path)->create());
    }

    public function test_ac_27_seller_b_cannot_reach_a_s_verification_routes(): void
    {
        $this->assertCrossTenantNotFound($this->b, [
            ['GET', route('bookings.evidence', $this->booking)],
            ['POST', route('bookings.confirm', $this->booking)],
            ['POST', route('bookings.reject', $this->booking)],
        ], function () {
            $this->assertDatabaseHas('bookings', ['id' => $this->booking->id, 'status' => BookingStatus::NeedsReview->value]);
            $this->assertSame(0, AuditEvent::acrossOrganizations()
                ->whereIn('action', ['payment.confirmed', 'payment.rejected'])
                ->where('subject_id', (string) $this->booking->id)->count());
        });
    }

    public function test_ac_27_the_record_policy_denies_another_organization_as_not_found(): void
    {
        $record = PaymentRecord::acrossOrganizations()->where('booking_id', $this->booking->id)->sole();

        $this->assertInstanceOf(OrganizationOwnedPolicy::class, Gate::getPolicyFor(PaymentRecord::class));

        // A: allowed; B: 404 (denied as not found).
        $this->assertTrue($this->asTenant($this->a, fn () => (new PaymentRecordPolicy)->view($this->a->owner, $record))->allowed());
        $this->assertTrue($this->asTenant($this->b, fn () => (new PaymentRecordPolicy)->view($this->b->owner, $record))->denied());
    }

    public function test_ac_27_the_customer_screen_shows_only_its_own_sellers_methods(): void
    {
        $this->asTenant($this->a, fn () => PaymentMethod::factory()->create(['account_number' => '1111111111']));
        $this->asTenant($this->b, fn () => PaymentMethod::factory()->create(['account_number' => '9999999999']));

        $json = json_encode($this->bookingPageProps('kencana', $this->booking->access_token));

        $this->assertStringContainsString('1111111111', $json);
        $this->assertStringNotContainsString('9999999999', $json);
    }
}
