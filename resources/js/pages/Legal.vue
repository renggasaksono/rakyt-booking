<script setup>
import { Head, Link } from '@inertiajs/vue3';

/** "Syarat" / "Privasi" placeholders until the legal copy is ready (F-001 A1). */
defineProps({
    title: { type: String, required: true },
    body: { type: String, required: true },
    back: { type: Object, required: true },
});
</script>

<template>
    <Head :title="title" />
    <main class="mx-auto min-h-screen max-w-md px-4 py-8">
        <h1 class="font-heading text-2xl font-semibold text-ink">
            {{ title }}
        </h1>
        <p class="mt-3 text-base text-secondary">
            {{ body }}
        </p>
        <Link
            :href="back.url"
            class="mt-6 inline-flex min-h-11 items-center text-sm text-sogan underline"
        >
            {{ back.label }}
        </Link>
    </main>
</template>
