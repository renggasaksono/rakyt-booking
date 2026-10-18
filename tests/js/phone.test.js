import { describe, expect, it } from 'vitest';
import { formatNational, isValidMobile, nationalDigits } from '../../resources/js/lib/phone.js';

describe('WhatsApp number (F-001 B3)', () => {
    it('reads every common way of writing the number', () => {
        for (const input of ['0812 7788 1020', '812-7788-1020', '62812 7788 1020', '+62 812 7788 1020']) {
            expect(nationalDigits(input)).toBe('81277881020');
        }
    });

    it('accepts only Indonesian mobile numbers', () => {
        expect(isValidMobile('81277881020')).toBe(true);
        expect(isValidMobile('8127788')).toBe(false);
        expect(isValidMobile('2112345678')).toBe(false);
    });

    it('groups the digits for display', () => {
        expect(formatNational('81277881020')).toBe('812 7788 1020');
    });
});
