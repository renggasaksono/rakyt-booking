<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import QuickEditSheet from '../../components/QuickEditSheet.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { formatDayMonth } from '../../lib/calendar.js';
import { t } from '../../lib/text.js';

/**
 * Upcoming departures on the phone (mockup 1g, AC-31): tap a row to open the
 * quick-edit sheet. Empty state per AC-42.
 */
const props = defineProps({
    departures: { type: Array, required: true },
    context: { type: Object, required: true },
    urls: { type: Object, required: true },
    copy: { type: Object, required: true },
});

const calendar = computed(() => props.copy.calendar);
const selectedId = ref(null);
const selected = computed(() => props.departures.find((departure) => departure.id === selectedId.value) ?? null);
const presets = ['same_day', 'h1', 'h3', 'h7', 'custom'];
</script>

<template>
    <Head :title="t(copy, 'upcoming.title')" />
    <SellerLayout>
        <div class="mx-auto max-w-md px-4 py-6">
            <Link
                :href="urls.index"
                class="inline-flex min-h-11 items-center text-sm text-sogan underline"
            >
                {{ t(copy, 'upcoming.back') }}
            </Link>
            <h1 class="font-heading text-xl font-semibold text-ink">
                {{ t(copy, 'upcoming.title') }}
            </h1>
            <p class="text-sm text-secondary">
                {{ t(copy, 'upcoming.subtitle') }}
            </p>

            <p
                v-if="!departures.length"
                class="mt-6 rounded-xl border border-dashed border-ink/30 bg-surface p-6 text-center font-heading text-sm font-semibold text-ink"
            >
                {{ t(copy, 'upcoming.empty') }}
            </p>

            <ul
                v-else
                class="mt-4 flex flex-col gap-2"
            >
                <li
                    v-for="departure in departures"
                    :key="departure.id"
                >
                    <button
                        type="button"
                        class="flex min-h-14 w-full items-center justify-between gap-3 rounded-xl border border-ink/10 bg-surface px-4 py-3 text-left"
                        @click="selectedId = departure.id"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-heading text-sm font-semibold text-ink">
                                {{ departure.trip_name }} · {{ formatDayMonth(departure.start_date, calendar) }}
                            </span>
                            <span class="block text-xs text-secondary">
                                <!-- No opacity for full rows: the word replaces the numbers. -->
                                {{ departure.display_status === 'full'
                                    ? t(copy, 'status.full')
                                    : t(copy, 'departures.seats_taken', { taken: departure.seats_taken, capacity: departure.capacity }) }}
                            </span>
                        </span>
                        <StatusBadge
                            :status="departure.display_status"
                            :label="t(copy, `status.${departure.display_status}`)"
                        />
                    </button>
                </li>
            </ul>
        </div>

        <QuickEditSheet
            v-if="selected"
            :key="selected.id"
            :departure="selected"
            :context="context"
            :presets="presets"
            :reload="['departures', 'flash']"
            :copy="copy"
            @close="selectedId = null"
        />
    </SellerLayout>
</template>
