import { describe, expect, it } from 'vitest';
import { pageTitle } from '../../resources/js/lib/title.js';

describe('page titles (ADR-0024 §2)', () => {
    it('adds the Rakyt suffix to app pages', () => {
        expect(pageTitle('Trip saya')).toBe('Trip saya · Rakyt');
        expect(pageTitle('')).toBe('Rakyt');
    });

    it('keeps the public pages\' server titles as they are', () => {
        expect(pageTitle('Kencana Trip · Rakyt')).toBe('Kencana Trip · Rakyt');
        expect(pageTitle('Karimunjawa 3H2M Open Trip · Kencana Trip')).toBe('Karimunjawa 3H2M Open Trip · Kencana Trip');
    });
});
