/**
 * Pure state for the hero product demo (F-017 AC-16, AC-17): a 3-step
 * walkthrough "Lihat trip → Pesan → Bayar" that autoplays, pauses on
 * interaction, and shows the 2-hour seat-hold countdown on the payment step.
 * The Vue component owns the timers and DOM; this module owns the logic so it
 * can be unit-tested without a browser.
 */

export const STEP_COUNT = 3;

// Seats are held for 2 hours (ADR-0006 / F-005); the demo counts down from it.
export const HOLD_SECONDS = 2 * 60 * 60;

/** Advance with wrap-around. */
export function nextStep(step, count = STEP_COUNT) {
    return (step + 1) % count;
}

export function clampStep(step, count = STEP_COUNT) {
    if (!Number.isFinite(step)) {
        return 0;
    }

    return Math.min(Math.max(Math.trunc(step), 0), count - 1);
}

/** "H.MM.SS" for the hold countdown; never negative. */
export function formatCountdown(remainingSeconds) {
    const total = Math.max(0, Math.floor(remainingSeconds));
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;
    const pad = (n) => String(n).padStart(2, '0');

    return `${hours}.${pad(minutes)}.${pad(seconds)}`;
}

/** Seconds left given how long the payment step has been visible. */
export function remainingHold(elapsedSeconds, holdSeconds = HOLD_SECONDS) {
    return Math.max(0, holdSeconds - Math.max(0, Math.floor(elapsedSeconds)));
}

/**
 * A tiny autoplay controller. `onAdvance` is called with the next step; the
 * caller wires real timers. It never advances once paused (manual interaction)
 * or when motion is reduced.
 */
export function createAutoplay({ reducedMotion = false, stepMs = 3500 } = {}) {
    let paused = reducedMotion;

    return {
        get paused() {
            return paused;
        },

        /** Should autoplay run at all? (false when the visitor prefers less motion). */
        get active() {
            return !reducedMotion && !paused;
        },

        get intervalMs() {
            return stepMs;
        },

        /** Manual interaction stops autoplay for good (AC-17). */
        pause() {
            paused = true;
        },

        resume() {
            if (!reducedMotion) {
                paused = false;
            }
        },

        /** The step to show next, or the same step when autoplay is off. */
        tick(step, count = STEP_COUNT) {
            return this.active ? nextStep(step, count) : clampStep(step, count);
        },
    };
}
