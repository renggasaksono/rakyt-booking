<script setup>
import { computed } from 'vue';
import { BOOKING_STATUS_STYLES } from '../lib/booking.js';

/**
 * Booking status pill: tint + deep text + glyph + word, never colour alone (ADR-0008).
 */
const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, required: true },
});

const style = computed(() => BOOKING_STATUS_STYLES[props.status] ?? BOOKING_STATUS_STYLES.cancelled);
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
