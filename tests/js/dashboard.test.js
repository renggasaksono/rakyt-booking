import { describe, expect, it } from 'vitest';
import {
    activeFilterCount,
    createdLabel,
    createdLong,
    dayMonth,
    filtersToQuery,
    holdActive,
    holdUntil,
    phoneDisplay,
    seatSegments,
    seatSummary,
} from '../../resources/js/lib/dashboard.js';

const calendar = {
    months_short: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'],
    days_short: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
};

const copy = {
    departures: {
        seats: { paid: ':n lunas', held: ':n ditahan', empty: ':n kosong', closes: 'tutup :date' },
    },
    time: { yesterday: 'Kemarin' },
};

const tz = 'Asia/Jakarta';

describe('seat summary (AC-12)', () => {
    it('lists the three parts and the closing date', () => {
        expect(seatSummary({ paid: 12, held: 2, empty: 2 }, '2024-10-21', '2024-10-10', copy, calendar))
            .toBe('12 lunas · 2 ditahan · 2 kosong · tutup 21 Okt');
    });

    it('drops zero parts and the closing date when there is none', () => {
        expect(seatSummary({ paid: 5, held: 0, empty: 11 }, null, '2024-10-10', copy, calendar)).toBe('5 lunas · 11 kosong');
        expect(seatSummary({ paid: 16, held: 0, empty: 0 }, null, '2024-10-10', copy, calendar)).toBe('16 lunas');
    });

    it('adds the year to a closing date in another year', () => {
        expect(seatSummary({ paid: 0, held: 0, empty: 4 }, '2025-01-03', '2024-12-30', copy, calendar)).toBe('4 kosong · tutup 3 Jan 2025');
    });
});

describe('seat segments (AC-12)', () => {
    it('are in proportion and drop zero parts', () => {
        expect(seatSegments({ paid: 12, held: 2, empty: 2 })).toEqual([
            { key: 'paid', n: 12, width: 75 },
            { key: 'held', n: 2, width: 12.5 },
            { key: 'empty', n: 2, width: 12.5 },
        ]);
        expect(seatSegments({ paid: 0, held: 0, empty: 16 })).toEqual([{ key: 'empty', n: 16, width: 100 }]);
    });
});

describe('creation time (AC-10)', () => {
    const now = '2024-10-16T01:15:00Z'; // Jum 16 Okt 08.15 WIB

    it('is the time today, "Kemarin" yesterday, else the date', () => {
        expect(createdLabel('2024-10-16T02:12:00Z', now, tz, copy, calendar)).toBe('09.12');
        expect(createdLabel('2024-10-15T17:00:00Z', now, tz, copy, calendar)).toBe('00.00');
        expect(createdLabel('2024-10-15T16:59:00Z', now, tz, copy, calendar)).toBe('Kemarin');
        expect(createdLabel('2024-10-14T04:00:00Z', now, tz, copy, calendar)).toBe('14 Okt');
        expect(createdLabel('2023-10-14T04:00:00Z', now, tz, copy, calendar)).toBe('14 Okt 2023');
    });

    it('reads "Kemarin" across a month boundary', () => {
        expect(createdLabel('2024-10-31T05:00:00Z', '2024-11-01T05:00:00Z', tz, copy, calendar)).toBe('Kemarin');
    });

    it('long form on the detail (AC-23)', () => {
        expect(createdLong('2024-10-16T02:12:00Z', now, tz, calendar)).toBe('Rab 16 Okt 09.12');
    });
});

describe('hold (AC-7, AC-25)', () => {
    it('is "s.d." today and "s.d. besok" on the next local day', () => {
        expect(holdUntil('2024-10-16T09:40:00Z', '2024-10-16T07:40:00Z', tz)).toEqual({ key: 'hold_until', time: '16.40' });
        expect(holdUntil('2024-10-16T18:10:00Z', '2024-10-16T16:10:00Z', tz)).toEqual({ key: 'hold_until_tomorrow', time: '01.10' });
    });

    it('is active only while awaiting payment and before its end', () => {
        const booking = { status: 'awaiting_payment', hold_expires_at: '2024-10-16T09:40:00Z' };

        expect(holdActive(booking, '2024-10-16T09:39:59Z')).toBe(true);
        expect(holdActive(booking, '2024-10-16T09:40:00Z')).toBe(false);
        expect(holdActive({ ...booking, status: 'needs_review' }, '2024-10-16T08:00:00Z')).toBe(false);
    });
});

describe('filters and query string (AC-19, AC-22)', () => {
    it('keeps applied values in order and drops empty ones', () => {
        expect(filtersToQuery({ cari: '  rina ', status: 'paid', trip: null, dari: '', sampai: '2024-10-31', keberangkatan: undefined, halaman: 3 }))
            .toEqual({ cari: 'rina', status: 'paid', sampai: '2024-10-31' });
        expect(Object.keys(filtersToQuery({ keberangkatan: 'x', cari: 'a', status: 'paid' }))).toEqual(['cari', 'status', 'keberangkatan']);
    });

    it('counts filters, not the search; a date range is one', () => {
        expect(activeFilterCount({ cari: 'rina', status: 'paid', dari: '2024-10-01', sampai: '2024-10-31' })).toBe(2);
        expect(activeFilterCount({ cari: 'rina' })).toBe(0);
        expect(activeFilterCount({ trip: 'a', keberangkatan: 'b', sampai: '2024-10-31' })).toBe(3);
    });
});

describe('display helpers', () => {
    it('formats the phone with dashes (AC-23)', () => {
        expect(phoneDisplay('+6281234567890')).toBe('+62 812-3456-7890');
    });

    it('shows a year only when not the current one', () => {
        expect(dayMonth('2024-10-24', '2024-10-10', calendar)).toBe('24 Okt');
        expect(dayMonth('2027-10-24', '2026-10-10', calendar)).toBe('24 Okt 2027');
    });
});
