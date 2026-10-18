<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import QuickEditSheet from '../../components/QuickEditSheet.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { formatDayMonth } from '../../lib/calendar.js';
import { mergeById } from '../../lib/pages.js';
import { t } from '../../lib/text.js';

/**
 * Upcoming departures on the phone (mockup 1g, AC-31): tap a row to open the
 * quick-edit sheet. 20 nearest first; "Tampilkan lebih banyak" loads the next
 * page (F-003 review N5). Empty state per AC-42.
 */
const props = defineProps({
    departures: { type: Array, required: true },
    pagination: { type: Object, required: true },
    context: { type: Object, required: true },
    urls: { type: Object, required: true },
    copy: { type: Object, required: true },
});

const calendar = computed(() => props.copy.calendar);
const shown = ref([...props.departures]);
// Which page each row came from, so a quick edit reloads that page.
const pageOf = new Map(props.departures.map((departure) => [departure.id, props.pagination.page]));
const nextUrl = ref(props.pagination.next_url);
const loading = ref(false);
const selectedId = ref(null);
const selected = computed(() => shown.value.find((departure) => departure.id === selectedId.value) ?? null);

// A loaded page is appended; a reload after a quick edit updates rows in place.
watch(
    () => props.departures,
    (departures) => {
        departures.forEach((departure) => pageOf.set(departure.id, props.pagination.page));
        shown.value = mergeById(shown.value, departures);
    },
);

function loadMore() {
    if (!nextUrl.value || loading.value) {
        return;
    }

    loading.value = true;
    router.get(nextUrl.value, {}, {
        preserveState: true,
        preserveScroll: true,
        // The address stays /keberangkatan; pages are only appended.
        preserveUrl: true,
        only: ['departures', 'pagination'],
        onSuccess: (page) => (nextUrl.value = page.props.pagination.next_url),
        onFinish: () => (loading.value = false),
    });
}

// After a quick edit (saved or refused), refresh the page that row is on (AC-33).
function refreshRow(id) {
    router.reload({
        data: { page: pageOf.get(id) ?? 1 },
        only: ['departures', 'pagination'],
        preserveUrl: true,
    });
}
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
                v-if="!shown.length"
                class="mt-6 rounded-xl border border-dashed border-ink/30 bg-surface p-6 text-center font-heading text-sm font-semibold text-ink"
            >
                {{ t(copy, 'upcoming.empty') }}
            </p>

            <ul
                v-else
                class="mt-4 flex flex-col gap-2"
            >
                <li
                    v-for="departure in shown"
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

            <button
                v-if="nextUrl"
                type="button"
                class="mt-4 min-h-11 w-full rounded-lg border border-ink px-4 text-sm font-semibold text-ink disabled:text-secondary"
                :disabled="loading"
                :aria-busy="loading ? 'true' : 'false'"
                @click="loadMore"
            >
                {{ t(copy, 'upcoming.more') }}
            </button>
        </div>

        <QuickEditSheet
            v-if="selected"
            :key="selected.id"
            :departure="selected"
            :context="context"
            :presets="presets"
            :reload="['flash']"
            :copy="copy"
            @saved="refreshRow"
            @failed="refreshRow"
            @close="selectedId = null"
        />
    </SellerLayout>
</template>
