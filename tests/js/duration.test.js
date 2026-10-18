import { describe, expect, it } from 'vitest';
import { clampDays, clampNights, durationKey, endDate } from '../../resources/js/lib/duration.js';

const presets = [
    { key: 'hours', duration_label: 'hours', duration_days: 1, duration_nights: 0 },
    { key: '1d', duration_label: 'days', duration_days: 1, duration_nights: 0 },
    { key: '3d2n', duration_label: 'days', duration_days: 3, duration_nights: 2 },
];

describe('AC-5 duration picker', () => {
    it('keeps Hari between 1 and 30', () => {
        expect(clampDays(0)).toBe(1);
        expect(clampDays(31)).toBe(30);
        expect(clampDays(5)).toBe(5);
    });

    it('prevents Malam above Hari and says so', () => {
        expect(clampNights(3, 4)).toEqual({ nights: 3, tooMany: true });
        expect(clampNights(5, 4)).toEqual({ nights: 4, tooMany: false });
        expect(clampNights(1, -1)).toEqual({ nights: 0, tooMany: false });
    });

    it('recognises presets and custom durations', () => {
        expect(durationKey({ duration_label: 'days', duration_days: 3, duration_nights: 2 }, presets)).toBe('3d2n');
        expect(durationKey({ duration_label: 'days', duration_days: 5, duration_nights: 4 }, presets)).toBe('custom');
        expect(durationKey({ duration_label: null }, presets)).toBeNull();
    });
});

describe('AC-17 end date', () => {
    it('is derived from the start date and the number of days', () => {
        expect(endDate('2026-10-24', 3)).toBe('2026-10-26');
        expect(endDate('2026-10-30', 3)).toBe('2026-11-01');
        expect(endDate('2026-10-24', 1)).toBe('2026-10-24');
        expect(endDate(null, 3)).toBeNull();
    });
});
