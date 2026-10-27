<?php

namespace App\Http\Controllers\PublicPages;

use App\Bookings\BookingPresenter;
use App\Bookings\BookingService;
use App\Http\Controllers\Controller;
use App\Media\PrivateMedia;
use App\Media\UploadRules;
use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Models\PaymentRecord;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Payments\PaymentMethodType;
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
        $booking = $this->bookingByToken($token);

        $props = $this->presenter->page($this->current->get(), $booking);

        $response = self::private(
            Inertia::render('Public/Booking', $props)
                ->withViewData('plainHead', ['title' => $props['title'], 'noindex' => true])
                ->toResponse($request),
        );
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }

    /**
     * AC-12–AC-15: the customer uploads payment evidence. The file is validated
     * (AC-14), then BookingService stores it and moves the booking to
     * needs_review under the lock, re-checking the hold (AC-12, AC-13). A refusal
     * (expired hold or wrong status) redirects back to the booking page, which
     * then shows the expired / current state (AC-13, AC-15). The upload POST is
     * no-store via the public.booking.* middleware (AC-30). A suspended seller's
     * booking takes no upload (F-014 AC-6).
     */
    public function uploadEvidence(Request $request, string $organization, string $token): RedirectResponse
    {
        $booking = $this->bookingByToken($token);
        $url = self::bookingUrl($this->current->get()->slug, $booking);

        // F-014 AC-6: a suspended seller takes no evidence; nothing is stored.
        if (! $this->visibility->organizationIsPublic($this->current->get())) {
            return redirect()->to($url, 303)->with('notice', __('booking.payment.errors.seller_unavailable'));
        }

        try {
            $data = Validator::make(
                ['bukti' => $request->file('bukti')],
                ['bukti' => UploadRules::paymentEvidence()],
                ['bukti' => __('booking.payment.errors.evidence')],
            )->validate();
        } catch (ValidationException $e) {
            throw $e->redirectTo($url);
        }

        $record = $this->bookings->uploadEvidence($booking, $data['bukti']);

        if ($record === null) {
            // Refused under the lock: the hold ended, or the seller was suspended meanwhile.
            $suspended = ! $this->visibility->organizationIsPublic($this->current->get()->refresh());

            return redirect()->to($url, 303)->with('notice', __($suspended ? 'booking.payment.errors.seller_unavailable' : 'booking.payment.errors.expired'));
        }

        return redirect()->to($url, 303);
    }

    /**
     * AC-19 / F-009 A21: the customer streams their own evidence through the
     * booking token. Another booking's token resolves a different booking, so
     * its evidence is never reached.
     */
    public function showEvidence(Request $request, string $organization, string $token): Response
    {
        $booking = $this->bookingByToken($token);
        $record = PaymentRecord::query()->where('booking_id', $booking->getKey())->first();

        abort_if($record === null || ! $record->hasEvidence(), 404);

        return PrivateMedia::response($record->evidence_path);
    }

    /**
     * AC-22: the seller's active QRIS image, reachable only through a valid
     * booking token (never a guessable path).
     */
    public function showQris(Request $request, string $organization, string $token): Response
    {
        $this->bookingByToken($token);

        $qris = PaymentMethod::query()
            ->where('active', true)
            ->where('type', PaymentMethodType::Qris->value)
            ->orderBy('position')->orderBy('id')
            ->first();

        abort_if($qris === null || $qris->qris_image_path === null, 404);

        return PrivateMedia::response($qris->qris_image_path);
    }

    public static function bookingUrl(string $slug, Booking $booking): string
    {
        return route('public.booking.show', ['organization' => $slug, 'token' => $booking->access_token]);
    }

    /**
     * The booking for this access token within the current (slug-resolved)
     * organization, or a 404 (ADR-0027 §1–2).
     */
    private function bookingByToken(string $token): Booking
    {
        $booking = Booking::query()
            ->where('access_token_hash', Booking::hashToken($token))
            ->with('departure.trip')
            ->first();

        abort_if($booking === null, 404);

        return $booking;
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
