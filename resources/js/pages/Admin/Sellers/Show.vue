<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AccountStatusPill from '../../../components/AccountStatusPill.vue';
import AuditEventCard from '../../../components/AuditEventCard.vue';
import BookingStatusPill from '../../../components/BookingStatusPill.vue';
import BottomSheet from '../../../components/BottomSheet.vue';
import FieldError from '../../../components/FieldError.vue';
import StatusBadge from '../../../components/StatusBadge.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import { SEAT_SEGMENT_CLASSES, seatSegments } from '../../../lib/dashboard.js';
import { t } from '../../../lib/text.js';

/**
 * One seller organization (F-012): profile, account, members, trips,
 * upcoming departures with the seller dashboard's seat counts, recent
 * bookings and the organization's audit events. The only change made here is
 * F-014's suspend / reactivate in the "Akun" section: a sheet that states
 * the effects and asks for a required reason. Neither is gold — suspending
 * is destructive (Ditolak-styled), reactivating an ink outline. Phone first:
 * sections stack as cards; from 1024 px profile and account sit side by side.
 */
const props = defineProps({
    organization: { type: Object, required: true },
    suspension: { type: Object, default: null },
    // eslint-disable-next-line vue/prop-name-casing
    last_activity: { type: Object, required: true },
    members: { type: Array, required: true },
    trips: { type: Object, required: true },
    departures: { type: Object, required: true },
    bookings: { type: Array, required: true },
    // eslint-disable-next-line vue/prop-name-casing
    audit_events: { type: Array, required: true },
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const REASON_MAX = 500;
const suspended = computed(() => props.organization.status === 'suspended');
const sheetOpen = ref(false);
const failed = ref(false);
const form = useForm({ alasan: '' });
const canSubmit = computed(() => form.alasan.trim() !== '' && !form.processing);

function openSheet() {
    form.reset();
    form.clearErrors();
    failed.value = false;
    sheetOpen.value = true;
}

function submit() {
    if (!canSubmit.value) {
        return;
    }

    failed.value = false;
    form.post(suspended.value ? props.urls.reactivate : props.urls.suspend, {
        preserveScroll: true,
        onSuccess: () => {
            sheetOpen.value = false;
            form.reset();
        },
        onNetworkError: () => {
            failed.value = true;

            return false;
        },
        onHttpException: () => {
            failed.value = true;

            return false;
        },
    });
}
</script>

<template>
    <Head :title="organization.name" />
    <AdminLayout>
        <main class="mx-auto max-w-4xl px-4 py-8">
            <Link
                :href="urls.index"
                class="inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline"
            >
                ← {{ t(copy, 'back') }}
            </Link>

            <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1">
                <h1 class="font-heading text-2xl font-semibold text-ink">
                    {{ organization.name }}
                </h1>
                <AccountStatusPill
                    :status="organization.status"
                    :label="organization.status_label"
                />
            </div>
            <p class="mt-1 font-mono text-xs text-secondary">
                {{ organization.slug }} · #{{ organization.id }}
            </p>
            <p class="mt-2 text-sm text-secondary">
                {{ t(copy, 'read_only') }}
            </p>

            <div class="mt-6 grid gap-4 lg:grid-cols-2">
                <section
                    class="rounded-lg border border-ink/10 bg-surface p-4"
                    aria-labelledby="profile-title"
                >
                    <h2
                        id="profile-title"
                        class="font-heading text-lg font-semibold text-ink"
                    >
                        {{ t(copy, 'profile.title') }}
                    </h2>
                    <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[8rem_1fr]">
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'profile.public_url') }}
                        </dt>
                        <dd>
                            <a
                                :href="organization.public_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex min-h-11 items-center break-all text-sogan underline sm:min-h-0"
                            >{{ organization.public_url }}</a>
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'profile.booking_code') }}
                        </dt>
                        <dd class="font-mono text-ink">
                            {{ organization.booking_code }}
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'profile.whatsapp') }}
                        </dt>
                        <dd class="text-ink">
                            {{ organization.whatsapp }}
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'profile.city') }}
                        </dt>
                        <dd class="text-ink">
                            {{ organization.city }}
                        </dd>
                        <template v-if="organization.bio">
                            <dt class="font-heading font-semibold text-ink">
                                {{ t(copy, 'profile.bio') }}
                            </dt>
                            <dd class="whitespace-pre-line text-ink">
                                {{ organization.bio }}
                            </dd>
                        </template>
                    </dl>
                </section>

                <!-- F-014: suspend / reactivate live in this section. -->
                <section
                    class="rounded-lg border border-ink/10 bg-surface p-4"
                    aria-labelledby="account-title"
                >
                    <h2
                        id="account-title"
                        class="font-heading text-lg font-semibold text-ink"
                    >
                        {{ t(copy, 'account.title') }}
                    </h2>
                    <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[8rem_1fr]">
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'account.status') }}
                        </dt>
                        <dd>
                            <AccountStatusPill
                                :status="organization.status"
                                :label="organization.status_label"
                            />
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'account.plan') }}
                        </dt>
                        <dd class="text-ink">
                            {{ organization.plan_label }}
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'account.registered') }}
                        </dt>
                        <dd class="text-ink">
                            {{ organization.registered_label }}
                        </dd>
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'account.last_activity') }}
                        </dt>
                        <dd>
                            <time
                                v-if="last_activity.at"
                                :datetime="last_activity.at"
                                class="text-ink"
                            >{{ last_activity.label }}</time>
                            <span
                                v-else
                                class="text-ink"
                            >{{ last_activity.label }}</span>
                            <span class="block text-xs text-secondary">{{ t(copy, 'account.last_activity_hint') }}</span>
                        </dd>
                        <template v-if="suspension">
                            <dt class="font-heading font-semibold text-ink">
                                {{ t(copy, 'suspension.since') }}
                            </dt>
                            <dd>
                                <time
                                    :datetime="suspension.at"
                                    class="text-ink"
                                >{{ suspension.at_label }}</time>
                            </dd>
                            <dt class="font-heading font-semibold text-ink">
                                {{ t(copy, 'suspension.by') }}
                            </dt>
                            <dd class="text-ink">
                                {{ suspension.by }}
                            </dd>
                            <dt class="font-heading font-semibold text-ink">
                                {{ t(copy, 'suspension.reason') }}
                            </dt>
                            <dd class="whitespace-pre-line text-ink [overflow-wrap:anywhere]">
                                {{ suspension.reason }}
                            </dd>
                        </template>
                    </dl>
                    <button
                        v-if="suspended"
                        type="button"
                        class="mt-4 min-h-11 w-full px-4 font-heading text-sm font-semibold text-ink ring-[1.5px] ring-inset ring-ink sm:w-auto"
                        @click="openSheet"
                    >
                        {{ t(copy, 'suspension.reactivate') }}
                    </button>
                    <button
                        v-else
                        type="button"
                        class="mt-4 min-h-11 w-full px-4 font-heading text-sm font-semibold text-status-rejected ring-[1.5px] ring-inset ring-status-rejected sm:w-auto"
                        @click="openSheet"
                    >
                        {{ t(copy, 'suspension.suspend') }}
                    </button>
                </section>
            </div>

            <section
                class="mt-4 rounded-lg border border-ink/10 bg-surface p-4"
                aria-labelledby="members-title"
            >
                <h2
                    id="members-title"
                    class="font-heading text-lg font-semibold text-ink"
                >
                    {{ t(copy, 'members.title') }}
                </h2>
                <ul class="mt-3 flex flex-col divide-y divide-ink/10">
                    <li
                        v-for="(member, i) in members"
                        :key="i"
                        class="py-2 text-sm"
                    >
                        <p class="font-heading font-semibold text-ink">
                            {{ member.name }}
                            <span class="ml-1 text-xs font-normal text-secondary">{{ member.role_label }}</span>
                        </p>
                        <p class="break-all text-ink">
                            {{ member.email }}
                        </p>
                        <p class="text-xs text-secondary">
                            {{ t(copy, 'members.last_login') }}: {{ member.last_login_label }}
                        </p>
                    </li>
                </ul>
            </section>

            <section
                class="mt-4 rounded-lg border border-ink/10 bg-surface p-4"
                aria-labelledby="trips-title"
            >
                <h2
                    id="trips-title"
                    class="font-heading text-lg font-semibold text-ink"
                >
                    {{ t(copy, 'trips.title') }}
                </h2>
                <p
                    v-if="trips.items.length === 0"
                    class="mt-2 text-sm text-secondary"
                >
                    {{ t(copy, 'trips.empty') }}
                </p>
                <ul
                    v-else
                    class="mt-3 flex flex-col divide-y divide-ink/10"
                >
                    <li
                        v-for="trip in trips.items"
                        :key="trip.id"
                        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 py-2 text-sm"
                    >
                        <span class="font-heading font-semibold text-ink">{{ trip.name }}</span>
                        <span class="flex items-center gap-2 text-secondary">
                            {{ t(copy, 'trips.departures_count', { count: trip.departures_count }) }}
                            <StatusBadge
                                :status="trip.status === 'published' ? 'open' : 'closed'"
                                :label="trip.status_label"
                            />
                        </span>
                    </li>
                </ul>
                <p
                    v-if="trips.more"
                    class="mt-2 text-xs text-secondary"
                >
                    {{ t(copy, 'trips.more', { count: trips.items.length }) }}
                </p>
            </section>

            <section
                class="mt-4 rounded-lg border border-ink/10 bg-surface p-4"
                aria-labelledby="departures-title"
            >
                <h2
                    id="departures-title"
                    class="font-heading text-lg font-semibold text-ink"
                >
                    {{ t(copy, 'departures.title') }}
                </h2>
                <p
                    v-if="departures.items.length === 0"
                    class="mt-2 text-sm text-secondary"
                >
                    {{ t(copy, 'departures.empty') }}
                </p>
                <ul
                    v-else
                    class="mt-3 flex flex-col divide-y divide-ink/10"
                >
                    <li
                        v-for="departure in departures.items"
                        :key="departure.id"
                        class="py-3 text-sm"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                            <span class="font-heading font-semibold text-ink">{{ departure.date_label }} · {{ departure.trip_name }}</span>
                            <StatusBadge
                                :status="departure.status"
                                :label="departure.status_label"
                            />
                        </div>
                        <div
                            class="mt-2 flex h-2 w-full gap-[3px]"
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
                            {{ t(copy, 'departures.seats', departure.seats) }}
                        </p>
                    </li>
                </ul>
                <p
                    v-if="departures.more"
                    class="mt-2 text-xs text-secondary"
                >
                    {{ t(copy, 'departures.more', { count: departures.items.length }) }}
                </p>
            </section>

            <section
                class="mt-4 rounded-lg border border-ink/10 bg-surface p-4"
                aria-labelledby="bookings-title"
            >
                <h2
                    id="bookings-title"
                    class="font-heading text-lg font-semibold text-ink"
                >
                    {{ t(copy, 'bookings.title') }}
                </h2>
                <p
                    v-if="bookings.length === 0"
                    class="mt-2 text-sm text-secondary"
                >
                    {{ t(copy, 'bookings.empty') }}
                </p>
                <ul
                    v-else
                    class="mt-3 flex flex-col divide-y divide-ink/10"
                >
                    <li
                        v-for="booking in bookings"
                        :key="booking.reference"
                        class="py-3 text-sm"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                            <span class="font-heading font-semibold text-ink">{{ booking.customer_name }}</span>
                            <BookingStatusPill
                                :status="booking.status"
                                :label="booking.status_label"
                            />
                        </div>
                        <p class="mt-0.5 font-mono text-xs text-secondary">
                            {{ booking.reference }} · {{ booking.created_label }}
                        </p>
                        <p class="mt-1 text-ink">
                            {{ booking.trip_name }} · {{ booking.departure_label }}
                        </p>
                        <p class="text-ink">
                            {{ t(copy, 'bookings.participants', { count: booking.participants }) }} · {{ booking.total_label }}
                        </p>
                        <a
                            v-if="booking.evidence_url"
                            :href="booking.evidence_url"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline"
                        >{{ t(copy, 'bookings.evidence') }}</a>
                    </li>
                </ul>
            </section>

            <section
                class="mt-4"
                aria-labelledby="audit-title"
            >
                <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                    <h2
                        id="audit-title"
                        class="font-heading text-lg font-semibold text-ink"
                    >
                        {{ t(copy, 'audit_events.title') }}
                    </h2>
                    <Link
                        :href="urls.audit"
                        class="inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline"
                    >
                        {{ t(copy, 'audit_events.all') }}
                    </Link>
                </div>
                <p
                    v-if="audit_events.length === 0"
                    class="mt-2 rounded-lg border border-dashed border-ink/20 bg-surface px-4 py-6 text-sm text-secondary"
                >
                    {{ t(copy, 'audit_events.empty') }}
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

        <!-- F-014 AC-1: confirmation with the effects and a required reason. -->
        <BottomSheet
            v-if="sheetOpen"
            :title="t(copy, suspended ? 'suspension.reactivate_title' : 'suspension.suspend_title', { name: organization.name })"
            :close-label="t(copy, 'suspension.close')"
            @close="sheetOpen = false"
        >
            <form
                class="flex flex-col gap-3 text-sm text-ink"
                novalidate
                @submit.prevent="submit"
            >
                <ul class="flex list-disc flex-col gap-1 pl-5">
                    <li
                        v-for="(effect, i) in (suspended ? copy.suspension.reactivate_effects : copy.suspension.suspend_effects)"
                        :key="i"
                    >
                        {{ effect }}
                    </li>
                </ul>
                <div>
                    <label
                        for="suspension-reason"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'suspension.reason_label') }}</label>
                    <textarea
                        id="suspension-reason"
                        v-model="form.alasan"
                        :maxlength="REASON_MAX"
                        rows="3"
                        required
                        class="mt-1 w-full border bg-surface p-2 text-base text-ink"
                        :class="form.errors.alasan ? 'border-status-rejected' : 'border-ink/20'"
                        :placeholder="t(copy, suspended ? 'suspension.reactivate_placeholder' : 'suspension.suspend_placeholder')"
                        :aria-invalid="Boolean(form.errors.alasan) || undefined"
                        aria-describedby="suspension-reason-hint suspension-reason-error"
                    />
                    <p
                        id="suspension-reason-hint"
                        class="text-xs text-secondary"
                    >
                        {{ t(copy, 'suspension.reason_hint', { max: REASON_MAX }) }}
                    </p>
                    <FieldError
                        id="suspension-reason-error"
                        :message="form.errors.alasan"
                    />
                </div>
                <p
                    v-if="failed"
                    class="text-status-rejected"
                    role="alert"
                >
                    {{ t(copy, 'suspension.failed') }}
                </p>
                <div class="mt-1 flex flex-col gap-2">
                    <button
                        type="submit"
                        class="min-h-12 w-full px-4 font-heading text-base font-semibold text-surface disabled:opacity-60"
                        :class="suspended ? 'bg-ink' : 'bg-status-rejected'"
                        :disabled="!canSubmit"
                        :aria-busy="form.processing ? 'true' : 'false'"
                    >
                        {{ form.processing ? t(copy, 'suspension.sending') : t(copy, suspended ? 'suspension.confirm_reactivate' : 'suspension.confirm_suspend') }}
                    </button>
                    <button
                        type="button"
                        class="min-h-12 w-full px-4 font-heading text-base font-semibold text-ink ring-[1.5px] ring-inset ring-ink"
                        @click="sheetOpen = false"
                    >
                        {{ t(copy, 'suspension.cancel') }}
                    </button>
                </div>
            </form>
        </BottomSheet>
    </AdminLayout>
</template>
