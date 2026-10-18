/**
 * Departure display rules shared by the editor and the quick-edit sheet.
 */

/**
 * AC-27: glyph + word, never colour alone; never brown or gold. Each status has
 * its own glyph, distinct from the booking status set (◷ ◐ ✓ ✕ –). Tints are the
 * --color-status-*-tint tokens (resources/css/app.css).
 */
export const STATUS_STYLES = {
    open: { glyph: '●', className: 'bg-status-paid-tint text-status-paid' },
    closed: { glyph: '○', className: 'bg-status-cancelled-tint text-status-cancelled' },
    full: { glyph: '◼', className: 'bg-status-check-tint text-status-check' },
    past: { glyph: '◁', className: 'bg-status-cancelled-tint text-status-cancelled' },
};

/** AC-18: another departure of the trip starts on the same date. */
export function hasSameDate(departures, startDate, exceptId = null) {
    return Boolean(startDate) && departures.some((departure) => departure.id !== exceptId && departure.start_date === startDate);
}

/** AC-33: the seats stepper never goes below the seats taken. */
export function clampCapacity(value, seatsTaken, max = 999) {
    const wanted = Math.trunc(Number(value) || 0);

    return Math.min(max, Math.max(Math.max(1, seatsTaken), wanted));
}

/** AC-32: the bookings sentence only when there are bookings. */
export function priceNote(copy, activeBookings, t) {
    const base = t(copy, 'quick_edit.price_note');

    return activeBookings > 0 ? `${base} ${t(copy, 'quick_edit.price_note_bookings', { n: activeBookings })}` : base;
}
