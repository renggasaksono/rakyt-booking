<script setup>
import { computed } from 'vue';
import { STATUS_STYLES } from '../lib/departure.js';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, required: true },
});

const style = computed(() => STATUS_STYLES[props.status] ?? STATUS_STYLES.closed);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
        :class="style.className"
    >
        <span aria-hidden="true">{{ style.glyph }}</span>
        {{ label }}
    </span>
</template>
