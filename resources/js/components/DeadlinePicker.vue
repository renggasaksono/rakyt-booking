<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { formatLongParts, formatShort } from '../lib/calendar.js';
import { computeDeadline, isDeadlineValid, latestAllowed, showsRuleHint, visiblePresetOptions } from '../lib/deadline.js';
import { t } from '../lib/text.js';

/**
 * Tutup pemesanan (mockup 3a, AC-21–AC-23): presets with their real dates,
 * "Pilih tanggal & jam…", and the chosen deadline read back in words. Presets
 * that can no longer be chosen are hidden.
 */
const props = defineProps({
    preset: { type: String, required: true },
    custom: { type: String, default: null },
    startDate: { type: String, default: null },
    meetingTime: { type: String, default: null },
    nowLocal: { type: String, required: true },
    presets: { type: Array, required: true },
    copy: { type: Object, required: true },
    settingsUrl: { type: String, default: null },
    error: { type: String, default: null },
    idPrefix: { type: String, default: 'deadline' },
});

const emit = defineEmits(['update:preset', 'update:custom']);

const open = ref(false);
const calendar = computed(() => props.copy.calendar);
const options = computed(() => (props.startDate ? visiblePresetOptions(props.presets, props.startDate, props.meetingTime, props.nowLocal) : []));
const ruleHint = computed(() => showsRuleHint(props.preset, props.meetingTime));
const deadline = computed(() => computeDeadline(props.preset, props.startDate, props.meetingTime, props.custom));
const valid = computed(() => props.startDate && isDeadlineValid(deadline.value, props.startDate, props.meetingTime, props.nowLocal));
const confirmation = computed(() => {
    if (!deadline.value) {
        return null;
    }

    return t(props.copy, 'deadline.confirm', { when: t(props.copy, 'time.long', formatLongParts(deadline.value, calendar.value)) });
});
const customMin = computed(() => props.nowLocal.replace(' ', 'T'));
const customMax = computed(() => (props.startDate ? latestAllowed(props.startDate, props.meetingTime).replace(' ', 'T') : undefined));

function choose(option) {
    emit('update:preset', option.value);
    open.value = option.value === 'custom';
}
</script>

<template>
    <div>
        <button
            type="button"
            class="flex min-h-11 w-full items-center justify-between rounded-lg border bg-surface px-3 text-left text-base text-ink disabled:text-secondary"
            :class="error ? 'border-status-rejected' : 'border-ink/20'"
            :disabled="!startDate"
            :aria-expanded="open ? 'true' : 'false'"
            :aria-controls="`${idPrefix}-options`"
            @click="open = !open"
        >
            <span>{{ t(copy, `deadline.presets.${preset}`) }}</span>
            <span aria-hidden="true">{{ open ? '▴' : '▾' }}</span>
        </button>

        <div
            v-show="open"
            :id="`${idPrefix}-options`"
            class="mt-2 rounded-lg border border-ink/10 bg-surface p-2"
            role="radiogroup"
            :aria-label="t(copy, 'deadline.label')"
        >
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                role="radio"
                class="flex min-h-11 w-full items-center justify-between gap-2 rounded-md px-3 text-left text-sm text-ink hover:bg-gading"
                :aria-checked="preset === option.value ? 'true' : 'false'"
                @click="choose(option)"
            >
                <span>{{ t(copy, `deadline.presets.${option.value}`) }}</span>
                <span class="text-xs text-secondary">
                    {{ option.deadline ? formatShort(option.deadline, calendar) : '' }}
                    <span
                        v-if="preset === option.value"
                        aria-hidden="true"
                    > ✓</span>
                </span>
            </button>
        </div>

        <div
            v-if="preset === 'custom'"
            class="mt-2"
        >
            <label
                :for="`${idPrefix}-custom`"
                class="mb-1 block text-xs font-semibold text-secondary"
            >{{ t(copy, 'deadline.custom_input') }}</label>
            <input
                :id="`${idPrefix}-custom`"
                type="datetime-local"
                class="min-h-11 w-full rounded-lg border border-ink/20 bg-surface px-3 text-base text-ink"
                :value="custom ? custom.replace(' ', 'T') : ''"
                :min="customMin"
                :max="customMax"
                @input="emit('update:custom', $event.target.value ? $event.target.value.replace('T', ' ') : null)"
            >
        </div>

        <p
            v-if="confirmation && valid"
            class="mt-2 text-sm text-status-check"
        >
            <span aria-hidden="true">◐</span> {{ confirmation }}
            <span class="block text-xs text-secondary">{{ t(copy, 'deadline.after') }}</span>
        </p>
        <p
            v-if="error"
            class="mt-1 text-xs text-status-rejected"
            role="alert"
        >
            {{ error }}
        </p>
        <p class="mt-1 text-xs text-secondary">
            <template v-if="ruleHint">
                {{ t(copy, 'deadline.rule_hint') }}
            </template>
            {{ t(copy, 'deadline.default_hint') }}
            <Link
                v-if="settingsUrl"
                :href="settingsUrl"
                class="inline-flex min-h-11 items-center text-sogan underline"
            >
                {{ t(copy, 'deadline.settings_link') }}
            </Link>
        </p>
    </div>
</template>
