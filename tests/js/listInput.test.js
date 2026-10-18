import { describe, expect, it } from 'vitest';
import { addItem, limitLength, removeItem, toggleItem } from '../../resources/js/lib/listInput.js';

describe('AC-6 inclusions', () => {
    it('stops typing at 40 characters', () => {
        expect(limitLength('a'.repeat(45), 40)).toHaveLength(40);
    });

    it('adds trimmed items, ignores blanks and duplicates', () => {
        expect(addItem([], '  Kapal  ').list).toEqual(['Kapal']);
        expect(addItem(['Kapal'], 'Kapal').list).toEqual(['Kapal']);
        expect(addItem(['Kapal'], '   ').list).toEqual(['Kapal']);
    });

    it('allows at most 20 items', () => {
        const full = Array.from({ length: 20 }, (_, n) => `Item ${n}`);

        expect(addItem(full, 'Satu lagi')).toEqual({ list: full, error: 'full' });
    });

    it('toggles suggestion chips', () => {
        expect(toggleItem([], 'Guide').list).toEqual(['Guide']);
        expect(toggleItem(['Guide'], 'Guide').list).toEqual([]);
        expect(removeItem(['Kapal', 'Guide'], 'Kapal')).toEqual(['Guide']);
    });
});
