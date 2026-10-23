import { describe, expect, it } from 'vitest';
import { pageTitle } from '../../resources/js/lib/title.js';

describe('page titles (ADR-0024 §2)', () => {
    it('adds the Rakyt Booking suffix to app pages', () => {
        expect(pageTitle('Trip saya')).toBe('Trip saya · Rakyt Booking');
        expect(pageTitle('')).toBe('Rakyt Booking');
    });

    it('keeps the public pages\' server titles as they are', () => {
        expect(pageTitle('Kencana Trip · Rakyt Booking')).toBe('Kencana Trip · Rakyt Booking');
        expect(pageTitle('Karimunjawa 3H2M Open Trip · Kencana Trip')).toBe('Karimunjawa 3H2M Open Trip · Kencana Trip');
    });
});
