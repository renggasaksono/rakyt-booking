/**
 * "Nomor WhatsApp usaha" after the fixed "+62" prefix (F-001 B3). Mirrors
 * App\Security\PhoneNumber + OrganizationRules; the server normalises and
 * validates again.
 */

/** Digits after +62: "0812 7788 1020", "62812…", "+62 812…" → "81277881020". */
export function nationalDigits(value) {
    let digits = String(value ?? '').replace(/\D+/g, '');

    if (digits.startsWith('00')) {
        digits = digits.slice(2);
    }

    if (digits.startsWith('62')) {
        digits = digits.slice(2);
    }

    return digits.replace(/^0+/, '');
}

/** +62 8 followed by 8–11 digits. */
export function isValidMobile(national) {
    return /^8\d{8,11}$/.test(national);
}

/** "81277881020" → "812 7788 1020" */
export function formatNational(national) {
    const digits = String(national ?? '');

    return [digits.slice(0, 3), digits.slice(3, 7), digits.slice(7)].filter(Boolean).join(' ');
}
