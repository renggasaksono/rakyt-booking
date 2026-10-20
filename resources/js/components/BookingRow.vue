<script setup>
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatRupiah } from '../lib/money.js';
import { formatDates } from '../lib/publicPage.js';
import { createdLabel, dayMonth, holdActive, holdUntil } from '../lib/dashboard.js';
import { t } from '../lib/text.js';
import BookingStatusPill from './BookingStatusPill.vue';

/**
 * One booking row (F-007): Perlu tindakan (AC-7), Pesanan terbaru (AC-10) and
 * the list (AC-16). The whole row links to the detail; "Ingatkan via WA" sits
 * outside the link. Names render as text and truncate on one line.
 */
const props = defineProps({
    booking: { type: Object, required: true },
    variant: { type: String, default: 'list' },
    context: { type: Object, required: true },
    copy: { type: Object, required: true },
    statusWords: { type: Object, required: true },
    calendar: { type: Object, required: true },
});

const b = computed(() => props.booking);
const people = computed(() => t(props.copy, 'index.participants', { n: b.value.participants }));
const total = computed(() => `${t(props.copy, 'currency')}${formatRupiah(b.value.total_amount)}`);
const start = computed(() => dayMonth(b.value.departure.start_date, props.context.today, props.calendar));
const range = computed(() => formatDates(b.value.departure.start_date, b.value.departure.end_date, props.context.today, props.calendar));
const created = computed(() => createdLabel(b.value.created_at, props.context.now, props.context.timezone, props.copy, props.calendar));
const hold = computed(() => {
    if (!holdActive(b.value, props.context.now)) {
        return null;
    }

    const until = holdUntil(b.value.hold_expires_at, props.context.now, props.context.timezone);

    return t(props.copy, `index.${until.key}`, { time: until.time });
});

// F-006 AC-16: verify needs_review rows inline (reason is on the detail, optional).
const needsReview = computed(() => b.value.status === 'needs_review' && b.value.payment);
const verifying = ref(false);

function verify(url) {
    if (verifying.value || !url) {
        return;
    }

    verifying.value = true;
    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => (verifying.value = false),
    });
}
</script>

<template>
    <div class="rounded-xl border border-ink/10 bg-surface">
        <Link
            :href="b.urls.show"
            class="block min-h-11 px-4 py-3"
        >
            <span class="flex items-start justify-between gap-2">
                <span class="min-w-0 truncate font-heading text-sm font-semibold text-ink">
                    <template v-if="variant === 'action'">{{ b.customer_name }} · {{ people }}</template>
                    <template v-else>{{ b.customer_name }}</template>
                </span>
                <BookingStatusPill
                    :status="b.status"
                    :label="statusWords[b.status] ?? b.status"
                />
            </span>

            <span
                v-if="variant === 'action'"
                class="mt-1 block truncate text-xs text-secondary"
            >{{ b.reference }} · {{ b.trip.name }} · {{ start }}</span>
            <span
                v-if="variant === 'action'"
                class="mt-1 flex flex-wrap items-baseline justify-between gap-x-3 text-sm"
            >
                <span class="font-semibold text-ink">{{ total }}</span>
                <span
                    v-if="hold"
                    class="text-xs text-status-waiting"
                >{{ hold }}</span>
            </span>

            <span
                v-if="variant === 'recent'"
                class="mt-1 flex items-baseline justify-between gap-3 text-xs text-secondary"
            >
                <span class="min-w-0 truncate">{{ b.trip.name }} · {{ start }} · {{ people }}</span>
                <span class="shrink-0">{{ created }}</span>
            </span>

            <template v-if="variant === 'list'">
                <span class="mt-1 block truncate text-xs text-secondary">{{ b.reference }} · {{ b.trip.name }} · {{ range }}</span>
                <span class="mt-1 flex flex-wrap items-baseline justify-between gap-x-3 text-sm">
                    <span class="text-ink">{{ people }} · <span class="font-semibold">{{ total }}</span></span>
                    <span class="text-xs text-secondary">{{ created }}</span>
                </span>
            </template>
        </Link>

        <div
            v-if="variant === 'action' && b.whatsapp_reminder_url"
            class="border-t border-ink/10 px-4 py-2"
        >
            <a
                :href="b.whatsapp_reminder_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex min-h-11 items-center rounded-lg border-2 border-ink px-3 font-heading text-sm font-semibold text-ink"
            >
                {{ t(copy, 'show.remind') }}
            </a>
        </div>

        <!-- AC-16: needs_review rows show the evidence and Konfirmasi / Tolak inline. -->
        <div
            v-if="variant === 'action' && needsReview"
            class="flex items-center gap-3 border-t border-ink/10 px-4 py-2"
        >
            <a
                v-if="b.payment.evidence_url"
                :href="b.payment.evidence_url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex min-h-11 shrink-0 items-center"
                :aria-label="t(copy, 'show.evidence_open')"
            >
                <img
                    :src="b.payment.evidence_url"
                    :alt="t(copy, 'show.evidence')"
                    class="h-12 w-12 rounded-lg border border-ink/10 object-cover"
                >
            </a>
            <div class="flex flex-1 gap-2">
                <button
                    type="button"
                    class="min-h-11 flex-1 rounded-lg bg-ink px-3 font-heading text-sm font-semibold text-surface disabled:bg-ink/60"
                    :disabled="verifying"
                    @click="verify(b.urls.confirm)"
                >
                    {{ t(copy, 'show.confirm') }}
                </button>
                <button
                    type="button"
                    class="min-h-11 flex-1 rounded-lg border-2 border-ink px-3 font-heading text-sm font-semibold text-ink"
                    :disabled="verifying"
                    @click="verify(b.urls.reject)"
                >
                    {{ t(copy, 'show.reject') }}
                </button>
            </div>
        </div>
    </div>
</template>
