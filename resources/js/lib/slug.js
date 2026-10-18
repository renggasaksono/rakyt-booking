/**
 * Booking-link slug rules, mirroring App\Organizations\BookingSlug (F-001
 * B4, B5). The server re-validates on submit.
 */

export const SLUG_MIN = 3;
export const SLUG_MAX = 30;

/** B4: lower-cased, accents stripped, spaces to "-", anything else removed. */
export function slugFromName(name) {
    return String(name ?? '')
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '')
        .replace(/-+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, SLUG_MAX)
        .replace(/-+$/, '');
}

/** B5: uppercase is lower-cased as typed; spaces are not allowed, so they become "-". */
export function normalizeSlugInput(value) {
    return String(value ?? '').toLowerCase().replace(/\s/g, '-').slice(0, SLUG_MAX);
}

export function isValidSlug(slug) {
    return (
        typeof slug === 'string'
        && slug.length >= SLUG_MIN
        && slug.length <= SLUG_MAX
        && /^[a-z0-9](?:[a-z0-9]|-(?!-))*[a-z0-9]$/.test(slug)
    );
}

/**
 * B4: the suggestion follows the name until the seller edits the slug by hand.
 * Returns the next { slug, touched } state.
 */
export function followName(state, name) {
    return state.touched ? state : { ...state, slug: slugFromName(name) };
}

/** Debounce for the availability check (B6: ~400 ms after typing stops). */
export function debounce(fn, wait = 400) {
    let timer = null;

    const debounced = (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };

    debounced.cancel = () => clearTimeout(timer);

    return debounced;
}
