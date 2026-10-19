<?php

namespace Tests\Feature\Bookings;

use App\Bookings\BookingStatus;
use App\Bookings\CancelReason;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Organization;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Trips\TripStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsBookings;
use Tests\Support\BuildsPublicPages;
use Tests\Support\BuildsTrips;
use Tests\Support\InteractsWithTenants;
use Tests\Support\Tenant;
use Tests\TestCase;

/**
 * F-005 section B: validation and the booking transaction (AC-10–AC-18), the
 * inline release of expired holds (AC-26) and the fixed hold (AC-28). The
 * clock is Thursday 2024-10-10 10:00 WIB; the scheduler never runs here.
 */
class CreateBookingTest extends TestCase
{
    use BuildsBookings, BuildsPublicPages, BuildsTrips, InteractsWithTenants, RefreshDatabase;

    private Tenant $a;

    private Trip $trip;

    private TripDeparture $departure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClock();
        $this->a = $this->seller('bromo-adventure', 'Bromo Adventure');
        $this->trip = $this->bookableTrip($this->a);
        $this->departure = $this->departureFor($this->a, $this->trip, '2024-10-24', ['capacity' => 16]);
    }

    private function submit(array $overrides = []): TestResponse
    {
        return $this->from($this->formUrl('bromo-adventure', 'karimunjawa-3h2m', $this->departure->public_id))
            ->submitBooking('bromo-adventure', 'karimunjawa-3h2m', $this->bookingInput($this->departure, $overrides));
    }

    private function formRedirect(): string
    {
        return url($this->formUrl('bromo-adventure', 'karimunjawa-3h2m', $this->departure->public_id));
    }

    public function test_ac_10_fields_are_validated_with_bahasa_messages_and_values_kept(): void
    {
        $cases = [
            ['name' => 'R'],
            ['name' => str_repeat('a', 81)],
            ['name' => "Rina\x07 Wulandari"],
            ['name' => '   '],
            ['phone' => '0812'],
            ['phone' => '0212345678'],
            ['phone' => ''],
            ['participants' => 0],
            ['participants' => 'dua'],
            ['notes' => str_repeat('x', 501)],
        ];
        // Ten submits from one phone: lift the per-phone limit (AC-39 has its own tests).
        config(['rakyt.rate_limits.public_booking.per_phone' => [100, 60]]);
        $messages = [
            'name' => 'Isi nama lengkap (2–80 huruf).',
            'phone' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.',
            'participants' => 'Minimal 1 peserta.',
            'notes' => 'Catatan maksimal 500 karakter.',
        ];

        foreach ($cases as $overrides) {
            $field = array_key_first($overrides);

            $this->submit([...$overrides, 'phone' => $overrides['phone'] ?? '0812 3456 7890', 'notes' => $overrides['notes'] ?? 'Vegetarian'])
                ->assertRedirect($this->formRedirect())
                ->assertSessionHasErrors([$field => $messages[$field]])
                ->assertSessionHasInput('notes', $overrides['notes'] ?? 'Vegetarian');
        }

        $this->assertSame(0, $this->bookingCount());
        $this->assertSame(0, $this->freshDeparture($this->departure)->seats_taken);
    }

    public function test_ac_10_name_is_trimmed_and_whitespace_collapsed(): void
    {
        $this->submit(['name' => "  Rina \t  Wulandari  ", 'notes' => '  Vegetarian  '])->assertStatus(303);

        $booking = $this->latestBooking();
        $this->assertSame('Rina Wulandari', $booking->customer_name);
        $this->assertSame('Vegetarian', $booking->notes);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function phoneFormats(): array
    {
        return [
            'local' => ['0812 3456 7890'],
            'no prefix' => ['812-3456-7890'],
            'plus' => ['+62 812 3456 7890'],
            'country code' => ['62812345678 90'],
            'international' => ['0062 812 3456 7890'],
        ];
    }

    #[DataProvider('phoneFormats')]
    public function test_ac_11_phone_numbers_are_stored_in_e164(string $phone): void
    {
        $this->submit(['phone' => $phone])->assertStatus(303);

        $this->assertSame('+6281234567890', $this->latestBooking()->customer_phone);
    }

    public function test_ac_12_a_valid_submit_creates_the_booking_takes_seats_and_audits_in_one_go(): void
    {
        $response = $this->submit(['participants' => 2]);

        $booking = $this->latestBooking();
        $response->assertStatus(303)->assertRedirect(route('public.booking.show', ['organization' => 'bromo-adventure', 'token' => $booking->access_token]));

        $this->assertSame(BookingStatus::AwaitingPayment, $booking->status);
        $this->assertSame([2, 1_250_000, 2_500_000], [$booking->participants, $booking->unit_price, $booking->total_amount]);
        $this->assertSame($this->a->organization->id, $booking->organization_id);
        $this->assertTrue($booking->hold_expires_at->equalTo(CarbonImmutable::parse($booking->created_at)->addMinutes(120)));
        $this->assertTrue($booking->hold_expires_at->equalTo(CarbonImmutable::now()->addMinutes(120)));
        $this->assertSame(2, $this->freshDeparture($this->departure)->seats_taken);

        $events = AuditEvent::acrossOrganizations()->where('action', 'booking.created')->get();
        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame(['customer', (string) $booking->id, '127.0.0.1'], [$event->actor_type, $event->actor_id, $event->ip]);
        $this->assertEquals([
            'reference' => $booking->reference,
            'trip_departure_id' => $this->departure->id,
            'participants' => 2,
            'unit_price' => 1_250_000,
            'total_amount' => 2_500_000,
            'status' => 'awaiting_payment',
            'hold_expires_at' => '2024-10-10T05:00:00+00:00',
        ], $event->after);
        $this->assertStringNotContainsString('Rina', json_encode($event->after));
        $this->assertStringNotContainsString('+62812', json_encode($event->after));
    }

    public function test_ac_13_a_changed_price_refuses_the_booking_and_shows_the_new_price(): void
    {
        $this->asTenant($this->a, fn () => $this->departure->forceFill(['price_override' => 1_300_000])->save());

        $this->submit(['price' => 1_250_000])
            ->assertRedirect($this->formRedirect())
            ->assertSessionHasErrors(['price' => 'Harga untuk tanggal ini berubah menjadi Rp1.300.000 per orang. Periksa total lalu kirim lagi.']);

        $this->assertSame(0, $this->bookingCount());
        $this->assertSame(0, Organization::query()->find($this->a->organization->id)->last_booking_number, 'The counter rolls back too.');
        $this->assertSame(1_300_000, $this->formProps('bromo-adventure', 'karimunjawa-3h2m', $this->departure->public_id)['departure']['price']);

        $this->submit(['price' => 1_300_000])->assertStatus(303);
        $this->assertSame(2_600_000, $this->latestBooking()->total_amount);
    }

    public function test_ac_14_fewer_seats_than_asked_refuses_and_lowers_the_stepper(): void
    {
        $this->bookingFor($this->a, $this->departure, 13, BookingStatus::Paid);

        $this->submit(['participants' => 5])
            ->assertRedirect($this->formRedirect())
            ->assertSessionHasErrors(['participants' => 'Sisa kursi tinggal 3. Kurangi jumlah peserta.']);

        $this->assertSame(1, $this->bookingCount());
        $this->assertSame(3, $this->formProps('bromo-adventure', 'karimunjawa-3h2m', $this->departure->public_id)['departure']['seats_left']);
    }

    public function test_ac_14_no_seats_left_shows_the_full_state(): void
    {
        $this->bookingFor($this->a, $this->departure, 16, BookingStatus::Paid);

        $this->submit(['participants' => 1])
            ->assertRedirect($this->formRedirect())
            ->assertSessionHasErrors(['departure' => 'Tanggal ini sudah tidak bisa dipesan.']);

        $this->assertSame('full', $this->formProps('bromo-adventure', 'karimunjawa-3h2m', $this->departure->public_id)['departure']['state']);
    }

    public function test_ac_15_a_departure_closed_or_started_after_the_form_loaded_is_refused(): void
    {
        $this->asTenant($this->a, fn () => $this->departure->forceFill(['status' => 'closed', 'closed_reason' => 'manual'])->save());

        $this->submit()->assertSessionHasErrors(['departure' => 'Tanggal ini sudah tidak bisa dipesan.']);

        $this->asTenant($this->a, fn () => $this->departure->forceFill(['status' => 'open', 'closed_reason' => null, 'booking_deadline_at' => now()->addDays(30)])->save());
        $this->travelTo(CarbonImmutable::parse('2024-10-25 08:00', 'Asia/Jakarta'));

        $this->submit()->assertSessionHasErrors(['departure' => 'Tanggal ini sudah tidak bisa dipesan.']);

        $this->assertSame(0, $this->bookingCount());
    }

    public function test_ac_15_a_trip_unpublished_or_switched_to_request_date_meanwhile_creates_nothing(): void
    {
        $this->asTenant($this->a, fn () => $this->trip->forceFill(['status' => TripStatus::Draft])->save());
        $this->submit()->assertNotFound();

        $this->asTenant($this->a, fn () => $this->trip->forceFill(['status' => TripStatus::Published, 'booking_mode' => 'request_date'])->save());
        $this->submit()->assertNotFound();

        $this->assertSame(0, $this->bookingCount());
        $this->assertSame(0, $this->freshDeparture($this->departure)->seats_taken);
    }

    public function test_ac_15_the_deadline_is_exact_to_the_second_without_the_scheduler(): void
    {
        // H-3 deadline for 24 Okt: Senin 21 Okt 23.59.59 WIB.
        $this->travelTo(CarbonImmutable::parse('2024-10-21 23:59:30', 'Asia/Jakarta'));
        $this->submit()->assertStatus(303);

        $this->travelTo(CarbonImmutable::parse('2024-10-22 00:00:00', 'Asia/Jakarta'));
        $this->submit()->assertSessionHasErrors(['departure' => 'Tanggal ini sudah tidak bisa dipesan.']);

        $this->assertSame('open', $this->freshDeparture($this->departure)->status->value, 'Still open: the auto-close job never ran.');
        $this->assertSame(1, $this->bookingCount());
    }

    public function test_ac_16_the_same_submission_key_creates_one_booking(): void
    {
        $key = (string) Str::uuid();

        $first = $this->submit(['submission_key' => $key])->assertStatus(303);
        $second = $this->submit(['submission_key' => $key])->assertStatus(303);

        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertSame(1, $this->bookingCount());
        $this->assertSame(2, $this->freshDeparture($this->departure)->seats_taken);
        $this->assertSame(1, AuditEvent::acrossOrganizations()->where('action', 'booking.created')->count());
        $this->assertSame(1, Organization::query()->find($this->a->organization->id)->last_booking_number);
    }

    public function test_ac_12_totals_above_two_billion_rupiah_are_stored(): void
    {
        // 22 × Rp100.000.000 does not fit in a 32-bit integer (F-005 review).
        $big = $this->departureFor($this->a, $this->trip, '2024-10-25', ['capacity' => 30, 'price_override' => 100_000_000]);

        $this->submitBooking('bromo-adventure', 'karimunjawa-3h2m', $this->bookingInput($big, ['participants' => 22, 'price' => 100_000_000]))->assertStatus(303);

        $this->assertSame(2_200_000_000, $this->latestBooking()->total_amount);
        $this->assertSame(22, $this->freshDeparture($big)->seats_taken);
    }

    public function test_ac_16_a_submission_key_is_never_replayed_for_another_departure(): void
    {
        $key = (string) Str::uuid();
        $other = $this->departureFor($this->a, $this->trip, '2024-10-25');

        $this->submit(['submission_key' => $key])->assertStatus(303);
        $this->submitBooking('bromo-adventure', 'karimunjawa-3h2m', $this->bookingInput($other, ['submission_key' => $key]))
            ->assertSessionHasErrors(['departure' => 'Tanggal ini sudah tidak bisa dipesan.']);

        $this->assertSame(1, $this->bookingCount());
        $this->assertSame(0, $this->freshDeparture($other)->seats_taken);
    }

    public function test_ac_17_the_reference_carries_the_seller_code_the_departure_date_and_the_counter(): void
    {
        $this->assertSame('BRM', $this->a->organization->fresh()->booking_code);

        $this->submit()->assertStatus(303);
        $this->assertSame('RKB-BRM-1024-0001', $this->latestBooking()->reference);

        $this->submit()->assertStatus(303);
        $this->assertSame('RKB-BRM-1024-0002', $this->latestBooking()->reference);

        // More digits after 9 999.
        Organization::query()->whereKey($this->a->organization->id)->update(['last_booking_number' => 9_999]);
        $this->submit()->assertStatus(303);
        $this->assertSame('RKB-BRM-1024-10000', $this->latestBooking()->reference);
    }

    public function test_ac_17_two_sellers_with_equal_counters_get_different_references(): void
    {
        $b = $this->seller('komodo-trip', 'Komodo Trip');
        $bTrip = $this->bookableTrip($b);
        $bDeparture = $this->departureFor($b, $bTrip, '2024-10-24');
        Organization::query()->whereIn('id', [$this->a->organization->id, $b->organization->id])->update(['last_booking_number' => 143]);

        $this->submit()->assertStatus(303);
        $this->submitBooking('komodo-trip', 'karimunjawa-3h2m', $this->bookingInput($bDeparture, ['phone' => '081299990000']))->assertStatus(303);

        $this->assertEqualsCanonicalizing(
            ['RKB-BRM-1024-0144', 'RKB-KMD-1024-0144'],
            Booking::acrossOrganizations()->pluck('reference')->all(),
        );
    }

    public function test_ac_18_the_token_is_random_hashed_and_encrypted_and_the_reference_grants_nothing(): void
    {
        $this->submit()->assertStatus(303);
        $booking = $this->latestBooking();
        $token = $booking->access_token;

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{32}$/', $token);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'access_token_hash' => hash('sha256', $token)]);
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id, 'access_token' => $token]);
        $this->assertArrayNotHasKey('access_token', $booking->toArray());
        $this->assertArrayNotHasKey('access_token_hash', $booking->toArray());

        $this->assertStringNotContainsString($token, AuditEvent::acrossOrganizations()->get()->toJson());
        $this->assertStringNotContainsString($token, json_encode(session()->all()));

        $this->get('/bromo-adventure/pesanan/'.$booking->reference)->assertNotFound();
    }

    public function test_ac_26_expired_holds_of_the_departure_are_released_inline_before_counting_seats(): void
    {
        $small = $this->departureFor($this->a, $this->trip, '2024-10-25', ['capacity' => 4]);
        $expired = $this->expiredHoldFor($this->a, $small, 2);
        $this->bookingFor($this->a, $small, 2);
        $elsewhere = $this->expiredHoldFor($this->a, $this->departure, 3);

        // Full by seats_taken; the form still offers the seats (the late job is not the customer's problem).
        $this->assertSame(2, $this->formProps('bromo-adventure', 'karimunjawa-3h2m', $small->public_id)['departure']['seats_left']);

        $this->submitBooking('bromo-adventure', 'karimunjawa-3h2m', $this->bookingInput($small, ['participants' => 2]))->assertStatus(303);

        $released = Booking::acrossOrganizations()->find($expired->id);
        $this->assertSame([BookingStatus::Cancelled, CancelReason::HoldExpired], [$released->status, $released->cancel_reason]);
        $this->assertSame(4, $this->freshDeparture($small)->seats_taken);
        $event = AuditEvent::acrossOrganizations()->where('action', 'booking.hold_expired')->sole();
        $this->assertSame(['system', 'bookings:release-expired-holds', (string) $expired->id], [$event->actor_type, $event->actor_id, $event->subject_id]);
        $this->assertEquals(['status' => 'awaiting_payment', 'cancel_reason' => null], $event->before);
        $this->assertEquals(['status' => 'cancelled', 'cancel_reason' => 'hold_expired'], $event->after);

        // Only that departure's holds.
        $this->assertSame(BookingStatus::AwaitingPayment, Booking::acrossOrganizations()->find($elsewhere->id)->status);
    }

    public function test_ac_28_the_hold_is_set_once_and_never_extended(): void
    {
        $this->assertNotContains('hold_expires_at', (new Booking)->getFillable());

        $this->submit()->assertStatus(303);
        $booking = $this->latestBooking();

        $this->expectException(LogicException::class);
        $this->asTenant($this->a, fn () => $booking->forceFill(['hold_expires_at' => now()->addHours(5)])->save());
    }
}
