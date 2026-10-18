import { describe, expect, it, vi } from 'vitest';
import { createAutosave } from '../../resources/js/lib/autosave.js';

describe('AC-3 autosave', () => {
    it('saves the changed fields 2 s after the last change', async () => {
        vi.useFakeTimers();
        const save = vi.fn().mockResolvedValue({ savedAt: '10.42' });
        const states = [];
        const autosave = createAutosave({ save, onChange: (s) => states.push(s) });

        autosave.queue({ name: 'Karimun' });
        await vi.advanceTimersByTimeAsync(1500);
        autosave.queue({ name: 'Karimunjawa', base_price: 1250000 });
        await vi.advanceTimersByTimeAsync(1999);
        expect(save).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        expect(save).toHaveBeenCalledOnce();
        expect(save).toHaveBeenCalledWith({ name: 'Karimunjawa', base_price: 1250000 });
        expect(autosave.state).toBe('saved');
        expect(autosave.savedAt).toBe('10.42');
        expect(states.at(-1)).toEqual({ state: 'saved', savedAt: '10.42' });
        vi.useRealTimers();
    });

    it('saves right away when the seller leaves the field', async () => {
        const save = vi.fn().mockResolvedValue({ savedAt: '10.43' });
        const autosave = createAutosave({ save });

        autosave.queue({ meeting_point: 'Jepara' });
        await autosave.flush();

        expect(save).toHaveBeenCalledWith({ meeting_point: 'Jepara' });
    });

    it('keeps the values after a failed save and retries them', async () => {
        const save = vi.fn().mockRejectedValueOnce({ status: 0 }).mockResolvedValue({ savedAt: '10.44' });
        const autosave = createAutosave({ save });

        autosave.queue({ name: 'Karimunjawa' });
        await autosave.flush();
        expect(autosave.state).toBe('error');
        expect(autosave.hasUnsaved()).toBe(true);

        autosave.queue({ terms: 'DP 50%' });
        await autosave.retry();

        expect(save).toHaveBeenLastCalledWith({ name: 'Karimunjawa', terms: 'DP 50%' });
        expect(autosave.state).toBe('saved');
        expect(autosave.hasUnsaved()).toBe(false);
    });

    it('never runs two saves at once', async () => {
        let release;
        const save = vi.fn().mockImplementationOnce(() => new Promise((resolve) => (release = resolve))).mockResolvedValue({ savedAt: '10.45' });
        const autosave = createAutosave({ save });

        autosave.queue({ name: 'A' });
        const first = autosave.flush();
        autosave.queue({ name: 'B' });
        const second = autosave.flush();
        expect(save).toHaveBeenCalledTimes(1);

        release({ savedAt: '10.44' });
        await first;
        await second;

        expect(save).toHaveBeenCalledTimes(2);
        expect(save).toHaveBeenLastCalledWith({ name: 'B' });
    });
});
