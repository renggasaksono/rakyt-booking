import { describe, expect, it } from 'vitest';
import {
    bookUrl,
    closingInfo,
    formatDates,
    formatDay,
    initialSelection,
    initials,
    seatsLine,
    shareOrCopy,
} from '../../resources/js/lib/publicPage.js';

const calendar = {
    months_short: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'],
};

const bookable = (id, deadline, preset = 'h3') => ({ public_id: id, state: 'bookable', deadline, deadline_preset: preset });

describe('F-002 AC-10 initials', () => {
    it('takes up to two initials of the business name', () => {
        expect(initials('Kencana Trip')).toBe('KT');
        expect(initials('kencana trip jepara')).toBe('KT');
        expect(initials('Bahari')).toBe('B');
        expect(initials('  (Ujung) Kulon  ')).toBe('UK');
        expect(initials('')).toBe('');
    });
});

describe('F-002 AC-19 departure dates', () => {
    const today = '2026-10-19';

    it('formats ranges without the current year', () => {
        expect(formatDates('2026-10-24', '2026-10-26', today, calendar)).toBe('24–26 Okt');
        expect(formatDates('2026-10-31', '2026-11-02', today, calendar)).toBe('31 Okt – 2 Nov');
        expect(formatDates('2026-10-24', '2026-10-24', today, calendar)).toBe('24 Okt');
        expect(formatDay('2026-10-24', today, calendar)).toBe('24 Okt');
    });

    it('adds the year when it is not the current one', () => {
        expect(formatDates('2027-01-02', '2027-01-04', today, calendar)).toBe('2–4 Jan 2027');
        expect(formatDates('2026-12-30', '2027-01-02', today, calendar)).toBe('30 Des 2026 – 2 Jan 2027');
        expect(formatDates('2027-01-30', '2027-02-01', today, calendar)).toBe('30 Jan – 1 Feb 2027');
        expect(formatDay('2027-03-01', today, calendar)).toBe('1 Mar 2027');
    });
});

describe('F-002 AC-19 seats line (Q4)', () => {
    it('says "Tinggal n dari m" at or below the threshold', () => {
        expect(seatsLine(5, 16, 5)).toEqual({ key: 'trip.seats_low', n: 5, m: 16 });
        expect(seatsLine(1, 16, 5)).toEqual({ key: 'trip.seats_low', n: 1, m: 16 });
        expect(seatsLine(6, 16, 5)).toEqual({ key: 'trip.seats', n: 6 });
    });
});

describe('F-002 AC-19a closing date without repetition', () => {
    it('says a shared preset once and shows only the urgent row (the spec example)', () => {
        const departures = [
            bookable('a', '2026-10-21 23:59'),
            bookable('b', '2026-10-28 23:59'),
            bookable('c', '2026-11-11 23:59'),
        ];

        expect(closingInfo(departures, '2026-10-19 10:00', 7)).toEqual({
            summary: 'h3',
            rows: { a: { kind: 'date', date: '2026-10-21', time: '23.59' } },
        });
    });

    it('shows a date on every row when presets differ or one is custom', () => {
        const mixed = closingInfo([bookable('a', '2026-11-21 23:59', 'h3'), bookable('b', '2026-11-27 23:59', 'h1')], '2026-10-19 10:00', 7);
        expect(mixed.summary).toBeNull();
        expect(Object.keys(mixed.rows)).toEqual(['a', 'b']);

        const custom = closingInfo([bookable('a', '2026-11-21 18:00', 'custom')], '2026-10-19 10:00', 7);
        expect(custom.summary).toBeNull();
        expect(custom.rows.a).toEqual({ kind: 'date', date: '2026-11-21', time: '18.00' });
    });

    it('words urgent deadlines today and tomorrow with the time', () => {
        const info = closingInfo(
            [bookable('a', '2026-10-19 23:59', 'h1'), bookable('b', '2026-10-20 23:59', 'h1'), bookable('c', '2026-11-30 23:59', 'h1')],
            '2026-10-19 10:00',
            7,
        );

        expect(info.summary).toBe('h1');
        expect(info.rows).toEqual({
            a: { kind: 'today', date: '2026-10-19', time: '23.59' },
            b: { kind: 'tomorrow', date: '2026-10-20', time: '23.59' },
        });
    });

    it('supports the same-day preset and ignores rows that cannot be booked', () => {
        const info = closingInfo(
            [bookable('a', '2026-11-24 05:00', 'same_day'), { public_id: 'b', state: 'full', deadline: null, deadline_preset: null }],
            '2026-10-19 10:00',
            7,
        );

        expect(info).toEqual({ summary: 'same_day', rows: {} });
    });

    it('has no summary without bookable departures', () => {
        expect(closingInfo([], '2026-10-19 10:00', 7)).toEqual({ summary: null, rows: {} });
    });
});

describe('F-002 AC-21/AC-22 selection and booking link', () => {
    it('selects the earliest bookable departure', () => {
        expect(initialSelection([{ public_id: 'x', state: 'full' }, { public_id: 'y', state: 'bookable' }])).toBe('y');
        expect(initialSelection([{ public_id: 'x', state: 'closed' }])).toBeNull();
    });

    it('links to the F-005 form with the departure public id', () => {
        expect(bookUrl('/kencana/karimunjawa/pesan', '01jabc')).toBe('/kencana/karimunjawa/pesan?keberangkatan=01jabc');
    });
});

describe('F-002 AC-12 share', () => {
    const data = { title: 'Kencana Trip', text: 'Lihat', url: 'https://rakyt.id/kencana' };

    it('uses the native share sheet when there is one', async () => {
        const shared = [];
        const result = await shareOrCopy(data, { share: async (d) => shared.push(d), copy: async () => {} });

        expect(result).toBe('shared');
        expect(shared).toEqual([data]);
    });

    it('shows nothing when the user cancels', async () => {
        const copied = [];
        const cancel = Object.assign(new Error('cancel'), { name: 'AbortError' });
        const result = await shareOrCopy(data, { share: async () => { throw cancel; }, copy: async (url) => copied.push(url) });

        expect(result).toBe('cancelled');
        expect(copied).toEqual([]);
    });

    it('copies the link without Web Share or when share fails', async () => {
        const copied = [];
        expect(await shareOrCopy(data, { share: undefined, copy: async (url) => copied.push(url) })).toBe('copied');
        expect(await shareOrCopy(data, { share: async () => { throw new Error('NotAllowedError'); }, copy: async (url) => copied.push(url) })).toBe('copied');
        expect(copied).toEqual([data.url, data.url]);
    });

    it('reports a failed copy', async () => {
        expect(await shareOrCopy(data, { share: undefined, copy: async () => { throw new Error('no'); } })).toBe('failed');
    });
});
