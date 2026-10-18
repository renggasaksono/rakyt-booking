/**
 * Durasi picker rules (F-003 AC-5): Hari 1–30, Malam 0–Hari.
 */

export const MAX_DAYS = 30;

export function clampDays(days, maxDays = MAX_DAYS) {
    return Math.min(maxDays, Math.max(1, Math.trunc(Number(days) || 1)));
}

/**
 * Returns the nights value to keep and whether the seller tried to go above Hari.
 */
export function clampNights(days, nights) {
    const wanted = Math.max(0, Math.trunc(Number(nights) || 0));

    return { nights: Math.min(wanted, days), tooMany: wanted > days };
}

/**
 * Duration fields -> picker key ("hours", "3d2n", … or "custom", or null when unset).
 */
export function durationKey(fields, presets) {
    if (!fields.duration_label) {
        return null;
    }

    const match = presets.find(
        (preset) =>
            preset.duration_label === fields.duration_label &&
            preset.duration_days === fields.duration_days &&
            preset.duration_nights === fields.duration_nights,
    );

    return match ? match.key : 'custom';
}

/**
 * End date (ISO) from a start date and the number of days (ADR-0005).
 */
export function endDate(startDate, days) {
    if (!startDate || !days) {
        return null;
    }

    const [year, month, day] = startDate.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day + days - 1));

    return date.toISOString().slice(0, 10);
}
