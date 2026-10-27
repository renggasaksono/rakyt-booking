<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AccountStatusPill from '../../../components/AccountStatusPill.vue';
import BookingStatusPill from '../../../components/BookingStatusPill.vue';
import OrganizationPicker from '../../../components/OrganizationPicker.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import { activeBookingFilterCount, bookingFilterQuery } from '../../../lib/adminBookings.js';
import { t } from '../../../lib/text.js';

/**
 * Pesanan (F-013): bookings across every seller, newest first. One search box
 * (exact reference, full customer phone in any format, or part of the
 * customer's or seller's name), plus status, seller and departure-date
 * filters that combine and live in the URL. Read-only. Phone first — results
 * are cards and the filters stack; from 1024 px the filters sit in a grid.
 * Rows never carry the customer's phone (AC-5); it is on the detail only.
 */
const props = defineProps({
    bookings: { type: Array, required: true },
    filters: { type: Object, required: true },
    // eslint-disable-next-line vue/prop-name-casing
    selected_organization: { type: Object, default: null },
    // eslint-disable-next-line vue/prop-name-casing
    search_kind: { type: String, default: null },
    options: { type: Object, required: true },
    pagination: { type: Object, required: true },
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const draft = reactive({ ...props.filters });
const hasFilters = computed(() => activeBookingFilterCount(props.filters) > 0);

function apply() {
    router.get(props.urls.index, bookingFilterQuery(draft), { preserveScroll: false });
}
</script>

<template>
    <Head :title="t(copy, 'title')" />
    <AdminLayout>
        <main class="mx-auto max-w-4xl px-4 py-8">
            <h1 class="font-heading text-2xl font-semibold text-ink">
                {{ t(copy, 'heading') }}
            </h1>
            <p class="mt-2 text-sm text-secondary">
                {{ t(copy, 'intro') }}
            </p>

            <form
                class="mt-6 grid gap-3 rounded-lg border border-ink/10 bg-surface p-4 lg:grid-cols-2 lg:items-start"
                role="search"
                :aria-label="t(copy, 'filters.title')"
                @submit.prevent="apply"
            >
                <label class="flex flex-col font-heading text-sm font-semibold text-ink lg:col-span-2">
                    {{ t(copy, 'filters.search') }}
                    <input
                        v-model="draft.cari"
                        type="search"
                        maxlength="100"
                        autocomplete="off"
                        :placeholder="t(copy, 'filters.search_placeholder')"
                        class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-3 text-base font-normal"
                        aria-describedby="search-hint"
                    >
                    <span
                        id="search-hint"
                        class="mt-1 text-xs font-normal text-secondary"
                    >{{ t(copy, 'filters.search_hint') }}</span>
                </label>

                <label class="flex flex-col font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'filters.status') }}
                    <select
                        v-model="draft.status"
                        class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-2 text-base font-normal"
                    >
                        <option :value="null">{{ t(copy, 'filters.all_statuses') }}</option>
                        <option
                            v-for="status in options.statuses"
                            :key="status.value"
                            :value="status.value"
                        >{{ status.label }}</option>
                    </select>
                </label>

                <OrganizationPicker
                    v-model="draft.organisasi"
                    :selected="selected_organization"
                    :lookup-url="urls.organization_lookup"
                    :label="t(copy, 'filters.organization')"
                    :copy="copy.picker"
                />

                <fieldset class="flex flex-col lg:col-span-2">
                    <legend class="font-heading text-sm font-semibold text-ink">
                        {{ t(copy, 'filters.departure') }}
                    </legend>
                    <div class="mt-1 grid grid-cols-2 gap-3">
                        <label class="flex flex-col text-xs text-secondary">
                            {{ t(copy, 'filters.from') }}
                            <input
                                v-model="draft.dari"
                                type="date"
                                class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-2 text-base text-ink"
                            >
                        </label>
                        <label class="flex flex-col text-xs text-secondary">
                            {{ t(copy, 'filters.to') }}
                            <input
                                v-model="draft.sampai"
                                type="date"
                                class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-2 text-base text-ink"
                            >
                        </label>
                    </div>
                </fieldset>

                <div class="flex flex-wrap items-center gap-3 lg:col-span-2">
                    <button
                        type="submit"
                        class="min-h-12 rounded-lg bg-ink px-5 font-heading text-sm font-semibold text-surface"
                    >
                        {{ t(copy, 'filters.apply') }}
                    </button>
                    <Link
                        v-if="hasFilters"
                        :href="urls.index"
                        class="inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline"
                    >
                        {{ t(copy, 'filters.reset') }}
                    </Link>
                </div>
            </form>

            <p
                v-if="search_kind"
                class="mt-4 text-sm text-secondary"
                aria-live="polite"
            >
                {{ t(copy, `search_kinds.${search_kind}`) }}
            </p>

            <p
                v-if="bookings.length === 0"
                class="mt-6 rounded-lg border border-dashed border-ink/20 bg-surface px-4 py-6 text-sm text-secondary"
            >
                {{ hasFilters ? t(copy, 'empty_filtered') : t(copy, 'empty') }}
            </p>

            <ul
                v-else
                class="mt-6 flex flex-col gap-3"
            >
                <li
                    v-for="booking in bookings"
                    :key="booking.id"
                    class="rounded-lg border border-ink/10 bg-surface hover:border-ink/30"
                >
                    <Link
                        :href="booking.url"
                        class="block p-4 pb-2"
                    >
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <h2 class="font-heading text-base font-semibold text-ink">
                                {{ booking.customer_name }}
                            </h2>
                            <BookingStatusPill
                                :status="booking.status"
                                :label="booking.status_label"
                            />
                        </div>
                        <p class="mt-0.5 font-mono text-xs text-secondary">
                            {{ booking.reference }}
                        </p>
                        <p class="mt-2 text-sm text-ink">
                            {{ booking.trip_name }} · {{ booking.departure_label }}
                        </p>
                        <p class="text-sm text-ink">
                            {{ t(copy, 'participants', { count: booking.participants }) }} · {{ booking.total_label }}
                        </p>
                        <p class="mt-1 text-xs text-secondary">
                            <time :datetime="booking.created_at">{{ t(copy, 'created', { date: booking.created_label }) }}</time>
                        </p>
                    </Link>
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 border-t border-ink/10 px-4 py-1">
                        <Link
                            :href="booking.organization.url"
                            class="inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline"
                        >
                            {{ booking.organization.name }}
                        </Link>
                        <AccountStatusPill
                            v-if="booking.organization.status !== 'active'"
                            :status="booking.organization.status"
                            :label="booking.organization.status_label"
                        />
                    </div>
                </li>
            </ul>

            <nav
                v-if="pagination.prev_url || pagination.next_url"
                class="mt-6 flex items-center justify-between gap-3"
                :aria-label="t(copy, 'page', { page: pagination.page })"
            >
                <Link
                    v-if="pagination.prev_url"
                    :href="pagination.prev_url"
                    class="inline-flex min-h-11 items-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    ← {{ t(copy, 'prev') }}
                </Link>
                <span v-else />
                <Link
                    v-if="pagination.next_url"
                    :href="pagination.next_url"
                    class="inline-flex min-h-11 items-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    {{ t(copy, 'next') }} →
                </Link>
            </nav>
        </main>
    </AdminLayout>
</template>
