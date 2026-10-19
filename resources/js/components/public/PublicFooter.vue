<script setup>
/**
 * AC-38: "Dibuat dengan Rakyt" on every public page, every plan (Q2).
 */
defineProps({
    label: { type: String, required: true },
    href: { type: String, required: true },
});
</script>

<template>
    <footer class="mt-10 flex justify-center">
        <a
            :href="href"
            class="inline-flex min-h-11 items-center px-3 text-xs text-secondary underline-offset-2 hover:underline"
        >
            {{ label }}
        </a>
    </footer>
</template>
