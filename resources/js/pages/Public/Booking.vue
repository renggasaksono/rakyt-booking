<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicFooter from '../../components/public/PublicFooter.vue';
import {
    BOOKING_STATUS_STYLES,
    clockOffset,
    countdown,
    dateLine,
    formatPhone,
    holdExpired,
    localParts,
    releaseTime,
    remainingMs,
} from '../../lib/booking.js';
import { formatRupiah } from '../../lib/money.js';
import { t } from '../../lib/text.js';

/**
 * Customer booking page rakyt.id/<slug>/pesanan/<token> (F-005 section E,
 * top half of mockups 1d/2e). No gold button until F-006 adds "Unggah bukti
 * transfer" (AC-33); F-006 also adds payment instructions and the steps.
 */
const props = defineProps({
    title: { type: String, required: true },
    organization: { type: Object, required: true },
    booking: { type: Object, required: true },
    trip: { type: Object, required: true },
    departure: { type: Object, required: true },
    meeting: { type: Object, required: true },
    context: { type: Object, required: true },
    copy: { type: Object, required: true },
    calendar: { type: Object, required: true },
    urls: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

// AC-30: the server's clock, carried forward by the phone's.
const offset = clockOffset(props.context.now, Date.now());
const left = ref(remainingMs(props.booking.hold_expires_at, Date.now(), offset));
let timer = null;

onMounted(() => {
    timer = window.setInterval(() => {
        left.value = remainingMs(props.booking.hold_expires_at, Date.now(), offset);

        if (left.value <= 0) {
            window.clearInterval(timer);
        }
    }, 1000);
});

onBeforeUnmount(() => window.clearInterval(timer));

const rupiah = (amount) => `${t(props.copy, 'currency')}${formatRupiah(amount)}`;
const expired = computed(() => holdExpired(props.booking, left.value));
const counting = computed(() => props.booking.status === 'awaiting_payment' && !expired.value);
// AC-31: an expired hold reads "Dibatalkan" even before the job has run.
const status = computed(() => (expired.value ? 'cancelled' : props.booking.status));
const pill = computed(() => BOOKING_STATUS_STYLES[status.value] ?? BOOKING_STATUS_STYLES.cancelled);
const today = computed(() => localParts(props.context.now, props.context.timezone).date);
const dates = computed(() => dateLine(props.departure.start_date, props.departure.end_date, today.value, props.calendar));
const meetingText = computed(() => [props.meeting.point, props.meeting.time?.replace(':', '.')].filter(Boolean).join(' · '));

const countdownText = computed(() => {
    const value = countdown(left.value);

    return value === null ? '' : t(props.copy, `page.countdown.${value.key}`, { h: value.h, m: value.m });
});

const releaseText = computed(() => {
    const release = releaseTime(props.booking.hold_expires_at, props.context.now, props.context.timezone);

    return t(props.copy, `page.${release.key}`, { time: release.time });
});
</script>

<template>
    <Head :title="title" />
    <main class="mx-auto max-w-md px-4 pb-10">
        <nav class="flex h-14 items-center">
            <span class="truncate text-sm text-secondary">{{ organization.name }}</span>
        </nav>

        <header>
            <h1 class="font-heading text-2xl font-semibold leading-tight text-ink">
                {{ t(copy, 'page.sent') }}
            </h1>
            <p class="mt-3 text-xs text-secondary">
                {{ t(copy, 'page.reference') }}
            </p>
            <p class="font-heading text-2xl font-semibold tracking-wide text-ink [overflow-wrap:anywhere]">
                {{ booking.reference }}
            </p>
            <p
                class="mt-2 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold"
                :class="pill.className"
            >
                <span aria-hidden="true">{{ pill.glyph }}</span>
                {{ t(copy, `status.${status}`) }}
            </p>
        </header>

        <section
            v-if="counting"
            class="mt-4 rounded-xl border border-ink/15 bg-surface p-3"
            aria-live="polite"
        >
            <p class="text-xs text-secondary">
                {{ t(copy, 'page.countdown_label') }}
            </p>
            <p class="font-heading text-xl font-semibold text-status-waiting">
                {{ countdownText }}
            </p>
            <p class="mt-1 text-xs text-ink">
                {{ releaseText }}
            </p>
        </section>

        <section
            v-else-if="expired"
            class="mt-4 rounded-xl border border-ink/15 bg-surface p-3"
            role="status"
        >
            <p class="text-sm text-ink">
                {{ t(copy, 'page.expired') }}
            </p>
            <a
                v-if="trip.url"
                :href="trip.url"
                class="mt-1 inline-flex min-h-11 items-center text-sm font-semibold text-ink underline underline-offset-2"
            >
                {{ t(copy, 'page.book_again') }}
            </a>
        </section>

        <section class="mt-6">
            <h2 class="font-heading text-base font-semibold text-ink">
                {{ t(copy, 'page.summary') }}
            </h2>
            <div class="mt-2 rounded-xl border border-ink/15 bg-surface p-3 text-sm text-ink">
                <p class="font-heading font-semibold [overflow-wrap:anywhere]">
                    {{ trip.name }}
                </p>
                <p class="text-secondary">
                    {{ dates }}
                </p>
                <dl class="mt-3 space-y-2">
                    <div v-if="meetingText">
                        <dt class="text-xs text-secondary">
                            {{ t(copy, 'page.meeting') }}
                        </dt>
                        <dd class="[overflow-wrap:anywhere]">
                            {{ meetingText }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-secondary">
                            {{ t(copy, 'page.name') }}
                        </dt>
                        <dd class="[overflow-wrap:anywhere]">
                            {{ booking.customer_name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-secondary">
                            {{ t(copy, 'page.phone') }}
                        </dt>
                        <dd>{{ formatPhone(booking.customer_phone) }}</dd>
                    </div>
                </dl>
                <div class="mt-3 border-t border-ink/10 pt-3">
                    <p class="text-secondary">
                        {{ t(copy, 'page.line', { n: booking.participants, price: rupiah(booking.unit_price) }) }}
                    </p>
                    <p class="font-heading text-lg font-semibold">
                        {{ t(copy, 'page.total', { amount: rupiah(booking.total_amount) }) }}
                    </p>
                </div>
            </div>
        </section>

        <a
            :href="organization.whatsapp_url"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-6 inline-flex min-h-12 w-full items-center justify-center rounded-lg border border-ink px-4 font-heading text-base font-semibold text-ink"
        >
            {{ t(copy, 'page.whatsapp') }}
        </a>

        <PublicFooter
            :label="t(copy, 'footer')"
            :href="urls.home"
        />
    </main>
</template>
