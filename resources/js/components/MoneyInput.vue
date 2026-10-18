<script setup>
import { computed } from 'vue';
import { formatRupiah, parseRupiah } from '../lib/money.js';

const props = defineProps({
    modelValue: { type: Number, default: null },
    id: { type: String, required: true },
    currency: { type: String, default: 'Rp' },
    placeholder: { type: String, default: '' },
    invalid: { type: Boolean, default: false },
    describedby: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue', 'blur']);

const display = computed(() => formatRupiah(props.modelValue));

function onInput(event) {
    const value = parseRupiah(event.target.value);
    // Re-render the formatted value even when the number did not change.
    event.target.value = formatRupiah(value);
    emit('update:modelValue', value);
}
</script>

<template>
    <div
        class="flex min-h-11 items-center rounded-lg border bg-surface focus-within:ring-2 focus-within:ring-sogan"
        :class="invalid ? 'border-status-rejected' : 'border-ink/20'"
    >
        <span
            class="pl-3 text-sm text-secondary"
            aria-hidden="true"
        >{{ currency }}</span>
        <input
            :id="id"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            pattern="[0-9.]*"
            class="min-h-11 w-full min-w-0 rounded-lg bg-transparent px-2 text-base text-ink outline-none"
            :value="display"
            :placeholder="placeholder"
            :aria-invalid="invalid ? 'true' : undefined"
            :aria-describedby="describedby"
            @input="onInput"
            @blur="emit('blur')"
        >
    </div>
</template>
