<script setup>
import { Head, Link } from '@inertiajs/vue3';

/**
 * Error page for Inertia visits (F-009 R7), matching the Blade error pages:
 * Bahasa, phone width, ink-outline actions only.
 */
defineProps({
    status: { type: Number, required: true },
    title: { type: String, required: true },
    message: { type: String, default: '' },
    actions: { type: Object, required: true },
    urls: { type: Object, required: true },
});

function goBack() {
    window.history.back();
}
</script>

<template>
    <Head :title="title" />
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-12">
        <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
            {{ status }}
        </p>
        <h1 class="mt-2 font-heading text-2xl font-semibold text-ink">
            {{ title }}
        </h1>
        <p class="mt-3 text-base text-secondary">
            {{ message }}
        </p>
        <div class="mt-6 flex flex-wrap gap-3">
            <button
                type="button"
                class="inline-flex min-h-12 items-center rounded-lg border-2 border-ink px-5 font-heading font-semibold text-ink"
                @click="goBack"
            >
                {{ actions.back }}
            </button>
            <Link
                :href="urls.home"
                class="inline-flex min-h-12 items-center rounded-lg border-2 border-ink px-5 font-heading font-semibold text-ink"
            >
                {{ actions.home }}
            </Link>
        </div>
    </main>
</template>
