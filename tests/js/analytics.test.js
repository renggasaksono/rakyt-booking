import { describe, expect, it, vi } from 'vitest';
import { CONSENT_KEY, FUNNEL_EVENTS, createAnalytics, stripPii } from '../../resources/js/lib/analytics.js';

function memoryStorage(initial = null) {
    let value = initial;

    return {
        get: () => value,
        set: (v) => {
            value = v;
        },
    };
}

describe('AC-19 config gating', () => {
    it('is disabled and sends nothing without a measurement id', () => {
        const gtag = vi.fn();
        const ga = createAnalytics({ measurementId: null, gtag });

        ga.start();
        const sent = ga.track(FUNNEL_EVENTS.ctaClick);

        expect(ga.enabled).toBe(false);
        expect(sent).toBe(false);
        expect(gtag).not.toHaveBeenCalled();
        expect(ga.needsPrompt()).toBe(false);
    });

    it('is enabled with a measurement id and a gtag sink', () => {
        const ga = createAnalytics({ measurementId: 'G-TEST123', gtag: vi.fn() });

        expect(ga.enabled).toBe(true);
    });
});

describe('AC-20 consent defaults to denied', () => {
    it('sets Consent Mode v2 default-denied on start and configures GA', () => {
        const gtag = vi.fn();
        const ga = createAnalytics({ measurementId: 'G-TEST123', gtag, storage: memoryStorage() });

        ga.start();

        expect(ga.consent).toBe('denied');
        expect(gtag).toHaveBeenCalledWith('consent', 'default', expect.objectContaining({ analytics_storage: 'denied' }));
        expect(gtag).toHaveBeenCalledWith('config', 'G-TEST123', expect.objectContaining({ anonymize_ip: true }));
        // No consent "update" to granted until the visitor opts in.
        expect(gtag).not.toHaveBeenCalledWith('consent', 'update', { analytics_storage: 'granted' });
    });

    it('offers the prompt only until a choice is stored', () => {
        const storage = memoryStorage();
        const ga = createAnalytics({ measurementId: 'G-X', gtag: vi.fn(), storage });

        expect(ga.needsPrompt()).toBe(true);
        ga.deny();
        expect(ga.needsPrompt()).toBe(false);
        expect(storage.get()).toBe('denied');
    });

    it('grants on opt-in and restores a stored grant on start', () => {
        const gtag = vi.fn();
        const storage = memoryStorage();
        const ga = createAnalytics({ measurementId: 'G-X', gtag, storage });

        ga.grant();
        expect(ga.consent).toBe('granted');
        expect(gtag).toHaveBeenCalledWith('consent', 'update', { analytics_storage: 'granted' });
        expect(storage.get()).toBe('granted');

        // A returning visitor who already granted: start() re-grants.
        const again = createAnalytics({ measurementId: 'G-X', gtag: vi.fn(), storage });
        again.start();
        expect(again.consent).toBe('granted');
    });
});

describe('AC-21 funnel events carry no PII', () => {
    it('fires the named funnel events', () => {
        const gtag = vi.fn();
        const ga = createAnalytics({ measurementId: 'G-X', gtag });

        expect(ga.track(FUNNEL_EVENTS.ctaClick)).toBe(true);
        expect(ga.trackServerEvent('sign_up')).toBe(true);
        expect(ga.trackServerEvent('onboarding_start')).toBe(true);
        expect(ga.trackServerEvent('onboarding_publish')).toBe(true);
        expect(ga.trackServerEvent('unknown_event')).toBe(false);

        expect(gtag).toHaveBeenCalledWith('event', 'landing_cta_click', {});
        expect(gtag).toHaveBeenCalledWith('event', 'sign_up', {});
        expect(gtag).toHaveBeenCalledWith('event', 'onboarding_start', {});
        expect(gtag).toHaveBeenCalledWith('event', 'onboarding_publish', {});
    });

    it('strips identifying params before sending', () => {
        expect(stripPii({ email: 'a@b.com', name: 'Sari', plan: 'free' })).toEqual({ plan: 'free' });
        expect(stripPii({ whatsapp: '0812', note: 'ok' })).toEqual({ note: 'ok' });
        // Values that look like an email or phone are dropped even under a safe key.
        expect(stripPii({ label: 'reach me at a@b.com' })).toEqual({});
        expect(stripPii({ label: '+62 812 3456 7890' })).toEqual({});
        expect(stripPii({ cta: 'hero' })).toEqual({ cta: 'hero' });
    });

    it('never sends PII through track()', () => {
        const gtag = vi.fn();
        const ga = createAnalytics({ measurementId: 'G-X', gtag });

        ga.track(FUNNEL_EVENTS.ctaClick, { location: 'hero', email: 'a@b.com', nama: 'Sari' });

        expect(gtag).toHaveBeenCalledWith('event', 'landing_cta_click', { location: 'hero' });
    });
});

describe('consent key is stable', () => {
    it('exposes a storage key for the choice', () => {
        expect(CONSENT_KEY).toBe('rakyt_ga_consent');
    });
});
