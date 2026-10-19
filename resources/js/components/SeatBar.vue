<script setup>
import { computed } from 'vue';
import { seatSegments, seatSummary } from '../lib/dashboard.js';

/**
 * AC-12: lunas / ditahan / kosong in proportion. The bar is decoration
 * (aria-hidden); the summary text is always shown and is what screen readers
 * get — colour is never the only signal. Tints are status tokens.
 */
const props = defineProps({
    seats: { type: Object, required: true },
    closesOn: { type: String, default: null },
    today: { type: String, required: true },
    copy: { type: Object, required: true },
    calendar: { type: Object, required: true },
});

const SEGMENT_CLASSES = {
    paid: 'bg-status-paid-tint border-status-paid',
    held: 'bg-status-waiting-tint border-status-waiting',
    empty: 'bg-surface border-ink/30',
};

const segments = computed(() => seatSegments(props.seats));
const summary = computed(() => seatSummary(props.seats, props.closesOn, props.today, props.copy, props.calendar));
</script>

<template>
    <div>
        <div
            class="flex h-3 w-full gap-0.5 overflow-hidden rounded-full"
            aria-hidden="true"
        >
            <span
                v-for="segment in segments"
                :key="segment.key"
                class="h-full border"
                :class="SEGMENT_CLASSES[segment.key]"
                :style="{ width: `${segment.width}%` }"
            />
        </div>
        <p class="mt-1 text-xs text-ink">
            {{ summary }}
        </p>
    </div>
</template>
