import { describe, expect, it } from 'vitest';
import {
    BOOKING_STATUS_STYLES,
    clampParticipants,
    clockOffset,
    countdown,
    dateLine,
    formatPhone,
    holdExpired,
    meetingLine,
    releaseTime,
    remainingMs,
    sendBooking,
    totalAmount,
} from '../../resources/js/lib/booking.js';

const calendar = {
    months_short: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'],
    days_short: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
};

describe('F-005 AC-1 date line', () => {
    const today = '2024-10-10';

    it('shows weekday and day for a multi-day trip in one month', () => {
        expect(dateLine('2024-10-24', '2024-10-26', today, calendar)).toBe('Kam 24 – Sab 26 Okt');
    });

    it('shows one date for a one-day trip', () => {
        expect(dateLine('2024-10-24', '2024-10-24', today, calendar)).toBe('Kam 24 Okt');
    });

    it('names both months across a month end', () => {
        expect(dateLine('2024-10-31', '2024-11-02', today, calendar)).toBe('Kam 31 Okt – Sab 2 Nov');
    });

    it('adds the year when it is not the current one', () => {
        expect(dateLine('2025-01-02', '2025-01-04', today, calendar)).toBe('Kam 2 – Sab 4 Jan 2025');
        expect(dateLine('2024-12-30', '2025-01-01', today, calendar)).toBe('Sen 30 Des 2024 – Rab 1 Jan 2025');
    });

    it('appends the meeting point and time when there is one', () => {
        expect(meetingLine({ point: 'Pelabuhan Jepara', time: '06:00' })).toBe('Pelabuhan Jepara 06.00');
        expect(meetingLine({ point: 'Pelabuhan Jepara', time: null })).toBe('Pelabuhan Jepara');
        expect(meetingLine({ point: null, time: '06:00' })).toBeNull();
    });
});

describe('F-005 AC-5 participant stepper', () => {
    it('clamps typed values to 1…seats left', () => {
        expect(clampParticipants(0, 4)).toBe(1);
        expect(clampParticipants(3, 4)).toBe(3);
        expect(clampParticipants(9, 4)).toBe(4);
        expect(clampParticipants('12', 4)).toBe(4);
        expect(clampParticipants('-2', 4)).toBe(2);
        expect(clampParticipants('', 4)).toBeNull();
    });
});

describe('F-005 AC-6 live total', () => {
    it('multiplies participants by the unit price', () => {
        expect(totalAmount(2, 1250000)).toBe(2500000);
        expect(totalAmount(null, 1250000)).toBe(0);
    });
});

describe('F-005 AC-29 / AC-30 countdown', () => {
    it('formats hours, minutes and the last minute', () => {
        expect(countdown((60 + 52) * 60000 + 30000)).toEqual({ key: 'hours', h: 1, m: 52 });
        expect(countdown(52 * 60000)).toEqual({ key: 'minutes', m: 52 });
        expect(countdown(59000)).toEqual({ key: 'under_minute' });
        expect(countdown(0)).toBeNull();
    });

    it('runs on the server clock, whatever the phone clock says', () => {
        const serverNow = '2024-10-10T03:00:00Z';
        const expires = '2024-10-10T05:00:00Z';
        // The phone is 3 hours ahead: without the offset the hold would look expired.
        const phoneAtLoad = Date.parse(serverNow) + 3 * 3600000;
        const offset = clockOffset(serverNow, phoneAtLoad);

        expect(remainingMs(expires, phoneAtLoad, offset)).toBe(2 * 3600000);
        expect(remainingMs(expires, phoneAtLoad + 8 * 60000, offset)).toBe(112 * 60000);
        expect(remainingMs(expires, phoneAtLoad + 3 * 3600000, offset)).toBe(0);
    });

    it('treats an awaiting booking at zero as expired (AC-31)', () => {
        expect(holdExpired({ status: 'awaiting_payment', cancel_reason: null }, 0)).toBe(true);
        expect(holdExpired({ status: 'awaiting_payment', cancel_reason: null }, 1000)).toBe(false);
        expect(holdExpired({ status: 'cancelled', cancel_reason: 'hold_expired' }, 0)).toBe(true);
        expect(holdExpired({ status: 'paid', cancel_reason: null }, 0)).toBe(false);
    });
});

describe('F-005 AC-29 release time', () => {
    it('says the local time today', () => {
        // 09.40 UTC = 16.40 WIB.
        expect(releaseTime('2024-10-10T09:40:00Z', '2024-10-10T07:40:00Z', 'Asia/Jakarta')).toEqual({ key: 'hold_until', time: '16.40' });
    });

    it('says "besok" when the release is on the next local day', () => {
        // 18.10 UTC = 01.10 WIB the next day.
        expect(releaseTime('2024-10-10T18:10:00Z', '2024-10-10T16:10:00Z', 'Asia/Jakarta')).toEqual({ key: 'hold_until_tomorrow', time: '01.10' });
        // Same instant in WITA (UTC+8) is 02.10 the next day.
        expect(releaseTime('2024-10-10T18:10:00Z', '2024-10-10T16:10:00Z', 'Asia/Makassar').time).toBe('02.10');
    });
});

describe('F-005 phone and statuses', () => {
    it('formats the stored number', () => {
        expect(formatPhone('+6281234567890')).toBe('+62 812 3456 7890');
    });

    it('gives every status a glyph (AC-32, ADR-0008)', () => {
        for (const status of ['awaiting_payment', 'needs_review', 'paid', 'rejected', 'cancelled', 'completed']) {
            expect(BOOKING_STATUS_STYLES[status].glyph).not.toBe('');
        }
    });
});

describe('F-005 AC-9 sending the form', () => {
    function fakeForm() {
        const form = {
            processing: false,
            data: { name: 'Rina', phone: '0812 3456 7890', participants: 2, submission_key: 'key-1' },
            posts: [],
            transform(callback) {
                this.transformer = callback;

                return this;
            },
            post(url, options) {
                this.processing = true;
                this.posts.push({ url, body: this.transformer(this.data), options });
            },
        };

        return form;
    }

    const digits = (value) => String(value).replace(/\D+/g, '').replace(/^62/, '').replace(/^0+/, '');

    it('sends once; a second tap while sending sends nothing', () => {
        const form = fakeForm();
        const options = { bookable: true, phoneDigits: digits, onFail: () => {}, onError: () => {} };

        expect(sendBooking(form, '/kencana/trip/pesan', options)).toBe(true);
        expect(sendBooking(form, '/kencana/trip/pesan', options)).toBe(false);
        expect(form.posts).toHaveLength(1);
        expect(form.posts[0].body.phone).toBe('+6281234567890');
    });

    it('sends nothing for a date that can no longer be booked', () => {
        const form = fakeForm();

        expect(sendBooking(form, '/x', { bookable: false, phoneDigits: digits, onFail: () => {}, onError: () => {} })).toBe(false);
        expect(form.posts).toHaveLength(0);
    });

    it('keeps the values and the submission key after a network failure', () => {
        const form = fakeForm();
        let failed = false;
        const options = { bookable: true, phoneDigits: digits, onFail: () => (failed = true), onError: () => {} };

        sendBooking(form, '/x', options);
        // Inertia calls onNetworkError; returning false suppresses its error modal.
        expect(form.posts[0].options.onNetworkError()).toBe(false);
        form.processing = false;
        expect(failed).toBe(true);

        sendBooking(form, '/x', options);
        expect(form.posts[1].body).toEqual(form.posts[0].body);
        expect(form.posts[1].body.submission_key).toBe('key-1');
    });

    it('keeps the scroll position only when there are errors', () => {
        const form = fakeForm();

        sendBooking(form, '/x', { bookable: true, phoneDigits: digits, onFail: () => {}, onError: () => {} });
        const { preserveScroll } = form.posts[0].options;

        expect(preserveScroll({ props: { errors: { name: 'x' } } })).toBe(true);
        expect(preserveScroll({ props: { errors: {} } })).toBe(false);
    });
});
