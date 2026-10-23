<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { t } from '../../lib/text.js';

/**
 * Admin landing page (F-010): the shell F-011–F-015 build on.
 */
defineProps({
    copy: { type: Object, required: true },
});
</script>

<template>
    <Head :title="t(copy, 'dashboard.title')" />
    <AdminLayout>
        <main class="mx-auto max-w-3xl px-4 py-8">
            <h1 class="font-heading text-2xl font-semibold text-ink">
                {{ t(copy, 'dashboard.heading') }}
            </h1>
            <p class="mt-3 text-base text-secondary">
                {{ t(copy, 'dashboard.intro') }}
            </p>
            <p class="mt-6 rounded-lg border border-dashed border-ink/20 bg-surface px-4 py-6 text-sm text-secondary">
                {{ t(copy, 'dashboard.empty') }}
            </p>
        </main>
    </AdminLayout>
</template>
