/**
 * Pure accordion state for the landing FAQ (F-017 AC-16). Single-open
 * behaviour: opening one panel closes the others; clicking the open one closes
 * it. The Vue component renders; this owns the toggle logic for unit tests.
 */

/** Toggle `index`: returns the new open index, or null when nothing is open. */
export function toggle(openIndex, index) {
    return openIndex === index ? null : index;
}

export function isOpen(openIndex, index) {
    return openIndex === index;
}
