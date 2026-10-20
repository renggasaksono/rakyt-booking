<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { formatRupiah } from '../../lib/money.js';
import { formatDay } from '../../lib/publicPage.js';
import { t } from '../../lib/text.js';

/**
 * AC-14: one published trip on the seller page (mockup 1a).
 */
const props = defineProps({
    trip: { type: Object, required: true },
    copy: { type: Object, required: true },
    calendar: { type: Object, required: true },
    today: { type: String, required: true },
    eager: { type: Boolean, default: false },
});

const status = computed(() => {
    switch (props.trip.state) {
        case 'bookable':
            return `${t(props.copy, 'card.seats', { n: props.trip.seats_left })} · ${formatDay(props.trip.next_start_date, props.today, props.calendar)}`;
        case 'request_date':
            return t(props.copy, 'card.request_date');
        default:
            return t(props.copy, 'card.no_date');
    }
});
</script>

<template>
    <Link
        :href="trip.url"
        class="block overflow-hidden bg-surface shadow-[0_1px_0_rgba(33,23,16,0.14)] focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-sogan"
    >
        <div class="aspect-[4/3] bg-gading">
            <img
                v-if="trip.cover"
                :src="trip.cover.src"
                :srcset="trip.cover.srcset"
                sizes="(min-width: 448px) 416px, calc(100vw - 32px)"
                width="400"
                height="300"
                :loading="eager ? 'eager' : 'lazy'"
                decoding="async"
                alt=""
                class="h-full w-full object-cover"
            >
            <div
                v-else
                class="flex h-full items-center justify-center text-xs text-secondary"
            >
                {{ t(copy, 'card.no_photo') }}
            </div>
        </div>
        <div class="p-3">
            <h3 class="font-heading text-base font-semibold text-ink">
                {{ trip.name }}
            </h3>
            <!-- One line, so the spaces between the parts are kept. -->
            <p class="mt-1 text-sm text-ink">
                <span class="text-secondary">{{ t(copy, 'card.from') }}</span> <strong>{{ t(copy, 'currency') }}{{ formatRupiah(trip.from_price) }}</strong><span class="text-secondary">{{ t(copy, 'card.per_person') }}</span>
            </p>
            <p class="mt-1 text-xs text-secondary">
                {{ status }}
            </p>
        </div>
    </Link>
</template>
