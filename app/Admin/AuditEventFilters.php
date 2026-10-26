<?php

namespace App\Admin;

use App\Audit\Actor;
use App\Audit\AuditAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Filters of the admin audit view (F-015): organization, actor and action.
 * Every value comes from the query string; anything invalid is dropped, so
 * the list always renders. An actor id counts only with its actor type, since
 * ids are unique within a type only.
 */
final readonly class AuditEventFilters
{
    /** Query keys in URL order. */
    public const KEYS = ['organisasi', 'aktor', 'aktor_id', 'aksi'];

    /** Events that belong to no organization (F-010 platform-level). */
    public const PLATFORM = 'platform';

    private const ACTOR_ID_MAX = 100;

    public function __construct(
        public ?string $organization = null,
        public ?string $actorType = null,
        public ?string $actorId = null,
        public ?string $action = null,
        public int $page = 1,
    ) {}

    /**
     * @param  list<int>  $organizationIds  the organizations that exist
     */
    public static function fromRequest(Request $request, array $organizationIds): self
    {
        $organization = self::string($request->query('organisasi'));

        if ($organization !== self::PLATFORM && ! (ctype_digit((string) $organization) && in_array((int) $organization, $organizationIds, true))) {
            $organization = null;
        }

        $actorType = self::string($request->query('aktor'));
        $actorType = in_array($actorType, Actor::TYPES, true) ? $actorType : null;

        $actorId = self::string($request->query('aktor_id'));
        if ($actorType === null || $actorId === null || mb_strlen($actorId) > self::ACTOR_ID_MAX || preg_match('/[\p{C}]/u', $actorId)) {
            $actorId = null;
        }

        $action = AuditAction::tryFrom((string) self::string($request->query('aksi')))?->value;

        $page = self::string($request->query('halaman'));
        $page = ctype_digit((string) $page) && (int) $page >= 1 && (int) $page <= 10_000 ? (int) $page : 1;

        return new self($organization, $actorType, $actorId, $action, $page);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query): Builder
    {
        return $query
            ->when($this->organization === self::PLATFORM, fn (Builder $q) => $q->whereNull('organization_id'))
            ->when($this->organization !== null && $this->organization !== self::PLATFORM, fn (Builder $q) => $q->where('organization_id', (int) $this->organization))
            ->when($this->actorType !== null, fn (Builder $q) => $q->where('actor_type', $this->actorType))
            ->when($this->actorId !== null, fn (Builder $q) => $q->where('actor_id', $this->actorId))
            ->when($this->action !== null, fn (Builder $q) => $q->where('action', $this->action));
    }

    /**
     * The active filters as URL query values, without empty ones.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return array_filter([
            'organisasi' => $this->organization,
            'aktor' => $this->actorType,
            'aktor_id' => $this->actorId,
            'aksi' => $this->action,
        ], fn (?string $value) => $value !== null);
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return [
            'organisasi' => $this->organization,
            'aktor' => $this->actorType,
            'aktor_id' => $this->actorId,
            'aksi' => $this->action,
        ];
    }

    private static function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
