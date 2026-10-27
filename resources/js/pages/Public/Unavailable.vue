<script setup>
import { Head } from '@inertiajs/vue3';
import PublicFooter from '../../components/public/PublicFooter.vue';
import { t } from '../../lib/text.js';

/**
 * A suspended seller's link (F-014 AC-4): a neutral "tidak tersedia" state.
 * Nothing of the seller reaches this page — no name, profile, trips or
 * booking CTA — so it has no gold button. Ink outline only.
 */
defineProps({
    title: { type: String, required: true },
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});
</script>

<template>
    <Head :title="title" />
    <main class="mx-auto flex min-h-screen max-w-md flex-col px-4 pb-8 pt-6">
        <div class="flex flex-1 flex-col justify-center py-12">
            <h1 class="font-heading text-2xl font-semibold leading-tight text-ink">
                {{ t(copy, 'heading') }}
            </h1>
            <p class="mt-3 text-base text-secondary">
                {{ t(copy, 'message') }}
            </p>
            <div class="mt-6">
                <a
                    :href="urls.home"
                    class="inline-flex min-h-12 items-center justify-center px-5 font-heading text-sm font-semibold text-ink shadow-[inset_0_0_0_1.5px_#211710]"
                >
                    {{ t(copy, 'home') }}
                </a>
            </div>
        </div>

        <PublicFooter
            :label="t(copy, 'footer')"
            :href="urls.home"
        />
    </main>
</template>
