import { describe, expect, it } from 'vitest';
import { accountNumberInput, paymentBlocks, paymentPayload } from '../../resources/js/lib/paymentMethods.js';

describe('F-001 C10–C11, F5 payment blocks', () => {
    it('starts with one empty block when there is no account', () => {
        const [block] = paymentBlocks([]);

        expect(block).toMatchObject({ id: null, provider: '', provider_name: '', account_number: '', account_holder: '' });
    });

    it('keeps saved accounts with unique local keys that are never sent', () => {
        const blocks = paymentBlocks([
            { id: 'a', provider: 'bca', provider_name: null, account_number: '111', account_holder: 'Agus' },
            { id: 'b', provider: 'bpd', provider_name: 'Bank Jateng', account_number: '222', account_holder: 'Agus' },
        ]);

        expect(new Set(blocks.map((b) => b.key)).size).toBe(2);
        expect(blocks[0].provider_name).toBe('');
        expect(paymentPayload(blocks)).toEqual([
            { id: 'a', provider: 'bca', provider_name: '', account_number: '111', account_holder: 'Agus' },
            { id: 'b', provider: 'bpd', provider_name: 'Bank Jateng', account_number: '222', account_holder: 'Agus' },
        ]);
    });

    it('accepts only digits and spaces in the account number', () => {
        expect(accountNumberInput('0812-77a8 1020')).toBe('0812778 1020');
    });
});
