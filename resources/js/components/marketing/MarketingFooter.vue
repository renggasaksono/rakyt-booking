<script setup>
import { Link } from '@inertiajs/vue3';

/**
 * Landing footer (F-017 AC-6): legal links and the "Dibuat dengan Rakyt" mark,
 * consistent with the public-page footer.
 */
defineProps({
    footer: { type: Object, required: true },
    urls: { type: Object, required: true },
});
</script>

<template>
    <footer class="mt-16 border-t border-ink/10 bg-surface">
        <div class="mx-auto flex max-w-5xl flex-col gap-4 px-4 py-8 text-sm text-secondary md:flex-row md:items-center md:justify-between">
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <span class="font-heading font-semibold text-ink">{{ footer.made_with }}</span>
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
            </div>
            <div class="flex flex-col gap-0.5 md:text-right">
                <span>{{ footer.company }}</span>
                <a
                    :href="`mailto:${footer.email}`"
                    class="underline-offset-2 hover:underline"
                >
                    {{ footer.email }}
                </a>
            </div>
        </div>
    </footer>
</template>
