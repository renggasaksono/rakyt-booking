/**
 * Consent-gated Google Analytics 4 helper for the landing sign-up funnel
 * (F-017 AC-19…AC-22, ADR-0034). Pure and dependency-injected so Vitest can
 * exercise it without a DOM: the caller passes `gtag` (the real one in the
 * browser, a spy in tests) and an optional `storage` for the consent choice.
 *
 * Consent Mode v2 starts with `analytics_storage` DENIED (PDP / decline
 * non-essential by default, ADR-0008 spirit); no cookie is set until the
 * visitor opts in. When no measurement id is configured the helper is a no-op
 * (tests and local default emit nothing — AC-19).
 */

export const FUNNEL_EVENTS = {
    ctaClick: 'landing_cta_click',
    signUp: 'sign_up',
    onboardingStart: 'onboarding_start',
    onboardingPublish: 'onboarding_publish',
};

// The server flashes these names; map them to GA event names so a page prop can
// drive an event after an Inertia navigation (register → onboarding → publish).
const SERVER_EVENTS = {
    sign_up: FUNNEL_EVENTS.signUp,
    onboarding_start: FUNNEL_EVENTS.onboardingStart,
    onboarding_publish: FUNNEL_EVENTS.onboardingPublish,
};

// Never send anything that identifies a person (AC-21). Params with these keys,
// or string values that look like an email or a phone number, are dropped.
const PII_KEYS = ['email', 'name', 'nama', 'phone', 'whatsapp', 'wa', 'password', 'token', 'user', 'user_id'];
const EMAIL = /@/;
const PHONE = /(?:\+?\d[\s-]?){7,}/;

export function stripPii(params = {}) {
    const clean = {};

    for (const [key, value] of Object.entries(params)) {
        if (PII_KEYS.includes(String(key).toLowerCase())) {
            continue;
        }

        if (typeof value === 'string' && (EMAIL.test(value) || PHONE.test(value))) {
            continue;
        }

        clean[key] = value;
    }

    return clean;
}

export const CONSENT_KEY = 'rakyt_ga_consent';

/**
 * @param {object}   options
 * @param {?string}  options.measurementId  GA4 id, or null/empty to disable.
 * @param {Function} options.gtag           The gtag() sink.
 * @param {object}  [options.storage]       { get(), set(value) } for the choice.
 */
export function createAnalytics({ measurementId, gtag, storage } = {}) {
    const enabled = Boolean(measurementId) && typeof gtag === 'function';
    let consent = 'denied';

    const push = (...args) => {
        if (enabled) {
            gtag(...args);
        }
    };

    return {
        enabled,

        get consent() {
            return consent;
        },

        /** The stored choice, if the visitor has already answered the prompt. */
        storedConsent() {
            const value = storage?.get();

            return value === 'granted' || value === 'denied' ? value : null;
        },

        /** True while the opt-in prompt should be offered. */
        needsPrompt() {
            return enabled && this.storedConsent() === null;
        },

        /** Consent Mode v2 default: everything denied until the visitor opts in. */
        start() {
            push('consent', 'default', {
                analytics_storage: 'denied',
                ad_storage: 'denied',
                ad_user_data: 'denied',
                ad_personalization: 'denied',
            });
            push('js', new Date());
            push('config', measurementId, { anonymize_ip: true });

            if (this.storedConsent() === 'granted') {
                this.grant();
            }
        },

        grant() {
            consent = 'granted';
            storage?.set('granted');
            push('consent', 'update', { analytics_storage: 'granted' });
        },

        deny() {
            consent = 'denied';
            storage?.set('denied');
            push('consent', 'update', { analytics_storage: 'denied' });
        },

        /** Fire a funnel event with PII stripped. Returns whether it was sent. */
        track(name, params = {}) {
            if (!enabled) {
                return false;
            }

            push('event', name, stripPii(params));

            return true;
        },

        /** Map a server-flashed event name to its GA event and fire it. */
        trackServerEvent(name, params = {}) {
            const event = SERVER_EVENTS[name];

            return event ? this.track(event, params) : false;
        },
    };
}
