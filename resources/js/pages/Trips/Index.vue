<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { formatDayMonth } from '../../lib/calendar.js';
import { formatRupiah } from '../../lib/money.js';
import { t } from '../../lib/text.js';

/**
 * Trip & keberangkatan (F-003): the seller's trips, with the empty state of AC-42.
 */
const props = defineProps({
    trips: { type: Array, required: true },
    urls: { type: Object, required: true },
    copy: { type: Object, required: true },
});

const calendar = computed(() => props.copy.calendar);
</script>

<template>
    <Head :title="t(copy, 'index.title')" />
    <SellerLayout>
        <div class="mx-auto max-w-3xl px-4 py-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <h1 class="font-heading text-xl font-semibold text-ink">
                    {{ t(copy, 'index.title') }}
                </h1>
                <Link
                    :href="urls.create"
                    class="inline-flex min-h-12 items-center rounded-lg bg-prada px-4 font-heading text-sm font-semibold text-ink"
                >
                    {{ t(copy, 'index.new_trip') }}
                </Link>
            </header>

            <Link
                :href="urls.upcoming"
                class="mt-2 inline-flex min-h-11 items-center text-sm text-sogan underline"
            >
                {{ t(copy, 'index.upcoming_link') }}
            </Link>

            <div
                v-if="!trips.length"
                class="mt-6 rounded-xl border border-dashed border-ink/30 bg-surface p-6 text-center"
            >
                <p class="font-heading text-base font-semibold text-ink">
                    {{ t(copy, 'index.empty_title') }}
                </p>
                <p class="mt-1 text-sm text-secondary">
                    {{ t(copy, 'index.empty_body') }}
                </p>
            </div>

            <ul
                v-else
                class="mt-4 flex flex-col gap-3"
            >
                <li
                    v-for="trip in trips"
                    :key="trip.id"
                >
                    <Link
                        :href="trip.edit_url"
                        class="flex min-h-20 gap-3 rounded-xl border border-ink/10 bg-surface p-3"
                    >
                        <div class="h-16 w-20 shrink-0 overflow-hidden rounded-lg bg-gading">
                            <img
                                v-if="trip.cover_url"
                                :src="trip.cover_url"
                                alt=""
                                class="h-full w-full object-cover"
                                loading="lazy"
                            >
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-heading text-sm font-semibold text-ink">
                                {{ trip.name }}
                            </p>
                            <p class="mt-0.5 text-xs text-secondary">
                                <span :class="trip.status === 'published' ? 'text-status-paid' : 'text-secondary'">
                                    <span aria-hidden="true">{{ trip.status === 'published' ? '●' : '○' }}</span>
                                    {{ t(copy, `index.status.${trip.status}`) }}
                                </span>
                                <template v-if="trip.from_price">
                                    · {{ t(copy, 'money.currency') }}{{ formatRupiah(trip.from_price) }}
                                </template>
                            </p>
                            <p class="mt-0.5 text-xs text-secondary">
                                <template v-if="trip.booking_mode === 'request_date'">
                                    {{ t(copy, 'index.request_date') }}
                                </template>
                                <template v-else-if="trip.next_departure">
                                    {{ t(copy, 'index.next_departure', { date: formatDayMonth(trip.next_departure.start_date, calendar) }) }}
                                </template>
                                <template v-else>
                                    {{ t(copy, 'index.no_departure') }}
                                </template>
                            </p>
                        </div>
                    </Link>
                </li>
            </ul>
        </div>
    </SellerLayout>
</template>
