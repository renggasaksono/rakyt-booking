/**
 * Browser glue for the consent-gated GA4 helper (F-017, ADR-0034). The pure,
 * unit-tested logic lives in ./analytics.js; this file owns the parts that need
 * a real browser — loading gtag.js after the page is interactive (AC-22),
 * persisting the consent choice in localStorage, and exposing a reactive
 * `promptOpen` for the opt-in banner. It boots at most once per page load and
 * is a no-op without a configured measurement id (AC-19).
 */

import { readonly, ref } from 'vue';
import { CONSENT_KEY, createAnalytics } from './analytics.js';

let instance = null;
let booted = false;
let pendingEvent = null;
const promptOpen = ref(false);

function storage() {
    return {
        get() {
            try {
                return window.localStorage.getItem(CONSENT_KEY);
            } catch {
                return null; // private mode / blocked storage
            }
        },
        set(value) {
            try {
                window.localStorage.setItem(CONSENT_KEY, value);
            } catch {
                // Nothing to do — consent just won't be remembered.
            }
        },
    };
}

function loadGtag(measurementId) {
    window.dataLayer = window.dataLayer || [];
    const gtag = function () {
        window.dataLayer.push(arguments);
    };
    window.gtag = gtag;

    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`;
    document.head.appendChild(script);

    return gtag;
}

function whenIdle(run) {
    const schedule = () => (window.requestIdleCallback ? window.requestIdleCallback(run) : setTimeout(run, 1));

    if (document.readyState === 'complete') {
        schedule();
    } else {
        window.addEventListener('load', schedule, { once: true });
    }
}

/**
 * Boot GA once and fire any server-flashed funnel event. Safe to call on every
 * Inertia navigation: it boots on the first funnel page that carries a
 * measurement id, then only forwards later flashed events.
 */
export function bootAnalytics(config) {
    if (!config || !config.measurementId) {
        return;
    }

    if (booted) {
        fireServerEvent(config.event);

        return;
    }

    booted = true;
    // Keep any event flashed before GA finishes loading, so the first funnel
    // milestone is not dropped in the gap before the idle callback runs.
    pendingEvent = config.event ?? null;

    // After first paint so GA never blocks the landing on a mid-range phone.
    whenIdle(() => {
        const gtag = loadGtag(config.measurementId);
        instance = createAnalytics({ measurementId: config.measurementId, gtag, storage: storage() });
        instance.start();
        promptOpen.value = instance.needsPrompt();

        if (pendingEvent) {
            instance.trackServerEvent(pendingEvent);
            pendingEvent = null;
        }
    });
}

export function fireServerEvent(name) {
    if (!name) {
        return;
    }

    // Queue until GA has booted; flushed when the instance is created.
    if (instance) {
        instance.trackServerEvent(name);
    } else {
        pendingEvent = name;
    }
}

/** For components: the opt-in state plus track/accept/decline. */
export function useAnalytics() {
    return {
        promptOpen: readonly(promptOpen),
        track: (name, params) => (instance ? instance.track(name, params) : false),
        accept() {
            instance?.grant();
            promptOpen.value = false;
        },
        decline() {
            instance?.deny();
            promptOpen.value = false;
        },
    };
}
