<?php

namespace App\Admin;

use App\Bookings\BookingFilters;
use App\Models\Organization;
use App\Organizations\OrganizationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Search and filter of the admin seller directory (F-012 AC-1, AC-2): a term
 * matched against the organization's name, slug or WhatsApp number (the
 * owner's phone), and the account status. Anything invalid is dropped, so the
 * list always renders.
 */
final readonly class SellerDirectoryFilters
{
    public const SEARCH_MIN = 2;

    public const SEARCH_MAX = 100;

    public function __construct(
        public ?string $search = null,
        public ?OrganizationStatus $status = null,
        public int $page = 1,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $page = $request->query('halaman');
        $page = is_string($page) && ctype_digit($page) && (int) $page >= 1 && (int) $page <= 10_000 ? (int) $page : 1;

        return new self(
            self::term($request->query('cari')),
            OrganizationStatus::tryFrom(is_string($request->query('status')) ? $request->query('status') : ''),
            $page,
        );
    }

    /**
     * A search term as typed: whitespace collapsed, 2–100 characters, or null.
     */
    public static function term(mixed $value): ?string
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || preg_match('/[\p{C}]/u', $value)) {
            return null;
        }

        $term = trim((string) preg_replace('/\s+/u', ' ', $value));
        $length = mb_strlen($term);

        return $length >= self::SEARCH_MIN && $length <= self::SEARCH_MAX ? $term : null;
    }

    /**
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->search !== null, fn (Builder $q) => self::matchSearch($q, (string) $this->search))
            ->when($this->status !== null, fn (Builder $q) => $q->where('status', $this->status->value));
    }

    /**
     * Name or slug (part, any case), or the WhatsApp number in any F-005
     * format or by its trailing digits — as the seller's booking search.
     *
     * @param  Builder<Organization>  $query
     * @return Builder<Organization>
     */
    public static function matchSearch(Builder $query, string $term): Builder
    {
        $like = '%'.BookingFilters::escapeLike($term).'%';
        $digits = BookingFilters::phoneDigits($term);

        return $query->where(fn (Builder $q) => $q
            ->where('name', 'ilike', $like)
            ->orWhere('slug', 'ilike', $like)
            ->when($digits !== null, fn (Builder $q) => $q->orWhere('whatsapp', 'like', '%'.$digits.'%')));
    }

    /**
     * The applied values, as they go back into URLs. Never the page.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter([
            'cari' => $this->search,
            'status' => $this->status?->value,
        ], fn (?string $value) => $value !== null);
    }

    /**
     * @return array{cari: ?string, status: ?string}
     */
    public function toArray(): array
    {
        return ['cari' => $this->search, 'status' => $this->status?->value];
    }
}
