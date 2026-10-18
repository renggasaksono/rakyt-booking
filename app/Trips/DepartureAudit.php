<?php

namespace App\Trips;

use App\Audit\Actor;
use App\Audit\AuditAction;
use App\Audit\AuditLog;
use App\Models\TripDeparture;
use BackedEnum;
use DateTimeInterface;

/**
 * Writes the departure audit events of F-003 AC-38: one event per changed
 * aspect (price, capacity, status, deadline), with before and after values.
 */
final class DepartureAudit
{
    private const ASPECTS = [
        'price' => [AuditAction::DeparturePriceChanged, ['price_override']],
        'capacity' => [AuditAction::DepartureCapacityChanged, ['capacity']],
        'status' => [AuditAction::DepartureStatusChanged, ['status', 'closed_reason']],
        'deadline' => [AuditAction::DepartureDeadlineChanged, ['booking_deadline_preset', 'booking_deadline_at']],
    ];

    private const SNAPSHOT = ['start_date', 'end_date', 'capacity', 'price_override', 'booking_deadline_preset', 'booking_deadline_at', 'status', 'closed_reason'];

    public function __construct(private AuditLog $audit) {}

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(TripDeparture $departure): array
    {
        $values = [];

        foreach (self::SNAPSHOT as $key) {
            $value = $departure->getAttribute($key);
            $values[$key] = str_ends_with($key, '_date') && $value instanceof DateTimeInterface
                ? $value->format('Y-m-d')
                : self::plain($value);
        }

        return $values;
    }

    /**
     * Compare a snapshot taken before the change with the saved departure.
     *
     * @param  array<string, mixed>  $before
     */
    public function recordChanges(TripDeparture $departure, array $before, ?Actor $actor = null): void
    {
        $after = self::snapshot($departure);

        foreach (self::ASPECTS as [$action, $keys]) {
            $old = array_intersect_key($before, array_flip($keys));
            $new = array_intersect_key($after, array_flip($keys));

            if ($old !== $new) {
                $this->audit->record($action, $departure, $old, $new, $actor);
            }
        }
    }

    public function created(TripDeparture $departure): void
    {
        $this->audit->record(AuditAction::DepartureCreated, $departure, [], self::snapshot($departure));
    }

    public function deleted(TripDeparture $departure): void
    {
        $this->audit->record(AuditAction::DepartureDeleted, $departure, self::snapshot($departure), []);
    }

    private static function plain(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            default => $value,
        };
    }
}
