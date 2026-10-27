import { describe, expect, it } from 'vitest';
import { lookupUrl, organizationLabel, sellerFilterQuery } from '../../resources/js/lib/adminSellers.js';

describe('sellerFilterQuery', () => {
    it('keeps only filled filters, trimmed', () => {
        expect(sellerFilterQuery({ cari: ' kencana ', status: null })).toEqual({ cari: 'kencana' });
        expect(sellerFilterQuery({ cari: '', status: 'suspended' })).toEqual({ status: 'suspended' });
    });

    it('ignores unknown keys and the page', () => {
        expect(sellerFilterQuery({ halaman: '2', organisasi: '3' })).toEqual({});
    });
});

describe('lookupUrl', () => {
    it('waits for two characters', () => {
        expect(lookupUrl('/admin/penjual/cari', '')).toBeNull();
        expect(lookupUrl('/admin/penjual/cari', ' k ')).toBeNull();
        expect(lookupUrl('/admin/penjual/cari', null)).toBeNull();
    });

    it('encodes the trimmed term', () => {
        expect(lookupUrl('/admin/penjual/cari', '  Kencana  Trip ')).toBe('/admin/penjual/cari?cari=Kencana+Trip');
        expect(lookupUrl('/admin/penjual/cari', '+62 812')).toBe('/admin/penjual/cari?cari=%2B62+812');
    });
});

describe('organizationLabel', () => {
    it('names the organization with its slug', () => {
        expect(organizationLabel({ id: '3', name: 'Kencana Trip', slug: 'kencana' })).toBe('Kencana Trip (kencana)');
    });
});
