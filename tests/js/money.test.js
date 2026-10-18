import { describe, expect, it } from 'vitest';
import { digitsOnly, formatRupiah, isValidPrice, parseRupiah } from '../../resources/js/lib/money.js';

describe('AC-8 money input', () => {
    it('keeps digits only', () => {
        expect(digitsOnly('Rp 1.250.000,-')).toBe('1250000');
        expect(digitsOnly('abc')).toBe('');
    });

    it('shows thousands with dots', () => {
        expect(formatRupiah(1250000)).toBe('1.250.000');
        expect(formatRupiah(1000)).toBe('1.000');
        expect(formatRupiah(999)).toBe('999');
        expect(formatRupiah(null)).toBe('');
    });

    it('parses to integer rupiah', () => {
        expect(parseRupiah('1.250.000')).toBe(1250000);
        expect(parseRupiah('0001000')).toBe(1000);
        expect(parseRupiah('')).toBeNull();
    });

    it('accepts Rp1.000 to Rp100.000.000 only', () => {
        expect(isValidPrice(999)).toBe(false);
        expect(isValidPrice(1000)).toBe(true);
        expect(isValidPrice(100000000)).toBe(true);
        expect(isValidPrice(100000001)).toBe(false);
        expect(isValidPrice(12.5)).toBe(false);
    });
});
