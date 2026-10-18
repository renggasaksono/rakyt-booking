<script setup>
import FlashMessages from '../components/FlashMessages.vue';

/**
 * Sign-up, sign-in and password screens (F-001 A1–A8). Phone first: one
 * column, the gold CTA sticky at the bottom (P1); no step header (C1).
 */
defineProps({
    brand: { type: String, required: true },
});
</script>

<template>
    <div class="min-h-screen bg-gading">
        <main class="mx-auto flex min-h-screen max-w-md flex-col px-4 pb-32 pt-6">
            <p class="font-heading text-xl font-semibold text-sogan">
                {{ brand }}
            </p>
            <slot />
        </main>
        <FlashMessages />
    </div>
</template>
