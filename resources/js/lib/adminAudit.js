/**
 * Admin audit view (F-015): filters in the URL. Mirrors
 * App\Admin\AuditEventFilters — an actor id only counts with its actor type.
 */
export const AUDIT_FILTER_KEYS = ['organisasi', 'aktor', 'aktor_id', 'aksi'];

export const PLATFORM = 'platform';

export function auditFilterQuery(filters) {
    const query = Object.fromEntries(
        AUDIT_FILTER_KEYS
            .map((key) => [key, typeof filters[key] === 'string' ? filters[key].trim() : filters[key]])
            .filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );

    if (!query.aktor) {
        delete query.aktor_id;
    }

    return query;
}

/** "Tampilkan hanya …" on an event: the organization it belongs to (or the platform). */
export function organizationFilter(filters, event) {
    return { ...filters, organisasi: event.organization ? String(event.organization.id) : PLATFORM };
}

/** "Tampilkan hanya …" on an event: the exact actor (type and id). */
export function actorFilter(filters, event) {
    return { ...filters, aktor: event.actor.type, aktor_id: event.actor.id ?? null };
}

export function activeAuditFilterCount(filters) {
    return Object.keys(auditFilterQuery(filters)).filter((key) => key !== 'aktor_id').length;
}
