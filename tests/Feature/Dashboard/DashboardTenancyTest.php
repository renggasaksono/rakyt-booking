<?php

namespace Tests\Feature\Dashboard;

use App\Bookings\BookingStatus;
use App\Logging\RedactSensitiveData;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Policies\BookingPolicy;
use App\Tenancy\OrganizationOwnedPolicy;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-007 section F: tenancy (AC-34, F-009 A9), guests and other roles (AC-35),
 * exact props (AC-36), rendering and logs (AC-37). The clock starts at
 * Thursday 2024-10-10 10:00 WIB.
 */
class DashboardTenancyTest extends TestCase
{
    use BuildsBookings, BuildsDashboard, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    private Trip $bTrip;

    private TripDeparture $bDeparture;

    private Booking $bBooking;

    private Booking $aBooking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller();
        $this->b = $this->seller('bahari', 'Bahari Trip');
        $aDeparture = $this->departureFor($this->a, $this->bookableTrip($this->a), '2024-10-24');
        $this->aBooking = $this->bookingFor($this->a, $aDeparture, 1, attributes: ['customer_name' => 'Agus Pelanggan', 'customer_phone' => '+6281311112222', 'reference' => 'RKB-KCN-1024-0001']);
        $this->bTrip = $this->bookableTrip($this->b, ['name' => 'Bahari Island Hopping']);
        $this->bDeparture = $this->departureFor($this->b, $this->bTrip, '2024-10-20');
        $this->bBooking = $this->bookingFor($this->b, $this->bDeparture, 2, attributes: ['customer_name' => 'Siti Pelanggan B', 'customer_phone' => '+6285799998888', 'reference' => 'RKB-BHR-1020-0007']);
        $this->setStatus($this->bookingFor($this->b, $this->bDeparture, 1), BookingStatus::NeedsReview);
    }

    public function test_ac_34_hari_ini_nav_and_list_never_include_another_sellers_data(): void
    {
        $today = $this->todayProps($this->a);
        $json = json_encode($today);

        $this->assertSame(1, $today['action_needed']['count']);
        $this->assertSame(['RKB-KCN-1024-0001'], $this->references($today['action_needed']['items']));
        $this->assertSame(['RKB-KCN-1024-0001'], $this->references($today['recent']));
        $this->assertSame(1, count($today['departures']));
        $this->assertSame(1, collect($today['seller']['nav'])->firstWhere('key', 'bookings')['badge']['count']);
        foreach (['Siti Pelanggan B', 'RKB-BHR', 'Bahari', $this->bDeparture->public_id, $this->bBooking->public_id] as $leak) {
            $this->assertStringNotContainsString($leak, $json, $leak);
        }

        $this->assertSame(['RKB-KCN-1024-0001'], $this->references($this->listProps($this->a)['bookings']));
        foreach (['Siti', '085799998888', '8888', 'RKB-BHR-1020-0007', '0007'] as $term) {
            $this->assertSame([], $this->listProps($this->a, ['cari' => $term])['bookings'], $term);
        }
    }

    public function test_ac_34_another_sellers_ids_behave_as_unknown_ones(): void
    {
        foreach ([['trip' => $this->bTrip->public_id], ['keberangkatan' => $this->bDeparture->public_id]] as $query) {
            $props = $this->listProps($this->a, $query);

            $this->assertSame(['RKB-KCN-1024-0001'], $this->references($props['bookings']));
            $this->assertNull($props['options']['departure']);
            $this->assertNotContains('Bahari Island Hopping', array_column($props['options']['trips'], 'name'));
        }
    }

    public function test_ac_34_detail_cancel_and_reminder_on_another_sellers_booking_are_404(): void
    {
        $this->assertCrossTenantNotFound($this->a, [
            ['GET', '/pesanan/'.$this->bBooking->public_id],
            ['POST', '/pesanan/'.$this->bBooking->public_id.'/batalkan'],
            ['GET', '/pesanan/'.$this->bBooking->public_id.'/ingatkan'],
        ], function () {
            $this->assertSame(BookingStatus::AwaitingPayment, Booking::acrossOrganizations()->findOrFail($this->bBooking->id)->status);
            $this->assertSame(3, TripDeparture::acrossOrganizations()->findOrFail($this->bDeparture->id)->seats_taken);
            $this->assertSame(0, AuditEvent::acrossOrganizations()->where('action', 'booking.cancelled')->count());
        });
    }

    public function test_ac_34_the_policy_extends_organization_owned_and_answers_404(): void
    {
        $this->assertTrue(is_subclass_of(BookingPolicy::class, OrganizationOwnedPolicy::class));
        $this->assertSame(BookingPolicy::class, get_class(Gate::getPolicyFor(Booking::class)));

        $this->asTenant($this->a, function () {
            $this->assertTrue(Gate::forUser($this->a->owner)->inspect('cancel', $this->bBooking)->denied());
            $this->assertSame(404, Gate::forUser($this->a->owner)->inspect('view', $this->bBooking)->status());
            $this->assertTrue(Gate::forUser($this->a->owner)->inspect('cancel', $this->aBooking)->allowed());
        });
    }

    public function test_ac_35_guests_go_to_sign_in_and_users_without_an_organization_to_onboarding(): void
    {
        $routes = ['/hari-ini', '/dashboard', '/pesanan', '/pesanan/'.$this->aBooking->public_id, '/pesanan/'.$this->aBooking->public_id.'/ingatkan'];

        foreach ($routes as $uri) {
            $this->get($uri)->assertRedirect(route('login'));
        }
        $this->post('/pesanan/'.$this->aBooking->public_id.'/batalkan')->assertRedirect(route('login'));

        $orphan = User::factory()->create();
        foreach (['/hari-ini', '/pesanan'] as $uri) {
            $this->actingAs($orphan)->get($uri)->assertRedirect(route('onboarding.organization'));
        }
        // A booking URL resolves through the organization scope first: without one it does not exist.
        $this->actingAs($orphan)->get('/pesanan/'.$this->aBooking->public_id)->assertNotFound();
        $this->actingAs($orphan)->post('/pesanan/'.$this->aBooking->public_id.'/batalkan')->assertNotFound();
        $this->assertSame(BookingStatus::AwaitingPayment, Booking::acrossOrganizations()->findOrFail($this->aBooking->id)->status);

        // Nothing here lives under /admin (ADR-0011).
        foreach (['dashboard', 'bookings.index', 'bookings.show', 'bookings.cancel', 'bookings.remind'] as $name) {
            $this->assertStringNotContainsString('/admin', route($name, ['booking' => $this->aBooking->public_id]));
        }
    }

    public function test_ac_36_hari_ini_props_are_exactly_the_listed_keys(): void
    {
        $props = $this->todayProps($this->a);

        $this->assertSame(['greeting', 'organization', 'public_url', 'action_needed', 'recent', 'departures', 'context', 'copy', 'urls'], array_values(array_diff(array_keys($props), ['errors', 'flash', 'seller', 'account'])));
        $this->assertSame(['text', 'date'], array_keys($props['greeting']));
        $this->assertSame(['name', 'slug', 'plan_label'], array_keys($props['organization']));
        $this->assertSame(['count', 'items'], array_keys($props['action_needed']));
        $this->assertSame(['new_trip', 'bookings', 'action_needed', 'departures', 'trips', 'public_page'], array_keys($props['urls']));
        $this->assertSame([
            'public_id', 'reference', 'customer_name', 'participants', 'total_amount', 'status', 'hold_expires_at',
            'created_at', 'trip', 'departure', 'urls', 'whatsapp_reminder_url',
        ], array_keys($props['action_needed']['items'][0]));
        $this->assertSame(array_keys($props['action_needed']['items'][0]), array_keys($props['recent'][0]));
        $departure = $props['departures'][0];
        $this->assertSame(['trip', 'seats', 'closes_on'], array_slice(array_keys($departure), -3));
        $this->assertSame(['capacity', 'paid', 'held', 'empty'], array_keys($departure['seats']));

        $this->assertNoSecrets($props, withToken: false);
    }

    public function test_ac_36_list_and_detail_props_are_exactly_the_listed_keys(): void
    {
        $list = $this->listProps($this->a);
        $this->assertSame(['bookings', 'filters', 'options', 'pagination', 'has_any', 'context', 'copy', 'urls'], array_values(array_diff(array_keys($list), ['errors', 'flash', 'seller', 'account'])));
        $this->assertSame(['cari', 'status', 'trip', 'dari', 'sampai', 'keberangkatan'], array_keys($list['filters']));
        $this->assertSame(['statuses', 'trips', 'departure'], array_keys($list['options']));
        $this->assertSame(['page', 'next_url', 'prev_url'], array_keys($list['pagination']));
        $this->assertArrayNotHasKey('whatsapp_reminder_url', $list['bookings'][0]);
        $this->assertNoSecrets($list, withToken: false);

        $detail = $this->detailProps($this->a, $this->aBooking);
        $this->assertSame(['booking', 'trip', 'meeting', 'whatsapp', 'context', 'copy', 'urls'], array_values(array_diff(array_keys($detail), ['errors', 'flash', 'seller', 'account'])));
        $this->assertSame(['chat_url', 'reminder_url', 'cancelled_url'], array_keys($detail['whatsapp']));
        $this->assertSame(['now', 'timezone', 'today'], array_keys($detail['context']));
        $this->assertSame(['back', 'cancel'], array_keys($detail['urls']));
        $this->assertNoSecrets($detail, withToken: true);
        $this->assertStringContainsString($this->aBooking->access_token, json_encode($detail, JSON_UNESCAPED_SLASHES));
    }

    public function test_ac_36_ac_24_the_token_is_never_in_the_list_or_hari_ini_logs_or_audit(): void
    {
        $token = $this->aBooking->access_token;

        $this->assertStringNotContainsString($token, json_encode($this->todayProps($this->a)));
        $this->assertStringNotContainsString($token, json_encode($this->listProps($this->a)));

        $this->actingAsTenant($this->a)->post('/pesanan/'.$this->aBooking->public_id.'/batalkan');
        $this->assertStringNotContainsString($token, json_encode(AuditEvent::acrossOrganizations()->get()->toArray()));
    }

    public function test_ac_37_names_and_notes_render_as_text_and_pages_are_not_stored(): void
    {
        foreach (['pages/Dashboard/Today', 'pages/Bookings/Index', 'pages/Bookings/Show', 'components/BookingRow', 'components/LinkCard', 'components/SeatBar'] as $file) {
            $this->assertStringNotContainsString('v-html', file_get_contents(resource_path("js/{$file}.vue")), $file);
        }
        $this->assertStringContainsString('whitespace-pre-line', file_get_contents(resource_path('js/pages/Bookings/Show.vue')));

        foreach (['/pesanan', '/pesanan/'.$this->aBooking->public_id, '/hari-ini'] as $uri) {
            $this->assertSame('no-store, private', $this->actingAsTenant($this->a)->get($uri)->headers->get('Cache-Control'), $uri);
        }
    }

    public function test_ac_37_the_search_term_is_redacted_from_logged_urls(): void
    {
        $record = (new RedactSensitiveData)(new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: 'GET https://rakyt-booking.test/pesanan?status=paid&cari=Rina%20Wulandari&halaman=2',
            context: ['url' => 'https://rakyt-booking.test/pesanan?cari=081234567890'],
        ));

        $json = json_encode([$record->message, $record->context]);
        $this->assertStringNotContainsString('Rina', $json);
        $this->assertStringNotContainsString('81234567890', $json);
        $this->assertStringContainsString('cari=[redacted]&halaman=2', $record->message);
        $this->assertStringContainsString('status=paid', $record->message);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function assertNoSecrets(array $props, bool $withToken): void
    {
        $json = json_encode($props, JSON_UNESCAPED_SLASHES);

        foreach (['organization_id', 'access_token_hash', 'submission_key', 'access_token', '"id":'.$this->aBooking->id] as $key) {
            $this->assertStringNotContainsString($key, $json, $key);
        }
        $this->assertStringNotContainsString($this->aBooking->access_token_hash, $json);
        $this->assertStringNotContainsString('Siti Pelanggan B', $json);

        if (! $withToken) {
            $this->assertStringNotContainsString($this->aBooking->access_token, $json);
        }

        $this->walk($props, function (string $key, mixed $value) {
            if (in_array($key, ['id', 'trip_id'], true)) {
                $this->assertIsString($value, "{$key} must be a public id.");
            }
        });
    }

    /**
     * @param  array<array-key, mixed>  $values
     */
    private function walk(array $values, callable $visit): void
    {
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $visit($key, $value);
            }

            if (is_array($value)) {
                $this->walk($value, $visit);
            }
        }
    }

    public function test_ac_36_organization_has_no_unscoped_booking_relation(): void
    {
        $this->assertFalse(method_exists(Organization::class, 'bookings'));
    }
}
