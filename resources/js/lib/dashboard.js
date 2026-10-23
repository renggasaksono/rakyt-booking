/**
 * Display rules for Hari ini, the booking list and the booking detail (F-007).
 * Dates are ISO ("YYYY-MM-DD") in the organization's time zone; instants are
 * ISO UTC from the server. Month and day names come from lang/id (trips.calendar).
 */
import { localParts } from './booking.js';
import { formatDay } from './publicPage.js';
import { t } from './text.js';

/** URL keys of the list's search and filters, in order (AC-19). */
export const FILTER_KEYS = ['cari', 'status', 'trip', 'dari', 'sampai', 'keberangkatan'];

function dateParts(iso) {
    const [year, month, day] = String(iso).slice(0, 10).split('-').map(Number);

    return { year, month, day, weekday: new Date(Date.UTC(year, month - 1, day)).getUTCDay() };
}

/** "24 Okt", "24 Okt 2027" when not the current year (F-002's rule). */
export function dayMonth(iso, today, calendar) {
    return formatDay(iso, today, calendar);
}

/**
 * AC-12: the seat summary as parts — "12 lunas", "2 ditahan", "2 kosong",
 * "tutup 21 Okt". Zero parts are left out; "tutup …" only while the departure
 * is open and before its deadline (the server sends closes_on only then).
 * The bar emphasises the "ditahan" part, so each part keeps its key.
 */
export function seatSummaryParts(seats, closesOn, today, copy, calendar) {
    const parts = [
        ['paid', seats.paid],
        ['held', seats.held],
        ['empty', seats.empty],
    ]
        .filter(([, n]) => n > 0)
        .map(([key, n]) => ({ key, text: t(copy, `departures.seats.${key}`, { n }) }));

    if (closesOn) {
        parts.push({ key: 'closes', text: t(copy, 'departures.seats.closes', { date: dayMonth(closesOn, today, calendar) }) });
    }

    return parts;
}

/** AC-12: "12 lunas · 2 ditahan · 2 kosong · tutup 21 Okt" — what screen readers get. */
export function seatSummary(seats, closesOn, today, copy, calendar) {
    return seatSummaryParts(seats, closesOn, today, copy, calendar).map((part) => part.text).join(' · ');
}

/**
 * AC-12 bar and legend colours (mockup 2f): solid Lunas green, amber for
 * ditahan, a faint ink track for kosong. Tokens only (resources/css/app.css).
 */
export const SEAT_SEGMENT_CLASSES = {
    paid: 'bg-status-paid',
    held: 'bg-status-waiting-bar',
    empty: 'bg-ink/12',
};

/**
 * AC-12: segment widths in percent of the capacity, zero parts dropped.
 */
export function seatSegments(seats) {
    const total = Math.max(1, seats.paid + seats.held + seats.empty);

    return [
        { key: 'paid', n: seats.paid },
        { key: 'held', n: seats.held },
        { key: 'empty', n: seats.empty },
    ]
        .filter((segment) => segment.n > 0)
        .map((segment) => ({ ...segment, width: (segment.n / total) * 100 }));
}

/**
 * AC-10, AC-16: when a booking was made — "09.12" today, "Kemarin", or "14 Okt".
 */
export function createdLabel(createdIso, nowIso, timezone, copy, calendar) {
    const created = localParts(createdIso, timezone);
    const today = localParts(nowIso, timezone).date;

    if (created.date === today) {
        return created.time;
    }

    const yesterday = new Date(`${today}T00:00:00Z`);
    yesterday.setUTCDate(yesterday.getUTCDate() - 1);

    if (created.date === yesterday.toISOString().slice(0, 10)) {
        return t(copy, 'time.yesterday');
    }

    return dayMonth(created.date, today, calendar);
}

/**
 * AC-23: "Jum 16 Okt 09.12" (the year when not the current one).
 */
export function createdLong(createdIso, nowIso, timezone, calendar) {
    const created = localParts(createdIso, timezone);
    const p = dateParts(created.date);

    return `${calendar.days_short[p.weekday]} ${dayMonth(created.date, localParts(nowIso, timezone).date, calendar)} ${created.time}`;
}

/**
 * AC-23: "+6281234567890" → "+62 812-3456-7890".
 */
export function phoneDisplay(e164) {
    const national = String(e164 ?? '').replace(/^\+62/, '');

    return `+62 ${[national.slice(0, 3), national.slice(3, 7), national.slice(7)].filter(Boolean).join('-')}`;
}

/**
 * AC-19: the applied filters as a query object — empty values dropped, never the page.
 */
export function filtersToQuery(filters) {
    return Object.fromEntries(
        FILTER_KEYS
            .map((key) => [key, typeof filters[key] === 'string' ? filters[key].trim() : filters[key]])
            .filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );
}

/** AC-22: "Filter (2)" counts the filters, not the search. */
export function activeFilterCount(filters) {
    return Object.keys(filtersToQuery(filters)).filter((key) => key !== 'cari' && key !== 'sampai' && key !== 'dari').length
        + (filters.dari || filters.sampai ? 1 : 0);
}

/**
 * AC-7, AC-23: "s.d. 16.40", or "s.d. besok 01.10" when the release falls on
 * the next local day. Returns { key, time } for bookings.show.<key>.
 */
export function holdUntil(holdExpiresAtIso, nowIso, timezone) {
    const release = localParts(holdExpiresAtIso, timezone);
    const now = localParts(nowIso, timezone);

    return { key: release.date > now.date ? 'hold_until_tomorrow' : 'hold_until', time: release.time };
}

/** AC-7, AC-25: the hold is still running at the server's clock. */
export function holdActive(booking, nowIso) {
    return booking.status === 'awaiting_payment' && Date.parse(booking.hold_expires_at) > Date.parse(nowIso);
}
