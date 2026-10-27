<?php

namespace App\Admin;

use App\Bookings\BookingStatus;
use App\Security\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Search and filters of the admin booking search (F-013, ADR-0041). Every
 * value comes from the query string; anything invalid is dropped, so the list
 * always renders.
 *
 * The search term is read as exactly one of:
 * - a booking reference (`RKB-BRM-1024-0142`, any case) — exact match (AC-1);
 * - a full Indonesian mobile number in any F-005 format (`0812…`, `+62 812-…`,
 *   `62812…`) — normalised to E.164 and matched exactly (AC-2);
 * - text — part of the customer's name, or of the seller's name or slug.
 */
final readonly class AdminBookingFilters
{
    /** Query keys in URL order (AC-3). */
    public const KEYS = ['cari', 'status', 'organisasi', 'dari', 'sampai'];

    public const SEARCH_REFERENCE = 'reference';

    public const SEARCH_PHONE = 'phone';

    public const SEARCH_TEXT = 'text';

    /** RKB-<booking code>-<MMDD>-<NNNN…> (ADR-0026 §5); bookings.reference is varchar(24). */
    private const REFERENCE_PATTERN = '/^RKB-[A-Z]{3}[A-Z0-9]?-\d{4}-\d{4,11}$/i';

    /** Only digits and phone punctuation: the term is meant as a number. */
    private const PHONE_LIKE_PATTERN = '/^[\d\s+\-().]+$/';

    /** Longest organization id accepted (bigint has 19 digits). */
    private const ORGANIZATION_ID_DIGITS = 18;

    public function __construct(
        public ?string $search = null,
        public ?BookingStatus $status = null,
        public ?int $organization = null,
        public ?string $from = null,
        public ?string $to = null,
        public int $page = 1,
    ) {}

    /**
     * @param  callable(int): bool  $organizationExists
     */
    public static function fromRequest(Request $request, callable $organizationExists): self
    {
        $from = self::date($request->query('dari'));
        $to = self::date($request->query('sampai'));

        // A reversed range is ignored as a whole (as the seller's list, F-007 AC-20).
        if ($from !== null && $to !== null && $from > $to) {
            $from = $to = null;
        }

        $status = $request->query('status');
        $page = $request->query('halaman');

        return new self(
            search: SellerDirectoryFilters::term($request->query('cari')),
            status: is_string($status) ? BookingStatus::tryFrom($status) : null,
            organization: self::organization($request->query('organisasi'), $organizationExists),
            from: $from,
            to: $to,
            page: is_string($page) && ctype_digit($page) && (int) $page >= 1 && (int) $page <= 10_000 ? (int) $page : 1,
        );
    }

    /**
     * How the search term is matched; null without a term.
     */
    public function searchKind(): ?string
    {
        return match (true) {
            $this->search === null => null,
            $this->reference() !== null => self::SEARCH_REFERENCE,
            $this->phone() !== null => self::SEARCH_PHONE,
            default => self::SEARCH_TEXT,
        };
    }

    /**
     * The term as a booking reference (upper case), or null.
     */
    public function reference(): ?string
    {
        return $this->search !== null && preg_match(self::REFERENCE_PATTERN, $this->search) === 1
            ? strtoupper($this->search)
            : null;
    }

    /**
     * AC-2: the term as an E.164 mobile number, as F-005 stores it, or null.
     */
    public function phone(): ?string
    {
        if ($this->search === null || preg_match(self::PHONE_LIKE_PATTERN, $this->search) !== 1) {
            return null;
        }

        $e164 = PhoneNumber::normalize($this->search);

        return PhoneNumber::isMobile($e164) ? $e164 : null;
    }

    /**
     * The applied values, as they go back into URLs (AC-3). Never the page.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter([
            'cari' => $this->search,
            'status' => $this->status?->value,
            'organisasi' => $this->organization === null ? null : (string) $this->organization,
            'dari' => $this->from,
            'sampai' => $this->to,
        ], fn (?string $value) => $value !== null);
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return array_merge(array_fill_keys(self::KEYS, null), $this->query());
    }

    /**
     * @param  callable(int): bool  $exists
     */
    private static function organization(mixed $value, callable $exists): ?int
    {
        if (! is_string($value) || ! ctype_digit($value) || strlen($value) > self::ORGANIZATION_ID_DIGITS) {
            return null;
        }

        return $exists((int) $value) ? (int) $value : null;
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }
}
