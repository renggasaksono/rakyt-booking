<script setup>
import { computed, ref } from 'vue';
import { clampDays, clampNights, durationKey } from '../lib/duration.js';
import { t } from '../lib/text.js';
import Stepper from './Stepper.vue';

/**
 * Durasi (mockup 3a, AC-5): presets plus "Atur sendiri" with Hari/Malam steppers.
 */
const props = defineProps({
    modelValue: { type: Object, required: true },
    presets: { type: Array, required: true },
    maxDays: { type: Number, default: 30 },
    copy: { type: Object, required: true },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const forceCustom = ref(false);
const nightsWarning = ref(false);

const selected = computed(() => (forceCustom.value ? 'custom' : durationKey(props.modelValue, props.presets)));
const current = computed(() => props.presets.find((preset) => preset.key === selected.value));

const summary = computed(() => {
    const value = props.modelValue;

    if (!value.duration_label) {
        return t(props.copy, 'duration.none');
    }

    if (current.value) {
        return current.value.display;
    }

    return value.duration_nights > 0
        ? t(props.copy, 'duration.days_nights', { days: value.duration_days, nights: value.duration_nights })
        : t(props.copy, 'duration.days', { days: value.duration_days });
});

function choose(preset) {
    forceCustom.value = false;
    nightsWarning.value = false;
    open.value = false;
    emit('update:modelValue', {
        duration_label: preset.duration_label,
        duration_days: preset.duration_days,
        duration_nights: preset.duration_nights,
    });
}

function chooseCustom() {
    forceCustom.value = true;
    const days = props.modelValue.duration_label === 'days' ? props.modelValue.duration_days : 2;
    const nights = props.modelValue.duration_label === 'days' ? props.modelValue.duration_nights : 1;
    emit('update:modelValue', { duration_label: 'days', duration_days: days, duration_nights: Math.min(nights, days) });
}

function setDays(days) {
    const clamped = clampDays(days ?? 1, props.maxDays);
    const { nights } = clampNights(clamped, props.modelValue.duration_nights ?? 0);
    nightsWarning.value = false;
    emit('update:modelValue', { duration_label: 'days', duration_days: clamped, duration_nights: nights });
}

function setNights(nights) {
    const days = props.modelValue.duration_days ?? 1;
    const result = clampNights(days, nights ?? 0);
    nightsWarning.value = result.tooMany;
    emit('update:modelValue', { duration_label: 'days', duration_days: days, duration_nights: result.nights });
}
</script>

<template>
    <div>
        <button
            type="button"
            class="flex min-h-11 w-full items-center justify-between rounded-lg border bg-surface px-3 text-left text-base text-ink"
            :class="error ? 'border-status-rejected' : 'border-ink/20'"
            :aria-expanded="open ? 'true' : 'false'"
            aria-controls="duration-options"
            @click="open = !open"
        >
            <span>{{ summary }}</span>
            <span aria-hidden="true">{{ open ? '▴' : '▾' }}</span>
        </button>

        <div
            v-show="open || selected === 'custom'"
            id="duration-options"
            class="mt-2 rounded-lg border border-ink/10 bg-surface p-2"
        >
            <div
                v-show="open"
                role="radiogroup"
                :aria-label="t(copy, 'duration.label')"
            >
                <button
                    v-for="preset in presets"
                    :key="preset.key"
                    type="button"
                    role="radio"
                    class="flex min-h-11 w-full items-center justify-between rounded-md px-3 text-left text-sm text-ink hover:bg-gading"
                    :aria-checked="selected === preset.key ? 'true' : 'false'"
                    @click="choose(preset)"
                >
                    <span>
                        {{ preset.display }}
                        <span
                            v-if="preset.duration_nights === 0"
                            class="ml-1 text-xs text-secondary"
                        >{{ t(copy, 'duration.no_overnight') }}</span>
                    </span>
                    <span
                        v-if="selected === preset.key"
                        aria-hidden="true"
                    >✓</span>
                </button>
                <button
                    type="button"
                    role="radio"
                    class="flex min-h-11 w-full items-center justify-between rounded-md px-3 text-left text-sm text-ink hover:bg-gading"
                    :aria-checked="selected === 'custom' ? 'true' : 'false'"
                    @click="chooseCustom"
                >
                    {{ t(copy, 'duration.custom') }}
                    <span
                        v-if="selected === 'custom'"
                        aria-hidden="true"
                    >✓</span>
                </button>
            </div>

            <div
                v-if="selected === 'custom'"
                class="mt-2 flex flex-wrap gap-4 px-1 pb-1"
            >
                <div>
                    <label
                        for="duration-days"
                        class="mb-1 block text-xs font-semibold text-secondary"
                    >{{ t(copy, 'duration.custom_days') }}</label>
                    <Stepper
                        id="duration-days"
                        :model-value="modelValue.duration_days"
                        :min="1"
                        :max="maxDays"
                        :decrease-label="t(copy, 'duration.decrease', { what: t(copy, 'duration.custom_days') })"
                        :increase-label="t(copy, 'duration.increase', { what: t(copy, 'duration.custom_days') })"
                        @update:model-value="setDays"
                    />
                </div>
                <div>
                    <label
                        for="duration-nights"
                        class="mb-1 block text-xs font-semibold text-secondary"
                    >{{ t(copy, 'duration.custom_nights') }}</label>
                    <Stepper
                        id="duration-nights"
                        :model-value="modelValue.duration_nights"
                        :min="0"
                        :max="maxDays"
                        :decrease-label="t(copy, 'duration.decrease', { what: t(copy, 'duration.custom_nights') })"
                        :increase-label="t(copy, 'duration.increase', { what: t(copy, 'duration.custom_nights') })"
                        describedby="duration-nights-help"
                        @update:model-value="setNights"
                    />
                </div>
                <p
                    id="duration-nights-help"
                    class="w-full text-xs"
                    :class="nightsWarning ? 'font-semibold text-status-rejected' : 'text-secondary'"
                    :role="nightsWarning ? 'alert' : undefined"
                >
                    {{ t(copy, 'duration.nights_max') }}
                </p>
            </div>
        </div>

        <p
            v-if="error"
            class="mt-1 text-xs text-status-rejected"
            role="alert"
        >
            {{ error }}
        </p>
        <p class="mt-1 text-xs text-secondary">
            {{ t(copy, 'duration.end_date_hint') }}
        </p>
    </div>
</template>
