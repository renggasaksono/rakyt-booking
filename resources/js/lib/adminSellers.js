/**
 * Admin seller directory (F-012): filters in the URL, mirroring
 * App\Admin\SellerDirectoryFilters, and the organization lookup the audit
 * view's organization picker uses.
 */
export const SELLER_FILTER_KEYS = ['cari', 'status', 'trip'];

/** Shortest term the lookup searches for (SellerDirectoryFilters::SEARCH_MIN). */
export const LOOKUP_MIN = 2;

export function sellerFilterQuery(filters) {
    return Object.fromEntries(
        SELLER_FILTER_KEYS
            .map((key) => [key, typeof filters[key] === 'string' ? filters[key].trim() : filters[key]])
            .filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );
}

/** The lookup URL for a typed term, or null while it is too short. */
export function lookupUrl(base, term) {
    const trimmed = (term ?? '').trim().replace(/\s+/g, ' ');

    if (trimmed.length < LOOKUP_MIN) {
        return null;
    }

    return `${base}?${new URLSearchParams({ cari: trimmed }).toString()}`;
}

/** "Kencana Trip (kencana)" */
export function organizationLabel(organization) {
    return `${organization.name} (${organization.slug})`;
}

/** Account status pill: tint + deep text + glyph + word, never colour alone (ADR-0008). */
export const ACCOUNT_STATUS_STYLES = {
    active: { glyph: '✓', className: 'bg-status-paid-tint text-status-paid' },
    suspended: { glyph: '⏸', className: 'bg-status-rejected-tint text-status-rejected' },
};
