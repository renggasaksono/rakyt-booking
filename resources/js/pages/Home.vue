<script setup>
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    tagline: {
        type: String,
        required: true,
    },
    links: {
        type: Object,
        required: true,
    },
});
</script>

<template>
    <Head title="Rakyt Booking" />
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-10">
        <h1 class="font-heading text-2xl font-semibold text-ink">
            Rakyt Booking
        </h1>
        <p class="mt-2 text-base text-secondary">
            {{ tagline }}
        </p>
        <div class="mt-6 flex flex-col gap-3">
            <Link
                :href="links.register.url"
                class="flex min-h-12 items-center justify-center rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink"
            >
                {{ links.register.label }}
            </Link>
            <Link
                :href="links.login.url"
                class="flex min-h-12 items-center justify-center rounded-lg border-2 border-ink px-4 font-heading text-base font-semibold text-ink"
            >
                {{ links.login.label }}
            </Link>
        </div>
    </main>
</template>
