import { describe, expect, it, vi } from 'vitest';
import { debounce, followName, isValidSlug, normalizeSlugInput, slugFromName } from '../../resources/js/lib/slug.js';

describe('slugFromName (F-001 B4)', () => {
    it('lower-cases, strips accents, turns spaces into hyphens and drops the rest', () => {
        expect(slugFromName('Kencana Trip')).toBe('kencana-trip');
        expect(slugFromName('  Café   Bahari! ')).toBe('cafe-bahari');
        expect(slugFromName('Trip & Tour #1')).toBe('trip-tour-1');
        expect(slugFromName('---')).toBe('');
    });

    it('keeps at most 30 characters without a trailing hyphen', () => {
        const slug = slugFromName('Kencana Trip Jepara Karimunjawa Indonesia');
        expect(slug.length).toBeLessThanOrEqual(30);
        expect(slug.endsWith('-')).toBe(false);
    });
});

describe('followName', () => {
    it('follows the name until the seller edits the slug', () => {
        expect(followName({ slug: '', touched: false }, 'Kencana Trip').slug).toBe('kencana-trip');
        expect(followName({ slug: 'kencana', touched: true }, 'Kencana Trip').slug).toBe('kencana');
    });
});

describe('isValidSlug (F-001 B5)', () => {
    it('accepts 3–30 lower-case letters, digits and single inner hyphens', () => {
        expect(isValidSlug('kencana')).toBe(true);
        expect(isValidSlug('kencana-2')).toBe(true);
        expect(isValidSlug('abc')).toBe(true);
        expect(isValidSlug('a'.repeat(30))).toBe(true);
    });

    it('rejects the rest', () => {
        for (const slug of ['ab', 'a'.repeat(31), '-kencana', 'kencana-', 'ken--cana', 'Kencana', 'ken_cana', 'ken cana']) {
            expect(isValidSlug(slug)).toBe(false);
        }
    });

    it('lower-cases input as typed', () => {
        expect(normalizeSlugInput('KenCana Trip')).toBe('kencana-trip');
    });
});

describe('debounce', () => {
    it('runs once after typing stops', () => {
        vi.useFakeTimers();
        const fn = vi.fn();
        const run = debounce(fn, 400);
        run('a');
        run('ab');
        vi.advanceTimersByTime(399);
        expect(fn).not.toHaveBeenCalled();
        vi.advanceTimersByTime(1);
        expect(fn).toHaveBeenCalledWith('ab');
        vi.useRealTimers();
    });
});
