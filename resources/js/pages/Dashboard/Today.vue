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
import { SEAT_SEGMENT_CLASSES, dayMonth } from '../../lib/dashboard.js';
import { t } from '../../lib/text.js';

/**
 * "Hari ini" (F-007 section B, mockups 1e/2f). Phone first: header, Perlu
 * tindakan, Keberangkatan terdekat, Pesanan terbaru, link card (Q1, AC-6);
 * two columns from 1024 px. "+ Trip baru" is the one gold button (AC-5);
 * while the quick-edit sheet is open its "Simpan" is (AC-13).
 */
const props = defineProps({
    greeting: { type: Object, required: true },
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
    { key: 'paid', glyph: BOOKING_STATUS_STYLES.paid.glyph },
    { key: 'held', glyph: BOOKING_STATUS_STYLES.awaiting_payment.glyph },
    { key: 'empty', glyph: '○' },
];

// AC-13: after a quick edit (saved or refused) the rows show the current numbers.
function refresh() {
    router.reload({ only: ['departures', 'action_needed', 'recent'] });
}
</script>

<template>
    <Head :title="t(copy, 'title')" />
    <SellerLayout>
        <div class="mx-auto max-w-5xl px-4 pb-10 pt-8 sm:px-6 lg:px-11 lg:pt-9">
            <!-- 1e/2f: the date as a Sogan kicker over a large greeting; business and plan live in the side nav. -->
            <header
                data-section="header"
                class="flex flex-wrap items-end justify-between gap-x-6 gap-y-5"
            >
                <div class="min-w-0">
                    <p class="font-heading text-[11px] font-semibold uppercase tracking-[0.16em] text-sogan">
                        {{ greeting.date }}
                    </p>
                    <h1 class="mt-1.5 font-heading text-2xl font-semibold leading-tight tracking-[-0.02em] text-ink lg:text-[30px]">
                        {{ greeting.text }}
                    </h1>
                </div>
                <Link
                    :href="urls.new_trip"
                    class="inline-flex min-h-12 items-center bg-prada px-5.5 font-heading text-sm font-semibold text-ink lg:min-h-11"
                >
                    {{ t(copy, 'new_trip') }}
                </Link>
            </header>

            <div class="mt-8 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:gap-x-11">
                <section
                    data-section="action_needed"
                    class="min-w-0 lg:col-start-1"
                    aria-labelledby="action-needed-title"
                >
                    <div class="section-header flex items-center justify-between gap-2">
                        <h2 id="action-needed-title">
                            {{ t(copy, 'action_needed.title', { n: action_needed.count }) }}
                        </h2>
                        <Link
                            v-if="action_needed.count > 0"
                            :href="urls.action_needed"
                            class="-my-3 inline-flex min-h-11 shrink-0 items-center font-sans text-sm font-normal normal-case tracking-normal text-sogan hover:underline"
                        >
                            {{ t(copy, 'action_needed.all') }}
                        </Link>
                    </div>
                    <p
                        v-if="!action_needed.items.length"
                        class="mt-3 rounded-xl border border-ink/10 bg-surface p-4 text-sm text-ink"
                    >
                        <span aria-hidden="true">✓</span> {{ t(copy, 'action_needed.empty') }}
                    </p>
                    <ul
                        v-else
                        class="mt-3 flex flex-col gap-2"
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

                <!--
                    Desktop right column: Keberangkatan terdekat with the link card right under it, so the card
                    never waits for the left column's rows. On a phone the card comes last instead (AC-6), from
                    the lg:hidden copy below; only one copy is ever displayed.
                -->
                <div class="flex min-w-0 flex-col gap-8 lg:col-start-2 lg:row-span-2 lg:row-start-1">
                    <section
                        data-section="departures"
                        class="min-w-0"
                        aria-labelledby="departures-title"
                    >
                        <div class="section-header flex items-center justify-between gap-2">
                            <h2 id="departures-title">
                                {{ t(copy, 'departures.title') }}
                            </h2>
                            <Link
                                :href="urls.departures"
                                class="-my-3 inline-flex min-h-11 shrink-0 items-center font-sans text-sm font-normal normal-case tracking-normal text-sogan hover:underline"
                            >
                                {{ t(copy, 'departures.all') }}
                            </Link>
                        </div>
                        <ul
                            v-if="departures.length"
                            class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-secondary"
                            :aria-hidden="true"
                        >
                            <li
                                v-for="item in legend"
                                :key="item.key"
                                class="inline-flex items-center gap-1.5"
                            >
                                <span
                                    class="inline-block h-2.5 w-2.5"
                                    :class="SEAT_SEGMENT_CLASSES[item.key]"
                                />
                                <span>{{ item.glyph }} {{ t(copy, `departures.legend.${item.key}`) }}</span>
                            </li>
                        </ul>
                        <div
                            v-if="!departures.length"
                            class="mt-3 rounded-xl border border-ink/10 bg-surface p-4 text-sm text-ink"
                        >
                            <p>{{ t(copy, 'departures.empty') }}</p>
                            <Link
                                :href="urls.trips"
                                class="mt-1 inline-flex min-h-11 items-center text-sm text-sogan underline"
                            >
                                {{ t(copy, 'departures.add') }}
                            </Link>
                        </div>
                        <!-- 2f: plain rows on the page ground — title · date with "Edit cepat" on the right, the bar, its summary. -->
                        <ul
                            v-else
                            class="mt-1 flex flex-col divide-y divide-ink/10"
                        >
                            <li
                                v-for="departure in departures"
                                :key="departure.id"
                                class="py-3"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <p class="min-w-0 truncate font-heading text-[15px] font-semibold text-ink">
                                        {{ departure.trip.name }} · {{ dayMonth(departure.start_date, context.today, calendar) }}
                                    </p>
                                    <div class="flex shrink-0 items-center gap-3">
                                        <StatusBadge
                                            v-if="departure.display_status === 'closed' || departure.display_status === 'full'"
                                            :status="departure.display_status"
                                            :label="t(copy.trips, `status.${departure.display_status}`)"
                                        />
                                        <button
                                            type="button"
                                            class="-my-3 inline-flex min-h-11 items-center font-heading text-xs font-semibold text-sogan hover:underline"
                                            @click="selectedId = departure.id"
                                        >
                                            {{ t(copy, 'departures.quick_edit') }}
                                        </button>
                                    </div>
                                </div>
                                <SeatBar
                                    class="mt-2"
                                    :seats="departure.seats"
                                    :closes-on="departure.closes_on"
                                    :today="context.today"
                                    :copy="copy"
                                    :calendar="calendar"
                                />
                            </li>
                        </ul>
                    </section>

                    <LinkCard
                        class="hidden lg:block"
                        :url="public_url"
                        :view-url="urls.public_page"
                        :copy="copy.link"
                    />
                </div>

                <section
                    data-section="recent"
                    class="min-w-0 lg:col-start-1"
                    aria-labelledby="recent-title"
                >
                    <div class="section-header flex items-center justify-between gap-2">
                        <h2 id="recent-title">
                            {{ t(copy, 'recent.title') }}
                        </h2>
                        <Link
                            :href="urls.bookings"
                            class="-my-3 inline-flex min-h-11 shrink-0 items-center font-sans text-sm font-normal normal-case tracking-normal text-sogan hover:underline"
                        >
                            {{ t(copy, 'recent.all') }}
                        </Link>
                    </div>
                    <p
                        v-if="!recent.length"
                        class="mt-3 rounded-xl border border-ink/10 bg-surface p-4 text-sm text-ink"
                    >
                        {{ t(copy, 'recent.empty') }}
                    </p>
                    <ul
                        v-else
                        class="mt-3 flex flex-col gap-2"
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
                    class="min-w-0 lg:hidden"
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
