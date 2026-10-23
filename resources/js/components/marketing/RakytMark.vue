<script setup>
import { computed } from 'vue';

/**
 * Rakyt monogram (F-017) — the "#" grid lockup mark from the landing mockups
 * (docs/design/mockups/rakyt-booking-landing-sogan-cerah.html). Four square-cap
 * strokes with a dot at each inner intersection; one dot is gold — the single
 * brand accent. `tone="ink"` draws ink strokes on a transparent ground (nav);
 * `tone="cream"` draws cream strokes for use inside the ink footer tile;
 * `tone="reverse"` is the ink-ground lockup of the seller side nav (1e/2f):
 * gading strokes and nodes, the accent node in gold-soft.
 */
const props = defineProps({
    tone: { type: String, default: 'ink' }, // 'ink' | 'cream' | 'reverse'
    size: { type: Number, default: 26 },
});

const line = computed(() => (props.tone === 'ink' ? 'var(--color-ink)' : 'var(--color-gading)'));
const accent = computed(() => (props.tone === 'reverse' ? 'var(--color-gold-soft)' : 'var(--color-gold-dull, #b8862f)'));
</script>

<template>
    <svg
        viewBox="0 0 64 64"
        :width="size"
        :height="size"
        class="block shrink-0"
        aria-hidden="true"
    >
        <g
            :stroke="line"
            :stroke-width="tone === 'cream' ? 6 : 5"
            stroke-linecap="square"
            fill="none"
        >
            <path d="M6 22 L58 22" />
            <path d="M6 44 L58 44" />
            <path d="M20 8 L20 58" />
            <path d="M44 8 L44 58" />
        </g>
        <template v-if="tone === 'cream'">
            <!-- footer: single gold node on the ink tile -->
            <circle
                cx="20"
                cy="44"
                r="8"
                fill="#d9ae5c"
            />
        </template>
        <template v-else>
            <circle
                cx="20"
                cy="22"
                r="6"
                :fill="line"
            />
            <circle
                cx="44"
                cy="22"
                r="6"
                :fill="line"
            />
            <circle
                cx="20"
                cy="44"
                r="6"
                :fill="accent"
            />
            <circle
                cx="44"
                cy="44"
                r="6"
                :fill="line"
            />
        </template>
    </svg>
</template>
