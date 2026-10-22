import { describe, expect, it, vi } from 'vitest';
import { bookingLinkFeedback, openPrintDialog } from '../../resources/js/lib/bookingProof.js';

const url = 'https://rakyt.id/kencana/pesanan/abcdef0123456789abcdef0123456789';

describe('bookingLinkFeedback (F-016 AC-4/AC-5)', () => {
    it("maps a successful copy to 'ok'", async () => {
        const writeText = vi.fn().mockResolvedValue();

        await expect(bookingLinkFeedback(url, { clipboard: { writeText } })).resolves.toBe('ok');
        expect(writeText).toHaveBeenCalledWith(url);
    });

    it("maps a rejected clipboard write to 'failed'", async () => {
        const nav = { clipboard: { writeText: () => Promise.reject(new Error('denied')) } };

        await expect(bookingLinkFeedback(url, nav)).resolves.toBe('failed');
    });

    it("maps a missing clipboard API to 'failed' without throwing", async () => {
        await expect(bookingLinkFeedback(url, {})).resolves.toBe('failed');
    });
});

describe('openPrintDialog (F-016 AC-8)', () => {
    it('opens the browser print dialog', () => {
        const print = vi.fn();

        openPrintDialog({ print });

        expect(print).toHaveBeenCalledOnce();
    });
});
