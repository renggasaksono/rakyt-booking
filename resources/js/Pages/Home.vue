<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    tagline: {
        type: String,
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
    </main>
</template>
