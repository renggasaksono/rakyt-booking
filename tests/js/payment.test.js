import { describe, expect, it, vi } from 'vitest';
import { copyText, digitsOnly, pausedNoticeKey, progressSteps } from '../../resources/js/lib/payment.js';

const copy = { steps: { transfer: 'Transfer', review: 'Dicek operator', done: 'Lunas' } };

describe('F-006 AC-4 three-step progress', () => {
    it('marks step 1 active while awaiting payment', () => {
        const steps = progressSteps(1, copy);
        expect(steps.map((s) => s.state)).toEqual(['active', 'idle', 'idle']);
        expect(steps.map((s) => s.label)).toEqual(['Transfer', 'Dicek operator', 'Lunas']);
    });

    it('marks step 1 done and step 2 active under review', () => {
        expect(progressSteps(2, copy).map((s) => s.state)).toEqual(['done', 'active', 'idle']);
    });

    it('marks every step done through step 3 when paid', () => {
        expect(progressSteps(3, copy).map((s) => s.state)).toEqual(['done', 'done', 'active']);
    });

    it('shows no active step for rejected / cancelled (null)', () => {
        expect(progressSteps(null, copy).map((s) => s.state)).toEqual(['idle', 'idle', 'idle']);
    });

    it('falls back to the key when a label is missing', () => {
        expect(progressSteps(1, {}).map((s) => s.label)).toEqual(['transfer', 'review', 'done']);
    });
});

describe('F-006 AC-3 copy values', () => {
    it('reduces an account number to digits only', () => {
        expect(digitsOnly('1234 5678 90')).toBe('1234567890');
        expect(digitsOnly('0812-3456-7890')).toBe('08123456789 0'.replace(' ', ''));
    });

    it('reduces an amount to its integer digits', () => {
        expect(digitsOnly(2500000)).toBe('2500000');
        expect(digitsOnly('Rp2.500.000')).toBe('2500000');
    });

    it('copies through the async clipboard API', async () => {
        const writeText = vi.fn().mockResolvedValue();
        vi.stubGlobal('navigator', { clipboard: { writeText } });

        await expect(copyText('2500000')).resolves.toBe(true);
        expect(writeText).toHaveBeenCalledWith('2500000');

        vi.unstubAllGlobals();
    });
});

describe('F-014 Q1 suspended seller notice', () => {
    it('shows nothing while the seller is active', () => {
        expect(pausedNoticeKey(false, 'awaiting_payment')).toBeNull();
    });

    it('tells an unpaid customer not to transfer yet', () => {
        expect(pausedNoticeKey(true, 'awaiting_payment')).toBe('payment.paused');
    });

    it('tells a customer under review their proof cannot be checked yet', () => {
        expect(pausedNoticeKey(true, 'needs_review')).toBe('payment.paused_review');
    });

    it('says only "seller unavailable" for paid, cancelled or expired bookings', () => {
        for (const status of ['paid', 'cancelled', 'rejected', 'completed']) {
            expect(pausedNoticeKey(true, status)).toBe('page.seller_unavailable');
        }
    });
});
