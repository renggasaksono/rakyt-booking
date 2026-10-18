<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { t } from '../../lib/text.js';

/** Result of opening the verification link (F-001 A10). */
defineProps({
    copy: { type: Object, required: true },
    verified: { type: Boolean, required: true },
    urls: { type: Object, required: true },
});
</script>

<template>
    <Head :title="t(copy, 'verify.title')" />
    <AuthLayout :brand="t(copy, 'brand')">
        <h1 class="mt-6 font-heading text-2xl font-semibold text-ink">
            {{ t(copy, 'verify.title') }}
        </h1>
        <p
            class="mt-4 rounded-lg px-3 py-2 text-sm"
            :class="verified ? 'bg-status-paid-tint text-status-paid' : 'bg-status-rejected-tint text-status-rejected'"
            role="status"
        >
            {{ verified ? `✓ ${t(copy, 'verify.verified')}` : `✕ ${t(copy, 'verify.invalid')}` }}
        </p>
        <Link
            :href="urls.continue"
            class="mt-4 inline-flex min-h-12 items-center justify-center rounded-lg border-2 border-ink px-4 font-heading text-base font-semibold text-ink"
        >
            {{ t(copy, 'verify.continue') }}
        </Link>
    </AuthLayout>
</template>
