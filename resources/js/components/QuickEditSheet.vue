<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatRange } from '../lib/calendar.js';
import { clampCapacity, priceNote } from '../lib/departure.js';
import { t } from '../lib/text.js';
import BottomSheet from './BottomSheet.vue';
import DeadlinePicker from './DeadlinePicker.vue';
import MoneyInput from './MoneyInput.vue';
import Stepper from './Stepper.vue';

/**
 * Edit cepat (mockup 1g, AC-31–AC-35): price, total seats, open/closed.
 * Tap row → change → Simpan; the sheet closes with the "Tersimpan" toast.
 */
const props = defineProps({
    departure: { type: Object, required: true },
    context: { type: Object, required: true },
    presets: { type: Array, required: true },
    reload: { type: Array, required: true },
    copy: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const calendar = computed(() => props.copy.calendar);

const form = useForm({
    price: props.departure.effective_price,
    capacity: props.departure.capacity,
    status: props.departure.status,
    booking_deadline_preset: null,
    booking_deadline_at: null,
});

const reopenPreset = ref('h1');
const reopenCustom = ref(null);

// AC-34: switching a deadline-closed departure back to Buka needs a new deadline.
const needsNewDeadline = computed(() => form.status === 'open' && props.departure.deadline_passed);
const title = computed(() => `${props.departure.trip_name} · ${formatRange(props.departure.start_date, props.departure.end_date, calendar.value).replace(/ \d{4}$/, '')}`);
const floor = computed(() => props.departure.seats_taken);

function submit() {
    form.transform((data) => ({
        ...data,
        capacity: clampCapacity(data.capacity, floor.value),
        booking_deadline_preset: needsNewDeadline.value ? reopenPreset.value : null,
        booking_deadline_at: needsNewDeadline.value && reopenPreset.value === 'custom' ? reopenCustom.value : null,
    })).patch(props.departure.urls.quick, {
        preserveScroll: true,
        only: props.reload,
        onSuccess: () => emit('close'),
        onError: (errors) => {
            // AC-33: bookings arrived meanwhile — show the current number.
            if (errors.capacity) {
                form.capacity = Math.max(form.capacity ?? 0, props.departure.seats_taken);
            }
        },
    });
}
</script>

<template>
    <BottomSheet
        :title="t(copy, 'quick_edit.title')"
        :subtitle="title"
        :close-label="t(copy, 'quick_edit.close')"
        @close="emit('close')"
    >
        <form
            class="flex flex-col gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <div>
                <label
                    for="quick-price"
                    class="mb-1 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'quick_edit.price') }}</label>
                <MoneyInput
                    id="quick-price"
                    v-model="form.price"
                    :currency="t(copy, 'money.currency')"
                    :invalid="Boolean(form.errors.price)"
                    describedby="quick-price-note"
                />
                <p
                    v-if="form.errors.price"
                    class="mt-1 text-xs text-status-rejected"
                    role="alert"
                >
                    {{ form.errors.price }}
                </p>
            </div>

            <div>
                <label
                    for="quick-capacity"
                    class="mb-1 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'quick_edit.capacity') }}</label>
                <Stepper
                    id="quick-capacity"
                    v-model="form.capacity"
                    :min="Math.max(1, floor)"
                    :max="999"
                    :decrease-label="t(copy, 'duration.decrease', { what: t(copy, 'quick_edit.capacity') })"
                    :increase-label="t(copy, 'duration.increase', { what: t(copy, 'quick_edit.capacity') })"
                    describedby="quick-capacity-floor"
                />
                <p
                    v-if="floor > 0 || form.errors.capacity"
                    id="quick-capacity-floor"
                    class="mt-1 text-xs"
                    :class="form.errors.capacity ? 'text-status-rejected' : 'text-secondary'"
                    :role="form.errors.capacity ? 'alert' : undefined"
                >
                    {{ form.errors.capacity || t(copy, 'quick_edit.floor', { n: floor }) }}
                </p>
            </div>

            <div
                class="grid grid-cols-2 gap-2"
                role="radiogroup"
                :aria-label="t(copy, 'deadline.label')"
            >
                <button
                    type="button"
                    role="radio"
                    class="min-h-11 rounded-lg border text-sm font-semibold"
                    :class="form.status === 'open' ? 'border-ink bg-ink text-surface' : 'border-ink/20 bg-surface text-ink'"
                    :aria-checked="form.status === 'open' ? 'true' : 'false'"
                    @click="form.status = 'open'"
                >
                    {{ t(copy, 'quick_edit.open') }}
                </button>
                <button
                    type="button"
                    role="radio"
                    class="min-h-11 rounded-lg border text-sm font-semibold"
                    :class="form.status === 'closed' ? 'border-ink bg-ink text-surface' : 'border-ink/20 bg-surface text-ink'"
                    :aria-checked="form.status === 'closed' ? 'true' : 'false'"
                    @click="form.status = 'closed'"
                >
                    {{ t(copy, 'quick_edit.closed') }}
                </button>
            </div>

            <div v-if="needsNewDeadline">
                <p
                    class="mb-2 text-sm text-status-check"
                    role="status"
                >
                    <span aria-hidden="true">◐</span> {{ t(copy, 'quick_edit.reopen', { deadline: departure.deadline_short }) }}
                </p>
                <DeadlinePicker
                    v-model:preset="reopenPreset"
                    v-model:custom="reopenCustom"
                    id-prefix="quick-deadline"
                    :start-date="departure.start_date"
                    :meeting-time="departure.trip_meeting_time"
                    :now-local="context.now_local"
                    :presets="presets"
                    :copy="copy"
                    :error="form.errors.booking_deadline_preset || form.errors.booking_deadline_at || null"
                />
            </div>
            <p
                v-else-if="form.errors.booking_deadline_preset"
                class="text-xs text-status-rejected"
                role="alert"
            >
                {{ form.errors.booking_deadline_preset }}
            </p>

            <p
                id="quick-price-note"
                class="text-xs text-secondary"
            >
                {{ priceNote(copy, departure.active_bookings, t) }}
            </p>

            <button
                type="submit"
                class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                :disabled="form.processing"
                :aria-busy="form.processing ? 'true' : 'false'"
            >
                {{ form.processing ? '…' : t(copy, 'quick_edit.save') }}
            </button>
        </form>
    </BottomSheet>
</template>
