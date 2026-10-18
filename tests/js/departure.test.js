import { describe, expect, it } from 'vitest';
import { clampCapacity, hasSameDate, priceNote, STATUS_STYLES } from '../../resources/js/lib/departure.js';
import { t } from '../../resources/js/lib/text.js';

const copy = {
    quick_edit: {
        price_note: 'Harga baru hanya berlaku untuk pesanan berikutnya.',
        price_note_bookings: ':n pesanan yang ada tidak berubah.',
    },
};

describe('AC-18 same date', () => {
    it('warns when another departure starts on the same date', () => {
        const departures = [{ id: 'a', start_date: '2024-10-24' }];

        expect(hasSameDate(departures, '2024-10-24')).toBe(true);
        expect(hasSameDate(departures, '2024-10-24', 'a')).toBe(false);
        expect(hasSameDate(departures, '2024-10-25')).toBe(false);
    });
});

describe('AC-27 status display', () => {
    it('has a glyph for every status and never uses brown or gold', () => {
        for (const key of ['open', 'closed', 'full', 'past']) {
            expect(STATUS_STYLES[key].glyph).not.toBe('');
            expect(STATUS_STYLES[key].className).not.toMatch(/sogan|prada|6e4527|e5a823/i);
        }
    });
});

describe('AC-32 price note', () => {
    it('mentions existing bookings only when there are some', () => {
        expect(priceNote(copy, 14, t)).toBe('Harga baru hanya berlaku untuk pesanan berikutnya. 14 pesanan yang ada tidak berubah.');
        expect(priceNote(copy, 0, t)).toBe('Harga baru hanya berlaku untuk pesanan berikutnya.');
    });
});

describe('AC-33 seats floor', () => {
    it('never goes below the seats taken or outside 1–999', () => {
        expect(clampCapacity(13, 14)).toBe(14);
        expect(clampCapacity(18, 14)).toBe(18);
        expect(clampCapacity(0, 0)).toBe(1);
        expect(clampCapacity(1200, 0)).toBe(999);
    });
});

describe('copy lookup', () => {
    it('replaces placeholders, longest first', () => {
        expect(t({ a: { b: ':n terisi — tidak bisa di bawah :n' } }, 'a.b', { n: 14 })).toBe('14 terisi — tidak bisa di bawah 14');
        expect(t({}, 'missing.key')).toBe('missing.key');
    });
});
