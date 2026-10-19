<?php

namespace App\Bookings;

use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripDeparture;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Search and filters of the seller's booking list (F-007 AC-17–AC-20). Every
 * value comes from the query string; anything invalid — an unknown status, a
 * malformed date, `dari` after `sampai`, an unknown or another seller's trip
 * or departure — is dropped, so the list always renders. Trips and departures
 * are looked up through the tenant scope, so another seller's id and an
 * unknown one behave the same.
 */
final readonly class BookingFilters
{
    /** Query keys in URL order (AC-19). */
    public const KEYS = ['cari', 'status', 'trip', 'dari', 'sampai', 'keberangkatan'];

    public const ACTION_NEEDED = 'perlu_tindakan';

    /** AC-18: `completed` is not offered (Q5). */
    public const STATUSES = [self::ACTION_NEEDED, 'awaiting_payment', 'needs_review', 'paid', 'rejected', 'cancelled'];

    public const SEARCH_MIN = 2;

    public const SEARCH_MAX = 100;

    /** "7890" finds +6281234567890 (AC-17). */
    public const PHONE_DIGITS_MIN = 4;

    private const PUBLIC_ID_PATTERN = '/^[0-9a-z]{26}$/';

    private const PHONE_LIKE_PATTERN = '/^[\d\s+\-().]+$/';

    public function __construct(
        public ?string $search = null,
        public ?string $status = null,
        public ?Trip $trip = null,
        public ?string $from = null,
        public ?string $to = null,
        public ?TripDeparture $departure = null,
        public int $page = 1,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $from = self::date($request->query('dari'));
        $to = self::date($request->query('sampai'));

        // AC-20: a reversed range is ignored as a whole.
        if ($from !== null && $to !== null && $from > $to) {
            $from = $to = null;
        }

        $status = $request->query('status');
        $page = $request->query('halaman');

        return new self(
            search: self::search($request->query('cari')),
            status: is_string($status) && in_array($status, self::STATUSES, true) ? $status : null,
            trip: self::publicId($request->query('trip'), fn (string $id) => Trip::query()->where('public_id', $id)->first()),
            from: $from,
            to: $to,
            departure: self::publicId($request->query('keberangkatan'), fn (string $id) => TripDeparture::query()->with('trip')->where('public_id', $id)->first()),
            page: is_string($page) && preg_match('/^[1-9]\d{0,5}$/', $page) === 1 ? (int) $page : 1,
        );
    }

    /**
     * @param  Builder<Booking>  $query
     * @return Builder<Booking>
     */
    public function apply(Builder $query, CarbonInterface $now): Builder
    {
        if ($this->search !== null) {
            $query->where(fn (Builder $q) => $this->matchSearch($q, $this->search));
        }

        if ($this->status === self::ACTION_NEEDED) {
            $query->actionNeeded($now);
        } elseif ($this->status !== null) {
            $query->where('status', $this->status);
        }

        if ($this->trip !== null) {
            $query->whereHas('departure', fn (Builder $d) => $d->where('trip_id', $this->trip->getKey()));
        }

        if ($this->from !== null) {
            $query->whereHas('departure', fn (Builder $d) => $d->whereDate('start_date', '>=', $this->from));
        }

        if ($this->to !== null) {
            $query->whereHas('departure', fn (Builder $d) => $d->whereDate('start_date', '<=', $this->to));
        }

        if ($this->departure !== null) {
            $query->where('trip_departure_id', $this->departure->getKey());
        }

        return $query;
    }

    /**
     * The applied values, as they go back into URLs (AC-19). Never the page.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter([
            'cari' => $this->search,
            'status' => $this->status,
            'trip' => $this->trip?->public_id,
            'dari' => $this->from,
            'sampai' => $this->to,
            'keberangkatan' => $this->departure?->public_id,
        ], fn (?string $value) => $value !== null);
    }

    public function isEmpty(): bool
    {
        return $this->query() === [];
    }

    /**
     * Name (part), reference (part) or phone (any F-005 format, or trailing digits).
     *
     * @param  Builder<Booking>  $query
     */
    private function matchSearch(Builder $query, string $term): void
    {
        $like = '%'.self::escapeLike($term).'%';

        $query->where('customer_name', 'ilike', $like)
            ->orWhere('reference', 'ilike', $like);

        $digits = self::phoneDigits($term);

        if ($digits !== null) {
            $query->orWhere('customer_phone', 'like', '%'.$digits.'%');
        }
    }

    /**
     * The digits to look for in an E.164 number, or null when the term is not a
     * phone number: "0812 3456 7890", "+62 812-3456-7890" and "6281234567890"
     * become "81234567890" (PhoneNumber's prefixes); a short term ("7890") is
     * matched as typed.
     */
    public static function phoneDigits(string $term): ?string
    {
        if (preg_match(self::PHONE_LIKE_PATTERN, $term) !== 1) {
            return null;
        }

        $digits = (string) preg_replace('/\D+/', '', $term);

        if (strlen($digits) < self::PHONE_DIGITS_MIN) {
            return null;
        }

        // A whole number: drop the 00 / 62 / 0 prefix like PhoneNumber::normalize().
        if (strlen($digits) >= 9) {
            $digits = (string) preg_replace('/^(?:0062|62|0)/', '', $digits);
        }

        return $digits === '' ? null : $digits;
    }

    /**
     * AC-17: `%` and `_` are text, not wildcards (PostgreSQL's default escape is "\").
     */
    public static function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    private static function search(mixed $value): ?string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0")) {
            return null;
        }

        $term = trim((string) preg_replace('/\s+/u', ' ', $value));
        $length = mb_strlen($term);

        return $length >= self::SEARCH_MIN && $length <= self::SEARCH_MAX ? $term : null;
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    /**
     * @template T
     *
     * @param  callable(string): ?T  $find
     * @return T|null
     */
    private static function publicId(mixed $value, callable $find): mixed
    {
        return is_string($value) && preg_match(self::PUBLIC_ID_PATTERN, $value) === 1 ? $find($value) : null;
    }
}
