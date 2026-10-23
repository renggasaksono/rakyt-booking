<script setup>
import { ref } from 'vue';
import { isOpen, toggle } from '../../lib/faq.js';

/**
 * Landing FAQ accordion (F-017 AC-16). Single-open; logic in lib/faq.js.
 */
defineProps({
    items: { type: Array, required: true },
});

const open = ref(null);

function onToggle(index) {
    open.value = toggle(open.value, index);
}
</script>

<template>
    <ul class="mt-6 divide-y divide-ink/10 border-y border-ink/10">
        <li
            v-for="(item, index) in items"
            :key="index"
        >
            <h3>
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-4 py-4 text-left"
                    :aria-expanded="isOpen(open, index)"
                    @click="onToggle(index)"
                >
                    <span class="font-heading text-base font-semibold text-ink">{{ item.q }}</span>
                    <span
                        class="flex-none font-heading text-lg text-secondary"
                        aria-hidden="true"
                    >{{ isOpen(open, index) ? '–' : '+' }}</span>
                </button>
            </h3>
            <p
                v-if="isOpen(open, index)"
                class="pb-4 text-sm leading-relaxed text-secondary"
            >
                {{ item.a }}
            </p>
        </li>
    </ul>
</template>
