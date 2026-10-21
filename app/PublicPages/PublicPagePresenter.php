<?php

namespace App\PublicPages;

use App\Bookings\BookingPresenter;
use App\Models\Organization;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\TripPhoto;
use App\Organizations\BookingSlug;
use App\Trips\LocalTime;
use App\Trips\TripPricing;
use App\WhatsApp\WhatsAppLink;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\URL;

/**
 * Props and head tags for the public pages (F-002). Every field that leaves
 * the server is listed here explicitly — never a model's toArray() (AC-8,
 * Data & rules → Public fields). Values are ISO in code; the Vue pages format
 * them for display.
 */
final class PublicPagePresenter
{
    private const CARD_WIDTHS = [400, 800];

    private const GALLERY_WIDTHS = [400, 800, 1600];

    public function __construct(private PublicVisibility $visibility) {}

    /**
     * Seller page (AC-10–AC-16, AC-30).
     *
     * @return array{props: array<string, mixed>, head: array<string, ?string>}
     */
    public function seller(Organization $organization): array
    {
        $now = CarbonImmutable::now();
        $today = TripDeparture::today($organization, $now);

        // A bounded number of queries whatever the trip count (Data & rules → Queries).
        $trips = $this->visibility->trips()
            ->with([
                'photos',
                'departures' => fn ($query) => $query->bookable($today, $now)->orderBy('start_date')->orderBy('id'),
            ])
            ->get();

        $trips = self::sortForList($trips);
        $cards = $trips->map(fn (Trip $trip) => $this->card($organization, $trip))->values()->all();
        $cover = $trips->first()?->photos->first();
        $title = __('public.head.seller_title', ['name' => $organization->name]);

        return [
            'props' => [
                'title' => $title,
                'organization' => [
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    'city' => $organization->city,
                    'bio' => $organization->bio,
                    'logo_url' => $organization->logoUrl(),
                    'whatsapp_url' => self::whatsappUrl($organization),
                    'share_url' => BookingSlug::url($organization->slug),
                ],
                'trips' => $cards,
                'context' => ['today' => $today],
                'copy' => __('public'),
                'calendar' => __('trips.calendar'),
                'urls' => ['home' => route('home')],
            ],
            'head' => [
                'title' => $title,
                'og_title' => $organization->name,
                'description' => self::limit($organization->bio ?: ($cards === []
                    ? $organization->city
                    : __('public.head.seller_description', ['n' => count($cards), 'city' => $organization->city]))),
                'image' => self::absolute($organization->logoUrl() ?? $cover?->url(800)),
                'url' => BookingSlug::url($organization->slug),
            ],
        ];
    }

    /**
     * Trip page (AC-17–AC-28, AC-29).
     *
     * @return array{props: array<string, mixed>, head: array<string, ?string>}
     */
    public function trip(Organization $organization, Trip $trip): array
    {
        $now = CarbonImmutable::now();
        $today = TripDeparture::today($organization, $now);
        $trip->setRelation('organization', $organization);

        // AC-18: every departure from today on, any state; started ones are hidden.
        $departures = $trip->isScheduled()
            ? $trip->departures()->whereDate('start_date', '>=', $today)->orderBy('start_date')->orderBy('id')->get()
                ->each(fn (TripDeparture $departure) => $departure->setRelation('trip', $trip))
            : new Collection;

        $bookable = $departures->filter(fn (TripDeparture $departure) => $departure->isBookable($now));
        $fromPrice = TripPricing::fromPrice($trip, $bookable);
        $duration = $trip->duration()?->display();
        $cover = $trip->photos->first();
        $title = __('public.head.trip_title', ['trip' => $trip->name, 'name' => $organization->name]);
        $base = '/'.$organization->slug.'/'.$trip->slug;

        return [
            'props' => [
                'title' => $title,
                'organization' => [
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                    // AC-A3: the trip-only message; null when the number is invalid (AC-A6).
                    'whatsapp_url' => WhatsAppLink::to($organization, __('whatsapp.trip', [
                        'name' => $organization->name,
                        'trip' => $trip->name,
                    ])),
                ],
                'trip' => [
                    'slug' => $trip->slug,
                    'name' => $trip->name,
                    'type' => $trip->type->value,
                    'type_label' => __("trips.editor.types.{$trip->type->value}.label"),
                    'duration' => $duration,
                    'short_description' => $trip->short_description,
                    'booking_mode' => $trip->booking_mode->value,
                    'from_price' => $fromPrice,
                    'photos' => $trip->photos->map(fn (TripPhoto $photo) => self::photo($photo, self::GALLERY_WIDTHS))->values()->all(),
                    'inclusions' => array_values($trip->inclusions ?? []),
                    'exclusions' => array_values($trip->exclusions ?? []),
                    'meeting_point' => $trip->meeting_point,
                    'meeting_time' => $trip->meeting_time,
                    'map_url' => self::safeLink($trip->map_url),
                    'itinerary' => self::itinerary($trip->itinerary ?? []),
                    'terms' => $trip->terms,
                ],
                'departures' => $departures->map(fn (TripDeparture $departure) => self::departure($organization, $departure, $now, $today))->values()->all(),
                'context' => [
                    'today' => $today,
                    'now_local' => $now->setTimezone($organization->timezone)->format('Y-m-d H:i'),
                ],
                'rules' => [
                    'low_seats' => (int) config('rakyt.public.low_seats'),
                    'urgent_days' => (int) config('rakyt.public.deadline_urgent_days'),
                    'shown' => (int) config('rakyt.public.departures_shown'),
                ],
                'copy' => __('public'),
                'calendar' => __('trips.calendar'),
                'urls' => [
                    'seller' => route('public.seller', ['organization' => $organization->slug]),
                    // Owned by F-005 and F-004 (Q8); F-002 only builds the links.
                    'book' => url($base.'/pesan'),
                    'request_date' => url($base.'/ajukan-tanggal'),
                    'home' => route('home'),
                ],
            ],
            'head' => [
                'title' => $title,
                'og_title' => $trip->name,
                'description' => self::limit($trip->short_description ?: implode(' · ', array_filter([
                    __('public.head.trip_from', ['price' => self::rupiah($fromPrice)]),
                    $duration,
                    $organization->name,
                ]))),
                'image' => self::absolute($cover?->url(800)),
                'url' => BookingSlug::url($organization->slug).'/'.$trip->slug,
            ],
        ];
    }

    /**
     * AC-13: trips with a bookable departure first, by their earliest one (ties
     * by name); then request-date trips; then scheduled trips without a
     * bookable departure — the last two newest published first.
     *
     * @param  Collection<int, Trip>  $trips
     * @return Collection<int, Trip>
     */
    public static function sortForList(Collection $trips): Collection
    {
        $group = fn (Trip $trip) => match (true) {
            $trip->isScheduled() && $trip->departures->isNotEmpty() => 0,
            ! $trip->isScheduled() => 1,
            default => 2,
        };

        return $trips->sort(function (Trip $a, Trip $b) use ($group) {
            $order = $group($a) <=> $group($b);

            if ($order !== 0) {
                return $order;
            }

            if ($group($a) === 0) {
                return [$a->departures->first()->start_date->toDateString(), $a->name, $a->id]
                    <=> [$b->departures->first()->start_date->toDateString(), $b->name, $b->id];
            }

            return [$b->published_at?->getTimestamp(), $b->id] <=> [$a->published_at?->getTimestamp(), $a->id];
        })->values();
    }

    /**
     * AC-14: one trip card. `departures` holds the trip's bookable ones.
     *
     * @return array<string, mixed>
     */
    private function card(Organization $organization, Trip $trip): array
    {
        $next = $trip->isScheduled() ? $trip->departures->first() : null;
        $cover = $trip->photos->first();

        return [
            'slug' => $trip->slug,
            'name' => $trip->name,
            'url' => route('public.trip', ['organization' => $organization->slug, 'trip' => $trip->slug]),
            'cover' => $cover === null ? null : self::photo($cover, self::CARD_WIDTHS),
            'from_price' => TripPricing::fromPrice($trip, $trip->departures),
            'state' => match (true) {
                ! $trip->isScheduled() => 'request_date',
                $next !== null => 'bookable',
                default => 'no_date',
            },
            'next_start_date' => $next?->start_date->toDateString(),
            'seats_left' => $next?->seatsLeft(),
        ];
    }

    /**
     * Data & rules → Departure state for display, plus the bookable-only fields.
     *
     * @return array<string, mixed>
     */
    private static function departure(Organization $organization, TripDeparture $departure, CarbonImmutable $now, string $today): array
    {
        $bookable = $departure->isBookable($now);

        return [
            'public_id' => $departure->public_id,
            'start_date' => $departure->start_date->toDateString(),
            'end_date' => $departure->end_date->toDateString(),
            'state' => match (true) {
                $bookable => 'bookable',
                $departure->isOpen() && $departure->isFull() => 'full',
                default => 'closed',
            },
            'seats_left' => $bookable ? $departure->seatsLeft() : null,
            'capacity' => $bookable ? $departure->capacity : null,
            'price' => $bookable ? $departure->effectivePrice() : null,
            'deadline' => $bookable ? LocalTime::input($departure->booking_deadline_at, $organization->timezone) : null,
            'deadline_preset' => $bookable ? $departure->booking_deadline_preset->value : null,
            // AC-A4: the dated message on bookable rows; null on full/closed rows
            // and when the number is invalid (AC-A6), like the other bookable-only fields.
            'whatsapp_url' => $bookable ? WhatsAppLink::to($organization, __('whatsapp.trip_dated', [
                'name' => $organization->name,
                'trip' => $departure->trip->name,
                'date' => BookingPresenter::dates($departure->start_date->toDateString(), $departure->end_date->toDateString(), $today),
            ])) : null,
        ];
    }

    /**
     * AC-32: the smallest variant as `src`, the generated variants in `srcset`;
     * the original when no variant exists yet.
     *
     * @param  list<int>  $widths
     * @return array{src: string, srcset: ?string}
     */
    private static function photo(TripPhoto $photo, array $widths): array
    {
        $available = array_values(array_filter($widths, fn (int $width) => isset($photo->variants[(string) $width])));

        return [
            'src' => $available === [] ? $photo->url() : $photo->url($available[0]),
            'srcset' => $available === [] ? null : implode(', ', array_map(fn (int $width) => $photo->url($width).' '.$width.'w', $available)),
        ];
    }

    /**
     * AC-26: filled days only, keeping the real day number.
     *
     * @param  array<int, mixed>  $lines
     * @return list<array{day: int, text: string}>
     */
    private static function itinerary(array $lines): array
    {
        $days = [];

        foreach (array_values($lines) as $index => $line) {
            if (is_string($line) && trim($line) !== '') {
                $days[] = ['day' => $index + 1, 'text' => $line];
            }
        }

        return $days;
    }

    /**
     * AC-27: only http(s) links leave the server.
     */
    private static function safeLink(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }

    /**
     * F-002 AC-11 / F-008 AC-B3: the seller-page "Chat WhatsApp" link, built by
     * the shared helper; null when the number is invalid (AC-A6/D3).
     */
    private static function whatsappUrl(Organization $organization): ?string
    {
        return WhatsAppLink::to($organization, __('whatsapp.seller', ['name' => $organization->name]));
    }

    /**
     * AC-28: "Rp1.250.000".
     */
    private static function rupiah(?int $amount): string
    {
        return 'Rp'.number_format((int) $amount, 0, ',', '.');
    }

    private static function limit(?string $text): ?string
    {
        $max = (int) config('rakyt.public.description_max');

        if ($text === null || mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    private static function absolute(?string $url): ?string
    {
        return $url === null ? null : URL::to($url);
    }
}
