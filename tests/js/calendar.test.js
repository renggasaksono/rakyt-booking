import { describe, expect, it } from 'vitest';
import { formatDate, formatDayMonth, formatLongParts, formatRange, formatShort } from '../../resources/js/lib/calendar.js';

const calendar = {
    months_short: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'],
    days_short: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
    days_long: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
};

describe('Indonesian dates', () => {
    it('formats dates and ranges like the mockups', () => {
        expect(formatDate('2024-10-24', calendar)).toBe('Kam, 24 Okt 2024');
        expect(formatDayMonth('2024-10-24', calendar)).toBe('24 Okt');
        expect(formatRange('2026-10-24', '2026-10-26', calendar)).toBe('24 – 26 Okt 2026');
        expect(formatRange('2026-10-30', '2026-11-01', calendar)).toBe('30 Okt – 1 Nov 2026');
        expect(formatRange('2026-12-31', '2027-01-01', calendar)).toBe('31 Des 2026 – 1 Jan 2027');
        expect(formatRange('2026-10-24', '2026-10-24', calendar)).toBe('24 Okt 2026');
    });

    it('formats deadlines with a dot between hours and minutes', () => {
        expect(formatShort('2024-10-21 23:59', calendar)).toBe('21 Okt 23.59');
        expect(formatLongParts('2024-10-21 23:59', calendar)).toEqual({ date: 'Senin, 21 Okt', time: '23.59' });
    });
});
