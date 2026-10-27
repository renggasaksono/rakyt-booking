<?php

namespace Tests\Feature\Admin;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Bookings\BookingStatus;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\PaymentRecord;
use App\Models\TripDeparture;
use App\Models\User;
use App\Tenancy\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Monolog\Handler\TestHandler;
use Tests\Support\BuildsAdmins;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsDashboard;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\SuspendsSellers;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-013: one booking's read-only detail in the admin (`/admin/pesanan/{id}`)
 * — seller, customer, trip and departure with F-007's seat counts, amounts,
 * payment and gated evidence, hold expiry and the booking's audit history.
 * The clock starts at Thursday 2024-10-10 10:00 WIB.
 */
class AdminBookingDetailTest extends TestCase
{
    use BuildsAdmins, BuildsBookings, BuildsDashboard, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase, SuspendsSellers;

    private Tenant $a;

    private Tenant $b;

    private User $admin;

    private TripDeparture $departure;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('rakyt.media.private_disk'));
        $this->freezeClock();
        $this->a = $this->seller('kencana', 'Kencana Trip');
        $this->b = $this->seller('bromo', 'Bromo Adventure');
        $this->admin = $this->platformAdmin(['name' => 'Rengga']);
        $this->departure = $this->departureFor($this->a, $this->bookableTrip($this->a, ['name' => 'Karimunjawa 3H2M']), '2024-10-24');
        $this->booking = $this->bookingFor($this->a, $this->departure, 2, attributes: [
            'customer_name' => 'Siti Aminah',
            'customer_phone' => '+6281299887766',
            'notes' => 'CATATAN-PELANGGAN',
        ]);
    }

    public function test_ac_6_the_detail_is_behind_the_admin_gate(): void
    {
        $url = route('admin.bookings.show', $this->booking->public_id);

        $this->get($url)->assertRedirect(route('login'));
        // Even the seller who owns the booking: forbidden, HTML or JSON.
        $this->actingAsTenant($this->a)->get($url)->assertForbidden()->assertDontSee('Siti Aminah');
        $this->actingAsTenant($this->a)->getJson($url)->assertForbidden()->assertDontSee('Siti Aminah');
        $this->actingAsTenant($this->b)->get($url)->assertForbidden()->assertDontSee('Siti Aminah');
        $this->actingAs($this->admin)->get($url)->assertRedirect(route('admin.two-factor.challenge'));
        $this->actingAs($this->admin)->getJson($url)->assertRedirect(route('admin.two-factor.challenge'));

        $this->show()
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn ($page) => $page->component('Admin/Bookings/Show'));
    }

    public function test_ac_6_sellers_still_cannot_read_each_others_bookings(): void
    {
        // The seller app's booking detail stays organization-scoped (F-007).
        $this->actingAsTenant($this->b)->get('/pesanan/'.$this->booking->public_id)->assertNotFound();
        $this->actingAsTenant($this->a)->get('/pesanan/'.$this->booking->public_id)->assertOk();
    }

    public function test_an_unknown_or_malformed_booking_is_not_found(): void
    {
        $this->actingAsConfirmedAdmin($this->admin)->get(route('admin.bookings.show', str_repeat('0', 26)))->assertNotFound();
        $this->actingAsConfirmedAdmin($this->admin)->get('/admin/pesanan/'.$this->booking->reference)->assertNotFound();
        $this->actingAsConfirmedAdmin($this->admin)->get('/admin/pesanan/'.strtoupper($this->booking->public_id))->assertNotFound();
        $this->actingAsConfirmedAdmin($this->admin)->get('/admin/pesanan/'.$this->booking->id)->assertNotFound();
    }

    public function test_ac_4_seller_customer_trip_departure_and_amounts(): void
    {
        $this->show()->assertInertia(fn ($page) => $page
            ->where('booking.id', $this->booking->public_id)
            ->where('booking.reference', $this->booking->reference)
            ->where('booking.status', 'awaiting_payment')
            ->where('booking.status_label', 'Menunggu pembayaran')
            ->where('booking.cancel_reason_label', null)
            ->where('booking.customer_name', 'Siti Aminah')
            ->where('booking.participants', 2)
            ->where('booking.unit_price', 1_250_000)
            ->where('booking.unit_price_label', 'Rp1.250.000')
            ->where('booking.total_amount', 2_500_000)
            ->where('booking.total_label', 'Rp2.500.000')
            ->where('booking.created_at', '2024-10-10T03:00:00Z')
            ->where('booking.created_label', '10 Okt 2024 10.00 WIB')
            ->where('organization', [
                'id' => $this->a->id(),
                'name' => 'Kencana Trip',
                'slug' => 'kencana',
                'status' => 'active',
                'status_label' => 'Aktif',
                'url' => route('admin.sellers.show', $this->a->id()),
            ])
            ->where('trip.name', 'Karimunjawa 3H2M')
            ->where('departure.start_date', '2024-10-24')
            ->where('departure.end_date', '2024-10-26')
            ->where('departure.date_label', '24–26 Okt')
            ->where('departure.status', 'open')
            ->where('departure.status_label', 'Buka')
            ->where('payment.amount_due', 2_500_000)
            ->where('payment.amount_due_label', 'Rp2.500.000')
            ->where('payment.evidence_url', null)
            ->where('payment.decision', null)
            ->where('urls.audit', route('admin.audit.index', ['organisasi' => $this->a->id()]))
        );
    }

    public function test_ac_4_seats_match_the_seller_dashboard(): void
    {
        $this->bookingFor($this->a, $this->departure, 8, BookingStatus::Paid);
        $this->setStatus($this->bookingFor($this->a, $this->departure, 1), BookingStatus::NeedsReview);
        $this->bookingFor($this->a, $this->departure, 3, BookingStatus::Cancelled);
        $this->expiredHoldFor($this->a, $this->departure, 1);
        // Another seller's bookings never count.
        $other = $this->departureFor($this->b, $this->bookableTrip($this->b), '2024-10-24');
        $this->bookingFor($this->b, $other, 5, BookingStatus::Paid);

        $dashboard = $this->todayProps($this->a)['departures'][0]['seats'];
        $admin = $this->propsOf($this->show(), 'Admin/Bookings/Show')['departure']['seats'];

        $this->assertSame(['capacity' => 16, 'paid' => 8, 'held' => 4, 'empty' => 4], $dashboard);
        $this->assertSame($dashboard, $admin);
    }

    public function test_ac_4_hold_expiry_for_an_awaiting_payment_booking(): void
    {
        $this->show()->assertInertia(fn ($page) => $page
            ->where('booking.hold.expires_at', '2024-10-10T05:00:00Z')
            ->where('booking.hold.expires_label', '10 Okt 2024 12.00 WIB')
            ->where('booking.hold.active', true)
        );

        // Past the end, not yet released by the job: shown as no longer active.
        $this->travel(121)->minutes();
        $this->show()->assertInertia(fn ($page) => $page->where('booking.hold.active', false));

        // No hold for a booking past awaiting payment.
        $paid = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Paid);
        $this->show($paid)->assertInertia(fn ($page) => $page->where('booking.hold', null));
    }

    public function test_ac_4_a_cancelled_booking_shows_why(): void
    {
        $cancelled = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Cancelled);

        $this->show($cancelled)->assertInertia(fn ($page) => $page
            ->where('booking.status_label', 'Dibatalkan')
            ->where('booking.cancel_reason_label', 'Waktu pembayaran habis, kuota dilepas otomatis.')
            ->where('booking.hold', null)
        );
    }

    public function test_ac_4_payment_status_and_evidence_through_the_signed_admin_route(): void
    {
        $booking = $this->setStatus($this->bookingFor($this->a, $this->departure, 1), BookingStatus::NeedsReview);
        Storage::disk(config('rakyt.media.private_disk'))->put('org/rahasia/evidence/bukti.jpg', 'jpeg-bytes');
        $this->asTenant($this->a, fn () => PaymentRecord::factory()->withEvidence('org/rahasia/evidence/bukti.jpg')->create([
            'booking_id' => $booking->id,
            'amount_due' => 1_250_000,
        ]));

        $issue = route('admin.sellers.evidence', [$this->a->id(), $booking->public_id]);
        $response = $this->show($booking);

        $response->assertInertia(fn ($page) => $page
            ->where('booking.status_label', 'Perlu dicek')
            ->where('payment.amount_due', 1_250_000)
            ->where('payment.evidence_url', $issue)
            ->where('payment.uploaded_at', '2024-10-10T03:00:00Z')
            ->where('payment.uploaded_label', '10 Okt 2024 10.00 WIB')
            ->where('payment.decision', null)
        );
        // Never the storage path.
        $response->assertDontSee('org/rahasia', false)->assertDontSee('bukti.jpg', false);

        // F-012's issuing route hands the admin a short-lived signed URL that streams the file.
        $signed = $this->actingAsConfirmedAdmin($this->admin)->get($issue)->assertRedirect()->headers->get('Location');
        $this->actingAsConfirmedAdmin($this->admin)->get($signed)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_ac_4_a_rejected_payment_shows_the_sellers_decision_and_reason(): void
    {
        $booking = $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Rejected);
        $this->asTenant($this->a, fn () => PaymentRecord::factory()->withEvidence()->rejected('Nominal tidak sesuai')->create(['booking_id' => $booking->id]));

        $this->show($booking)->assertInertia(fn ($page) => $page
            ->where('booking.status_label', 'Ditolak')
            ->where('payment.decision', 'rejected')
            ->where('payment.decision_label', 'Ditolak')
            ->where('payment.verified_label', '10 Okt 2024 10.00 WIB')
            ->where('payment.reject_reason', 'Nominal tidak sesuai')
        );
    }

    public function test_ac_4_status_history_from_audit_events_of_this_booking_only(): void
    {
        $created = $this->record(AuditAction::BookingCreated, $this->booking);
        $this->travel(10)->minutes();
        $uploaded = $this->record(AuditAction::PaymentEvidenceUploaded, $this->booking, Actor::customer($this->booking->id), ['status' => 'awaiting_payment'], ['status' => 'needs_review']);
        // Another booking's and another seller's events never show.
        $sibling = $this->bookingFor($this->a, $this->departure, 1);
        $this->record(AuditAction::BookingCancelled, $sibling, reason: 'ALASAN-LAIN');
        $this->record(AuditAction::OrganizationSlugChanged, $this->b->organization, reason: 'ALASAN-SELLER-LAIN');

        $response = $this->show();
        $response->assertDontSee('ALASAN-LAIN')->assertDontSee('ALASAN-SELLER-LAIN');
        $response->assertInertia(fn ($page) => $page
            ->has('audit_events', 2)
            ->where('audit_events.0.id', $uploaded->id)
            ->where('audit_events.0.action_label', 'Bukti bayar diunggah')
            ->where('audit_events.0.actor.type_label', 'Pelanggan')
            ->where('audit_events.0.changes', [['field' => 'status', 'before' => 'awaiting_payment', 'after' => 'needs_review']])
            ->where('audit_events.1.id', $created->id)
        );
    }

    public function test_ac_4_a_booking_without_recorded_history_shows_none(): void
    {
        $this->show()->assertInertia(fn ($page) => $page->where('audit_events', []));
    }

    public function test_ac_4_the_detail_is_read_only(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Admin/Bookings/Show.vue'));

        // No form, no write, no gold CTA.
        foreach (['<form', 'useForm', 'router.post', 'method="post"', 'prada'] as $write) {
            $this->assertStringNotContainsString($write, $vue);
        }

        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->actingAsConfirmedAdmin($this->admin)
                ->call(strtoupper($method), route('admin.bookings.show', $this->booking->public_id))
                ->assertStatus(405);
        }
    }

    public function test_ac_5_the_customer_phone_is_on_the_detail_without_tokens_or_notes(): void
    {
        $booking = Booking::acrossOrganizations()->findOrFail($this->booking->id);
        $response = $this->show();

        $response->assertInertia(fn ($page) => $page->where('booking.customer_phone', '+6281299887766'));

        foreach ([$booking->access_token, $booking->access_token_hash, $booking->submission_key, '/pesanan/'.$booking->access_token, 'CATATAN-PELANGGAN'] as $secret) {
            $response->assertDontSee($secret, false);
        }

        $this->assertSame(
            ['cancel_reason_label', 'created_at', 'created_label', 'customer_name', 'customer_phone', 'hold', 'id', 'participants', 'reference', 'status', 'status_label', 'total_amount', 'total_label', 'unit_price', 'unit_price_label'],
            collect($this->propsOf($response, 'Admin/Bookings/Show')['booking'])->keys()->sort()->values()->all(),
        );
    }

    public function test_ac_5_customer_phones_never_reach_the_logs(): void
    {
        $handler = new TestHandler;
        Log::channel('json')->getLogger()->pushHandler($handler);

        $this->actingAsConfirmedAdmin($this->admin)->get(route('admin.bookings.index', ['cari' => '0812 9988 7766']))->assertOk();
        $this->show()->assertOk();
        // Were a search URL or a booking ever logged, the term and the model's attributes stay out.
        Log::channel('json')->info('admin booking search', [
            'url' => route('admin.bookings.index', ['cari' => '081299887766']),
            'booking' => Booking::acrossOrganizations()->findOrFail($this->booking->id),
            'phone' => '+6281299887766',
        ]);

        $logged = json_encode(array_map(fn ($record) => [$record->message, $record->context, $record->extra], $handler->getRecords()));

        foreach (['81299887766', '0812 9988 7766', '9988 7766', 'Siti Aminah'] as $leak) {
            $this->assertStringNotContainsString($leak, (string) $logged, "[{$leak}] leaked into the log.");
        }
    }

    public function test_a_suspended_sellers_booking_shows_the_account_status(): void
    {
        $this->suspendOrganization($this->a);

        $this->show()->assertInertia(fn ($page) => $page
            ->where('organization.status', 'suspended')
            ->where('organization.status_label', 'Ditangguhkan')
        );
    }

    public function test_the_back_link_restores_the_lists_filters(): void
    {
        $query = ['cari' => 'siti', 'status' => 'awaiting_payment', 'organisasi' => (string) $this->a->id(), 'dari' => '2024-10-01', 'sampai' => '2024-10-31'];

        $this->actingAsConfirmedAdmin($this->admin)
            ->get(route('admin.bookings.show', [$this->booking->public_id, ...$query, 'status' => 'hapus']))
            ->assertInertia(fn ($page) => $page->where('urls.back', route('admin.bookings.index', [...$query, 'status' => null])));
    }

    public function test_the_detail_runs_a_fixed_number_of_queries(): void
    {
        $this->record(AuditAction::BookingCreated, $this->booking);
        $baseline = count($this->queriesOf(fn () => $this->show()->assertOk()));

        foreach (range(1, 5) as $i) {
            $this->bookingFor($this->a, $this->departure, 1, BookingStatus::Paid);
            $this->record(AuditAction::PaymentConfirmed, $this->booking, Actor::user($this->a->owner));
        }
        $this->asTenant($this->a, fn () => PaymentRecord::factory()->withEvidence()->create(['booking_id' => $this->booking->id]));

        $this->assertSame($baseline, count($this->queriesOf(fn () => $this->show()->assertOk())));
    }

    public function test_detail_reads_name_the_bookings_organization(): void
    {
        $queries = collect($this->queriesOf(fn () => $this->show()->assertOk()))
            ->filter(fn (string $sql) => preg_match('/from "(trip_departures|trips|payment_records)"/', $sql) === 1
                || str_contains($sql, 'SUM(participants)'))
            ->values();

        $this->assertCount(4, $queries);
        foreach ($queries as $sql) {
            $this->assertMatchesRegularExpression('/"(trip_departures|trips|payment_records|bookings)"\."organization_id" = \?/', $sql);
        }
    }

    public function test_ac_7_the_detail_is_phone_first(): void
    {
        $vue = file_get_contents(resource_path('js/pages/Admin/Bookings/Show.vue'));

        $this->assertStringNotContainsString('<table', $vue);
        $this->assertStringContainsString('min-h-11', $vue);
    }

    private function show(?Booking $booking = null): TestResponse
    {
        return $this->actingAsConfirmedAdmin($this->admin)->get(route('admin.bookings.show', ($booking ?? $this->booking)->public_id));
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function record(AuditAction $action, Model $subject, ?Actor $actor = null, array $before = [], array $after = [], ?string $reason = null): AuditEvent
    {
        app(CurrentOrganization::class)->set(null);

        return DB::transaction(fn () => app(AuditLog::class)->record($action, $subject, $before, $after, $actor ?? Actor::system('test'), $reason));
    }
}
