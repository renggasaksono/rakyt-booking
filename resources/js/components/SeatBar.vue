<script setup>
import { computed } from 'vue';
import { SEAT_SEGMENT_CLASSES, seatSegments, seatSummaryParts } from '../lib/dashboard.js';

/**
 * AC-12: lunas / ditahan / kosong in proportion (mockup 2f) — a thin, square,
 * solid bar with hairline gaps, and "2 ditahan" picked out in the waiting
 * colour. The bar is decoration (aria-hidden); the summary text is always shown
 * and is what screen readers get — colour is never the only signal.
 */
const props = defineProps({
    seats: { type: Object, required: true },
    closesOn: { type: String, default: null },
    today: { type: String, required: true },
    copy: { type: Object, required: true },
    calendar: { type: Object, required: true },
});

const segments = computed(() => seatSegments(props.seats));
const parts = computed(() => seatSummaryParts(props.seats, props.closesOn, props.today, props.copy, props.calendar));
</script>

<template>
    <div>
        <div
            class="flex h-2 w-full gap-[3px]"
            aria-hidden="true"
        >
            <span
                v-for="segment in segments"
                :key="segment.key"
                class="h-full basis-0"
                :class="SEAT_SEGMENT_CLASSES[segment.key]"
                :style="{ flexGrow: segment.n }"
            />
        </div>
        <p class="mt-2 text-xs text-secondary">
            <template
                v-for="(part, i) in parts"
                :key="part.key"
            >
                <template v-if="i > 0">
                    ·
                </template>
                <b
                    v-if="part.key === 'held'"
                    class="font-semibold text-status-waiting"
                >{{ part.text }}</b>
                <template v-else>
                    {{ part.text }}
                </template>
            </template>
        </p>
    </div>
</template>
