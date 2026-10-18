<script setup>
import { computed } from 'vue';
import { formatRange } from '../lib/calendar.js';
import { formatRupiah } from '../lib/money.js';
import { t } from '../lib/text.js';
import StatusBadge from './StatusBadge.vue';

/**
 * Departures of a trip (mockup 1f §04, AC-26, AC-27): cards on phones, a
 * table from md up; past departures collapsed and read-only.
 */
const props = defineProps({
    upcoming: { type: Array, required: true },
    past: { type: Array, required: true },
    copy: { type: Object, required: true },
});

const emit = defineEmits(['edit', 'quick', 'delete']);

const calendar = computed(() => props.copy.calendar);

function deadlineLabel(departure) {
    return departure.booking_deadline_preset === 'custom'
        ? departure.deadline_short
        : t(props.copy, `deadline.short.${departure.booking_deadline_preset}`);
}

function priceLabel(departure) {
    return departure.effective_price ? formatRupiah(departure.effective_price) : '—';
}
</script>

<template>
    <div>
        <!-- Phone: cards; tapping opens the quick-edit sheet (AC-31). -->
        <ul class="flex flex-col gap-2 md:hidden">
            <li
                v-for="departure in upcoming"
                :key="departure.id"
            >
                <button
                    type="button"
                    class="flex min-h-14 w-full items-center justify-between gap-3 rounded-lg border border-ink/10 bg-surface px-3 py-2 text-left"
                    @click="emit('quick', departure)"
                >
                    <span>
                        <span class="block font-heading text-sm font-semibold text-ink">{{ formatRange(departure.start_date, departure.end_date, calendar) }}</span>
                        <span class="block text-xs text-secondary">
                            {{ t(copy, 'departures.seats_taken', { taken: departure.seats_taken, capacity: departure.capacity }) }} ·
                            {{ t(copy, 'money.currency') }}{{ priceLabel(departure) }} ·
                            {{ deadlineLabel(departure) }}
                        </span>
                    </span>
                    <StatusBadge
                        :status="departure.display_status"
                        :label="t(copy, `status.${departure.display_status}`)"
                    />
                </button>
                <div class="mt-1 flex justify-end gap-2">
                    <button
                        type="button"
                        class="min-h-11 px-3 text-sm text-sogan underline"
                        @click="emit('edit', departure)"
                    >
                        {{ t(copy, 'departures.edit') }}
                    </button>
                    <button
                        v-if="departure.can_delete"
                        type="button"
                        class="min-h-11 px-3 text-sm text-status-rejected underline"
                        @click="emit('delete', departure)"
                    >
                        {{ t(copy, 'departures.delete') }}
                    </button>
                </div>
            </li>
        </ul>

        <!-- Tablet and desktop: table. -->
        <table class="hidden w-full text-left text-sm md:table">
            <thead class="text-xs uppercase tracking-wide text-secondary">
                <tr>
                    <th class="py-2 font-semibold">
                        {{ t(copy, 'departures.start_date') }}
                    </th>
                    <th class="py-2 font-semibold">
                        {{ t(copy, 'departures.seats') }}
                    </th>
                    <th class="py-2 font-semibold">
                        {{ t(copy, 'departures.price') }}
                    </th>
                    <th class="py-2 font-semibold">
                        {{ t(copy, 'departures.deadline') }}
                    </th>
                    <th class="py-2 font-semibold">
                        {{ t(copy, 'departures.status') }}
                    </th>
                    <th class="py-2" />
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="departure in upcoming"
                    :key="departure.id"
                    class="border-t border-ink/10"
                >
                    <td class="py-2 text-ink">
                        {{ formatRange(departure.start_date, departure.end_date, calendar) }}
                    </td>
                    <td class="py-2 text-ink">
                        {{ t(copy, 'departures.seats_taken', { taken: departure.seats_taken, capacity: departure.capacity }) }}
                    </td>
                    <td class="py-2 text-ink">
                        {{ priceLabel(departure) }}
                    </td>
                    <td class="py-2 text-ink">
                        {{ deadlineLabel(departure) }}
                    </td>
                    <td class="py-2">
                        <StatusBadge
                            :status="departure.display_status"
                            :label="t(copy, `status.${departure.display_status}`)"
                        />
                    </td>
                    <td class="py-2 text-right">
                        <button
                            type="button"
                            class="min-h-11 px-2 text-sogan underline"
                            @click="emit('quick', departure)"
                        >
                            {{ t(copy, 'quick_edit.title') }}
                        </button>
                        <button
                            type="button"
                            class="min-h-11 px-2 text-sogan underline"
                            @click="emit('edit', departure)"
                        >
                            {{ t(copy, 'departures.edit') }}
                        </button>
                        <button
                            v-if="departure.can_delete"
                            type="button"
                            class="min-h-11 px-2 text-status-rejected underline"
                            @click="emit('delete', departure)"
                        >
                            {{ t(copy, 'departures.delete') }}
                        </button>
                    </td>
                </tr>
            </tbody>
        </table>

        <details
            v-if="past.length"
            class="mt-4"
        >
            <summary class="flex min-h-11 cursor-pointer items-center font-heading text-sm font-semibold text-secondary">
                {{ t(copy, 'departures.past_group') }} ({{ past.length }})
            </summary>
            <ul class="mt-2 flex flex-col gap-2">
                <li
                    v-for="departure in past"
                    :key="departure.id"
                    class="flex items-center justify-between gap-3 rounded-lg border border-ink/10 px-3 py-2 text-sm text-secondary"
                >
                    <span>
                        {{ formatRange(departure.start_date, departure.end_date, calendar) }} ·
                        {{ t(copy, 'departures.seats_taken', { taken: departure.seats_taken, capacity: departure.capacity }) }}
                    </span>
                    <StatusBadge
                        status="past"
                        :label="t(copy, 'status.past')"
                    />
                </li>
            </ul>
        </details>
    </div>
</template>
