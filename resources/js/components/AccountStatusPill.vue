<script setup>
import { computed } from 'vue';
import { ACCOUNT_STATUS_STYLES } from '../lib/adminSellers.js';

/**
 * An organization's account status in the admin (F-012): aktif / ditangguhkan.
 */
const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, required: true },
});

const style = computed(() => ACCOUNT_STATUS_STYLES[props.status] ?? ACCOUNT_STATUS_STYLES.suspended);
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold"
        :class="style.className"
    >
        <span aria-hidden="true">{{ style.glyph }}</span>
        {{ label }}
    </span>
</template>
