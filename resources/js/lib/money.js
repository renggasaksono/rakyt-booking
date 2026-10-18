/**
 * Rupiah input helpers (F-003 AC-8): digits only, thousands with dots,
 * stored as integer rupiah.
 */

export function digitsOnly(value) {
    return String(value ?? '').replace(/\D+/g, '');
}

export function parseRupiah(value) {
    const digits = digitsOnly(value).replace(/^0+(?=\d)/, '');

    if (digits === '') {
        return null;
    }

    // Beyond this the number is not a price anyway; keep it safe as an integer.
    return Number(digits.slice(0, 12));
}

export function formatRupiah(value) {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return String(Math.trunc(Number(value))).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

export function isValidPrice(value, { min = 1000, max = 100000000 } = {}) {
    return Number.isInteger(value) && value >= min && value <= max;
}
