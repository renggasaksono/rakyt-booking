<script setup>
/**
 * Rakyt monogram (F-017) — the "#" grid lockup mark from the landing mockups
 * (docs/design/mockups/rakyt-booking-landing-sogan-cerah.html). Four square-cap
 * strokes with a dot at each inner intersection; one dot is gold — the single
 * brand accent. `tone="ink"` draws ink strokes on a transparent ground (nav);
 * `tone="cream"` draws cream strokes for use inside the ink footer tile.
 */
defineProps({
    tone: { type: String, default: 'ink' }, // 'ink' | 'cream'
    size: { type: Number, default: 26 },
});
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
            :stroke="tone === 'cream' ? 'var(--color-gading)' : 'var(--color-ink)'"
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
                fill="var(--color-ink)"
            />
            <circle
                cx="44"
                cy="22"
                r="6"
                fill="var(--color-ink)"
            />
            <circle
                cx="20"
                cy="44"
                r="6"
                fill="var(--color-gold-dull, #b8862f)"
            />
            <circle
                cx="44"
                cy="44"
                r="6"
                fill="var(--color-ink)"
            />
        </template>
    </svg>
</template>
