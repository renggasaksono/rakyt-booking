/*
 * Customer payment screen helpers (F-006). Pure functions tested by Vitest
 * (tests/js/payment.test.js); the Vue page wires them to the DOM.
 */

const STEP_KEYS = ['transfer', 'review', 'done'];

/**
 * The 3-step progress (AC-4) for a current step (1, 2, 3 or null). Each step is
 * `done` before the current one, `active` on it, `idle` after; all `idle` when
 * there is no progress (rejected / cancelled).
 *
 * @param {number|null} current
 * @param {object} copy  the `payment` copy block (steps labels)
 * @returns {Array<{key: string, label: string, state: 'done'|'active'|'idle'}>}
 */
export function progressSteps(current, copy = {}) {
    const labels = copy?.steps ?? {};

    return STEP_KEYS.map((key, index) => {
        const step = index + 1;
        let state = 'idle';

        if (current != null) {
            state = step < current ? 'done' : step === current ? 'active' : 'idle';
        }

        return { key, label: labels[key] ?? key, state };
    });
}

/**
 * Digits only — what the copy buttons put on the clipboard (AC-3): an account
 * number without spaces, or an amount without "Rp" and separators.
 *
 * @param {string|number} value
 * @returns {string}
 */
export function digitsOnly(value) {
    return String(value ?? '').replace(/\D+/g, '');
}

/**
 * Copy text to the clipboard, resolving to whether it worked (AC-3). Falls back
 * to a hidden textarea where the async clipboard API is unavailable.
 *
 * @param {string} text
 * @returns {Promise<boolean>}
 */
export async function copyText(text) {
    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(text);

            return true;
        }
    } catch {
        // fall through to the legacy path
    }

    try {
        const area = document.createElement('textarea');
        area.value = text;
        area.setAttribute('readonly', '');
        area.style.position = 'absolute';
        area.style.left = '-9999px';
        document.body.appendChild(area);
        area.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(area);

        return ok;
    } catch {
        return false;
    }
}
