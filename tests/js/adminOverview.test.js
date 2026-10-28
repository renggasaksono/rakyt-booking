import { describe, expect, it } from 'vitest';
import { formatCount } from '../../resources/js/lib/adminOverview.js';

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
