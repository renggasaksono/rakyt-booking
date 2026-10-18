<script setup>
import { ref } from 'vue';

/**
 * Password with a "Lihat" show/hide toggle instead of a confirmation field
 * (F-001 A1, Q14). autocomplete tells password managers which kind it is (P3).
 */
defineProps({
    modelValue: { type: String, default: '' },
    id: { type: String, required: true },
    autocomplete: { type: String, required: true },
    invalid: { type: Boolean, default: false },
    describedby: { type: String, default: undefined },
    labels: { type: Object, required: true },
});

defineEmits(['update:modelValue']);

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <input
            :id="id"
            :value="modelValue"
            :type="visible ? 'text' : 'password'"
            :autocomplete="autocomplete"
            :aria-invalid="invalid || undefined"
            :aria-describedby="describedby"
            class="min-h-12 w-full rounded-lg border bg-surface py-2 pl-3 pr-28 text-base text-ink"
            :class="invalid ? 'border-status-rejected' : 'border-ink/20'"
            autocapitalize="none"
            spellcheck="false"
            @input="$emit('update:modelValue', $event.target.value)"
        >
        <button
            type="button"
            class="absolute inset-y-0 right-0 flex min-h-12 min-w-11 items-center px-3 font-heading text-sm font-medium text-sogan underline"
            :aria-label="visible ? labels.hide_label : labels.show_label"
            :aria-pressed="visible"
            @click="visible = !visible"
        >
            {{ visible ? labels.hide : labels.show }}
        </button>
    </div>
</template>
