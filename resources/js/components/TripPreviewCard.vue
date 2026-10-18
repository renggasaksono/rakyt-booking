<script setup>
import { computed } from 'vue';
import { formatDayMonth } from '../lib/calendar.js';
import { formatRupiah } from '../lib/money.js';
import { t } from '../lib/text.js';

/**
 * Live preview (AC-13), matching the F-002 trip card.
 */
const props = defineProps({
    preview: { type: Object, default: null },
    name: { type: String, default: '' },
    copy: { type: Object, required: true },
});

const calendar = computed(() => props.copy.calendar);
const next = computed(() => props.preview?.next_departure ?? null);
</script>

<template>
    <article class="overflow-hidden rounded-xl border border-ink/10 bg-surface">
        <div class="aspect-[4/3] bg-gading">
            <img
                v-if="preview?.cover_url"
                :src="preview.cover_url"
                alt=""
                class="h-full w-full object-cover"
            >
            <div
                v-else
                class="flex h-full items-center justify-center text-xs text-secondary"
            >
                {{ t(copy, 'preview.no_photo') }}
            </div>
        </div>
        <div class="p-3">
            <h3 class="font-heading text-base font-semibold text-ink">
                {{ preview?.name || name || t(copy, 'editor.title_new') }}
            </h3>
            <p
                v-if="preview?.from_price"
                class="mt-1 text-sm text-ink"
            >
                <span class="text-secondary">{{ t(copy, 'preview.from') }}</span>
                <strong> {{ t(copy, 'money.currency') }}{{ formatRupiah(preview.from_price) }}</strong>
            </p>
            <p class="mt-1 text-xs text-secondary">
                <template v-if="next">
                    {{ formatDayMonth(next.start_date, calendar) }} ·
                    {{ next.seats_left > 0 ? t(copy, 'preview.seats', { n: next.seats_left }) : t(copy, 'preview.full') }}
                </template>
                <template v-else>
                    {{ t(copy, 'preview.no_departure') }}
                </template>
            </p>
        </div>
    </article>
</template>
