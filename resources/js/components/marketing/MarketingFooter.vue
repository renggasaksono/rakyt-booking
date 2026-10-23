<script setup>
import { Link } from '@inertiajs/vue3';
import RakytMark from './RakytMark.vue';

/**
 * Landing footer (F-017 AC-6): the Rakyt brand lockup — an ink tile holding the
 * monogram, the "Rakyt." wordmark and the company line — with legal links. This
 * is Rakyt's own site, so it carries the brand mark, not the "Dibuat dengan
 * Rakyt" attribution badge (that belongs on seller public pages). Design follows
 * the landing mockups (docs/design/mockups/rakyt-booking-landing-*.html).
 */
defineProps({
    footer: { type: Object, required: true },
    urls: { type: Object, required: true },
});
</script>

<template>
    <footer class="border-t border-ink/10 bg-surface">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-5 px-4 py-10">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 items-center justify-center bg-ink">
                    <RakytMark
                        tone="cream"
                        :size="24"
                    />
                </span>
                <div>
                    <div class="font-heading text-[15px] font-semibold text-ink">
                        {{ footer.brand }}<span class="text-[var(--color-gold-dull,#b8862f)]">.</span>
                    </div>
                    <div class="text-[11px] uppercase tracking-[0.1em] text-[var(--color-secondary-meta,#7a6250)]">
                        {{ footer.company }}
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-secondary">
                <Link
                    :href="urls.terms"
                    class="min-h-9 py-1 underline-offset-2 hover:underline"
                >
                    {{ footer.terms }}
                </Link>
                <Link
                    :href="urls.privacy"
                    class="min-h-9 py-1 underline-offset-2 hover:underline"
                >
                    {{ footer.privacy }}
                </Link>
                <a
                    :href="`mailto:${footer.email}`"
                    class="min-h-9 py-1 underline-offset-2 hover:underline"
                >
                    {{ footer.email }}
                </a>
            </div>
        </div>
    </footer>
</template>
