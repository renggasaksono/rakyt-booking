/**
 * Bank / e-wallet blocks shared by onboarding step 4 and Pengaturan → Rekening
 * (F-001 C10–C11, F5). Each block gets a local key for v-for; it is not sent.
 */

let sequence = 0;

export function blankPaymentBlock() {
    return { key: ++sequence, id: null, provider: '', provider_name: '', account_number: '', account_holder: '' };
}

/** Saved accounts as form blocks, or one empty block when there are none. */
export function paymentBlocks(methods) {
    return methods.length
        ? methods.map((method) => ({ key: ++sequence, ...method, provider_name: method.provider_name ?? '' }))
        : [blankPaymentBlock()];
}

/** The blocks as the server expects them (no local keys). */
export function paymentPayload(blocks) {
    // eslint-disable-next-line no-unused-vars
    return blocks.map(({ key, ...method }) => method);
}

/** C10: digits only while typing; spaces stay for readability and are ignored on save. */
export function accountNumberInput(value) {
    return value.replace(/[^\d\s]/g, '');
}
