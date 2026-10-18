<script setup>
import { computed, nextTick, ref } from 'vue';
import { addItem, limitLength, removeItem, toggleItem } from '../lib/listInput.js';
import { t } from '../lib/text.js';

/**
 * Termasuk / Tidak termasuk (mockup 4c, AC-6): suggestion chips, "+ Lainnya"
 * free text up to 40 characters, at most 20 items.
 */
const props = defineProps({
    modelValue: { type: Array, required: true },
    id: { type: String, required: true },
    label: { type: String, required: true },
    suggestions: { type: Array, default: () => [] },
    maxItems: { type: Number, default: 20 },
    maxLength: { type: Number, default: 40 },
    copy: { type: Object, required: true },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const adding = ref(false);
const draft = ref('');
const full = ref(false);
const input = ref(null);

const custom = computed(() => props.modelValue.filter((item) => !props.suggestions.includes(item)));
const options = computed(() => ({ maxItems: props.maxItems, maxLength: props.maxLength }));

function apply(result) {
    full.value = result.error === 'full';
    emit('update:modelValue', result.list);
}

async function startAdding() {
    adding.value = true;
    await nextTick();
    input.value?.focus();
}

function onDraft(event) {
    draft.value = limitLength(event.target.value, props.maxLength);
    event.target.value = draft.value;
}

function commit() {
    apply(addItem(props.modelValue, draft.value, options.value));
    draft.value = '';
}
</script>

<template>
    <fieldset>
        <legend class="mb-2 font-heading text-sm font-semibold text-ink">
            {{ label }}
        </legend>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="item in suggestions"
                :key="item"
                type="button"
                class="min-h-11 rounded-full border px-4 text-sm"
                :class="modelValue.includes(item) ? 'border-ink bg-ink text-surface' : 'border-ink/20 bg-surface text-ink'"
                :aria-pressed="modelValue.includes(item) ? 'true' : 'false'"
                @click="apply(toggleItem(modelValue, item, options))"
            >
                <span
                    v-if="modelValue.includes(item)"
                    aria-hidden="true"
                >✓ </span>{{ item }}
            </button>
            <span
                v-for="item in custom"
                :key="item"
                class="inline-flex min-h-11 items-center gap-1 rounded-full border border-ink bg-ink pl-4 text-sm text-surface"
            >
                {{ item }}
                <button
                    type="button"
                    class="min-h-11 min-w-11 rounded-full"
                    :aria-label="t(copy, 'editor.list_remove', { item })"
                    @click="emit('update:modelValue', removeItem(modelValue, item))"
                >
                    ×
                </button>
            </span>
            <button
                v-if="!adding"
                type="button"
                class="min-h-11 rounded-full border border-dashed border-ink/40 px-4 text-sm text-ink"
                @click="startAdding"
            >
                {{ t(copy, 'editor.list_other') }}
            </button>
        </div>

        <div
            v-if="adding"
            class="mt-2 flex items-center gap-2"
        >
            <div class="relative flex-1">
                <input
                    :id="id"
                    ref="input"
                    type="text"
                    class="min-h-11 w-full rounded-lg border border-ink/20 bg-surface px-3 pr-14 text-base text-ink"
                    :maxlength="maxLength"
                    :value="draft"
                    :placeholder="t(copy, 'editor.list_placeholder')"
                    :aria-label="label"
                    :aria-describedby="`${id}-counter`"
                    @input="onDraft"
                    @keydown.enter.prevent="commit"
                >
                <span
                    :id="`${id}-counter`"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-secondary"
                >{{ t(copy, 'editor.list_counter', { n: Array.from(draft).length, max: maxLength }) }}</span>
            </div>
            <button
                type="button"
                class="min-h-11 rounded-lg border border-ink px-4 text-sm font-semibold text-ink"
                @click="commit"
            >
                {{ t(copy, 'editor.list_add') }}
            </button>
        </div>

        <p
            v-if="full || error"
            class="mt-1 text-xs text-status-rejected"
            role="alert"
        >
            {{ error || t(copy, 'editor.list_full', { max: maxItems }) }}
        </p>
    </fieldset>
</template>
