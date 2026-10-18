<script setup>
const props = defineProps({
    modelValue: { type: Number, default: null },
    id: { type: String, required: true },
    min: { type: Number, default: 1 },
    max: { type: Number, default: 999 },
    decreaseLabel: { type: String, required: true },
    increaseLabel: { type: String, required: true },
    describedby: { type: String, default: undefined },
});

const emit = defineEmits(['update:modelValue', 'below-min', 'above-max']);

function set(value) {
    if (value < props.min) {
        emit('below-min', value);
        emit('update:modelValue', props.min);

        return;
    }

    if (value > props.max) {
        emit('above-max', value);
        emit('update:modelValue', props.max);

        return;
    }

    emit('update:modelValue', value);
}

function onInput(event) {
    const digits = event.target.value.replace(/\D+/g, '');

    if (digits === '') {
        emit('update:modelValue', null);

        return;
    }

    set(Number(digits));
}
</script>

<template>
    <div class="flex items-center gap-2">
        <button
            type="button"
            class="flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-ink/20 bg-surface text-xl text-ink disabled:text-secondary"
            :aria-label="decreaseLabel"
            :disabled="modelValue !== null && modelValue <= min"
            @click="set((modelValue ?? min) - 1)"
        >
            −
        </button>
        <input
            :id="id"
            type="text"
            inputmode="numeric"
            pattern="[0-9]*"
            class="min-h-11 w-20 rounded-lg border border-ink/20 bg-surface text-center text-base text-ink"
            :value="modelValue ?? ''"
            :aria-describedby="describedby"
            @input="onInput"
        >
        <button
            type="button"
            class="flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-ink/20 bg-surface text-xl text-ink disabled:text-secondary"
            :aria-label="increaseLabel"
            :disabled="modelValue !== null && modelValue >= max"
            @click="set((modelValue ?? min - 1) + 1)"
        >
            +
        </button>
    </div>
</template>
