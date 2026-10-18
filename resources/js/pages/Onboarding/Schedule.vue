<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import DeadlinePicker from '../../components/DeadlinePicker.vue';
import FieldError from '../../components/FieldError.vue';
import MoneyInput from '../../components/MoneyInput.vue';
import StickyAction from '../../components/StickyAction.vue';
import Stepper from '../../components/Stepper.vue';
import OnboardingLayout from '../../layouts/OnboardingLayout.vue';
import { computeDeadline } from '../../lib/deadline.js';
import { endDate } from '../../lib/duration.js';
import { saveFailureHandlers } from '../../lib/http.js';
import { defaultDeadlinePreset, formatShortDay, scheduleSummary } from '../../lib/onboarding.js';
import { t } from '../../lib/text.js';

/**
 * Langkah 3 dari 4 · Jadwal (mockup 4d, F-001 C5–C9). Scheduled trips get
 * one departure; request-date trips only a price (C8). Extra dates are added
 * later in the trip editor (C9).
 */
const props = defineProps({
    trip: { type: Object, required: true },
    departure: { type: Object, default: null },
    context: { type: Object, required: true },
    options: { type: Object, required: true },
    header: { type: Object, required: true },
    highlight: { type: String, default: null },
    urls: { type: Object, required: true },
    copy: { type: Object, required: true },
    tripCopy: { type: Object, required: true },
});

const scheduled = computed(() => props.trip.booking_mode === 'scheduled');
const calendar = computed(() => props.tripCopy.calendar);

const form = useForm({
    base_price: props.trip.base_price,
    start_date: props.departure?.start_date ?? '',
    capacity: props.departure?.capacity ?? null,
    booking_deadline_preset: props.departure?.booking_deadline_preset ?? props.context.default_deadline_preset,
    booking_deadline_at: props.departure?.booking_deadline_local ?? null,
});

const failed = ref(false);
// C6: once the seller picks a preset themselves, the date no longer moves it.
const presetTouched = ref(Boolean(props.departure));

watch(() => form.start_date, (date) => {
    if (!presetTouched.value && date) {
        form.booking_deadline_preset = defaultDeadlinePreset(
            props.context.default_deadline_preset,
            props.options.deadline_presets,
            date,
            props.trip.meeting_time,
            props.context.now_local,
        );
    }
}, { immediate: true });

const end = computed(() => endDate(form.start_date, props.trip.duration_days));
const deadline = computed(() => computeDeadline(form.booking_deadline_preset, form.start_date, props.trip.meeting_time, form.booking_deadline_at));
const summary = computed(() => scheduleSummary({ seats: form.capacity, price: form.base_price, deadline: deadline.value }, calendar.value));

function setPreset(value) {
    presetTouched.value = true;
    form.booking_deadline_preset = value;
}

function submit() {
    if (form.processing) {
        return;
    }

    failed.value = false;
    form.transform((data) => (scheduled.value
        ? { ...data, booking_deadline_at: data.booking_deadline_preset === 'custom' ? data.booking_deadline_at : null }
        : { base_price: data.base_price }))
        .post(props.urls.store, {
            preserveScroll: true,
            ...saveFailureHandlers(() => (failed.value = true)),
        });
}
</script>

<template>
    <Head :title="t(copy, 'schedule.title')" />
    <OnboardingLayout
        :header="header"
        :copy="copy"
        :back-url="urls.back"
    >
        <h1 class="font-heading text-xl font-semibold text-ink">
            {{ t(copy, 'schedule.title') }}
        </h1>

        <p
            v-if="$page.props.errors?.publish"
            class="mt-3 rounded-lg bg-status-rejected-tint px-3 py-2 text-sm text-status-rejected"
            role="alert"
        >
            {{ $page.props.errors.publish }}
        </p>

        <form
            id="schedule-form"
            class="mt-5 flex flex-col gap-5"
            novalidate
            @submit.prevent="submit"
        >
            <div>
                <label
                    for="base_price"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'schedule.price') }}</label>
                <MoneyInput
                    id="base_price"
                    v-model="form.base_price"
                    class="mt-1"
                    :currency="t(tripCopy, 'money.currency')"
                    :invalid="Boolean(form.errors.base_price) || highlight === 'base_price'"
                    describedby="price-hint price-error"
                />
                <p
                    v-if="!scheduled"
                    id="price-hint"
                    class="mt-1 text-xs text-secondary"
                >
                    {{ t(copy, 'schedule.request_hint') }}
                </p>
                <FieldError
                    id="price-error"
                    :message="form.errors.base_price"
                />
            </div>

            <template v-if="scheduled">
                <div :class="highlight === 'departure' ? 'rounded-lg ring-2 ring-status-rejected ring-offset-4 ring-offset-gading' : ''">
                    <label
                        for="start_date"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'schedule.start_date') }}</label>
                    <div class="mt-1 flex flex-wrap items-center gap-3">
                        <input
                            id="start_date"
                            v-model="form.start_date"
                            type="date"
                            :min="context.today"
                            class="min-h-12 flex-1 rounded-lg border bg-surface px-3 text-base text-ink"
                            :class="form.errors.start_date ? 'border-status-rejected' : 'border-ink/20'"
                            :aria-invalid="Boolean(form.errors.start_date) || undefined"
                            aria-describedby="end-hint start-error"
                        >
                        <span
                            v-if="end"
                            class="text-sm text-ink"
                        >{{ t(copy, 'schedule.end_date', { date: formatShortDay(end, calendar) }) }}</span>
                    </div>
                    <p
                        v-if="trip.duration_display"
                        id="end-hint"
                        class="mt-1 text-xs text-secondary"
                    >
                        {{ t(copy, 'schedule.end_hint', { duration: trip.duration_display }) }}
                    </p>
                    <FieldError
                        id="start-error"
                        :message="form.errors.start_date"
                    />
                </div>

                <div>
                    <label
                        for="capacity"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'schedule.seats') }}</label>
                    <Stepper
                        id="capacity"
                        v-model="form.capacity"
                        class="mt-1"
                        :min="1"
                        :max="options.limits.seats_max"
                        :decrease-label="t(copy, 'schedule.seats_decrease')"
                        :increase-label="t(copy, 'schedule.seats_increase')"
                        describedby="capacity-hint capacity-error"
                    />
                    <p
                        id="capacity-hint"
                        class="mt-1 text-xs text-secondary"
                    >
                        {{ t(copy, 'schedule.seats_hint') }}
                    </p>
                    <FieldError
                        id="capacity-error"
                        :message="form.errors.capacity"
                    />
                </div>

                <div>
                    <p class="font-heading text-sm font-semibold text-ink">
                        {{ t(copy, 'schedule.deadline') }}
                    </p>
                    <DeadlinePicker
                        class="mt-1"
                        :preset="form.booking_deadline_preset"
                        :custom="form.booking_deadline_at"
                        :start-date="form.start_date || null"
                        :meeting-time="trip.meeting_time"
                        :now-local="context.now_local"
                        :presets="options.deadline_presets"
                        :copy="tripCopy"
                        :error="form.errors.booking_deadline_preset ?? form.errors.booking_deadline_at ?? null"
                        id-prefix="onboarding-deadline"
                        @update:preset="setPreset"
                        @update:custom="(value) => (form.booking_deadline_at = value)"
                    />
                </div>

                <p
                    v-if="summary"
                    class="rounded-lg bg-surface px-3 py-2 text-sm text-ink"
                    aria-live="polite"
                >
                    {{ t(copy, 'schedule.summary', summary) }}
                </p>
            </template>

            <FieldError :message="form.errors.departure ?? form.errors.duration" />
            <FieldError :message="failed ? t(copy, 'save_failed') : null" />
        </form>

        <template #action>
            <StickyAction>
                <button
                    type="submit"
                    form="schedule-form"
                    class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                    :disabled="form.processing"
                    :aria-busy="form.processing"
                >
                    {{ form.processing ? t(copy, 'saving') : t(copy, 'schedule.submit') }}
                </button>
            </StickyAction>
        </template>
    </OnboardingLayout>
</template>
