/**
 * Onboarding helpers (F-001 C5–C7, D4–D5).
 */
import { formatRupiah } from './money.js';
import { presetOptions } from './deadline.js';

const RELATIVE = ['h7', 'h3', 'h1', 'same_day'];

/**
 * C6: the organization's default preset, or — when that one is already past
 * for this date — the nearest later preset that is still valid, else custom.
 */
export function defaultDeadlinePreset(defaultPreset, presets, startDate, meetingTime, nowLocal) {
    if (!startDate) {
        return defaultPreset;
    }

    const options = presetOptions(presets, startDate, meetingTime, nowLocal);
    const usable = (value) => options.some((option) => option.value === value && !option.disabled);

    if (usable(defaultPreset)) {
        return defaultPreset;
    }

    const later = RELATIVE.slice(Math.max(0, RELATIVE.indexOf(defaultPreset)) + 1);

    return later.find(usable) ?? 'custom';
}

/** "Senin 21 Okt 23.59" from a wall-clock "YYYY-MM-DD HH:mm". */
export function formatDeadlineWords(local, calendar) {
    if (!local) {
        return '';
    }

    const [date, time] = local.split(' ');
    const [year, month, day] = date.split('-').map(Number);
    const weekday = new Date(Date.UTC(year, month - 1, day)).getUTCDay();

    return `${calendar.days_long[weekday]} ${day} ${calendar.months_short[month - 1]} ${time.replace(':', '.')}`;
}

/** "Sab 26 Okt" */
export function formatShortDay(iso, calendar) {
    if (!iso) {
        return '';
    }

    const [year, month, day] = iso.split('-').map(Number);
    const weekday = new Date(Date.UTC(year, month - 1, day)).getUTCDay();

    return `${calendar.days_short[weekday]} ${day} ${calendar.months_short[month - 1]}`;
}

/** C7: plain-words summary parts, or null while something is missing. */
export function scheduleSummary({ seats, price, deadline }, calendar) {
    if (!seats || !price || !deadline) {
        return null;
    }

    return { seats, price: `Rp${formatRupiah(price)}`, deadline: formatDeadlineWords(deadline, calendar) };
}

/**
 * D5: native share sheet when there is one, otherwise copy the link.
 * Returns 'shared', 'copied' or 'failed'.
 */
export async function shareOrCopy({ url, text }, nav = globalThis.navigator) {
    if (nav?.share) {
        try {
            await nav.share({ url, text });

            return 'shared';
        } catch (error) {
            if (error?.name === 'AbortError') {
                return 'cancelled';
            }
        }
    }

    return copyLink(url, nav);
}

export async function copyLink(url, nav = globalThis.navigator) {
    try {
        if (nav?.clipboard?.writeText) {
            await nav.clipboard.writeText(url);

            return 'copied';
        }
    } catch {
        // Fall through to the legacy path below.
    }

    // Plain-HTTP pages (local .test dev) and older Android browsers have no
    // async clipboard: fall back to a hidden textarea + execCommand('copy').
    return legacyCopy(url) ? 'copied' : 'failed';
}

function legacyCopy(text) {
    try {
        const field = document.createElement('textarea');
        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.top = '-100vh';
        document.body.appendChild(field);
        field.select();
        const copied = document.execCommand('copy');
        field.remove();

        return copied;
    } catch {
        return false;
    }
}
