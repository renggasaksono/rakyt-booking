/**
 * Display rules for the public booking pages (F-002). Dates are ISO
 * ("YYYY-MM-DD") or wall-clock ("YYYY-MM-DD HH:mm") values already in the
 * organization's time zone; month names come from lang/id (trips.calendar).
 */

const pad = (n) => String(n).padStart(2, '0');

function parts(iso) {
    const [date, time = '00:00'] = String(iso).replace('T', ' ').split(' ');
    const [year, month, day] = date.split('-').map(Number);
    const [hour, minute] = time.split(':').map(Number);

    return { year, month, day, hour, minute, date };
}

function shiftDate(date, days) {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(Date.UTC(year, month - 1, day + days)).toISOString().slice(0, 10);
}

/**
 * AC-10: up to two initials of Nama usaha, e.g. "Kencana Trip" → "KT".
 */
export function initials(name) {
    const words = String(name ?? '')
        .trim()
        .split(/\s+/)
        .map((word) => word.replace(/[^\p{L}\p{N}]/gu, ''))
        .filter(Boolean);

    return words
        .slice(0, 2)
        .map((word) => Array.from(word)[0].toUpperCase())
        .join('');
}

/**
 * AC-19: "24–26 Okt", "31 Okt – 2 Nov", "24 Okt"; the year only when it is not
 * the current one ("2–4 Jan 2027", "30 Des 2026 – 2 Jan 2027").
 */
export function formatDates(startIso, endIso, today, calendar) {
    const s = parts(startIso);
    const e = parts(endIso || startIso);
    const currentYear = parts(today).year;
    const month = (p) => calendar.months_short[p.month - 1];
    const year = (p) => (p.year === currentYear ? '' : ` ${p.year}`);

    if (s.date === e.date) {
        return `${s.day} ${month(s)}${year(s)}`;
    }

    if (s.year !== e.year) {
        return `${s.day} ${month(s)} ${s.year} – ${e.day} ${month(e)} ${e.year}`;
    }

    if (s.month !== e.month) {
        return `${s.day} ${month(s)} – ${e.day} ${month(e)}${year(e)}`;
    }

    return `${s.day}–${e.day} ${month(e)}${year(e)}`;
}

/**
 * "24 Okt" (with the year when it is not the current one).
 */
export function formatDay(iso, today, calendar) {
    return formatDates(iso, iso, today, calendar);
}

/**
 * AC-19 / Q4: which seats line a bookable row shows.
 */
export function seatsLine(seatsLeft, capacity, lowSeats) {
    return seatsLeft <= lowSeats ? { key: 'trip.seats_low', n: seatsLeft, m: capacity } : { key: 'trip.seats', n: seatsLeft };
}

/**
 * AC-19a: one summary line when every bookable departure uses the same
 * relative preset; otherwise a closing date on each bookable row. A row whose
 * deadline is within `urgentDays` always shows its own date.
 *
 * Returns { summary: preset | null, rows: { [public_id]: { kind, date, time } } }
 * where kind is "today", "tomorrow" or "date".
 */
export function closingInfo(departures, nowLocal, urgentDays) {
    const bookable = departures.filter((departure) => departure.state === 'bookable' && departure.deadline);
    const presets = new Set(bookable.map((departure) => departure.deadline_preset));
    const [shared] = presets;
    const summary = presets.size === 1 && shared !== 'custom' ? shared : null;

    const now = parts(nowLocal);
    const urgentUntil = `${shiftDate(now.date, urgentDays)} ${pad(now.hour)}:${pad(now.minute)}`;
    const tomorrow = shiftDate(now.date, 1);
    const rows = {};

    for (const departure of bookable) {
        const deadline = String(departure.deadline).slice(0, 16);

        if (summary !== null && deadline > urgentUntil) {
            continue;
        }

        const d = parts(deadline);
        const kind = d.date === now.date ? 'today' : d.date === tomorrow ? 'tomorrow' : 'date';

        rows[departure.public_id] = { kind, date: d.date, time: `${pad(d.hour)}.${pad(d.minute)}` };
    }

    return { summary, rows };
}

/**
 * AC-21: the earliest bookable departure, or null.
 */
export function initialSelection(departures) {
    return departures.find((departure) => departure.state === 'bookable')?.public_id ?? null;
}

/**
 * F-008 AC-A5: the "Tanya via WhatsApp" link for the current selection — the
 * selected departure's dated message, else the trip-only message on the
 * organization. Null (invalid number, AC-A6) hides the button.
 */
export function whatsappUrl(selected, organization) {
    return selected?.whatsapp_url ?? organization?.whatsapp_url ?? null;
}

/**
 * AC-22: "Pesan →" target for the selected departure (route owned by F-005).
 */
export function bookUrl(base, publicId) {
    return `${base}?keberangkatan=${encodeURIComponent(publicId)}`;
}

/**
 * AC-12: the native share sheet when there is one; otherwise, or when it
 * fails for any reason but a user cancel, copy the link. Returns "shared",
 * "cancelled", "copied" or "failed".
 */
export async function shareOrCopy(data, { share, copy }) {
    if (typeof share === 'function') {
        try {
            await share(data);

            return 'shared';
        } catch (error) {
            if (error?.name === 'AbortError') {
                return 'cancelled';
            }
        }
    }

    try {
        await copy(data.url);

        return 'copied';
    } catch {
        return 'failed';
    }
}
