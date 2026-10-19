import { describe, expect, it } from 'vitest';
import { availablePresets, computeDeadline, isDeadlineValid, presetOptions, showsRuleHint, visiblePresetOptions } from '../../resources/js/lib/deadline.js';

const ALL = ['same_day', 'h1', 'h3', 'h7', 'custom'];

describe('AC-21 deadline presets show real dates', () => {
    it('matches mockup 3a for a departure on 24 Okt with kumpul 06.00', () => {
        expect(computeDeadline('same_day', '2024-10-24', '06:00')).toBe('2024-10-24 05:00');
        expect(computeDeadline('h1', '2024-10-24', '06:00')).toBe('2024-10-23 23:59');
        expect(computeDeadline('h3', '2024-10-24', '06:00')).toBe('2024-10-21 23:59');
        expect(computeDeadline('h7', '2024-10-24', '06:00')).toBe('2024-10-17 23:59');
        expect(computeDeadline('custom', '2024-10-24', '06:00', '2024-10-22T18:00')).toBe('2024-10-22 18:00');
    });

    it('moves "same day" to the previous evening for a meeting just after midnight', () => {
        expect(computeDeadline('same_day', '2024-10-24', '00:30')).toBe('2024-10-23 23:30');
    });
});

describe('AC-22 no meeting time', () => {
    it('does not offer "same day"', () => {
        expect(availablePresets(ALL, null)).toEqual(['h1', 'h3', 'h7', 'custom']);
        expect(computeDeadline('same_day', '2024-10-24', null)).toBeNull();
    });

    it('allows a custom deadline up to the start date 23.59', () => {
        expect(isDeadlineValid('2024-10-24 23:59', '2024-10-24', null, '2024-10-10 10:00')).toBe(true);
        expect(isDeadlineValid('2024-10-25 00:00', '2024-10-24', null, '2024-10-10 10:00')).toBe(false);
    });
});

describe('AC-23 past or late deadlines cannot be chosen', () => {
    it('disables presets already in the past', () => {
        const options = presetOptions(ALL, '2024-10-12', '06:00', '2024-10-10 10:00');

        expect(options.find((o) => o.value === 'h3').disabled).toBe(true);
        expect(options.find((o) => o.value === 'h1').disabled).toBe(false);
        expect(options.find((o) => o.value === 'custom').disabled).toBe(false);
    });

    it('hides past presets instead of striking them through, and always keeps "Pilih tanggal & jam…"', () => {
        expect(visiblePresetOptions(ALL, '2024-10-12', '06:00', '2024-10-10 10:00').map((o) => o.value))
            .toEqual(['same_day', 'h1', 'custom']);
        expect(visiblePresetOptions(ALL, '2024-10-10', null, '2024-10-10 10:00').map((o) => o.value))
            .toEqual(['custom']);
        expect(visiblePresetOptions(ALL, '2024-10-24', null, '2024-10-10 10:00').map((o) => o.value))
            .toEqual(['h1', 'h3', 'h7', 'custom']);
    });

    it('rejects a custom deadline after the meeting time', () => {
        expect(isDeadlineValid('2024-10-24 06:01', '2024-10-24', '06:00', '2024-10-10 10:00')).toBe(false);
        expect(isDeadlineValid('2024-10-24 06:00', '2024-10-24', '06:00', '2024-10-10 10:00')).toBe(true);
    });
});

describe('the meeting-time rule is shown only where it applies', () => {
    it('needs a meeting time or a custom deadline', () => {
        expect(showsRuleHint('h3', null)).toBe(false);
        expect(showsRuleHint('h3', '')).toBe(false);
        expect(showsRuleHint('h3', '06:00')).toBe(true);
        expect(showsRuleHint('custom', null)).toBe(true);
    });
});
