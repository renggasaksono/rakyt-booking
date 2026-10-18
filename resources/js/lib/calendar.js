/**
 * Indonesian date display from ISO values (F-003 Data & rules → Time). Names
 * come from lang/id (trips.calendar) so they match the server's Carbon output.
 * Wall-clock values ("YYYY-MM-DD HH:mm") are already in the organization's zone.
 */

function parts(iso) {
    const [date, time = '00:00'] = String(iso).replace('T', ' ').split(' ');
    const [year, month, day] = date.split('-').map(Number);
    const [hour, minute] = time.split(':').map(Number);
    const weekday = new Date(Date.UTC(year, month - 1, day)).getUTCDay();

    return { year, month, day, hour, minute, weekday };
}

const pad = (n) => String(n).padStart(2, '0');

/** "Kam, 24 Okt 2026" */
export function formatDate(iso, calendar) {
    const p = parts(iso);

    return `${calendar.days_short[p.weekday]}, ${p.day} ${calendar.months_short[p.month - 1]} ${p.year}`;
}

/** "24 Okt" */
export function formatDayMonth(iso, calendar) {
    const p = parts(iso);

    return `${p.day} ${calendar.months_short[p.month - 1]}`;
}

/** "24 – 26 Okt 2026", "30 Okt – 1 Nov 2026", "24 Okt 2026" */
export function formatRange(startIso, endIso, calendar) {
    const s = parts(startIso);
    const e = parts(endIso ?? startIso);
    const month = (p) => calendar.months_short[p.month - 1];

    if (startIso === endIso || !endIso) {
        return `${s.day} ${month(s)} ${s.year}`;
    }

    if (s.year !== e.year) {
        return `${s.day} ${month(s)} ${s.year} – ${e.day} ${month(e)} ${e.year}`;
    }

    if (s.month !== e.month) {
        return `${s.day} ${month(s)} – ${e.day} ${month(e)} ${e.year}`;
    }

    return `${s.day} – ${e.day} ${month(e)} ${e.year}`;
}

/** "21 Okt 23.59" */
export function formatShort(local, calendar) {
    const p = parts(local);

    return `${p.day} ${calendar.months_short[p.month - 1]} ${pad(p.hour)}.${pad(p.minute)}`;
}

/** "Senin, 21 Okt" and "23.59", for the copy template ":date pukul :time" */
export function formatLongParts(local, calendar) {
    const p = parts(local);

    return {
        date: `${calendar.days_long[p.weekday]}, ${p.day} ${calendar.months_short[p.month - 1]}`,
        time: `${pad(p.hour)}.${pad(p.minute)}`,
    };
}
