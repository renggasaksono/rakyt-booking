/**
 * Admin booking search (F-013): filters in the URL, mirroring
 * App\Admin\AdminBookingFilters. A reversed departure range is dropped, as
 * the server does.
 */
export const BOOKING_FILTER_KEYS = ['cari', 'status', 'organisasi', 'dari', 'sampai'];

export function bookingFilterQuery(filters) {
    const query = Object.fromEntries(
        BOOKING_FILTER_KEYS
            .map((key) => [key, typeof filters[key] === 'string' ? filters[key].trim() : filters[key]])
            .filter(([, value]) => value !== null && value !== undefined && value !== ''),
    );

    if (query.dari && query.sampai && query.dari > query.sampai) {
        delete query.dari;
        delete query.sampai;
    }

    return query;
}

export function activeBookingFilterCount(filters) {
    return Object.keys(bookingFilterQuery(filters)).length;
}
