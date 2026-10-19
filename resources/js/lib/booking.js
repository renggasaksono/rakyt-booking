/**
 * Display rules for the booking form and the customer booking page (F-005).
 * Dates are ISO ("YYYY-MM-DD") in the organization's time zone; instants are
 * ISO UTC from the server. Month and day names come from lang/id (trips.calendar).
 */

const pad = (n) => String(n).padStart(2, '0');

/**
 * Status pills: tint + glyph + word, never colour alone (AC-29, AC-32, ADR-0008).
 * The glossary gives no glyph for `completed`; "☑" keeps it distinct from Lunas "✓".
 */
export const BOOKING_STATUS_STYLES = {
    awaiting_payment: { glyph: '◷', className: 'bg-status-waiting-tint text-status-waiting' },
    needs_review: { glyph: '◐', className: 'bg-status-check-tint text-status-check' },
    paid: { glyph: '✓', className: 'bg-status-paid-tint text-status-paid' },
    rejected: { glyph: '✕', className: 'bg-status-rejected-tint text-status-rejected' },
    cancelled: { glyph: '–', className: 'bg-status-cancelled-tint text-status-cancelled' },
    completed: { glyph: '☑', className: 'bg-status-paid-tint text-status-paid' },
};

/**
 * AC-5: a typed or stepped value kept within 1…seats left. Empty stays empty
 * while typing (null).
 */
export function clampParticipants(value, max) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    const number = Math.trunc(Number(String(value).replace(/\D+/g, '')) || 0);

    return Math.min(Math.max(1, max), Math.max(1, number));
}

/**
 * AC-6: participants × unit price, integer rupiah.
 */
export function totalAmount(participants, unitPrice) {
    return (Number(participants) || 0) * (Number(unitPrice) || 0);
}

function parts(iso) {
    const [year, month, day] = String(iso).slice(0, 10).split('-').map(Number);
    const weekday = new Date(Date.UTC(year, month - 1, day)).getUTCDay();

    return { year, month, day, weekday };
}

/**
 * AC-1: "Kam 24 – Sab 26 Okt", "Kam 31 Okt – Sab 2 Nov", "Kam 24 Okt"; the year
 * only when it is not the current one.
 */
export function dateLine(startIso, endIso, today, calendar) {
    const s = parts(startIso);
    const e = parts(endIso || startIso);
    const currentYear = parts(today).year;
    const day = (p) => `${calendar.days_short[p.weekday]} ${p.day}`;
    const month = (p) => calendar.months_short[p.month - 1];
    const year = (p) => (p.year === currentYear ? '' : ` ${p.year}`);

    if (String(startIso) === String(endIso || startIso)) {
        return `${day(s)} ${month(s)}${year(s)}`;
    }

    if (s.year !== e.year) {
        return `${day(s)} ${month(s)} ${s.year} – ${day(e)} ${month(e)} ${e.year}`;
    }

    if (s.month !== e.month) {
        return `${day(s)} ${month(s)} – ${day(e)} ${month(e)}${year(e)}`;
    }

    return `${day(s)} – ${day(e)} ${month(e)}${year(e)}`;
}

/**
 * AC-1: "Pelabuhan Jepara 06.00", or null without a meeting point.
 */
export function meetingLine(meeting) {
    if (!meeting?.point) {
        return null;
    }

    return [meeting.point, meeting.time ? meeting.time.replace(':', '.') : null].filter(Boolean).join(' ');
}

/**
 * AC-30: the server's clock minus the phone's, taken when the page loaded.
 * The countdown adds it to the phone's clock, so a wrong phone clock does not change it.
 */
export function clockOffset(serverNowIso, clientNowMs) {
    return Date.parse(serverNowIso) - clientNowMs;
}

/**
 * Milliseconds of hold left, never below zero.
 */
export function remainingMs(holdExpiresAtIso, clientNowMs, offsetMs) {
    return Math.max(0, Date.parse(holdExpiresAtIso) - (clientNowMs + offsetMs));
}

/**
 * AC-29: "1 j 52 m", "52 m" under an hour, "< 1 m" under a minute; null at zero.
 * Returns { key, h, m } for booking.page.countdown.<key>.
 */
export function countdown(ms) {
    if (ms <= 0) {
        return null;
    }

    const minutes = Math.floor(ms / 60000);

    if (minutes < 1) {
        return { key: 'under_minute' };
    }

    if (minutes < 60) {
        return { key: 'minutes', m: minutes };
    }

    return { key: 'hours', h: Math.floor(minutes / 60), m: minutes % 60 };
}

/**
 * The wall-clock date and time of an instant in a time zone: { date: "YYYY-MM-DD", time: "HH.mm" }.
 */
export function localParts(iso, timezone) {
    const values = Object.fromEntries(
        new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        })
            .formatToParts(new Date(iso))
            .map((part) => [part.type, part.value]),
    );

    return { date: `${values.year}-${values.month}-${values.day}`, time: `${pad(values.hour)}.${pad(values.minute)}` };
}

/**
 * AC-29: "s.d. 16.40", or "s.d. besok 01.10" when the release is on the next
 * local day. Returns { key, time } for booking.page.<key>.
 */
export function releaseTime(holdExpiresAtIso, nowIso, timezone) {
    const release = localParts(holdExpiresAtIso, timezone);
    const now = localParts(nowIso, timezone);

    return { key: release.date > now.date ? 'hold_until_tomorrow' : 'hold_until', time: release.time };
}

/**
 * "+6281234567890" → "+62 812 3456 7890".
 */
export function formatPhone(e164) {
    const national = String(e164 ?? '').replace(/^\+62/, '');

    return `+62 ${[national.slice(0, 3), national.slice(3, 7), national.slice(7)].filter(Boolean).join(' ')}`;
}

/**
 * AC-31: the hold is over — released by the job, or past its end before the job ran.
 */
export function holdExpired(booking, ms) {
    return (booking.status === 'cancelled' && booking.cancel_reason === 'hold_expired')
        || (booking.status === 'awaiting_payment' && ms <= 0);
}

/**
 * AC-9: send the booking form. A tap while sending, or on a date that can no
 * longer be booked, sends nothing. A lost connection keeps every typed value
 * and the submission key, so the retry is the same submit (AC-16); onFail
 * shows "Belum terkirim…". `form` is an Inertia useForm(); `phoneDigits`
 * turns the typed number into the digits after +62.
 *
 * Returns whether a request was sent.
 */
export function sendBooking(form, url, { bookable, phoneDigits, onFail, onError }) {
    if (form.processing || !bookable) {
        return false;
    }

    form.transform((data) => ({
        ...data,
        participants: data.participants ?? 1,
        phone: phoneDigits(data.phone) === '' ? data.phone : `+62${phoneDigits(data.phone)}`,
    })).post(url, {
        // Stay in place on errors; the booking page opens at its top.
        preserveScroll: (page) => Object.keys(page.props.errors ?? {}).length > 0,
        onError,
        onNetworkError: () => {
            onFail();

            return false;
        },
        onHttpException: () => {
            onFail();

            return false;
        },
    });

    return true;
}
