import { describe, expect, it } from 'vitest';
import { ACCOUNT_STATUS_STYLES } from '../../resources/js/lib/adminSellers.js';
import { formatCount, statStyle } from '../../resources/js/lib/adminOverview.js';
import { BOOKING_STATUS_STYLES } from '../../resources/js/lib/booking.js';

describe('formatCount', () => {
    it('groups thousands the Indonesian way', () => {
        expect(formatCount(0)).toBe('0');
        expect(formatCount(42)).toBe('42');
        expect(formatCount(1234)).toBe('1.234');
        expect(formatCount(1250000)).toBe('1.250.000');
    });

    it('shows zero for a missing count', () => {
        expect(formatCount(undefined)).toBe('0');
        expect(formatCount(Number.NaN)).toBe('0');
    });
});

describe('statStyle', () => {
    it('reuses the booking status glyphs and tints', () => {
        expect(statStyle('awaiting_payment')).toBe(BOOKING_STATUS_STYLES.awaiting_payment);
        expect(statStyle('awaiting_payment').glyph).toBe('◷');
        expect(statStyle('needs_review')).toBe(BOOKING_STATUS_STYLES.needs_review);
        expect(statStyle('needs_review').glyph).toBe('◐');
    });

    it('reuses the directory account status pills', () => {
        expect(statStyle('active')).toBe(ACCOUNT_STATUS_STYLES.active);
        expect(statStyle('suspended')).toBe(ACCOUNT_STATUS_STYLES.suspended);
    });

    it('has no style for a count that is not one status', () => {
        expect(statStyle('bookings')).toBeNull();
        expect(statStyle('sellers')).toBeNull();
        expect(statStyle('published_trips')).toBeNull();
    });
});
