import { describe, expect, it } from 'vitest';
import { activeBookingFilterCount, bookingFilterQuery } from '../../resources/js/lib/adminBookings.js';

describe('bookingFilterQuery', () => {
    it('keeps only filled filters, trimmed, in URL order', () => {
        expect(bookingFilterQuery({ sampai: '2024-10-31', cari: ' RKB-BRM-1024-0142 ', status: null, organisasi: '3', dari: '' }))
            .toEqual({ cari: 'RKB-BRM-1024-0142', organisasi: '3', sampai: '2024-10-31' });
        expect(Object.keys(bookingFilterQuery({ sampai: '2024-10-31', status: 'paid', cari: 'rina' }))).toEqual(['cari', 'status', 'sampai']);
    });

    it('ignores unknown keys and the page', () => {
        expect(bookingFilterQuery({ halaman: '2', aktor: 'user' })).toEqual({});
    });

    it('drops a reversed departure range as a whole', () => {
        expect(bookingFilterQuery({ dari: '2024-11-01', sampai: '2024-10-01', status: 'paid' })).toEqual({ status: 'paid' });
        expect(bookingFilterQuery({ dari: '2024-10-01', sampai: '2024-10-01' })).toEqual({ dari: '2024-10-01', sampai: '2024-10-01' });
    });
});

describe('activeBookingFilterCount', () => {
    it('counts applied filters', () => {
        expect(activeBookingFilterCount({ cari: 'rina', status: 'paid', organisasi: null })).toBe(2);
        expect(activeBookingFilterCount({})).toBe(0);
    });
});
