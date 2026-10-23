import { describe, expect, it } from 'vitest';
import {
    HOLD_SECONDS,
    STEP_COUNT,
    clampStep,
    createAutoplay,
    formatCountdown,
    nextStep,
    remainingHold,
} from '../../resources/js/lib/landingDemo.js';

describe('AC-16 the 3-step demo cycles', () => {
    it('advances with wrap-around', () => {
        expect(STEP_COUNT).toBe(3);
        expect(nextStep(0)).toBe(1);
        expect(nextStep(1)).toBe(2);
        expect(nextStep(2)).toBe(0);
    });

    it('clamps out-of-range steps', () => {
        expect(clampStep(-1)).toBe(0);
        expect(clampStep(5)).toBe(2);
        expect(clampStep(1.9)).toBe(1);
        expect(clampStep(NaN)).toBe(0);
    });
});

describe('AC-16 the 2-hour hold countdown', () => {
    it('formats seconds as H.MM.SS', () => {
        expect(formatCountdown(HOLD_SECONDS)).toBe('2.00.00');
        expect(formatCountdown(3661)).toBe('1.01.01');
        expect(formatCountdown(59)).toBe('0.00.59');
        expect(formatCountdown(0)).toBe('0.00.00');
        expect(formatCountdown(-5)).toBe('0.00.00');
    });

    it('counts down from two hours and never goes negative', () => {
        expect(remainingHold(0)).toBe(HOLD_SECONDS);
        expect(remainingHold(60)).toBe(HOLD_SECONDS - 60);
        expect(remainingHold(HOLD_SECONDS + 100)).toBe(0);
    });
});

describe('AC-17 autoplay pauses on interaction and respects reduced motion', () => {
    it('advances while active and stops once paused', () => {
        const auto = createAutoplay({ stepMs: 1000 });

        expect(auto.active).toBe(true);
        expect(auto.intervalMs).toBe(1000);
        expect(auto.tick(0)).toBe(1);

        auto.pause();
        expect(auto.paused).toBe(true);
        expect(auto.active).toBe(false);
        expect(auto.tick(1)).toBe(1); // holds the current step
    });

    it('does not autoplay when motion is reduced', () => {
        const auto = createAutoplay({ reducedMotion: true });

        expect(auto.active).toBe(false);
        expect(auto.tick(0)).toBe(0);

        auto.resume(); // stays off under reduced motion
        expect(auto.active).toBe(false);
    });

    it('can resume after a pause when motion is allowed', () => {
        const auto = createAutoplay();

        auto.pause();
        expect(auto.active).toBe(false);
        auto.resume();
        expect(auto.active).toBe(true);
    });
});
