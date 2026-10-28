import { ACCOUNT_STATUS_STYLES } from './adminSellers.js';
import { BOOKING_STATUS_STYLES } from './booking.js';

/**
 * Admin overview (F-011): counts as Indonesian numbers ("1.234").
 */
const COUNT_FORMAT = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

export function formatCount(count) {
    return COUNT_FORMAT.format(Number.isFinite(count) ? count : 0);
}

/**
 * The status-set style (tint + deep text + glyph, ADR-0008) of a count: the
 * booking statuses' (F-007) and the account statuses' (F-012), never a copy.
 * Null for a count that is not one status (sellers, trips, all bookings).
 */
export function statStyle(key) {
    return BOOKING_STATUS_STYLES[key] ?? ACCOUNT_STATUS_STYLES[key] ?? null;
}
