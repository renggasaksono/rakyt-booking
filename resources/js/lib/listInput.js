/**
 * "Termasuk" / "Tidak termasuk" items (F-003 AC-6): trimmed, at most 40
 * characters each and 20 items, no duplicates.
 */

export function normalizeItem(value) {
    return String(value ?? '').replace(/\s+/g, ' ').trim();
}

/**
 * Typing is stopped at the limit (the field shows "n/40").
 */
export function limitLength(value, maxLength) {
    return Array.from(String(value ?? '')).slice(0, maxLength).join('');
}

export function addItem(list, value, { maxItems = 20, maxLength = 40 } = {}) {
    const item = limitLength(normalizeItem(value), maxLength);

    if (item === '') {
        return { list, error: null };
    }

    if (list.includes(item)) {
        return { list, error: null };
    }

    if (list.length >= maxItems) {
        return { list, error: 'full' };
    }

    return { list: [...list, item], error: null };
}

export function toggleItem(list, value, options) {
    return list.includes(value) ? { list: list.filter((item) => item !== value), error: null } : addItem(list, value, options);
}

export function removeItem(list, value) {
    return list.filter((item) => item !== value);
}
