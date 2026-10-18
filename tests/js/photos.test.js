import { describe, expect, it } from 'vitest';
import { changesCover } from '../../resources/js/lib/photos.js';

describe('cover change detection', () => {
    it('is true when another photo moves into first place', () => {
        expect(changesCover(['a', 'b', 'c'], ['b', 'a', 'c'])).toBe(true);
    });

    it('is true when the cover is removed and another photo takes its place', () => {
        expect(changesCover(['a', 'b'], ['b'])).toBe(true);
    });

    it('is false when only later photos move or are removed', () => {
        expect(changesCover(['a', 'b', 'c'], ['a', 'c', 'b'])).toBe(false);
        expect(changesCover(['a', 'b', 'c'], ['a', 'c'])).toBe(false);
    });

    it('is false when there was no cover or none is left', () => {
        expect(changesCover([], ['a'])).toBe(false);
        expect(changesCover(['a'], [])).toBe(false);
    });
});
