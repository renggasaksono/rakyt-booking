<script setup>
import RakytMark from './marketing/RakytMark.vue';

/**
 * The "Rakyt | Booking" product lockup: monogram, wordmark, a hairline and the
 * product name as an uppercase kicker. One component so the landing nav and the
 * seller side nav never drift apart. `tone="ink"` sits on light grounds (Sogan
 * kicker); `tone="reverse"` sits on the ink side nav, where Sogan fails contrast
 * (2.1 : 1) and the kicker takes gold-soft instead (8.5 : 1).
 */
defineProps({
    brand: { type: String, required: true },
    suffix: { type: String, default: null },
    tone: { type: String, default: 'ink' }, // 'ink' | 'reverse'
});
</script>

<template>
    <span class="flex items-center gap-2.5">
        <RakytMark
            :tone="tone"
            :size="26"
        />
        <span
            class="font-heading text-xl font-semibold tracking-[-0.03em]"
            :class="tone === 'reverse' ? 'text-gading' : 'text-ink'"
        >{{ brand }}</span>
        <span
            v-if="suffix"
            class="border-l pl-2.5 font-heading text-[11px] font-semibold uppercase tracking-[0.14em]"
            :class="tone === 'reverse' ? 'border-gading/20 text-gold-soft' : 'border-ink/20 text-sogan'"
        >{{ suffix }}</span>
    </span>
</template>
