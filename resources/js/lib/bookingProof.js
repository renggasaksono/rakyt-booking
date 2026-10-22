/*
 * Customer booking-confirmation actions (F-016): copy the private booking link
 * with success / failure feedback, and the browser-native print of the proof
 * snapshot. Pure-ish helpers so tests/js can cover the feedback mapping and the
 * print wiring without a DOM (AGENTS.md: JS UI logic lives in resources/js/lib
 * with Vitest tests). The Vue page wires these to the DOM and aria-live.
 */
import { copyLink } from './onboarding.js';

/**
 * AC-3–AC-5: copy the booking's own private URL and map the result to the state
 * the page announces via `aria-live` — 'ok' on success, 'failed' otherwise (the
 * clipboard API being unavailable, blocked, or rejecting). Never throws:
 * `copyLink` is already try/catch-wrapped.
 *
 * @param {string} url  the page's own URL (urls.self)
 * @param {Navigator} [nav]
 * @returns {Promise<'ok'|'failed'>}
 */
export async function bookingLinkFeedback(url, nav) {
    return (await copyLink(url, nav)) === 'copied' ? 'ok' : 'failed';
}

/**
 * AC-8: open the browser print dialog for "Simpan bukti pesanan" — the customer
 * chooses "Save as PDF" or a printer. No PDF library or server route.
 *
 * @param {{print: () => void}} [win]
 */
export function openPrintDialog(win = globalThis) {
    win.print();
}
