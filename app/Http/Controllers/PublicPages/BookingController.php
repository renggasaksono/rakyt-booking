<?php

namespace App\Http\Controllers\PublicPages;

use App\Bookings\BookingPresenter;
use App\Bookings\BookingService;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\PublicPages\PublicVisibility;
use App\Security\PhoneNumber;
use App\Tenancy\CurrentOrganization;
use App\Trips\BookingMode;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer booking (F-005, ADR-0027): the form `/{slug}/{trip}/pesan`, its
 * submit, and the booking page `/{slug}/pesanan/{token}`. The organization
 * comes from the slug (ResolvePublicOrganization), never from a signed-in
 * user; trips, departures and bookings are read within it, so another
 * seller's ids and tokens are a 404 by construction (AC-38).
 */
class BookingController extends Controller
{
    /** Departure public ids are lower-case ULIDs (HasPublicId). */
    private const PUBLIC_ID_PATTERN = '/^[0-9a-z]{26}$/';

    public function __construct(
        private CurrentOrganization $current,
        private PublicVisibility $visibility,
        private BookingService $bookings,
        private BookingPresenter $presenter,
    ) {}

    /**
     * AC-1–AC-9: the form, or the "no longer bookable" state (AC-3).
     */
    public function create(Request $request, string $organization, string $trip): Response
    {
        $model = $this->trip($trip);
        $departure = $this->departure($model, $request->query('keberangkatan'));

        // A late scheduler never shows free seats as taken (ADR-0026 §7, AC-26).
        if ($this->bookings->releaseExpiredOn($departure) > 0) {
            $departure = $this->departure($model, $departure->public_id);
        }

        $props = $this->presenter->form($this->current->get(), $model, $departure);

        return self::private(
            Inertia::render('Public/BookingForm', $props)
                ->withViewData('plainHead', ['title' => $props['title'], 'noindex' => true])
                ->toResponse($request),
        );
    }

    /**
     * AC-10–AC-17: validate, then create in one transaction (BookingService).
     * Refusals go back to the form with the typed values kept.
     */
    public function store(Request $request, string $organization, string $trip): RedirectResponse
    {
        $model = $this->trip($trip);
        $departure = $this->departure($model, $request->input('keberangkatan'));
        $form = route('public.booking.create', [
            'organization' => $this->current->get()->slug,
            'trip' => $model->slug,
            'keberangkatan' => $departure->public_id,
        ]);

        try {
            $booking = $this->bookings->create($departure, $this->validated($request));
        } catch (ValidationException $e) {
            throw $e->redirectTo($form);
        }

        return redirect()->to(self::bookingUrl($this->current->get()->slug, $booking), 303);
    }

    /**
     * AC-29–AC-36: the customer's booking page. The token is looked up by its
     * hash within this organization (ADR-0027 §1–2).
     */
    public function show(Request $request, string $organization, string $token): Response
    {
        $booking = Booking::query()
            ->where('access_token_hash', Booking::hashToken($token))
            ->with('departure.trip')
            ->first();

        abort_if($booking === null, 404);

        $props = $this->presenter->page($this->current->get(), $booking);

        $response = self::private(
            Inertia::render('Public/Booking', $props)
                ->withViewData('plainHead', ['title' => $props['title'], 'noindex' => true])
                ->toResponse($request),
        );
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    public static function bookingUrl(string $slug, Booking $booking): string
    {
        return route('public.booking.show', ['organization' => $slug, 'token' => $booking->access_token]);
    }

    /**
     * A published, scheduled trip of this organization (AC-2: a request-date trip is a 404).
     */
    private function trip(string $slug): Trip
    {
        $trip = $this->visibility->trips()
            ->where('slug', $slug)
            ->where('booking_mode', BookingMode::Scheduled)
            ->first();

        abort_if($trip === null, 404);

        return $trip;
    }

    /**
     * A departure of this trip by public id (AC-2: missing, malformed, another
     * trip's or another seller's id is the same 404).
     */
    private function departure(Trip $trip, mixed $publicId): TripDeparture
    {
        abort_unless(is_string($publicId) && preg_match(self::PUBLIC_ID_PATTERN, $publicId) === 1, 404);

        $departure = $trip->departures()->where('public_id', $publicId)->first();

        abort_if($departure === null, 404);

        return $departure;
    }

    /**
     * AC-10, AC-11.
     *
     * @return array{name: string, phone: string, participants: int, notes: ?string, submission_key: string, price: int}
     */
    private function validated(Request $request): array
    {
        $input = [
            'name' => self::clean($request->input('name')),
            'phone' => is_string($request->input('phone')) ? PhoneNumber::normalize($request->input('phone')) : null,
            'participants' => $request->input('participants'),
            'notes' => is_string($request->input('notes')) && trim($request->input('notes')) !== '' ? trim($request->input('notes')) : null,
            'submission_key' => $request->input('submission_key'),
            'price' => $request->input('price'),
        ];

        $config = config('rakyt.booking');

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'min:'.$config['name_min'], 'max:'.$config['name_max'], self::noControlCharacters()],
            'phone' => ['required', 'string', self::mobile()],
            // No upper bound here (Q7): more than the seats left is refused under the lock (AC-14).
            'participants' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:'.$config['notes_max']],
            'submission_key' => ['required', 'uuid'],
            'price' => ['required', 'integer', 'min:0'],
        ], [
            'name' => __('booking.errors.name'),
            'phone' => __('booking.errors.phone'),
            'participants' => __('booking.errors.participants'),
            'notes' => __('booking.errors.notes', ['max' => $config['notes_max']]),
        ])->validate();

        return [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'participants' => (int) $data['participants'],
            'notes' => $data['notes'] ?? null,
            'submission_key' => strtolower($data['submission_key']),
            'price' => (int) $data['price'],
        ];
    }

    /**
     * Trimmed, inner whitespace collapsed (AC-10).
     */
    private static function clean(mixed $value): mixed
    {
        return is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value) ?? $value) : $value;
    }

    private static function noControlCharacters(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value) === 1) {
                $fail(__('booking.errors.name'));
            }
        };
    }

    private static function mobile(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if (! is_string($value) || ! PhoneNumber::isMobile($value)) {
                $fail(__('booking.errors.phone'));
            }
        };
    }

    /**
     * AC-36: never stored by a browser or proxy.
     */
    private static function private(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
