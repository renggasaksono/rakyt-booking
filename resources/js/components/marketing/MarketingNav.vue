<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Sticky landing nav (F-017): brand, in-page anchors, "Masuk" (secondary ink),
 * and the primary CTA. The nav CTA is ink-filled — never gold — so that at any
 * scroll position at most one gold (prada) CTA is on screen (AC-5, ADR-0008):
 * the gold lives in the hero and the closing band.
 */
defineProps({
    nav: { type: Object, required: true },
    cta: { type: Object, required: true },
    loginUrl: { type: String, required: true },
});

const emit = defineEmits(['cta']);
</script>

<template>
    <header class="sticky top-0 z-40 border-b border-ink/10 bg-gading/95 backdrop-blur">
        <nav class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3">
            <a
                href="#atas"
                class="font-heading text-lg font-semibold tracking-tight text-ink"
            >
                {{ nav.brand }}
            </a>

            <ul class="ml-4 hidden items-center gap-5 md:flex">
                <li
                    v-for="anchor in nav.anchors"
                    :key="anchor.id"
                >
                    <a
                        :href="`#${anchor.id}`"
                        class="text-sm text-secondary hover:text-ink"
                    >
                        {{ anchor.label }}
                    </a>
                </li>
            </ul>

            <div class="ml-auto flex items-center gap-2">
                <Link
                    :href="loginUrl"
                    class="inline-flex min-h-10 items-center px-3 text-sm font-semibold text-ink hover:underline"
                >
                    {{ nav.login }}
                </Link>
                <Link
                    :href="cta.url"
                    class="inline-flex min-h-10 items-center justify-center bg-ink px-4 font-heading text-sm font-semibold text-surface"
                    @click="emit('cta', 'nav')"
                >
                    {{ cta.label }}
                </Link>
            </div>
        </nav>
    </header>
</template>
