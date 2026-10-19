<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BookingRow from '../../components/BookingRow.vue';
import LinkCard from '../../components/LinkCard.vue';
import QuickEditSheet from '../../components/QuickEditSheet.vue';
import SeatBar from '../../components/SeatBar.vue';
import StatusBadge from '../../components/StatusBadge.vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { BOOKING_STATUS_STYLES } from '../../lib/booking.js';
import { dayMonth } from '../../lib/dashboard.js';
import { t } from '../../lib/text.js';

/**
 * "Hari ini" (F-007 section B, mockups 1e/2f). Phone first: header, Perlu
 * tindakan, Keberangkatan terdekat, Pesanan terbaru, link card (Q1, AC-6);
 * two columns from 1024 px. "+ Trip baru" is the one gold button (AC-5);
 * while the quick-edit sheet is open its "Simpan" is (AC-13).
 */
const props = defineProps({
    greeting: { type: Object, required: true },
    organization: { type: Object, required: true },
    // eslint-disable-next-line vue/prop-name-casing
    public_url: { type: String, required: true },
    // eslint-disable-next-line vue/prop-name-casing
    action_needed: { type: Object, required: true },
    recent: { type: Array, required: true },
    departures: { type: Array, required: true },
    context: { type: Object, required: true },
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const calendar = computed(() => props.copy.trips.calendar);
const statusWords = computed(() => props.copy.status_words);
const selectedId = ref(null);
const selected = computed(() => props.departures.find((departure) => departure.id === selectedId.value) ?? null);
const presets = ['same_day', 'h1', 'h3', 'h7', 'custom'];
const legend = [
    { key: 'paid', glyph: BOOKING_STATUS_STYLES.paid.glyph, className: 'bg-status-paid-tint border-status-paid' },
    { key: 'held', glyph: BOOKING_STATUS_STYLES.awaiting_payment.glyph, className: 'bg-status-waiting-tint border-status-waiting' },
    { key: 'empty', glyph: '○', className: 'bg-surface border-ink/30' },
];

// AC-13: after a quick edit (saved or refused) the rows show the current numbers.
function refresh() {
    router.reload({ only: ['departures', 'action_needed', 'recent'] });
}
</script>

<template>
    <Head :title="t(copy, 'title')" />
    <SellerLayout>
        <div class="mx-auto max-w-5xl px-4 py-6">
            <header
                data-section="header"
                class="flex flex-wrap items-start justify-between gap-3"
            >
                <div class="min-w-0">
                    <p class="text-sm text-secondary">
                        {{ greeting.date }}
                    </p>
                    <h1 class="font-heading text-xl font-semibold text-ink">
                        {{ greeting.text }}
                    </h1>
                    <p class="mt-1 flex flex-wrap items-center gap-2 text-sm text-ink">
                        <span class="truncate">{{ organization.name }}</span>
                        <span class="rounded-full border border-ink/20 bg-surface px-2 py-0.5 text-xs font-semibold text-ink">{{ organization.plan_label }}</span>
                    </p>
                </div>
                <Link
                    :href="urls.new_trip"
                    class="inline-flex min-h-12 items-center rounded-lg bg-prada px-4 font-heading text-sm font-semibold text-ink"
                >
                    {{ t(copy, 'new_trip') }}
                </Link>
            </header>

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <section
                    data-section="action_needed"
                    class="min-w-0 lg:col-start-1"
                    aria-labelledby="action-needed-title"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2
                            id="action-needed-title"
                            class="font-heading text-base font-semibold text-ink"
                        >
                            {{ t(copy, 'action_needed.title', { n: action_needed.count }) }}
                        </h2>
                        <Link
                            v-if="action_needed.count > 0"
                            :href="urls.action_needed"
                            class="inline-flex min-h-11 items-center text-sm text-sogan underline"
                        >
                            {{ t(copy, 'action_needed.all') }}
                        </Link>
                    </div>
                    <p
                        v-if="!action_needed.items.length"
                        class="mt-2 rounded-xl border border-ink/10 bg-surface p-4 text-sm text-ink"
                    >
                        <span aria-hidden="true">✓</span> {{ t(copy, 'action_needed.empty') }}
                    </p>
                    <ul
                        v-else
                        class="mt-2 flex flex-col gap-2"
                    >
                        <li
                            v-for="booking in action_needed.items"
                            :key="booking.public_id"
                        >
                            <BookingRow
                                :booking="booking"
                                variant="action"
                                :context="context"
                                :copy="copy.bookings"
                                :status-words="statusWords"
                                :calendar="calendar"
                            />
                        </li>
                    </ul>
                </section>

                <section
                    data-section="departures"
                    class="min-w-0 lg:col-start-2 lg:row-span-2 lg:row-start-1"
                    aria-labelledby="departures-title"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2
                            id="departures-title"
                            class="font-heading text-base font-semibold text-ink"
                        >
                            {{ t(copy, 'departures.title') }}
                        </h2>
                        <Link
                            :href="urls.departures"
                            class="inline-flex min-h-11 items-center text-sm text-sogan underline"
                        >
                            {{ t(copy, 'departures.all') }}
                        </Link>
                    </div>
                    <ul
                        v-if="departures.length"
                        class="mt-1 flex flex-wrap gap-x-3 text-xs text-ink"
                        :aria-hidden="true"
                    >
                        <li
                            v-for="item in legend"
                            :key="item.key"
                            class="inline-flex items-center gap-1"
                        >
                            <span
                                class="inline-block h-3 w-3 rounded-sm border"
                                :class="item.className"
                            />
                            <span>{{ item.glyph }} {{ t(copy, `departures.legend.${item.key}`) }}</span>
                        </li>
                    </ul>
                    <div
                        v-if="!departures.length"
                        class="mt-2 rounded-xl border border-ink/10 bg-surface p-4 text-sm text-ink"
                    >
                        <p>{{ t(copy, 'departures.empty') }}</p>
                        <Link
                            :href="urls.trips"
                            class="mt-1 inline-flex min-h-11 items-center text-sm text-sogan underline"
                        >
                            {{ t(copy, 'departures.add') }}
                        </Link>
                    </div>
                    <ul
                        v-else
                        class="mt-2 flex flex-col gap-2"
                    >
                        <li
                            v-for="departure in departures"
                            :key="departure.id"
                            class="rounded-xl border border-ink/10 bg-surface p-4"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 truncate font-heading text-sm font-semibold text-ink">
                                    {{ departure.trip.name }} · {{ dayMonth(departure.start_date, context.today, calendar) }}
                                </p>
                                <StatusBadge
                                    v-if="departure.display_status === 'closed' || departure.display_status === 'full'"
                                    :status="departure.display_status"
                                    :label="t(copy.trips, `status.${departure.display_status}`)"
                                />
                            </div>
                            <SeatBar
                                class="mt-2"
                                :seats="departure.seats"
                                :closes-on="departure.closes_on"
                                :today="context.today"
                                :copy="copy"
                                :calendar="calendar"
                            />
                            <button
                                type="button"
                                class="mt-2 min-h-11 rounded-lg border-2 border-ink px-3 font-heading text-sm font-semibold text-ink"
                                @click="selectedId = departure.id"
                            >
                                {{ t(copy, 'departures.quick_edit') }}
                            </button>
                        </li>
                    </ul>
                </section>

                <section
                    data-section="recent"
                    class="min-w-0 lg:col-start-1"
                    aria-labelledby="recent-title"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h2
                            id="recent-title"
                            class="font-heading text-base font-semibold text-ink"
                        >
                            {{ t(copy, 'recent.title') }}
                        </h2>
                        <Link
                            :href="urls.bookings"
                            class="inline-flex min-h-11 items-center text-sm text-sogan underline"
                        >
                            {{ t(copy, 'recent.all') }}
                        </Link>
                    </div>
                    <p
                        v-if="!recent.length"
                        class="mt-2 rounded-xl border border-ink/10 bg-surface p-4 text-sm text-ink"
                    >
                        {{ t(copy, 'recent.empty') }}
                    </p>
                    <ul
                        v-else
                        class="mt-2 flex flex-col gap-2"
                    >
                        <li
                            v-for="booking in recent"
                            :key="booking.public_id"
                        >
                            <BookingRow
                                :booking="booking"
                                variant="recent"
                                :context="context"
                                :copy="copy.bookings"
                                :status-words="statusWords"
                                :calendar="calendar"
                            />
                        </li>
                    </ul>
                </section>

                <div
                    data-section="link"
                    class="min-w-0 lg:col-start-2"
                >
                    <LinkCard
                        :url="public_url"
                        :view-url="urls.public_page"
                        :copy="copy.link"
                    />
                </div>
            </div>
        </div>

        <QuickEditSheet
            v-if="selected"
            :key="selected.id"
            :departure="selected"
            :context="context"
            :presets="presets"
            :reload="['flash']"
            :copy="copy.trips"
            @saved="refresh"
            @failed="refresh"
            @close="selectedId = null"
        />
    </SellerLayout>
</template>
