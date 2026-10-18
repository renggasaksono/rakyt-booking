<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * Bottom sheet on phones, centred dialog on larger screens.
 */
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    closeLabel: { type: String, required: true },
});

const emit = defineEmits(['close']);
const panel = ref(null);

function onKey(event) {
    if (event.key === 'Escape') {
        emit('close');
    }
}

onMounted(async () => {
    document.addEventListener('keydown', onKey);
    await nextTick();
    panel.value?.focus();
});

onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <div
        class="fixed inset-0 z-40 flex items-end justify-center bg-ink/40 sm:items-center"
        @click.self="emit('close')"
    >
        <div
            ref="panel"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
            tabindex="-1"
            class="max-h-[92vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-surface p-5 shadow-xl outline-none sm:rounded-2xl"
        >
            <div class="mb-4 flex items-start justify-between gap-3">
                <div>
                    <h2 class="font-heading text-lg font-semibold text-ink">
                        {{ title }}
                    </h2>
                    <p
                        v-if="subtitle"
                        class="text-sm text-secondary"
                    >
                        {{ subtitle }}
                    </p>
                </div>
                <button
                    type="button"
                    class="-m-2 min-h-11 min-w-11 rounded-full p-2 text-xl text-ink"
                    :aria-label="closeLabel"
                    @click="emit('close')"
                >
                    ×
                </button>
            </div>
            <slot />
        </div>
    </div>
</template>
