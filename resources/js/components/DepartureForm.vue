<script setup>
import { useForm } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref } from 'vue';
import { formatDate, formatRange } from '../lib/calendar.js';
import { hasSameDate } from '../lib/departure.js';
import { endDate } from '../lib/duration.js';
import { t } from '../lib/text.js';
import BottomSheet from './BottomSheet.vue';
import DeadlinePicker from './DeadlinePicker.vue';
import MoneyInput from './MoneyInput.vue';
import Stepper from './Stepper.vue';

/**
 * Add or edit a departure (AC-17–AC-25, AC-28). Saved through an Inertia visit;
 * the server recomputes and validates the deadline.
 */
const props = defineProps({
    departure: { type: Object, default: null },
    template: { type: Object, default: null },
    durationDays: { type: Number, default: null },
    meetingTime: { type: String, default: null },
    departures: { type: Array, required: true },
    context: { type: Object, required: true },
    presets: { type: Array, required: true },
    storeUrl: { type: String, required: true },
    settingsUrl: { type: String, default: null },
    copy: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const editing = computed(() => props.departure !== null);
const calendar = computed(() => props.copy.calendar);
const dateInput = ref(null);

const form = useForm({
    start_date: props.departure?.start_date ?? '',
    capacity: props.departure?.capacity ?? props.template?.capacity ?? null,
    price_override: props.departure?.price_override ?? props.template?.price_override ?? null,
    booking_deadline_preset: props.departure?.booking_deadline_preset ?? props.template?.booking_deadline_preset ?? props.context.default_deadline_preset,
    booking_deadline_at: props.departure?.booking_deadline_preset === 'custom' ? props.departure.booking_deadline_local : null,
    status: props.departure?.status ?? 'open',
});

const end = computed(() => endDate(form.start_date, props.durationDays));
const sameDate = computed(() => hasSameDate(props.departures, form.start_date, props.departure?.id ?? null));
const dateLocked = computed(() => editing.value && !props.departure.can_change_date);

onMounted(async () => {
    await nextTick();

    // AC-25: a copied row starts with the date empty and focused.
    if (!editing.value) {
        dateInput.value?.focus();
    }
});

function submit() {
    const options = {
        preserveScroll: true,
        only: ['departures', 'checklist', 'preview', 'copyTemplate', 'flash'],
        onSuccess: () => emit('close'),
    };

    form.transform((data) => ({
        ...data,
        booking_deadline_at: data.booking_deadline_preset === 'custom' ? data.booking_deadline_at : null,
    }));

    if (editing.value) {
        form.put(props.departure.urls.update, options);
    } else {
        form.post(props.storeUrl, options);
    }
}
</script>

<template>
    <BottomSheet
        :title="editing ? t(copy, 'departures.edit_title') : t(copy, 'departures.new_title')"
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
                    for="departure-start"
                    class="mb-1 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'departures.start_date') }}</label>
                <input
                    id="departure-start"
                    ref="dateInput"
                    v-model="form.start_date"
                    type="date"
                    class="min-h-11 w-full rounded-lg border bg-surface px-3 text-base text-ink disabled:text-secondary"
                    :class="form.errors.start_date ? 'border-status-rejected' : 'border-ink/20'"
                    :min="context.today"
                    :disabled="dateLocked"
                    aria-describedby="departure-start-help"
                >
                <p
                    id="departure-start-help"
                    class="mt-1 text-xs text-secondary"
                >
                    <template v-if="dateLocked">
                        {{ t(copy, 'departures.date_locked') }}
                    </template>
                    <template v-else-if="form.start_date && end">
                        {{ formatDate(form.start_date, calendar) }} · {{ formatRange(form.start_date, end, calendar) }}
                    </template>
                    <template v-else-if="!durationDays">
                        {{ t(copy, 'departures.need_duration') }}
                    </template>
                </p>
                <p
                    v-if="sameDate"
                    class="mt-1 text-xs text-status-check"
                >
                    <span aria-hidden="true">◐</span> {{ t(copy, 'departures.same_date_warning') }}
                </p>
                <p
                    v-if="form.errors.start_date || form.errors.duration"
                    class="mt-1 text-xs text-status-rejected"
                    role="alert"
                >
                    {{ form.errors.start_date || form.errors.duration }}
                </p>
            </div>

            <div>
                <label
                    for="departure-capacity"
                    class="mb-1 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'departures.seats') }}</label>
                <Stepper
                    id="departure-capacity"
                    v-model="form.capacity"
                    :min="Math.max(1, departure?.seats_taken ?? 0)"
                    :max="999"
                    :decrease-label="t(copy, 'duration.decrease', { what: t(copy, 'departures.seats') })"
                    :increase-label="t(copy, 'duration.increase', { what: t(copy, 'departures.seats') })"
                />
                <p
                    v-if="departure?.seats_taken"
                    class="mt-1 text-xs text-secondary"
                >
                    {{ t(copy, 'quick_edit.floor', { n: departure.seats_taken }) }}
                </p>
                <p
                    v-if="form.errors.capacity"
                    class="mt-1 text-xs text-status-rejected"
                    role="alert"
                >
                    {{ form.errors.capacity }}
                </p>
            </div>

            <div>
                <label
                    for="departure-price"
                    class="mb-1 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'departures.price') }}</label>
                <MoneyInput
                    id="departure-price"
                    v-model="form.price_override"
                    :currency="t(copy, 'money.currency')"
                    :invalid="Boolean(form.errors.price_override)"
                    describedby="departure-price-help"
                />
                <p
                    id="departure-price-help"
                    class="mt-1 text-xs text-secondary"
                >
                    {{ t(copy, 'departures.price_hint') }}
                </p>
                <p
                    v-if="form.errors.price_override"
                    class="mt-1 text-xs text-status-rejected"
                    role="alert"
                >
                    {{ form.errors.price_override }}
                </p>
            </div>

            <div>
                <p class="mb-1 font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'deadline.label') }}
                </p>
                <DeadlinePicker
                    v-model:preset="form.booking_deadline_preset"
                    v-model:custom="form.booking_deadline_at"
                    id-prefix="departure-deadline"
                    :start-date="form.start_date || null"
                    :meeting-time="meetingTime"
                    :now-local="context.now_local"
                    :presets="presets"
                    :copy="copy"
                    :settings-url="settingsUrl"
                    :error="form.errors.booking_deadline_preset || form.errors.booking_deadline_at || null"
                />
            </div>

            <label
                v-if="editing"
                class="flex min-h-11 items-center gap-3 text-sm text-ink"
            >
                <input
                    type="checkbox"
                    class="h-5 w-5"
                    :checked="form.status === 'open'"
                    @change="form.status = $event.target.checked ? 'open' : 'closed'"
                >
                {{ t(copy, 'departures.open_toggle') }}
            </label>

            <p
                v-if="form.errors.departure"
                class="text-xs text-status-rejected"
                role="alert"
            >
                {{ form.errors.departure }}
            </p>

            <div class="flex gap-2">
                <button
                    type="button"
                    class="min-h-12 flex-1 rounded-lg border border-ink px-4 text-sm font-semibold text-ink"
                    @click="emit('close')"
                >
                    {{ t(copy, 'departures.cancel') }}
                </button>
                <button
                    type="submit"
                    class="min-h-12 flex-1 rounded-lg bg-prada px-4 font-heading text-sm font-semibold text-ink disabled:bg-prada/40"
                    :disabled="form.processing"
                    :aria-busy="form.processing ? 'true' : 'false'"
                >
                    {{ t(copy, 'departures.save') }}
                </button>
            </div>
        </form>
    </BottomSheet>
</template>
