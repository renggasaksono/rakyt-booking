<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AccountStatusPill from '../../../components/AccountStatusPill.vue';
import AuditEventCard from '../../../components/AuditEventCard.vue';
import BookingStatusPill from '../../../components/BookingStatusPill.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import { formatPhone } from '../../../lib/booking.js';
import { SEAT_SEGMENT_CLASSES, seatSegments } from '../../../lib/dashboard.js';
import { t } from '../../../lib/text.js';

/**
 * One booking across sellers (F-013): seller, customer, trip and departure
 * with the seller dashboard's seat counts, amounts, payment and evidence,
 * hold expiry and the booking's audit history. Read-only — no actions, so no
 * CTA at all. Phone first: sections stack as cards; from 1024 px they sit
 * two by two.
 */
defineProps({
    booking: { type: Object, required: true },
    organization: { type: Object, required: true },
    trip: { type: Object, required: true },
    departure: { type: Object, required: true },
    payment: { type: Object, required: true },
    // eslint-disable-next-line vue/prop-name-casing
    audit_events: { type: Array, required: true },
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});
</script>

<template>
    <Head :title="booking.reference" />
    <AdminLayout>
        <main class="mx-auto max-w-4xl px-4 py-8">
            <Link
                :href="urls.back"
                class="inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline"
            >
                ← {{ t(copy, 'back') }}
            </Link>

            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                <h1 class="font-mono text-xl font-semibold text-ink [overflow-wrap:anywhere]">
                    {{ booking.reference }}
                </h1>
                <BookingStatusPill
                    :status="booking.status"
                    :label="booking.status_label"
                />
            </div>
            <p
                v-if="booking.cancel_reason_label"
                class="mt-1 text-sm text-secondary"
            >
                {{ booking.cancel_reason_label }}
            </p>
            <p class="mt-2 text-sm text-secondary">
                {{ t(copy, 'read_only') }}
            </p>

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <section
                    class="rounded-lg border border-ink/10 bg-surface p-4"
                    aria-labelledby="seller-title"
                >
                    <h2
                        id="seller-title"
                        class="font-heading text-lg font-semibold text-ink"
                    >
                        {{ t(copy, 'detail.seller') }}
                    </h2>
                    <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="font-heading text-base font-semibold text-ink">{{ organization.name }}</span>
                        <AccountStatusPill
                            :status="organization.status"
                            :label="organization.status_label"
                        />
                    </div>
                    <p class="mt-0.5 font-mono text-xs text-secondary">
                        {{ organization.slug }} · #{{ organization.id }}
                    </p>
                    <Link
                        :href="organization.url"
                        class="mt-1 inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline"
                    >
                        {{ t(copy, 'detail.seller_link') }}
                    </Link>
                </section>

                <section
                    class="rounded-lg border border-ink/10 bg-surface p-4"
                    aria-labelledby="customer-title"
                >
                    <h2
                        id="customer-title"
                        class="font-heading text-lg font-semibold text-ink"
                    >
                        {{ t(copy, 'detail.customer') }}
                    </h2>
                    <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[9rem_1fr]">
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.name') }}
                        </dt>
                        <dd class="text-ink [overflow-wrap:anywhere]">
                            {{ booking.customer_name }}
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.phone') }}
                        </dt>
                        <dd class="font-mono text-ink">
                            {{ formatPhone(booking.customer_phone) }}
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.participants') }}
                        </dt>
                        <dd class="text-ink">
                            {{ t(copy, 'participants', { count: booking.participants }) }}
                        </dd>
                    </dl>
                </section>

                <section
                    class="rounded-lg border border-ink/10 bg-surface p-4"
                    aria-labelledby="trip-title"
                >
                    <h2
                        id="trip-title"
                        class="font-heading text-lg font-semibold text-ink"
                    >
                        {{ t(copy, 'detail.trip') }}
                    </h2>
                    <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[9rem_1fr]">
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.trip_name') }}
                        </dt>
                        <dd class="text-ink">
                            {{ trip.name }}
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.departure') }}
                        </dt>
                        <dd class="text-ink">
                            <time :datetime="departure.start_date">{{ departure.date_label }}</time>
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.departure_status') }}
                        </dt>
                        <dd>
                            <StatusBadge
                                :status="departure.status"
                                :label="departure.status_label"
                            />
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.seats') }}
                        </dt>
                        <dd>
                            <div
                                class="mt-1 flex h-2 w-full gap-[3px]"
                                aria-hidden="true"
                            >
                                <span
                                    v-for="segment in seatSegments(departure.seats)"
                                    :key="segment.key"
                                    class="h-full basis-0"
                                    :class="SEAT_SEGMENT_CLASSES[segment.key]"
                                    :style="{ flexGrow: segment.n }"
                                />
                            </div>
                            <p class="mt-1 text-xs text-secondary">
                                {{ t(copy, 'seats', departure.seats) }}
                            </p>
                        </dd>
                    </dl>
                </section>

                <section
                    class="rounded-lg border border-ink/10 bg-surface p-4"
                    aria-labelledby="booking-title"
                >
                    <h2
                        id="booking-title"
                        class="font-heading text-lg font-semibold text-ink"
                    >
                        {{ t(copy, 'detail.booking') }}
                    </h2>
                    <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[9rem_1fr]">
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.created') }}
                        </dt>
                        <dd class="text-ink">
                            <time :datetime="booking.created_at">{{ booking.created_label }}</time>
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.unit_price') }}
                        </dt>
                        <dd class="text-ink">
                            {{ booking.unit_price_label }}
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.total') }}
                        </dt>
                        <dd class="font-heading font-semibold text-ink">
                            {{ booking.total_label }}
                        </dd>
                        <template v-if="booking.hold">
                            <dt class="font-heading font-semibold text-ink">
                                {{ t(copy, 'detail.hold') }}
                            </dt>
                            <dd class="text-ink">
                                <time :datetime="booking.hold.expires_at">{{ booking.hold.expires_label }}</time>
                                <span
                                    v-if="!booking.hold.active"
                                    class="block text-xs text-secondary"
                                >{{ t(copy, 'detail.hold_expired') }}</span>
                            </dd>
                        </template>
                    </dl>
                </section>
            </div>

            <section
                class="mt-4 rounded-lg border border-ink/10 bg-surface p-4"
                aria-labelledby="payment-title"
            >
                <h2
                    id="payment-title"
                    class="font-heading text-lg font-semibold text-ink"
                >
                    {{ t(copy, 'detail.payment') }}
                </h2>
                <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[9rem_1fr]">
                    <dt class="font-heading font-semibold text-ink">
                        {{ t(copy, 'detail.status') }}
                    </dt>
                    <dd>
                        <BookingStatusPill
                            :status="booking.status"
                            :label="booking.status_label"
                        />
                    </dd>
                    <dt class="font-heading font-semibold text-ink">
                        {{ t(copy, 'detail.amount_due') }}
                    </dt>
                    <dd class="text-ink">
                        {{ payment.amount_due_label }}
                    </dd>
                    <dt class="font-heading font-semibold text-ink">
                        {{ t(copy, 'detail.evidence') }}
                    </dt>
                    <dd class="text-ink">
                        <template v-if="payment.evidence_url">
                            <a
                                :href="payment.evidence_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline sm:min-h-0"
                            >{{ t(copy, 'detail.evidence_link') }}</a>
                            <span
                                v-if="payment.uploaded_label"
                                class="block text-xs text-secondary"
                            >
                                {{ t(copy, 'detail.uploaded') }}: <time :datetime="payment.uploaded_at">{{ payment.uploaded_label }}</time>
                            </span>
                        </template>
                        <span
                            v-else
                            class="text-secondary"
                        >{{ t(copy, 'detail.evidence_none') }}</span>
                    </dd>
                    <template v-if="payment.decision">
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.decision') }}
                        </dt>
                        <dd class="text-ink">
                            {{ payment.decision_label }}
                            <span
                                v-if="payment.verified_label"
                                class="block text-xs text-secondary"
                            >
                                {{ t(copy, 'detail.verified') }}: <time :datetime="payment.verified_at">{{ payment.verified_label }}</time>
                            </span>
                        </dd>
                    </template>
                    <template v-if="payment.reject_reason">
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'detail.reject_reason') }}
                        </dt>
                        <dd class="whitespace-pre-line text-ink [overflow-wrap:anywhere]">
                            {{ payment.reject_reason }}
                        </dd>
                    </template>
                </dl>
            </section>

            <section
                class="mt-4"
                aria-labelledby="history-title"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                    <h2
                        id="history-title"
                        class="font-heading text-lg font-semibold text-ink"
                    >
                        {{ t(copy, 'detail.history') }}
                    </h2>
                    <Link
                        :href="urls.audit"
                        class="inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline"
                    >
                        {{ t(copy, 'detail.history_all') }}
                    </Link>
                </div>
                <p
                    v-if="audit_events.length === 0"
                    class="mt-2 rounded-lg border border-dashed border-ink/20 bg-surface px-4 py-6 text-sm text-secondary"
                >
                    {{ t(copy, 'detail.history_empty') }}
                </p>
                <ol
                    v-else
                    class="mt-3 flex flex-col gap-3"
                >
                    <li
                        v-for="event in audit_events"
                        :key="event.id"
                    >
                        <AuditEventCard
                            :event="event"
                            :copy="copy.audit"
                            heading-level="h3"
                        />
                    </li>
                </ol>
            </section>
        </main>
    </AdminLayout>
</template>
