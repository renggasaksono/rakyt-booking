/**
 * Admin overview (F-011): counts as Indonesian numbers ("1.234").
 */
const COUNT_FORMAT = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

export function formatCount(count) {
    return COUNT_FORMAT.format(Number.isFinite(count) ? count : 0);
}

/** Status-set tone of a count card (ADR-0008): tint + deep text, never colour alone. */
export const STAT_TONES = {
    awaiting_payment: 'bg-status-waiting-tint text-status-waiting',
    needs_review: 'bg-status-check-tint text-status-check',
    suspended: 'bg-status-rejected-tint text-status-rejected',
};
