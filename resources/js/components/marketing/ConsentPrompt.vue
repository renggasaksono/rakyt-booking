<script setup>
import { Link } from '@inertiajs/vue3';
import { useAnalytics } from '../../lib/useAnalytics.js';

/**
 * Lightweight opt-in prompt for Google Analytics (F-017 AC-20, ADR-0034).
 * Consent Mode v2 starts default-denied; this banner only appears once GA is
 * configured and booted and the visitor has not chosen yet. It sets no cookie
 * until "Boleh". With no measurement id it never shows (AC-19/AC-22).
 */
defineProps({
    consent: { type: Object, required: true },
    privacyUrl: { type: String, required: true },
});

const { promptOpen, accept, decline } = useAnalytics();
</script>

<template>
    <div
        v-if="promptOpen"
        class="fixed inset-x-0 bottom-0 z-50 px-4 pb-4"
        role="dialog"
        aria-live="polite"
    >
        <div class="mx-auto flex max-w-xl flex-col gap-3 bg-ink p-4 text-surface shadow-lg sm:flex-row sm:items-center">
            <p class="text-sm">
                {{ consent.message }}
                <Link
                    :href="privacyUrl"
                    class="underline underline-offset-2"
                >
                    {{ consent.learn }}
                </Link>
            </p>
            <div class="flex flex-none gap-2 sm:ml-auto">
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center px-3 text-sm font-semibold text-surface/80 hover:text-surface"
                    @click="decline"
                >
                    {{ consent.decline }}
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center bg-surface px-4 font-heading text-sm font-semibold text-ink"
                    @click="accept"
                >
                    {{ consent.accept }}
                </button>
            </div>
        </div>
    </div>
</template>
