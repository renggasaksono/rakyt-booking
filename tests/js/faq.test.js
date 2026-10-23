import { describe, expect, it } from 'vitest';
import { isOpen, toggle } from '../../resources/js/lib/faq.js';

describe('AC-16 FAQ accordion is single-open', () => {
    it('opens a closed panel', () => {
        expect(toggle(null, 2)).toBe(2);
    });

    it('closes the panel that is already open', () => {
        expect(toggle(2, 2)).toBeNull();
    });

    it('switches to another panel, closing the previous one', () => {
        expect(toggle(0, 3)).toBe(3);
    });

    it('reports which panel is open', () => {
        expect(isOpen(1, 1)).toBe(true);
        expect(isOpen(1, 2)).toBe(false);
        expect(isOpen(null, 0)).toBe(false);
    });
});
