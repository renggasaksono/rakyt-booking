import { describe, expect, it } from 'vitest';
import { activeAuditFilterCount, actorFilter, auditFilterQuery, organizationFilter } from '../../resources/js/lib/adminAudit.js';

describe('auditFilterQuery', () => {
    it('keeps only filled filters, trimmed', () => {
        expect(auditFilterQuery({ organisasi: '3', aktor: null, aktor_id: '', aksi: ' booking.created ' }))
            .toEqual({ organisasi: '3', aksi: 'booking.created' });
    });

    it('drops an actor id without an actor type', () => {
        expect(auditFilterQuery({ aktor_id: '7' })).toEqual({});
        expect(auditFilterQuery({ aktor: 'platform_admin', aktor_id: '7' })).toEqual({ aktor: 'platform_admin', aktor_id: '7' });
    });

    it('ignores unknown keys', () => {
        expect(auditFilterQuery({ halaman: '2', cari: 'x' })).toEqual({});
    });
});

describe('click-to-filter', () => {
    const filters = { organisasi: null, aktor: null, aktor_id: null, aksi: 'booking.created' };

    it('filters by the event organization, or the platform when it has none', () => {
        expect(organizationFilter(filters, { organization: { id: 12 } })).toEqual({ ...filters, organisasi: '12' });
        expect(organizationFilter(filters, { organization: null })).toEqual({ ...filters, organisasi: 'platform' });
    });

    it('filters by the exact actor', () => {
        expect(actorFilter(filters, { actor: { type: 'system', id: 'admin:grant' } }))
            .toEqual({ ...filters, aktor: 'system', aktor_id: 'admin:grant' });
    });
});

describe('activeAuditFilterCount', () => {
    it('counts the actor once, with or without an id', () => {
        expect(activeAuditFilterCount({ organisasi: '1', aktor: 'user', aktor_id: '5', aksi: null })).toBe(2);
        expect(activeAuditFilterCount({})).toBe(0);
    });
});
