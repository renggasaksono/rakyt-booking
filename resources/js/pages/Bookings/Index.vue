<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import BookingRow from '../../components/BookingRow.vue';
import BottomSheet from '../../components/BottomSheet.vue';
import LinkCard from '../../components/LinkCard.vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { activeFilterCount, dayMonth, filtersToQuery } from '../../lib/dashboard.js';
import { t } from '../../lib/text.js';

/**
 * Pesanan (F-007 section C): search, filters in the URL (AC-19), 20 per
 * page. Phone: search on top, "Filter (n)" opens a bottom sheet, active
 * filters as removable chips (AC-22); desktop: filters inline.
 */
const props = defineProps({
    bookings: { type: Array, required: true },
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
    pagination: { type: Object, required: true },
    // eslint-disable-next-line vue/prop-name-casing
    has_any: { type: Boolean, required: true },
    context: { type: Object, required: true },
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const calendar = computed(() => props.copy.calendar);
const search = ref(props.filters.cari ?? '');
const draft = reactive({ ...props.filters });
const sheetOpen = ref(false);
const activeCount = computed(() => activeFilterCount(props.filters));
const hasFilters = computed(() => Object.keys(filtersToQuery(props.filters)).length > 0);

const chips = computed(() => {
    const list = [];
    const f = props.filters;

    if (f.status) {
        list.push({ keys: ['status'], label: props.options.statuses.find((s) => s.value === f.status)?.label ?? f.status });
    }

    if (f.trip) {
        list.push({ keys: ['trip'], label: props.options.trips.find((trip) => trip.id === f.trip)?.name ?? f.trip });
    }

    if (f.dari || f.sampai) {
        const from = f.dari ? dayMonth(f.dari, props.context.today, calendar.value) : '…';
        const to = f.sampai ? dayMonth(f.sampai, props.context.today, calendar.value) : '…';
        list.push({ keys: ['dari', 'sampai'], label: `${from} – ${to}` });
    }

    if (f.keberangkatan && props.options.departure) {
        list.push({ keys: ['keberangkatan'], label: `${props.options.departure.trip_name} · ${dayMonth(props.options.departure.start_date, props.context.today, calendar.value)}` });
    }

    return list;
});

// AC-19: every change goes into the URL and resets to page 1.
function visit(filters) {
    router.get(props.urls.index, filtersToQuery(filters), { preserveScroll: false });
}

function submitSearch() {
    visit({ ...props.filters, cari: search.value });
}

function apply() {
    sheetOpen.value = false;
    visit({ ...draft, cari: search.value });
}

function removeChip(keys) {
    visit({ ...props.filters, ...Object.fromEntries(keys.map((key) => [key, null])) });
}

function clearAll() {
    search.value = '';
    router.get(props.urls.index);
}

function openSheet() {
    Object.assign(draft, props.filters);
    sheetOpen.value = true;
}
</script>

<template>
    <Head :title="t(copy, 'index.title')" />
    <SellerLayout>
        <div class="mx-auto max-w-3xl px-4 py-6">
            <h1 class="font-heading text-xl font-semibold text-ink">
                {{ t(copy, 'index.title') }}
            </h1>

            <form
                class="mt-3 flex gap-2"
                role="search"
                @submit.prevent="submitSearch"
            >
                <label
                    for="booking-search"
                    class="sr-only"
                >{{ t(copy, 'index.search') }}</label>
                <input
                    id="booking-search"
                    v-model="search"
                    type="search"
                    maxlength="100"
                    class="min-h-12 min-w-0 flex-1 rounded-lg border border-ink/20 bg-surface px-3 text-base text-ink"
                    :placeholder="t(copy, 'index.search')"
                >
                <button
                    type="submit"
                    class="min-h-12 shrink-0 rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    {{ t(copy, 'index.search_submit') }}
                </button>
            </form>

            <!-- Phone: "Filter (n)" + sheet. -->
            <div class="mt-3 flex flex-wrap items-center gap-2 lg:hidden">
                <button
                    type="button"
                    class="min-h-11 rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                    @click="openSheet"
                >
                    {{ activeCount > 0 ? t(copy, 'index.filter_count', { n: activeCount }) : t(copy, 'index.filter') }}
                </button>
            </div>

            <!-- Desktop: filters inline. -->
            <form
                class="mt-3 hidden flex-wrap items-end gap-3 lg:flex"
                @submit.prevent="apply"
            >
                <label class="flex flex-col text-sm text-ink">
                    {{ t(copy, 'index.status') }}
                    <select
                        v-model="draft.status"
                        class="mt-1 min-h-11 rounded-lg border border-ink/20 bg-surface px-2"
                    >
                        <option :value="null">{{ t(copy, 'index.all') }}</option>
                        <option
                            v-for="status in options.statuses"
                            :key="status.value"
                            :value="status.value"
                        >{{ status.label }}</option>
                    </select>
                </label>
                <label class="flex flex-col text-sm text-ink">
                    {{ t(copy, 'index.trip') }}
                    <select
                        v-model="draft.trip"
                        class="mt-1 min-h-11 max-w-56 rounded-lg border border-ink/20 bg-surface px-2"
                    >
                        <option :value="null">{{ t(copy, 'index.all_trips') }}</option>
                        <option
                            v-for="trip in options.trips"
                            :key="trip.id"
                            :value="trip.id"
                        >{{ trip.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col text-sm text-ink">
                    {{ t(copy, 'index.date') }} · {{ t(copy, 'index.from') }}
                    <input
                        v-model="draft.dari"
                        type="date"
                        class="mt-1 min-h-11 rounded-lg border border-ink/20 bg-surface px-2"
                    >
                </label>
                <label class="flex flex-col text-sm text-ink">
                    {{ t(copy, 'index.to') }}
                    <input
                        v-model="draft.sampai"
                        type="date"
                        class="mt-1 min-h-11 rounded-lg border border-ink/20 bg-surface px-2"
                    >
                </label>
                <button
                    type="submit"
                    class="min-h-11 rounded-lg bg-ink px-4 font-heading text-sm font-semibold text-surface"
                >
                    {{ t(copy, 'index.apply') }}
                </button>
            </form>

            <ul
                v-if="chips.length"
                class="mt-3 flex flex-wrap gap-2"
            >
                <li
                    v-for="chip in chips"
                    :key="chip.keys.join()"
                >
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center gap-2 rounded-full border border-ink/20 bg-surface px-3 text-sm text-ink"
                        :aria-label="t(copy, 'index.remove_filter', { label: chip.label })"
                        @click="removeChip(chip.keys)"
                    >
                        {{ chip.label }} <span aria-hidden="true">×</span>
                    </button>
                </li>
            </ul>

            <button
                v-if="hasFilters"
                type="button"
                class="mt-2 inline-flex min-h-11 items-center text-sm text-sogan underline"
                @click="clearAll"
            >
                {{ t(copy, 'index.clear') }}
            </button>

            <div
                v-if="!has_any"
                class="mt-6"
            >
                <p class="mb-3 font-heading text-base font-semibold text-ink">
                    {{ t(copy, 'index.empty') }}
                </p>
                <LinkCard
                    :url="urls.public_url"
                    :copy="copy.link"
                    :show-title="false"
                />
            </div>

            <div
                v-else-if="!bookings.length"
                class="mt-6 rounded-xl border border-ink/10 bg-surface p-6 text-center"
            >
                <p class="font-heading text-base font-semibold text-ink">
                    {{ t(copy, 'index.no_match') }}
                </p>
                <button
                    type="button"
                    class="mt-3 min-h-11 rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                    @click="clearAll"
                >
                    {{ t(copy, 'index.clear') }}
                </button>
            </div>

            <ul
                v-else
                class="mt-4 flex flex-col gap-2"
            >
                <li
                    v-for="booking in bookings"
                    :key="booking.public_id"
                >
                    <BookingRow
                        :booking="booking"
                        variant="list"
                        :context="context"
                        :copy="copy"
                        :status-words="copy.status_words"
                        :calendar="calendar"
                    />
                </li>
            </ul>

            <nav
                v-if="pagination.prev_url || pagination.next_url"
                class="mt-4 flex justify-between gap-2"
            >
                <Link
                    v-if="pagination.prev_url"
                    :href="pagination.prev_url"
                    class="inline-flex min-h-11 items-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    ← {{ t(copy, 'index.prev') }}
                </Link>
                <span v-else />
                <Link
                    v-if="pagination.next_url"
                    :href="pagination.next_url"
                    class="inline-flex min-h-11 items-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    {{ t(copy, 'index.next') }} →
                </Link>
            </nav>
        </div>

        <BottomSheet
            v-if="sheetOpen"
            :title="t(copy, 'index.filters_title')"
            :close-label="t(copy, 'index.close')"
            @close="sheetOpen = false"
        >
            <form
                class="flex flex-col gap-4"
                @submit.prevent="apply"
            >
                <fieldset>
                    <legend class="mb-2 font-heading text-sm font-semibold text-ink">
                        {{ t(copy, 'index.status') }}
                    </legend>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="status in [{ value: null, label: t(copy, 'index.all') }, ...options.statuses]"
                            :key="status.value ?? 'all'"
                            type="button"
                            class="min-h-11 rounded-full border px-3 text-sm"
                            :class="draft.status === status.value ? 'border-ink bg-ink text-surface' : 'border-ink/20 bg-surface text-ink'"
                            :aria-pressed="draft.status === status.value ? 'true' : 'false'"
                            @click="draft.status = status.value"
                        >
                            {{ status.label }}
                        </button>
                    </div>
                </fieldset>
                <label class="flex flex-col font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'index.trip') }}
                    <select
                        v-model="draft.trip"
                        class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-2 text-base font-normal"
                    >
                        <option :value="null">{{ t(copy, 'index.all_trips') }}</option>
                        <option
                            v-for="trip in options.trips"
                            :key="trip.id"
                            :value="trip.id"
                        >{{ trip.name }}</option>
                    </select>
                </label>
                <fieldset>
                    <legend class="mb-1 font-heading text-sm font-semibold text-ink">
                        {{ t(copy, 'index.date') }}
                    </legend>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex flex-col text-xs text-secondary">
                            {{ t(copy, 'index.from') }}
                            <input
                                v-model="draft.dari"
                                type="date"
                                class="mt-1 min-h-12 min-w-0 rounded-lg border border-ink/20 bg-surface px-2 text-base text-ink"
                            >
                        </label>
                        <label class="flex flex-col text-xs text-secondary">
                            {{ t(copy, 'index.to') }}
                            <input
                                v-model="draft.sampai"
                                type="date"
                                class="mt-1 min-h-12 min-w-0 rounded-lg border border-ink/20 bg-surface px-2 text-base text-ink"
                            >
                        </label>
                    </div>
                </fieldset>
                <button
                    type="submit"
                    class="min-h-12 w-full rounded-lg bg-ink px-4 font-heading text-base font-semibold text-surface"
                >
                    {{ t(copy, 'index.apply') }}
                </button>
            </form>
        </BottomSheet>
    </SellerLayout>
</template>
