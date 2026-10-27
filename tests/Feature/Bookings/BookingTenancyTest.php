<?php

namespace Tests\Feature\Bookings;

use App\Bookings\BookingStatus;
use App\Logging\RedactSensitiveData;
use App\Models\Booking;
use App\Models\TripDeparture;
use App\Security\PhoneNumber;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-005 section F: cross-tenant isolation (AC-38, F-009 A5 booking case),
 * rate limits (AC-39), logs (AC-40), the F-003 integration (AC-41) and the
 * phone-width design rules that can be checked in code (AC-42). The clock is
 * Thursday 2024-10-10 10:00 WIB.
 */
class BookingTenancyTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Tenant $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller('kencana', 'Kencana Trip');
        $this->b = $this->seller('bahari', 'Bahari Tour', ['whatsapp' => '+6281399990000']);
    }

    public function test_ac_38_a_another_sellers_departure_under_this_trip_is_a_404(): void
    {
        $aTrip = $this->bookableTrip($this->a);
        $bDeparture = $this->departureFor($this->b, $this->bookableTrip($this->b), '2024-10-24');

        $this->get($this->formUrl('kencana', 'karimunjawa-3h2m', $bDeparture->public_id))->assertNotFound();
        $this->submitBooking('kencana', $aTrip->slug, $this->bookingInput($bDeparture))->assertNotFound();

        $this->assertSame(0, $this->bookingCount());
        $this->assertSame(0, $this->freshDeparture($bDeparture)->seats_taken);
    }

    public function test_ac_38_b_another_sellers_trip_under_this_slug_is_a_404(): void
    {
        $bTrip = $this->bookableTrip($this->b, ['name' => 'Bromo Sunrise']);
        $bDeparture = $this->departureFor($this->b, $bTrip, '2024-10-24');

        $this->get($this->formUrl('kencana', 'bromo-sunrise', $bDeparture->public_id))->assertNotFound();
        $this->submitBooking('kencana', 'bromo-sunrise', $this->bookingInput($bDeparture))->assertNotFound();
        $this->get($this->formUrl('bahari', 'bromo-sunrise', $bDeparture->public_id))->assertOk();
    }

    public function test_ac_38_c_a_token_works_only_under_its_own_sellers_slug(): void
    {
        $booking = $this->bookingFor($this->b, $this->departureFor($this->b, $this->bookableTrip($this->b), '2024-10-24'));

        $this->get('/kencana/pesanan/'.$booking->access_token)->assertNotFound();
        $this->get('/bahari/pesanan/'.$booking->access_token)->assertOk();
    }

    public function test_ac_38_d_a_signed_in_seller_booking_another_sellers_trip_books_for_that_seller(): void
    {
        $this->bookableTrip($this->a, ['name' => 'Rahasia A']);
        $bDeparture = $this->departureFor($this->b, $this->bookableTrip($this->b), '2024-10-24');

        $form = $this->propsOf($this->actingAsTenant($this->a)->get($this->formUrl('bahari', 'karimunjawa-3h2m', $bDeparture->public_id)), 'Public/BookingForm');
        $this->actingAsTenant($this->a)->submitBooking('bahari', 'karimunjawa-3h2m', $this->bookingInput($bDeparture))->assertStatus(303);

        $booking = $this->latestBooking();
        $this->assertSame($this->b->organization->id, $booking->organization_id);
        $this->assertSame(2, $this->freshDeparture($bDeparture)->seats_taken);
        $this->assertStringStartsWith('RKB-BHR-', $booking->reference);

        $page = $this->propsOf($this->actingAsTenant($this->a)->get('/bahari/pesanan/'.$booking->access_token), 'Public/Booking');

        foreach ([$form, $page] as $props) {
            $json = json_encode($props);
            $this->assertStringNotContainsString('Kencana', $json);
            $this->assertStringNotContainsString('Rahasia A', $json);
            $this->assertStringNotContainsString($this->a->owner->email, $json);
            $this->assertArrayNotHasKey('seller', $props);
            $this->assertArrayNotHasKey('account', $props);
        }
    }

    public function test_ac_38_sellers_see_only_their_own_bookings(): void
    {
        $booking = $this->bookingFor($this->b, $this->departureFor($this->b, $this->bookableTrip($this->b), '2024-10-24'));

        $this->asTenant($this->a, function () use ($booking) {
            $this->assertNull(Booking::query()->find($booking->id));
            $this->assertTrue(Gate::forUser($this->a->owner)->inspect('view', $booking)->denied());
            $this->assertSame(404, Gate::forUser($this->a->owner)->inspect('view', $booking)->status());
        });
        $this->asTenant($this->b, fn () => $this->assertTrue(Gate::forUser($this->b->owner)->inspect('view', $booking)->allowed()));
    }

    public function test_ac_39_six_bookings_from_one_phone_in_an_hour_are_refused_with_input_kept(): void
    {
        $departure = $this->departureFor($this->a, $this->bookableTrip($this->a), '2024-10-24');
        [$max] = config('rakyt.rate_limits.public_booking.per_phone');
        $formats = ['0812 3456 7890', '+62 812 3456 7890', '6281234567890'];

        for ($i = 0; $i < $max; $i++) {
            $this->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($departure, ['participants' => 1, 'phone' => $formats[$i % 3]]))->assertStatus(303);
        }

        $form = url($this->formUrl('kencana', 'karimunjawa-3h2m', $departure->public_id));
        $this->from($form)->withHeader('X-Inertia', 'true')
            ->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($departure, ['name' => 'Rina Keenam', 'phone' => '0812-3456-7890']))
            ->assertRedirect($form)
            ->assertSessionHasErrors(['rate_limit' => 'Terlalu banyak percobaan. Coba lagi dalam 60 menit.'])
            ->assertSessionHasInput('name', 'Rina Keenam');

        $this->assertSame($max, $this->bookingCount());

        // Another phone still books.
        $this->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($departure, ['participants' => 1, 'phone' => '0813 1111 2222']))->assertStatus(303);
    }

    public function test_ac_39_an_empty_phone_only_counts_against_the_ip(): void
    {
        $departure = $this->departureFor($this->a, $this->bookableTrip($this->a), '2024-10-24');
        [$max] = config('rakyt.rate_limits.public_booking.per_phone');

        for ($i = 0; $i <= $max + 1; $i++) {
            $this->submitBooking('kencana', 'karimunjawa-3h2m', $this->bookingInput($departure, ['phone' => '']))
                ->assertSessionHasErrors(['phone' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.']);
        }
    }

    public function test_ac_39_the_routes_use_the_named_limiters(): void
    {
        $middleware = fn (string $name) => Route::getRoutes()->getByName($name)->gatherMiddleware();

        $this->assertContains('throttle:public-page', $middleware('public.booking.create'));
        $this->assertContains('throttle:public-page', $middleware('public.booking.show'));
        $this->assertContains('throttle:public-booking', $middleware('public.booking.store'));
        $this->assertContains('no-referrer', $middleware('public.booking.show'));
        $this->assertSame([5, 60], config('rakyt.rate_limits.public_booking.per_phone'));
        $this->assertSame([30, 10], config('rakyt.rate_limits.public_booking.per_ip'));
    }

    public function test_ac_40_booking_page_urls_never_reach_the_logs_with_their_token(): void
    {
        $booking = $this->bookingFor($this->a, $this->departureFor($this->a, $this->bookableTrip($this->a), '2024-10-24'));
        $handler = new TestHandler;
        Log::channel('json')->getLogger()->pushHandler($handler);

        // An access log line, and an error carrying the URL, as a proxy or tracker would write them.
        Event::listen(RequestHandled::class, function (RequestHandled $event) {
            Log::channel('json')->info('GET '.$event->request->fullUrl(), ['path' => $event->request->path(), 'phone' => '+6281234567890']);
            Log::channel('json')->error('Gagal', ['exception' => new \RuntimeException('Not found: '.$event->request->getUri())]);
        });

        $this->get('/kencana/pesanan/'.$booking->access_token)->assertOk();

        $logged = json_encode(array_map(fn (LogRecord $record) => [$record->message, $record->context], $handler->getRecords()));
        $this->assertNotEmpty($handler->getRecords());
        $this->assertStringNotContainsString($booking->access_token, $logged);
        $this->assertStringContainsString('pesanan\/[redacted]', $logged);
        $this->assertStringNotContainsString('81234567890', $logged);
        $this->assertStringContainsString(PhoneNumber::mask('+6281234567890'), $logged);
    }

    public function test_ac_40_the_processor_redacts_the_token_segment_in_any_string(): void
    {
        $record = (new RedactSensitiveData)(new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Warning,
            message: 'Lambat: https://rakyt.id/kencana/pesanan/AbCdEfGh12345678AbCdEfGh12345678?x=1',
            context: ['referer' => '/kencana/pesanan/AbCdEfGh12345678AbCdEfGh12345678/bukti'],
        ));

        $this->assertSame('Lambat: https://rakyt.id/kencana/pesanan/[redacted]?x=1', $record->message);
        $this->assertSame('/kencana/pesanan/[redacted]/bukti', $record->context['referer']);
    }

    public function test_ac_40_the_admin_booking_detail_path_is_kept_and_customer_token_paths_still_redacted(): void
    {
        $publicId = strtolower((string) Str::ulid());
        $token = Str::random(32);

        $record = (new RedactSensitiveData)(new LogRecord(
            datetime: new DateTimeImmutable,
            channel: 'test',
            level: Level::Info,
            message: "GET https://rakyt.id/admin/pesanan/{$publicId}",
            context: [
                // `admin` is a reserved slug, so /admin/pesanan/… is never a customer token page (F-013).
                'path' => "admin/pesanan/{$publicId}",
                'json' => json_encode(['url' => "/admin/pesanan/{$publicId}"]),
                'customer' => "/kencana/pesanan/{$token}",
                'lookalike' => "/superadmin/pesanan/{$token}",
                'escaped' => json_encode(['url' => "/kencana/pesanan/{$token}"]),
            ],
        ));

        $this->assertSame("GET https://rakyt.id/admin/pesanan/{$publicId}", $record->message);
        $this->assertSame("admin/pesanan/{$publicId}", $record->context['path']);
        $this->assertStringContainsString($publicId, $record->context['json']);
        $this->assertSame('/kencana/pesanan/[redacted]', $record->context['customer']);
        $this->assertSame('/superadmin/pesanan/[redacted]', $record->context['lookalike']);
        $this->assertStringNotContainsString($token, $record->context['escaped']);
    }

    public function test_ac_41_any_booking_blocks_deleting_a_departure_and_holding_ones_are_counted(): void
    {
        $trip = $this->bookableTrip($this->a);
        $departure = $this->departureFor($this->a, $trip, '2024-10-24');
        $this->bookingFor($this->a, $departure, 1, BookingStatus::Cancelled);

        // Only a cancelled booking: no seats taken, but delete is still refused (F-003 AC-16, AC-29).
        $this->assertSame(0, $this->freshDeparture($departure)->seats_taken);
        $this->actingAsTenant($this->a)
            ->deleteJson(route('trips.departures.destroy', [$trip, $departure]))
            ->assertUnprocessable();
        $this->assertDatabaseHas('trip_departures', ['id' => $departure->id]);

        $this->bookingFor($this->a, $departure, 2);
        $this->bookingFor($this->a, $departure, 1, BookingStatus::NeedsReview);
        $this->bookingFor($this->a, $departure, 3, BookingStatus::Paid);

        $props = $this->propsOf($this->actingAsTenant($this->a)->get(route('trips.edit', $trip)), 'Trips/Edit');
        $row = $props['departures']['upcoming'][0];
        $this->assertSame(3, $row['active_bookings']);
        $this->assertFalse($row['can_delete']);
    }

    public function test_ac_41_the_foreign_key_restricts_deleting_a_booked_departure(): void
    {
        $departure = $this->departureFor($this->a, $this->bookableTrip($this->a), '2024-10-24');
        $this->bookingFor($this->a, $departure, 1, BookingStatus::Cancelled);

        $this->expectException(QueryException::class);
        $this->asTenant($this->a, fn () => TripDeparture::query()->whereKey($departure->id)->delete());
    }

    public function test_ac_23_database_checks_reject_inconsistent_bookings(): void
    {
        $departure = $this->departureFor($this->a, $this->bookableTrip($this->a), '2024-10-24');
        $booking = $this->bookingFor($this->a, $departure, 2);

        foreach ([
            'bookings_participants_check' => ['participants' => 0, 'total_amount' => 0],
            'bookings_unit_price_check' => ['unit_price' => 999, 'total_amount' => 1998],
            'bookings_total_amount_check' => ['total_amount' => 1],
            'bookings_status_check' => ['status' => 'pending'],
            'bookings_cancel_reason_check' => ['status' => 'cancelled'],
        ] as $constraint => $values) {
            try {
                DB::transaction(fn () => DB::table('bookings')->where('id', $booking->id)->update($values));
                $this->fail("{$constraint} did not reject the row.");
            } catch (QueryException $e) {
                $this->assertStringContainsString($constraint, $e->getMessage());
            }
        }

        try {
            DB::transaction(fn () => DB::table('bookings')->where('id', $booking->id)->update(['cancel_reason' => 'hold_expired']));
            $this->fail('A cancel reason without cancelled was accepted.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('bookings_cancel_reason_check', $e->getMessage());
        }
    }

    public function test_ac_42_pages_follow_the_phone_width_rules_in_code(): void
    {
        foreach (['BookingForm', 'Booking'] as $page) {
            $template = file_get_contents(resource_path("js/pages/Public/{$page}.vue"));

            $this->assertDoesNotMatchRegularExpression('/text-\[(?:[0-9]|10)px\]/', $template, "{$page}: no text below 11 px.");
            $this->assertStringNotContainsString('v-html', $template, "{$page}: customer text is rendered as text.");
            $this->assertDoesNotMatchRegularExpression('/[A-Za-z]{4,} [a-z]{3,} [a-z]{3,}/', preg_replace('/<script.*?<\/script>|class="[^"]*"|\{\{.*?\}\}|<!--.*?-->|\/\*.*?\*\/|\/\/[^\n]*|[a-z-]+="[^"]*"/s', '', $template), "{$page}: copy comes from lang/id/booking.php.");
        }
    }
}
