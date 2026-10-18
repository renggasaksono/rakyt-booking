import { describe, expect, it } from 'vitest';
import { mergeById } from '../../resources/js/lib/pages.js';

describe('upcoming departures paging', () => {
    it('appends the next page', () => {
        expect(mergeById([{ id: 'a' }, { id: 'b' }], [{ id: 'c' }]).map((d) => d.id)).toEqual(['a', 'b', 'c']);
    });

    it('updates rows already shown after a reload, keeping their place', () => {
        const merged = mergeById([{ id: 'a', capacity: 16 }, { id: 'b', capacity: 16 }], [{ id: 'a', capacity: 18 }]);

        expect(merged).toEqual([{ id: 'a', capacity: 18 }, { id: 'b', capacity: 16 }]);
    });
});
