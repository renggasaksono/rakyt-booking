<script setup>
import FlashMessages from '../components/FlashMessages.vue';
import ProductMark from '../components/ProductMark.vue';

/**
 * Sign-up, sign-in and password screens (F-001 A1–A8). Phone first: one
 * column, the gold CTA sticky at the bottom (P1); no step header (C1). The
 * header is the "Rakyt | Booking" lockup of the landing nav and seller side nav.
 */
defineProps({
    brand: { type: String, required: true },
    suffix: { type: String, default: null },
});
</script>

<template>
    <div class="min-h-screen bg-gading">
        <main class="mx-auto flex min-h-screen max-w-md flex-col px-4 pb-32 pt-6">
            <ProductMark
                :brand="brand"
                :suffix="suffix"
            />
            <slot />
        </main>
        <FlashMessages />
    </div>
</template>
