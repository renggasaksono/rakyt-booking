<script setup>
import { usePage } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

const page = usePage();
const toast = ref(null);
const notice = ref(null);
let timer = null;

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.toast) {
            toast.value = flash.toast;
            clearTimeout(timer);
            timer = setTimeout(() => (toast.value = null), 3000);
        }

        notice.value = flash?.notice ?? null;
    },
    { immediate: true, deep: true },
);

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div
        class="pointer-events-none fixed inset-x-0 top-3 z-50 flex flex-col items-center gap-2 px-4"
        aria-live="polite"
    >
        <p
            v-if="toast"
            class="pointer-events-auto rounded-full bg-ink px-4 py-2 text-sm text-surface shadow-lg"
            role="status"
        >
            {{ toast }}
        </p>
        <div
            v-if="notice"
            class="pointer-events-auto flex max-w-md items-start gap-3 rounded-xl border border-status-check/30 bg-status-check-tint px-4 py-3 text-sm text-status-check shadow"
            role="status"
        >
            <span aria-hidden="true">◐</span>
            <span class="flex-1">{{ notice }}</span>
            <button
                type="button"
                class="-m-2 min-h-11 min-w-11 p-2 text-status-check"
                :aria-label="page.props.seller?.close_label"
                @click="notice = null"
            >
                ×
            </button>
        </div>
    </div>
</template>
