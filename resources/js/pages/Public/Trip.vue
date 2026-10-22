<script setup>
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PhotoGallery from '../../components/public/PhotoGallery.vue';
import PublicFooter from '../../components/public/PublicFooter.vue';
import StickyAction from '../../components/StickyAction.vue';
import { formatRupiah } from '../../lib/money.js';
import { bookUrl, closingInfo, formatDates, formatDay, initialSelection, seatsLine, whatsappUrl } from '../../lib/publicPage.js';
import { t } from '../../lib/text.js';

/**
 * Trip page rakyt.id/<slug>/<trip> (F-002 section C, mockups 1b, 2c, 3b).
 * Exactly one gold button: "Pesan →" or "Ajukan tanggal →" (AC-37).
 */
const props = defineProps({
    title: { type: String, required: true },
    organization: { type: Object, required: true },
    trip: { type: Object, required: true },
    departures: { type: Array, required: true },
    context: { type: Object, required: true },
    rules: { type: Object, required: true },
    copy: { type: Object, required: true },
    calendar: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const scheduled = computed(() => props.trip.booking_mode === 'scheduled');
const showAll = ref(false);
const shown = computed(() => (showAll.value ? props.departures : props.departures.slice(0, props.rules.shown)));
const hiddenCount = computed(() => props.departures.length - shown.value.length);
const closing = computed(() => closingInfo(props.departures, props.context.now_local, props.rules.urgent_days));
const selectedId = ref(initialSelection(props.departures));
const selected = computed(() => props.departures.find((departure) => departure.public_id === selectedId.value) ?? null);
const kicker = computed(() => [props.trip.type_label, props.trip.duration].filter(Boolean).join(' · '));
// AC-A5: follows the selection — the dated message when a departure is picked,
// else the trip-only message; null (invalid number, AC-A6) hides the button.
const whatsappLink = computed(() => whatsappUrl(selected.value, props.organization));
// AC-A2: an ink-outline button (box-shadow inset, not a border), full width,
// min 48 px tall; never gold, never in the sticky bar.
const askWhatsappClass = 'mt-4 inline-flex min-h-12 w-full items-center justify-center gap-2 bg-surface px-4 font-heading text-sm font-semibold text-ink shadow-[inset_0_0_0_1.5px_#211710]';
const hasMeeting = computed(() => props.trip.meeting_point || props.trip.meeting_time || props.trip.map_url || props.trip.itinerary.length);
const rupiah = (amount) => `${t(props.copy, 'currency')}${formatRupiah(amount)}`;

function dates(departure) {
    return formatDates(departure.start_date, departure.end_date, props.context.today, props.calendar);
}

function seats(departure) {
    const line = seatsLine(departure.seats_left, departure.capacity, props.rules.low_seats);

    return t(props.copy, line.key, { n: line.n, m: line.m });
}

function closingNote(departure) {
    const row = closing.value.rows[departure.public_id];

    if (!row) {
        return null;
    }

    if (row.kind === 'today') {
        return t(props.copy, 'trip.closing.row_today', { time: row.time });
    }

    if (row.kind === 'tomorrow') {
        return t(props.copy, 'trip.closing.row_tomorrow', { time: row.time });
    }

    return t(props.copy, 'trip.closing.row', { date: formatDay(row.date, props.context.today, props.calendar) });
}

function select(departure) {
    if (departure.state === 'bookable') {
        selectedId.value = departure.public_id;
    }
}

// Arrow keys move between bookable rows, like a native radio group.
function onKey(event, departure) {
    const step = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 }[event.key];

    if (!step) {
        return;
    }

    event.preventDefault();
    const bookable = shown.value.filter((row) => row.state === 'bookable');
    const index = bookable.findIndex((row) => row.public_id === departure.public_id);
    const next = bookable[index + step];

    if (next) {
        selectedId.value = next.public_id;
        document.getElementById(`keberangkatan-${next.public_id}`)?.focus();
    }
}
</script>

<template>
    <Head :title="title" />
    <main class="mx-auto max-w-md px-4 pb-36">
        <!-- Full-bleed photo banner running to the top edge, back button overlaid (mockup 1b/3b). -->
        <PhotoGallery
            v-if="trip.photos.length"
            :photos="trip.photos"
            :name="trip.name"
            :copy="copy"
        >
            <template #overlay>
                <Link
                    :href="urls.seller"
                    class="absolute left-3 top-3 z-10 inline-flex h-11 w-11 items-center justify-center bg-gading text-xl text-ink shadow"
                    :aria-label="t(copy, 'trip.back', { name: organization.name })"
                >
                    <span aria-hidden="true">←</span>
                </Link>
            </template>
        </PhotoGallery>
        <nav
            v-else
            class="flex h-14 items-center"
        >
            <Link
                :href="urls.seller"
                class="-ml-3 inline-flex min-h-11 min-w-11 items-center justify-center text-xl text-ink"
                :aria-label="t(copy, 'trip.back', { name: organization.name })"
            >
                <span aria-hidden="true">←</span>
            </Link>
            <span class="ml-1 truncate text-sm text-secondary">{{ organization.name }}</span>
        </nav>

        <header class="mt-4">
            <p class="font-heading text-[11px] font-semibold uppercase tracking-[.14em] text-sogan">
                {{ kicker }}
            </p>
            <h1 class="mt-1 font-heading text-2xl font-semibold leading-tight text-ink [overflow-wrap:anywhere]">
                {{ trip.name }}
            </h1>
            <p
                v-if="trip.short_description"
                class="mt-2 whitespace-pre-line text-sm text-ink [overflow-wrap:anywhere]"
            >
                {{ trip.short_description }}
            </p>
        </header>

        <!--
            F-004 AC-8 / ADR-0032: the ink "Tanya via WhatsApp" general-inquiry link
            is shown only on scheduled trips. On a request-date trip the single gold
            CTA is itself the WhatsApp "Ajukan tanggal →" handoff, so the two WhatsApp
            links never both appear.
        -->
        <section
            v-if="scheduled"
            class="mt-6"
            aria-labelledby="keberangkatan-judul"
        >
            <h2
                id="keberangkatan-judul"
                class="section-header"
            >
                {{ t(copy, 'trip.departures_title') }}
            </h2>
            <p
                v-if="!departures.length"
                class="mt-2 text-sm text-secondary"
            >
                {{ t(copy, 'trip.no_departures') }}
            </p>
            <div
                v-else
                role="radiogroup"
                aria-labelledby="keberangkatan-judul"
                class="mt-3 space-y-2"
            >
                <button
                    v-for="departure in shown"
                    :id="`keberangkatan-${departure.public_id}`"
                    :key="departure.public_id"
                    type="button"
                    role="radio"
                    :aria-checked="departure.public_id === selectedId ? 'true' : 'false'"
                    :aria-disabled="departure.state === 'bookable' ? undefined : 'true'"
                    :tabindex="departure.public_id === selectedId || (!selectedId && departure.state === 'bookable') ? 0 : -1"
                    class="flex min-h-14 w-full items-center gap-3 bg-surface px-3 py-2 text-left"
                    :class="departure.public_id === selectedId ? 'shadow-[inset_0_0_0_2px_#211710]' : 'shadow-[inset_0_0_0_1px_rgba(33,23,16,0.16)]'"
                    @click="select(departure)"
                    @keydown="onKey($event, departure)"
                >
                    <span
                        class="w-4 flex-none text-center text-ink"
                        aria-hidden="true"
                    >{{ departure.state !== 'bookable' ? '' : departure.public_id === selectedId ? '●' : '○' }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-heading text-sm font-semibold text-ink">{{ dates(departure) }}</span>
                        <span
                            v-if="departure.state === 'bookable'"
                            class="block text-xs text-secondary"
                        >{{ [seats(departure), closingNote(departure)].filter(Boolean).join(' ') }}</span>
                        <span
                            v-else
                            class="block text-xs text-secondary"
                        >{{ t(copy, 'trip.unavailable') }}</span>
                    </span>
                    <span
                        v-if="departure.state === 'bookable'"
                        class="flex-none font-heading text-sm font-semibold text-ink"
                    >{{ rupiah(departure.price) }}</span>
                    <span
                        v-else
                        class="flex-none text-sm text-secondary"
                    >{{ t(copy, departure.state === 'full' ? 'trip.full' : 'trip.closed') }}</span>
                </button>
            </div>
            <button
                v-if="hiddenCount > 0"
                type="button"
                class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-sogan underline underline-offset-2"
                @click="showAll = true"
            >
                {{ t(copy, 'trip.show_more', { n: hiddenCount }) }}
            </button>
            <p
                v-if="closing.summary"
                class="mt-2 text-xs text-secondary"
            >
                {{ t(copy, `trip.closing.${closing.summary}`) }}
            </p>
            <!-- F-004 AC-6: the "other date" link is the WhatsApp handoff; stays ink, opens a new tab. -->
            <a
                v-if="urls.request_date"
                :href="urls.request_date"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-2 inline-flex min-h-11 items-center text-sm text-ink underline underline-offset-2"
            >
                {{ t(copy, 'trip.other_date') }}
            </a>
        </section>

        <!-- AC-A2: directly after the departures section on scheduled trips. -->
        <a
            v-if="scheduled && whatsappLink"
            :href="whatsappLink"
            target="_blank"
            rel="noopener noreferrer"
            :class="askWhatsappClass"
        >
            {{ t(copy, 'trip.ask_whatsapp') }}
        </a>

        <section
            v-if="trip.inclusions.length"
            class="mt-6"
        >
            <h2 class="section-header">
                {{ t(copy, 'trip.sections.inclusions') }}
            </h2>
            <ul class="mt-2 space-y-1 text-sm text-ink">
                <li
                    v-for="(item, index) in trip.inclusions"
                    :key="index"
                    class="flex gap-2 [overflow-wrap:anywhere]"
                >
                    <span aria-hidden="true">✓</span><span>{{ item }}</span>
                </li>
            </ul>
        </section>

        <section
            v-if="trip.exclusions.length"
            class="mt-6"
        >
            <h2 class="section-header">
                {{ t(copy, 'trip.sections.exclusions') }}
            </h2>
            <ul class="mt-2 space-y-1 text-sm text-ink">
                <li
                    v-for="(item, index) in trip.exclusions"
                    :key="index"
                    class="flex gap-2 [overflow-wrap:anywhere]"
                >
                    <span aria-hidden="true">–</span><span>{{ item }}</span>
                </li>
            </ul>
        </section>

        <section
            v-if="hasMeeting"
            class="mt-6"
        >
            <h2 class="section-header">
                {{ t(copy, 'trip.sections.meeting') }}
            </h2>
            <p
                v-if="trip.meeting_point || trip.meeting_time"
                class="mt-2 text-sm text-ink [overflow-wrap:anywhere]"
            >
                {{ [trip.meeting_point, trip.meeting_time?.replace(':', '.')].filter(Boolean).join(' · ') }}
            </p>
            <a
                v-if="trip.map_url"
                :href="trip.map_url"
                target="_blank"
                rel="noopener noreferrer nofollow"
                class="mt-1 inline-flex min-h-11 items-center text-sm font-semibold text-sogan underline underline-offset-2"
            >
                {{ t(copy, 'trip.sections.map') }}
            </a>
            <ul
                v-if="trip.itinerary.length"
                class="mt-2 space-y-1 text-sm text-ink"
            >
                <li
                    v-for="day in trip.itinerary"
                    :key="day.day"
                    class="[overflow-wrap:anywhere]"
                >
                    <strong class="font-heading">{{ t(copy, 'trip.sections.day', { n: day.day }) }}</strong> · {{ day.text }}
                </li>
            </ul>
        </section>

        <section
            v-if="trip.terms"
            class="mt-6"
        >
            <h2 class="section-header">
                {{ t(copy, 'trip.sections.terms') }}
            </h2>
            <p class="mt-2 whitespace-pre-line text-sm text-ink [overflow-wrap:anywhere]">
                {{ trip.terms }}
            </p>
        </section>

        <PublicFooter
            :label="t(copy, 'footer')"
            :href="urls.home"
        />
    </main>

    <StickyAction>
        <div class="flex items-center gap-3">
            <div
                v-if="scheduled && selected"
                class="min-w-0 flex-1"
            >
                <p class="truncate text-xs text-secondary">
                    {{ t(copy, 'trip.bar_dates', { dates: dates(selected) }) }}
                </p>
                <p class="font-heading text-lg font-semibold text-ink">
                    {{ rupiah(selected.price) }}
                </p>
            </div>
            <div
                v-else
                class="min-w-0 flex-1"
            >
                <p class="text-xs text-secondary">
                    {{ t(copy, 'trip.bar_from') }}
                </p>
                <p class="font-heading text-lg font-semibold text-ink">
                    {{ rupiah(trip.from_price) }} <span class="text-sm font-normal text-secondary">{{ t(copy, 'trip.bar_per_person') }}</span>
                </p>
            </div>
            <a
                v-if="scheduled && selected"
                :href="bookUrl(urls.book, selected.public_id)"
                class="inline-flex min-h-12 flex-none items-center bg-prada px-5 font-heading text-base font-semibold text-ink"
            >
                {{ t(copy, 'trip.book') }}
            </a>
            <!--
                F-004 AC-8 / ADR-0032: on a request-date trip, and on a scheduled trip
                with no bookable departure, the single gold CTA is the WhatsApp "Ajukan
                tanggal →" handoff. Opens a new tab; hidden on an invalid number (AC-5).
            -->
            <a
                v-else-if="urls.request_date"
                :href="urls.request_date"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex min-h-12 flex-none items-center bg-prada px-5 font-heading text-base font-semibold text-ink"
            >
                {{ t(copy, 'trip.request_date') }}
            </a>
        </div>
    </StickyAction>
</template>
