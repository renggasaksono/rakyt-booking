<script setup>
import { computed } from 'vue';
import { t } from '../lib/text.js';

/**
 * Publish checklist and the trip's one gold action (AC-9, AC-11, AC-12, AC-15).
 */
const props = defineProps({
    checklist: { type: Object, required: true },
    published: { type: Boolean, default: false },
    canPublish: { type: Boolean, default: true },
    paymentSetupUrl: { type: String, default: null },
    copy: { type: Object, required: true },
    busy: { type: Boolean, default: false },
    error: { type: String, default: null },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['publish']);

const remaining = computed(() => props.checklist.total - props.checklist.done);
</script>

<template>
    <section :aria-label="t(copy, 'checklist.title', { done: checklist.done, total: checklist.total })">
        <h2 class="font-heading text-sm font-semibold text-ink">
            {{ t(copy, 'checklist.title', { done: checklist.done, total: checklist.total }) }}
        </h2>
        <ul
            v-if="!compact"
            class="mt-2 flex flex-col gap-2 text-sm"
        >
            <li
                v-for="item in checklist.items"
                :key="item.key"
                class="flex items-center gap-2"
                :class="item.done ? 'text-ink' : 'text-secondary'"
            >
                <span
                    aria-hidden="true"
                    :class="item.done ? 'text-status-paid' : ''"
                >{{ item.done ? '✓' : '○' }}</span>
                <span>{{ t(copy, `checklist.items.${item.key}`) }}</span>
                <a
                    v-if="item.key === 'payment_instructions' && !item.done && paymentSetupUrl"
                    :href="paymentSetupUrl"
                    class="text-sogan underline"
                >— {{ t(copy, 'checklist.payment_link') }}</a>
            </li>
        </ul>

        <template v-if="!published">
            <button
                type="button"
                class="mt-3 min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                :disabled="remaining > 0 || !canPublish || busy"
                :aria-busy="busy ? 'true' : 'false'"
                @click="emit('publish')"
            >
                {{ t(copy, 'checklist.publish') }}
            </button>
            <p class="mt-1 text-center text-xs text-secondary">
                {{ remaining > 0 ? t(copy, 'checklist.remaining', { n: remaining }) : t(copy, 'checklist.ready') }}
            </p>
        </template>
        <p
            v-else
            class="mt-2 text-xs text-status-paid"
        >
            <span aria-hidden="true">●</span> {{ t(copy, 'checklist.published') }}
        </p>
        <p
            v-if="error"
            class="mt-1 text-xs text-status-rejected"
            role="alert"
        >
            {{ error }}
        </p>
    </section>
</template>
