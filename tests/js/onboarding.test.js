import { describe, expect, it, vi } from 'vitest';
import { copyLink, defaultDeadlinePreset, formatDeadlineWords, formatShortDay, scheduleSummary, shareOrCopy } from '../../resources/js/lib/onboarding.js';

const calendar = {
    months_short: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'],
    days_short: ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'],
    days_long: ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
};
const presets = ['same_day', 'h1', 'h3', 'h7', 'custom'];

describe('defaultDeadlinePreset (F-001 C6)', () => {
    it('keeps the organization default while it is in the future', () => {
        expect(defaultDeadlinePreset('h3', presets, '2024-10-24', null, '2024-10-10 10:00')).toBe('h3');
    });

    it('moves to the nearest later preset when the default is past', () => {
        // Departure in 2 days: H-3 is past, H-1 is still ahead.
        expect(defaultDeadlinePreset('h3', presets, '2024-10-12', null, '2024-10-10 10:00')).toBe('h1');
    });

    it('falls back to a custom date when no preset is left', () => {
        expect(defaultDeadlinePreset('h3', presets, '2024-10-10', null, '2024-10-10 10:00')).toBe('custom');
    });

    it('uses "same day" when the trip has a meeting time', () => {
        expect(defaultDeadlinePreset('h1', presets, '2024-10-10', '15:00', '2024-10-10 10:00')).toBe('same_day');
    });
});

describe('schedule summary (F-001 C5, C7)', () => {
    it('reads like the mockup', () => {
        expect(formatDeadlineWords('2024-10-21 23:59', calendar)).toBe('Senin 21 Okt 23.59');
        expect(formatShortDay('2024-10-26', calendar)).toBe('Sab 26 Okt');
        expect(scheduleSummary({ seats: 16, price: 1250000, deadline: '2024-10-21 23:59' }, calendar))
            .toEqual({ seats: 16, price: 'Rp1.250.000', deadline: 'Senin 21 Okt 23.59' });
    });

    it('is empty while a value is missing', () => {
        expect(scheduleSummary({ seats: 16, price: null, deadline: '2024-10-21 23:59' }, calendar)).toBeNull();
    });
});

describe('share (F-001 D5)', () => {
    it('opens the share sheet when the browser has one', async () => {
        const share = vi.fn().mockResolvedValue(undefined);
        await expect(shareOrCopy({ url: 'https://rakyt.id/kencana', text: 'x' }, { share })).resolves.toBe('shared');
        expect(share).toHaveBeenCalledWith({ url: 'https://rakyt.id/kencana', text: 'x' });
    });

    it('copies the link without Web Share', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        await expect(shareOrCopy({ url: 'https://rakyt.id/kencana' }, { clipboard: { writeText } })).resolves.toBe('copied');
        expect(writeText).toHaveBeenCalledWith('https://rakyt.id/kencana');
    });

    it('reports a failed copy', async () => {
        await expect(copyLink('x', { clipboard: { writeText: () => Promise.reject(new Error('no')) } })).resolves.toBe('failed');
    });
});
