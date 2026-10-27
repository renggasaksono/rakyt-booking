<?php

namespace Tests\Feature\Admin;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Bookings\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\OrganizationMember;
use App\Models\PaymentRecord;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Organizations\OrganizationStatus;
use App\Tenancy\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsAdmins;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-012: an organization's detail page in the admin seller directory
 * (`/admin/penjual/{id}`) — profile, status, plan, registration, last
 * activity, members, trips, departures with F-007's seat counts, recent
 * bookings with gated payment evidence, and the organization's audit events.
 * Read-only. The clock starts at Thursday 2024-10-10 10:00 WIB.
 */
class AdminSellerDetailTest extends TestCase
{
    use BuildsAdmins, BuildsBookings, BuildsDashboard, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    private User $admin;

    private Trip $trip;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('rakyt.media.private_disk'));
        $this->freezeClock();
        $this->a = $this->seller('kencana', 'Kencana Trip', ['bio' => 'Open trip Karimunjawa sejak 2015.']);
        $this->a->owner->forceFill(['name' => 'Agus', 'email' => 'agus@example.com'])->save();
        $this->b = $this->seller('bromo', 'Bromo Adventure');
        $this->admin = $this->platformAdmin(['name' => 'Rengga']);
        $this->trip = $this->bookableTrip($this->a, ['name' => 'Karimunjawa 3H2M Open Trip']);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24');
    }

    public function test_ac_6_the_detail_page_and_evidence_are_behind_the_admin_gate(): void
    {
        $booking = $this->bookingWithEvidence();

        $urls = [
            route('admin.sellers.show', $this->a->id()),
            route('admin.sellers.evidence', [$this->a->id(), $booking->public_id]),
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        foreach ($urls as $url) {
            // The seller is forbidden even for their own organization, HTML or JSON.
            $this->actingAsTenant($this->a)->get($url)->assertForbidden()->assertDontSee('Kencana Trip');
            $this->actingAsTenant($this->a)->getJson($url)->assertForbidden()->assertDontSee('Kencana Trip');
            $this->actingAs($this->admin)->get($url)->assertRedirect(route('admin.two-factor.challenge'));
        }

        $this->showPage()
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->component('Admin/Sellers/Show'));
    }

    public function test_an_unknown_organization_is_not_found(): void
    {
        $this->actingAsConfirmedAdmin($this->admin)->get(route('admin.sellers.show', 999_999))->assertNotFound();
        $this->actingAsConfirmedAdmin($this->admin)->get('/admin/penjual/kencana')->assertNotFound();
        $this->actingAsConfirmedAdmin($this->admin)->get('/admin/penjual/9999999999999999999999')->assertNotFound();
    }

    public function test_ac_3_profile_status_plan_registration_and_last_activity(): void
    {
        $this->a->owner->forceFill(['last_login_at' => CarbonImmutable::parse('2024-10-09 20:15', 'Asia/Jakarta')->utc()])->save();

        $this->showPage()->assertInertia(fn ($page) => $page
            ->where('organization.id', $this->a->id())
            ->where('organization.name', 'Kencana Trip')
            ->where('organization.slug', 'kencana')
            ->where('organization.public_url', 'https://rakyt.id/kencana')
            ->where('organization.whatsapp', '+6281277881020')
            ->where('organization.city', 'Jepara, Jawa Tengah')
            ->where('organization.bio', 'Open trip Karimunjawa sejak 2015.')
            ->where('organization.status', 'active')
            ->where('organization.status_label', 'Aktif')
            ->where('organization.plan_label', 'Gratis')
            ->where('organization.registered_label', '10 Okt 2024')
            ->where('last_activity.at', '2024-10-09T13:15:00Z')
            ->where('last_activity.label', '9 Okt 2024 20.15 WIB')
        );
    }

    public function test_ac_3_an_owner_who_never_signed_in_shows_belum_pernah_masuk(): void
    {
        $this->showPage()->assertInertia(fn ($page) => $page
            ->where('last_activity.at', null)
            ->where('last_activity.label', 'Belum pernah masuk')
        );
    }

    public function test_ac_3_a_suspended_organization_shows_its_status(): void
    {
        $this->a->organization->forceFill(['status' => OrganizationStatus::Suspended])->save();

        $this->showPage()->assertInertia(fn ($page) => $page
            ->where('organization.status', 'suspended')
            ->where('organization.status_label', 'Ditangguhkan')
        );
    }

    public function test_ac_3_members_with_their_last_sign_in(): void
    {
        $partner = User::factory()->create(['name' => 'Budi', 'email' => 'budi@example.com']);
        $partner->forceFill(['last_login_at' => CarbonImmutable::parse('2024-10-10 08:00', 'Asia/Jakarta')->utc()])->save();
        $this->asTenant($this->a, fn () => OrganizationMember::factory()->create([
            'organization_id' => $this->a->id(),
            'user_id' => $partner->id,
        ]));

        $this->showPage()->assertInertia(fn ($page) => $page
            ->has('members', 2)
            ->where('members.0.name', 'Agus')
            ->where('members.0.email', 'agus@example.com')
            ->where('members.0.role', 'owner')
            ->where('members.0.role_label', 'Pemilik')
            ->where('members.0.last_login_label', 'Belum pernah masuk')
            ->where('members.1.name', 'Budi')
            ->where('members.1.last_login_label', '10 Okt 2024 08.00 WIB')
        );
    }

    public function test_ac_3_trips_departures_and_recent_bookings_of_this_organization_only(): void
    {
        $draft = $this->tripFor($this->a, ['name' => 'Bromo Sunrise Draf']);
        $this->bookingFor($this->a, $this->departure, 2, attributes: ['customer_name' => 'Siti']);

        // Another seller's records never leak into this page.
        $otherTrip = $this->bookableTrip($this->b, ['name' => 'Trip Seller Lain']);
        $otherDeparture = $this->departureFor($this->b, $otherTrip, '2024-10-24');
        $this->bookingFor($this->b, $otherDeparture, 1, attributes: ['customer_name' => 'Pelanggan Lain']);

        $response = $this->showPage();
        $response->assertDontSee('Trip Seller Lain')->assertDontSee('Pelanggan Lain');
        $response->assertInertia(fn ($page) => $page
            ->has('trips.items', 2)
            ->where('trips.items.0.name', 'Bromo Sunrise Draf')
            ->where('trips.items.0.status', 'draft')
            ->where('trips.items.0.status_label', 'Draf')
            ->where('trips.items.1.name', 'Karimunjawa 3H2M Open Trip')
            ->where('trips.items.1.status_label', 'Tampil')
            ->where('trips.items.1.departures_count', 1)
            ->where('trips.more', false)
            ->has('departures.items', 1)
            ->where('departures.items.0.trip_name', 'Karimunjawa 3H2M Open Trip')
            ->where('departures.items.0.date_label', '24–26 Okt')
            ->where('departures.items.0.status', 'open')
            ->where('departures.items.0.status_label', 'Buka')
            ->has('bookings', 1)
            ->where('bookings.0.customer_name', 'Siti')
            ->where('bookings.0.trip_name', 'Karimunjawa 3H2M Open Trip')
            ->where('bookings.0.departure_label', '24–26 Okt')
            ->where('bookings.0.participants', 2)
            ->where('bookings.0.total_label', 'Rp2.500.000')
            ->where('bookings.0.status', 'awaiting_payment')
            ->where('bookings.0.status_label', 'Menunggu pembayaran')
            ->where('bookings.0.evidence_url', null)
        );

        $this->assertNotNull($draft);
    }

    public function test_ac_3_departures_are_upcoming_nearest_first_and_bookings_newest_first(): void
    {
        $this->departureFor($this->a, $this->trip, '2024-10-01'); // past: not listed
        $later = $this->departureFor($this->a, $this->trip, '2024-11-07');
        $first = $this->bookingFor($this->a, $this->departure, 1, attributes: ['customer_name' => 'Pertama']);
        $this->travel(5)->minutes();
        $second = $this->bookingFor($this->a, $later, 1, attributes: ['customer_name' => 'Kedua']);

        $this->showPage()->assertInertia(fn ($page) => $page
            ->has('departures.items', 2)
            ->where('departures.items.0.id', $this->departure->public_id)
            ->where('departures.items.1.id', $later->public_id)
            ->where('bookings.0.reference', $second->reference)
            ->where('bookings.1.reference', $first->reference)
        );
    }

    public function test_ac_4_seat_summary_uses_the_seller_dashboard_counts(): void
    {
        $this->bookingFor($this->a, $this->departure, 12, BookingStatus::Paid);
        $this->bookingFor($this->a, $this->departure, 1);
        $this->setStatus($this->bookingFor($this->a, $this->departure, 1), BookingStatus::NeedsReview);
        $this->bookingFor($this->a, $this->departure, 3, BookingStatus::Cancelled);
        $this->expiredHoldFor($this->a, $this->departure, 1);

        $dashboard = $this->todayProps($this->a)['departures'][0]['seats'];
        $admin = $this->propsOf($this->showPage(), 'Admin/Sellers/Show')['departures']['items'][0]['seats'];

        $this->assertSame(['capacity' => 16, 'paid' => 12, 'held' => 3, 'empty' => 1], $dashboard);
        $this->assertSame($dashboard, $admin);
    }

    public function test_ac_5_recent_audit_events_of_this_organization_newest_first(): void
    {
        $older = $this->record(AuditAction::OrganizationSlugChanged, $this->a->organization, Actor::platformAdmin($this->admin), reason: 'Permintaan penjual');
        $this->travel(1)->minutes();
        $newer = $this->record(AuditAction::TripPublished, $this->trip);
        $this->record(AuditAction::OrganizationSlugChanged, $this->b->organization, reason: 'ALASAN-SELLER-LAIN');

        $response = $this->showPage();
        $response->assertDontSee('ALASAN-SELLER-LAIN');
        $response->assertInertia(fn ($page) => $page
            ->has('audit_events', 2)
            ->where('audit_events.0.id', $newer->id)
            ->where('audit_events.0.action_label', __('admin.audit.actions.trip.published'))
            ->where('audit_events.1.id', $older->id)
            ->where('audit_events.1.actor.name', 'Rengga')
            ->where('audit_events.1.reason', 'Permintaan penjual')
            ->where('urls.audit', route('admin.audit.index', ['organisasi' => $this->a->id()]))
        );
    }

    public function test_ac_5_a_seller_session_never_sees_the_audit_events(): void
    {
        $this->record(AuditAction::OrganizationSlugChanged, $this->a->organization, Actor::platformAdmin($this->admin), reason: 'ALASAN-ADMIN');

        foreach (['/hari-ini', '/pesanan', '/pengaturan', route('admin.sellers.show', $this->a->id())] as $url) {
            $this->assertStringNotContainsString('ALASAN-ADMIN', (string) $this->actingAsTenant($this->a)->get($url)->getContent(), $url);
        }
    }

    public function test_ac_7_payment_evidence_opens_only_through_a_short_lived_signed_url(): void
    {
        $booking = $this->bookingWithEvidence();
        $issue = route('admin.sellers.evidence', [$this->a->id(), $booking->public_id]);

        $this->showPage()->assertInertia(fn ($page) => $page->where('bookings.0.evidence_url', $issue));

        // The page links to an issuing route that hands the admin a fresh signed URL.
        $signed = $this->actingAsConfirmedAdmin($this->admin)->get($issue)
            ->assertRedirect()
            ->headers->get('Location');
        $this->assertStringContainsString('signature=', $signed);
        $this->assertStringContainsString('expires=', $signed);

        $this->actingAsConfirmedAdmin($this->admin)->get($signed)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        // Tampered or unsigned: refused.
        $this->actingAsConfirmedAdmin($this->admin)->get($signed.'x')->assertForbidden();
        $this->actingAsConfirmedAdmin($this->admin)->get(strtok($signed, '?'))->assertForbidden();

        // Short-lived: the signature expires within minutes.
        $this->travel(config('rakyt.media.private_url_ttl') + 1)->minutes();
        $this->actingAsConfirmedAdmin($this->admin)->get($signed)->assertForbidden();

        // A signed URL is still only for the admin.
        $this->actingAsTenant($this->a)->get($signed)->assertForbidden();
    }

    public function test_ac_7_evidence_of_another_organization_or_without_a_file_is_not_found(): void
    {
        $theirs = $this->bookingWithEvidence($this->b);
        $none = $this->bookingFor($this->a, $this->departure, 1);

        $this->actingAsConfirmedAdmin($this->admin)
            ->get(route('admin.sellers.evidence', [$this->a->id(), $theirs->public_id]))
            ->assertNotFound();
        $this->actingAsConfirmedAdmin($this->admin)
            ->get(route('admin.sellers.evidence', [$this->a->id(), $none->public_id]))
            ->assertNotFound();
        $this->actingAsConfirmedAdmin($this->admin)
            ->get(route('admin.sellers.evidence', [$this->a->id(), 'bukan-id']))
            ->assertNotFound();
    }

    public function test_ac_7_evidence_paths_never_reach_the_page(): void
    {
        $this->bookingWithEvidence(path: 'org/rahasia/evidence/bukti.jpg');

        $this->showPage()->assertDontSee('org/rahasia', false)->assertDontSee('bukti.jpg', false);
    }

    public function test_ac_3_the_directory_is_read_only(): void
    {
        $writes = collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with((string) $route->getName(), 'admin.sellers.'))
            ->reject(fn (Route $route) => $route->methods() === ['GET', 'HEAD'])
            ->map(fn (Route $route) => $route->getName())
            ->values()
            ->all();

        $this->assertSame([], $writes);
    }

    public function test_the_page_runs_a_fixed_number_of_queries(): void
    {
        $this->bookingFor($this->a, $this->departure, 1);
        $baseline = count($this->queriesOf(fn () => $this->showPage()->assertOk()));

        foreach (range(1, 5) as $i) {
            $trip = $this->bookableTrip($this->a, ['name' => "Trip {$i}"]);
            $departure = $this->departureFor($this->a, $trip, '2024-10-2'.$i);
            $this->bookingWithEvidence(departure: $departure);
            $this->record(AuditAction::TripPublished, $trip);
        }

        $this->assertSame($baseline, count($this->queriesOf(fn () => $this->showPage()->assertOk())));
    }

    public function test_ac_8_the_detail_page_is_phone_first(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Admin/Sellers/Show.vue'));

        $this->assertStringNotContainsString('<table', $vue);
        $this->assertStringContainsString('min-h-11', $vue);
    }

    private function showPage()
    {
        return $this->actingAsConfirmedAdmin($this->admin)->get(route('admin.sellers.show', $this->a->id()));
    }

    private function bookingWithEvidence(?Tenant $tenant = null, ?TripDeparture $departure = null, string $path = 'org/test/evidence/bukti.jpg'): Booking
    {
        $tenant ??= $this->a;

        if ($departure === null) {
            $departure = $tenant === $this->a
                ? $this->departure
                : $this->departureFor($tenant, $this->bookableTrip($tenant), '2024-10-24');
        }

        $booking = $this->bookingFor($tenant, $departure, 1);
        Storage::disk(config('rakyt.media.private_disk'))->put($path, 'jpeg-bytes');
        $this->asTenant($tenant, fn () => PaymentRecord::factory()->withEvidence($path)->create(['booking_id' => $booking->id]));

        return $booking;
    }

    private function record(AuditAction $action, Model $subject, ?Actor $actor = null, ?string $reason = null): AuditEvent
    {
        app(CurrentOrganization::class)->set(null);

        return DB::transaction(fn () => app(AuditLog::class)->record($action, $subject, [], [], $actor ?? Actor::system('test'), $reason));
    }
}
