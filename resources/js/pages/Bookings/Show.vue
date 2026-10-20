<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import BookingStatusPill from '../../components/BookingStatusPill.vue';
import BottomSheet from '../../components/BottomSheet.vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { dateLine, meetingLine } from '../../lib/booking.js';
import { createdLong, dayMonth, holdActive, holdUntil, phoneDisplay } from '../../lib/dashboard.js';
import { formatRupiah } from '../../lib/money.js';
import { copyLink } from '../../lib/onboarding.js';
import { t } from '../../lib/text.js';

/**
 * Booking detail (F-007 section D) and the seller's cancellation (section E).
 * The customer link carries the access token: it is only on this page (AC-24).
 * Nothing here is gold: cancelling is destructive (Ditolak-styled, AC-27/28).
 */
const props = defineProps({
    booking: { type: Object, required: true },
    trip: { type: Object, required: true },
    meeting: { type: Object, required: true },
    whatsapp: { type: Object, required: true },
    context: { type: Object, required: true },
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const calendar = computed(() => props.copy.calendar);
const b = computed(() => props.booking);
const rupiah = (n) => `${t(props.copy, 'currency')}${formatRupiah(n)}`;
const created = computed(() => createdLong(b.value.created_at, props.context.now, props.context.timezone, calendar.value));
const dates = computed(() => dateLine(b.value.departure.start_date, b.value.departure.end_date, props.context.today, calendar.value));
const meetingText = computed(() => meetingLine(props.meeting));
const hold = computed(() => {
    if (!holdActive(b.value, props.context.now)) {
        return null;
    }

    const until = holdUntil(b.value.hold_expires_at, props.context.now, props.context.timezone);

    return t(props.copy, `show.${until.key}`, { time: until.time });
});

const linkState = ref(null);
const copying = ref(false);

async function copyCustomerLink() {
    if (copying.value || !b.value.customer_url) {
        return;
    }

    copying.value = true;
    linkState.value = await copyLink(b.value.customer_url);
    copying.value = false;
}

const confirmOpen = ref(false);
const cancelling = ref(false);
const cancelFailed = ref(false);

// F-006: inline payment verification (AC-16–AC-18).
const confirmPayOpen = ref(false);
const rejectOpen = ref(false);
const verifying = ref(false);
const verifyFailed = ref(false);
const rejectReason = ref('');

function verify(url, data, onOk) {
    if (verifying.value) {
        return;
    }

    verifying.value = true;
    verifyFailed.value = false;
    router.post(url, data, {
        preserveScroll: true,
        onSuccess: onOk,
        onNetworkError: () => {
            verifyFailed.value = true;

            return false;
        },
        onHttpException: () => {
            verifyFailed.value = true;

            return false;
        },
        onFinish: () => (verifying.value = false),
    });
}

function confirmPayment() {
    verify(props.urls.confirm, {}, () => (confirmPayOpen.value = false));
}

function rejectPayment() {
    verify(props.urls.reject, { alasan: rejectReason.value }, () => (rejectOpen.value = false));
}

// AC-29/AC-30/AC-38: the response goes back to this page; a failed request keeps the sheet open.
function cancel() {
    if (cancelling.value) {
        return;
    }

    cancelling.value = true;
    cancelFailed.value = false;
    router.post(props.urls.cancel, {}, {
        preserveScroll: true,
        onSuccess: () => (confirmOpen.value = false),
        onNetworkError: () => {
            cancelFailed.value = true;

            return false;
        },
        onHttpException: () => {
            cancelFailed.value = true;

            return false;
        },
        onFinish: () => (cancelling.value = false),
    });
}
</script>

<template>
    <Head :title="t(copy, 'show.title', { reference: booking.reference })" />
    <SellerLayout>
        <div class="mx-auto max-w-2xl px-4 py-6">
            <Link
                :href="urls.back"
                class="inline-flex min-h-11 items-center text-sm text-sogan underline"
                :aria-label="t(copy, 'show.back_label')"
            >
                {{ t(copy, 'show.back') }}
            </Link>

            <header class="mt-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="break-all font-heading text-2xl font-semibold text-ink">
                        {{ booking.reference }}
                    </h1>
                    <BookingStatusPill
                        :status="booking.status"
                        :label="copy.status_words[booking.status] ?? booking.status"
                    />
                </div>
                <p class="mt-1 text-sm text-secondary">
                    {{ t(copy, 'show.created', { date: created }) }}
                </p>
                <p
                    v-if="hold"
                    class="mt-1 text-sm font-semibold text-status-waiting"
                >
                    <span aria-hidden="true">◷</span> {{ hold }}
                </p>
                <p
                    v-if="booking.status === 'cancelled' && booking.cancel_reason"
                    class="mt-1 text-sm text-status-cancelled"
                >
                    <span aria-hidden="true">–</span> {{ t(copy, `show.cancel_reasons.${booking.cancel_reason}`) }}
                </p>
            </header>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                <a
                    v-if="whatsapp.reminder_url"
                    :href="whatsapp.reminder_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    {{ t(copy, 'show.remind') }}
                </a>
                <a
                    v-if="whatsapp.cancelled_url"
                    :href="whatsapp.cancelled_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    {{ t(copy, 'show.notify_cancelled') }}
                </a>
                <a
                    :href="whatsapp.chat_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    {{ t(copy, 'show.chat') }}
                </a>
                <button
                    v-if="booking.customer_url"
                    type="button"
                    class="inline-flex min-h-11 items-center justify-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink disabled:text-secondary"
                    :disabled="copying"
                    :aria-busy="copying ? 'true' : 'false'"
                    @click="copyCustomerLink"
                >
                    {{ copying ? '…' : t(copy, 'show.copy_link') }}
                </button>
            </div>
            <p
                class="mt-1 text-xs"
                :class="linkState === 'failed' ? 'text-status-rejected' : 'text-status-paid'"
                aria-live="polite"
            >
                <template v-if="linkState === 'copied'">
                    ✓ {{ t(copy, 'show.copied') }}
                </template>
                <template v-else-if="linkState === 'failed'">
                    {{ t(copy, 'show.copy_failed') }}
                </template>
            </p>

            <section class="mt-4 rounded-xl border border-ink/10 bg-surface p-4">
                <h2 class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                    {{ t(copy, 'show.customer') }}
                </h2>
                <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                    <dt class="text-secondary">
                        {{ t(copy, 'show.name') }}
                    </dt>
                    <dd class="break-words text-ink">
                        {{ booking.customer_name }}
                    </dd>
                    <dt class="text-secondary">
                        {{ t(copy, 'show.phone') }}
                    </dt>
                    <dd class="text-ink">
                        {{ phoneDisplay(booking.customer_phone) }}
                    </dd>
                    <dt class="text-secondary">
                        {{ t(copy, 'show.notes') }}
                    </dt>
                    <dd class="whitespace-pre-line break-words text-ink">
                        {{ booking.notes || t(copy, 'show.none') }}
                    </dd>
                </dl>
            </section>

            <section class="mt-3 rounded-xl border border-ink/10 bg-surface p-4">
                <h2 class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                    {{ t(copy, 'show.trip') }}
                </h2>
                <Link
                    :href="trip.edit_url"
                    class="mt-2 inline-flex min-h-11 items-center font-heading text-base font-semibold text-sogan underline"
                >
                    {{ trip.name }}
                </Link>
                <p class="text-sm text-ink">
                    {{ dates }}
                </p>
                <p
                    v-if="meetingText"
                    class="mt-1 text-sm text-secondary"
                >
                    {{ t(copy, 'show.meeting') }}: {{ meetingText }}
                </p>
            </section>

            <section class="mt-3 rounded-xl border border-ink/10 bg-surface p-4">
                <h2 class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                    {{ t(copy, 'show.payment') }}
                </h2>
                <p class="mt-2 text-sm text-ink">
                    {{ t(copy, 'show.line', { n: booking.participants, price: rupiah(booking.unit_price) }) }}
                </p>
                <p class="mt-1 font-heading text-lg font-semibold text-ink">
                    {{ t(copy, 'show.total', { amount: rupiah(booking.total_amount) }) }}
                </p>

                <!-- F-006: uploaded evidence (AC-16). -->
                <div
                    v-if="booking.payment.has_evidence"
                    class="mt-3"
                >
                    <p class="text-xs text-secondary">
                        {{ t(copy, 'show.evidence') }}
                    </p>
                    <a
                        :href="booking.payment.evidence_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-1 inline-block"
                        :aria-label="t(copy, 'show.evidence_open')"
                    >
                        <img
                            :src="booking.payment.evidence_url"
                            :alt="t(copy, 'show.evidence')"
                            class="h-32 w-32 rounded-lg border border-ink/10 object-cover"
                        >
                    </a>
                </div>
                <p
                    v-if="booking.status === 'rejected' && booking.payment.reject_reason"
                    class="mt-2 text-sm text-secondary [overflow-wrap:anywhere]"
                >
                    {{ t(copy, 'show.rejected_reason', { reason: booking.payment.reject_reason }) }}
                </p>

                <!-- AC-16–AC-18: Konfirmasi / Tolak inline (ink / ink-outline, never gold). -->
                <div
                    v-if="booking.can_verify"
                    class="mt-4 flex gap-2"
                >
                    <button
                        type="button"
                        class="min-h-11 flex-1 rounded-lg bg-ink px-4 font-heading text-sm font-semibold text-surface"
                        @click="confirmPayOpen = true"
                    >
                        {{ t(copy, 'show.confirm') }}
                    </button>
                    <button
                        type="button"
                        class="min-h-11 flex-1 rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                        @click="rejectOpen = true"
                    >
                        {{ t(copy, 'show.reject') }}
                    </button>
                </div>
                <a
                    v-if="whatsapp.rejected_url"
                    :href="whatsapp.rejected_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="mt-3 inline-flex min-h-11 items-center justify-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    {{ t(copy, 'show.notify_rejected') }}
                </a>
            </section>

            <button
                v-if="booking.can_cancel"
                type="button"
                class="mt-6 min-h-11 w-full rounded-lg border-2 border-status-rejected bg-surface px-4 font-heading text-sm font-semibold text-status-rejected sm:w-auto"
                @click="confirmOpen = true"
            >
                {{ t(copy, 'cancel.action') }}
            </button>
        </div>

        <BottomSheet
            v-if="confirmOpen"
            :title="t(copy, 'cancel.title', { reference: booking.reference })"
            :close-label="t(copy, 'cancel.close')"
            @close="confirmOpen = false"
        >
            <div class="flex flex-col gap-2 text-sm text-ink">
                <p>{{ t(copy, 'cancel.seats', { n: booking.participants, date: dayMonth(booking.departure.start_date, context.today, calendar) }) }}</p>
                <p>{{ t(copy, 'cancel.no_notice') }}</p>
                <p
                    v-if="booking.status === 'paid'"
                    class="rounded-lg bg-status-waiting-tint p-3 text-status-waiting"
                >
                    {{ t(copy, 'cancel.paid') }}
                </p>
                <p
                    v-if="cancelFailed"
                    class="text-status-rejected"
                    role="alert"
                >
                    {{ t(copy, 'cancel.failed') }}
                </p>
            </div>
            <div class="mt-4 flex flex-col gap-2">
                <button
                    type="button"
                    class="min-h-12 w-full rounded-lg bg-status-rejected px-4 font-heading text-base font-semibold text-surface disabled:bg-status-rejected/60"
                    :disabled="cancelling"
                    :aria-busy="cancelling ? 'true' : 'false'"
                    @click="cancel"
                >
                    {{ cancelling ? t(copy, 'cancel.sending') : t(copy, 'cancel.confirm') }}
                </button>
                <button
                    type="button"
                    class="min-h-12 w-full rounded-lg border-2 border-ink px-4 font-heading text-base font-semibold text-ink"
                    @click="confirmOpen = false"
                >
                    {{ t(copy, 'cancel.back') }}
                </button>
            </div>
        </BottomSheet>

        <!-- AC-17: confirm payment. -->
        <BottomSheet
            v-if="confirmPayOpen"
            :title="t(copy, 'show.confirm_title', { reference: booking.reference })"
            :close-label="t(copy, 'cancel.close')"
            @close="confirmPayOpen = false"
        >
            <div class="flex flex-col gap-2 text-sm text-ink">
                <p>{{ t(copy, 'show.confirm_body') }}</p>
                <p
                    v-if="verifyFailed"
                    class="text-status-rejected"
                    role="alert"
                >
                    {{ t(copy, 'show.verify_failed') }}
                </p>
            </div>
            <div class="mt-4 flex flex-col gap-2">
                <button
                    type="button"
                    class="min-h-12 w-full rounded-lg bg-ink px-4 font-heading text-base font-semibold text-surface disabled:bg-ink/60"
                    :disabled="verifying"
                    :aria-busy="verifying ? 'true' : 'false'"
                    @click="confirmPayment"
                >
                    {{ verifying ? t(copy, 'show.sending') : t(copy, 'show.confirm_action') }}
                </button>
                <button
                    type="button"
                    class="min-h-12 w-full rounded-lg border-2 border-ink px-4 font-heading text-base font-semibold text-ink"
                    @click="confirmPayOpen = false"
                >
                    {{ t(copy, 'show.cancel_dialog') }}
                </button>
            </div>
        </BottomSheet>

        <!-- AC-18: reject payment with an optional reason. -->
        <BottomSheet
            v-if="rejectOpen"
            :title="t(copy, 'show.reject_title', { reference: booking.reference })"
            :close-label="t(copy, 'cancel.close')"
            @close="rejectOpen = false"
        >
            <div class="flex flex-col gap-2 text-sm text-ink">
                <p>{{ t(copy, 'show.reject_body', { n: booking.participants }) }}</p>
                <label class="mt-1 block">
                    <span class="text-xs text-secondary">{{ t(copy, 'show.reject_reason') }}</span>
                    <textarea
                        v-model="rejectReason"
                        maxlength="200"
                        rows="2"
                        class="mt-1 w-full rounded-lg border border-ink/20 p-2 text-sm text-ink"
                        :placeholder="t(copy, 'show.reject_reason_placeholder')"
                    />
                </label>
                <p
                    v-if="verifyFailed"
                    class="text-status-rejected"
                    role="alert"
                >
                    {{ t(copy, 'show.verify_failed') }}
                </p>
            </div>
            <div class="mt-4 flex flex-col gap-2">
                <button
                    type="button"
                    class="min-h-12 w-full rounded-lg bg-status-rejected px-4 font-heading text-base font-semibold text-surface disabled:bg-status-rejected/60"
                    :disabled="verifying"
                    :aria-busy="verifying ? 'true' : 'false'"
                    @click="rejectPayment"
                >
                    {{ verifying ? t(copy, 'show.sending') : t(copy, 'show.reject_action') }}
                </button>
                <button
                    type="button"
                    class="min-h-12 w-full rounded-lg border-2 border-ink px-4 font-heading text-base font-semibold text-ink"
                    @click="rejectOpen = false"
                >
                    {{ t(copy, 'show.cancel_dialog') }}
                </button>
            </div>
        </BottomSheet>
    </SellerLayout>
</template>
