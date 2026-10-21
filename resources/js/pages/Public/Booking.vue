<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
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
import { copyText, digitsOnly, progressSteps } from '../../lib/payment.js';
import { t } from '../../lib/text.js';

/**
 * Customer booking page rakyt.id/<slug>/pesanan/<token> (F-005 section E +
 * F-006 payment screen, mockups 1d/2e/3e). The gold CTA is "Unggah bukti
 * transfer"; WhatsApp stays secondary.
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
    payment: { type: Object, default: () => ({ methods: [] }) },
    // eslint-disable-next-line vue/prop-name-casing
    amount_due: { type: Number, default: 0 },
    evidence: { type: Object, default: null },
    // eslint-disable-next-line vue/prop-name-casing
    reject_reason: { type: String, default: null },
    progress: { type: Number, default: null },
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

// F-006 payment screen.
const paymentCopy = computed(() => props.copy.payment ?? {});
const payable = computed(() => counting.value);
const hasMethods = computed(() => (props.payment.methods ?? []).length > 0);
const showInstructions = computed(() => ['awaiting_payment', 'needs_review'].includes(status.value));
const canUpload = computed(() => hasMethods.value && (payable.value || status.value === 'needs_review'));
const steps = computed(() => progressSteps(props.progress, paymentCopy.value));
const showProgress = computed(() => props.progress != null);
const copied = ref(null);

async function copyValue(kind, value) {
    if (await copyText(digitsOnly(value))) {
        copied.value = kind;
        window.setTimeout(() => (copied.value = copied.value === kind ? null : copied.value), 2000);
    }
}

const form = useForm({ bukti: null });
const fileInput = ref(null);

function submitEvidence(event) {
    const file = event.target.files?.[0] ?? null;

    if (!file || form.processing) {
        return;
    }

    form.bukti = file;
    form.post(props.urls.evidence_upload, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            form.reset('bukti');
            if (fileInput.value) {
                fileInput.value.value = '';
            }
        },
    });
}
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

        <!-- F-006: 3-step progress (AC-4). -->
        <section
            v-if="showProgress"
            class="mt-6"
            :aria-label="t(copy, 'payment.title')"
        >
            <ol class="flex gap-2">
                <li
                    v-for="step in steps"
                    :key="step.key"
                    class="flex-1 text-center"
                >
                    <span
                        class="block h-1.5 rounded-full"
                        :class="step.state === 'idle' ? 'bg-ink/15' : 'bg-status-paid'"
                        aria-hidden="true"
                    />
                    <span
                        class="mt-1 block text-xs"
                        :class="step.state === 'active' ? 'font-semibold text-ink' : 'text-secondary'"
                    >{{ step.label }}</span>
                </li>
            </ol>
        </section>

        <!-- AC-8: evidence under review. -->
        <section
            v-if="booking.status === 'needs_review'"
            class="mt-6 rounded-xl border border-ink/15 bg-surface p-3"
            role="status"
        >
            <p class="text-sm text-ink">
                {{ t(copy, 'payment.under_review') }}
            </p>
            <a
                v-if="evidence"
                :href="evidence.thumb_url"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-2 inline-flex min-h-11 items-center"
            >
                <img
                    :src="evidence.thumb_url"
                    :alt="t(copy, 'payment.evidence_label')"
                    class="h-28 w-28 rounded-lg border border-ink/10 object-cover"
                >
            </a>
        </section>

        <!-- AC-9: paid. -->
        <section
            v-else-if="booking.status === 'paid'"
            class="mt-6 rounded-xl border border-status-paid/30 bg-status-paid-tint p-3"
            role="status"
        >
            <p class="text-sm font-semibold text-status-paid">
                {{ t(copy, 'payment.paid', { trip: trip.name }) }}
            </p>
        </section>

        <!-- AC-10: rejected. -->
        <section
            v-else-if="booking.status === 'rejected'"
            class="mt-6 rounded-xl border border-ink/15 bg-surface p-3"
            role="status"
        >
            <p class="text-sm text-ink">
                {{ t(copy, 'payment.rejected') }}
            </p>
            <p
                v-if="reject_reason"
                class="mt-1 text-sm text-secondary [overflow-wrap:anywhere]"
            >
                {{ t(copy, 'payment.rejected_reason', { reason: reject_reason }) }}
            </p>
            <a
                v-if="trip.url"
                :href="trip.url"
                class="mt-1 inline-flex min-h-11 items-center text-sm font-semibold text-ink underline underline-offset-2"
            >
                {{ t(copy, 'page.book_again') }}
            </a>
        </section>

        <!-- AC-1–AC-7: payment instructions while payable or under review. -->
        <section
            v-if="showInstructions"
            class="mt-6"
        >
            <h2 class="font-heading text-base font-semibold text-ink">
                {{ t(copy, 'payment.title') }}
            </h2>
            <p class="mt-1 text-sm text-secondary [overflow-wrap:anywhere]">
                {{ t(copy, 'payment.direct_notice', { seller: organization.name }) }}
            </p>

            <template v-if="hasMethods">
                <div class="mt-3 rounded-xl border border-ink/15 bg-surface p-3">
                    <p class="text-xs text-secondary">
                        {{ t(copy, 'payment.amount_label') }}
                    </p>
                    <div class="flex items-center justify-between gap-3">
                        <p class="font-heading text-xl font-semibold text-ink">
                            {{ rupiah(amount_due) }}
                        </p>
                        <button
                            type="button"
                            class="min-h-11 min-w-11 rounded-lg border-2 border-ink px-3 font-heading text-sm font-semibold text-ink"
                            :aria-label="t(copy, 'payment.copy_amount')"
                            @click="copyValue('amount', amount_due)"
                        >
                            {{ t(copy, 'payment.copy') }}
                        </button>
                    </div>
                    <p
                        v-if="copied === 'amount'"
                        class="mt-1 text-xs font-semibold text-status-paid"
                        aria-live="polite"
                    >
                        {{ t(copy, 'payment.copied_amount') }}
                    </p>
                </div>

                <div
                    v-for="(method, i) in payment.methods"
                    :key="i"
                    class="mt-3 rounded-xl border border-ink/15 bg-surface p-3"
                >
                    <template v-if="method.type === 'qris'">
                        <p class="text-xs text-secondary">
                            {{ t(copy, 'payment.qris_label') }}
                        </p>
                        <img
                            :src="method.qris_image_url"
                            :alt="t(copy, 'payment.qris_alt')"
                            class="mt-2 h-48 w-48 max-w-full rounded-lg border border-ink/10 object-contain"
                        >
                    </template>
                    <template v-else>
                        <p class="font-heading font-semibold text-ink">
                            {{ method.provider_label }}
                        </p>
                        <div class="mt-1 flex items-center justify-between gap-3">
                            <p class="font-heading text-lg tracking-wide text-ink [overflow-wrap:anywhere]">
                                {{ method.account_number }}
                            </p>
                            <button
                                type="button"
                                class="min-h-11 min-w-11 shrink-0 rounded-lg border-2 border-ink px-3 font-heading text-sm font-semibold text-ink"
                                :aria-label="t(copy, 'payment.copy_number')"
                                @click="copyValue('number-' + i, method.account_number)"
                            >
                                {{ t(copy, 'payment.copy') }}
                            </button>
                        </div>
                        <p
                            v-if="copied === 'number-' + i"
                            class="mt-1 text-xs font-semibold text-status-paid"
                            aria-live="polite"
                        >
                            {{ t(copy, 'payment.copied_number') }}
                        </p>
                        <p class="mt-2 text-sm text-ink [overflow-wrap:anywhere]">
                            {{ method.account_holder }}
                        </p>
                        <p class="text-xs text-secondary">
                            {{ t(copy, 'payment.account_holder_hint') }}
                        </p>
                    </template>
                </div>
            </template>

            <p
                v-else
                class="mt-3 rounded-lg bg-status-waiting-tint px-3 py-2 text-sm text-status-waiting"
            >
                {{ t(copy, 'payment.no_methods') }}
            </p>

            <template v-if="canUpload">
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/*"
                    class="sr-only"
                    @change="submitEvidence"
                >
                <button
                    type="button"
                    class="mt-4 min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                    :disabled="form.processing"
                    :aria-busy="form.processing"
                    @click="fileInput?.click()"
                >
                    {{ form.processing ? t(copy, 'payment.uploading') : (booking.status === 'needs_review' ? t(copy, 'payment.replace_cta') : t(copy, 'payment.upload_cta')) }}
                </button>
                <p
                    v-if="errors.bukti"
                    class="mt-1 text-sm text-status-rejected"
                >
                    {{ errors.bukti }}
                </p>
            </template>
        </section>

        <a
            v-if="organization.whatsapp_url"
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
