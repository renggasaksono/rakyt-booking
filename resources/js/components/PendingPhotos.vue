<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { firstError, upload } from '../lib/http.js';
import { t } from '../lib/text.js';

/**
 * Photos picked on onboarding step 2 before the draft trip exists (F-001 C3).
 * They are kept in the browser and uploaded one by one, with progress and a
 * retry, as soon as the first save has created the trip.
 */
const props = defineProps({
    // Set once the trip exists; enables "Coba lagi" on a failed photo.
    uploadUrl: { type: String, default: null },
    limits: { type: Object, required: true },
    copy: { type: Object, required: true },
    tripCopy: { type: Object, required: true },
});

const emit = defineEmits(['count', 'uploaded']);

const items = ref([]);
const error = ref(null);
const input = ref(null);
let sequence = 0;

watch(() => items.value.length, (count) => emit('count', count));

function pick() {
    error.value = null;
    input.value?.click();
}

function check(file) {
    if (!props.limits.photo_types.includes(file.type)) {
        return t(props.tripCopy, 'errors.photo_type');
    }

    if (file.size > props.limits.photo_max_kb * 1024) {
        return t(props.tripCopy, 'errors.photo_size');
    }

    if (items.value.length >= props.limits.photos) {
        return t(props.tripCopy, 'errors.photo_limit', { max: props.limits.photos });
    }

    return null;
}

function onFiles(event) {
    for (const file of Array.from(event.target.files ?? [])) {
        const problem = check(file);

        if (problem) {
            error.value = problem;
            continue;
        }

        items.value.push({ id: ++sequence, file, preview: URL.createObjectURL(file), progress: null, error: null });
    }

    event.target.value = '';
}

function remove(item) {
    URL.revokeObjectURL(item.preview);
    items.value = items.value.filter((entry) => entry.id !== item.id);
}

/**
 * Upload what is still pending, in order (first = cover). Resolves with
 * the last server payload; failed items stay with a retry.
 */
async function uploadAll(url) {
    let payload = null;

    for (const item of [...items.value]) {
        payload = (await uploadOne(url, item)) ?? payload;
    }

    return { payload, failed: items.value.length };
}

async function uploadOne(url, item) {
    item.error = null;
    item.progress = 0;

    try {
        const payload = await upload(url, 'photo', item.file, (progress) => (item.progress = progress));
        remove(item);
        emit('uploaded', payload);

        return payload;
    } catch (failure) {
        item.progress = null;
        item.error = firstError(failure, t(props.tripCopy, 'photos.failed'));

        return null;
    }
}

onBeforeUnmount(() => items.value.forEach((item) => URL.revokeObjectURL(item.preview)));

defineExpose({ uploadAll, count: () => items.value.length });
</script>

<template>
    <div>
        <ul
            v-if="items.length"
            class="mb-3 grid grid-cols-3 gap-2"
        >
            <li
                v-for="(item, index) in items"
                :key="item.id"
                class="relative overflow-hidden rounded-lg border border-ink/10 bg-surface"
            >
                <img
                    :src="item.preview"
                    alt=""
                    class="aspect-square w-full object-cover"
                >
                <span
                    v-if="index === 0"
                    class="absolute left-1 top-1 rounded-full bg-ink px-2 py-0.5 text-[11px] font-semibold text-surface"
                >{{ t(copy, 'trip.cover') }}</span>
                <p
                    v-if="item.progress !== null"
                    class="absolute inset-x-0 bottom-0 bg-ink/80 px-1 py-0.5 text-center text-xs text-surface"
                    aria-live="polite"
                >
                    {{ t(copy, 'trip.uploading', { progress: item.progress }) }}
                </p>
                <div
                    v-if="item.error"
                    class="absolute inset-x-0 bottom-0 bg-status-rejected-tint px-1 py-0.5 text-xs text-status-rejected"
                    role="alert"
                >
                    {{ item.error }}
                    <button
                        v-if="uploadUrl"
                        type="button"
                        class="min-h-11 font-semibold underline"
                        @click="uploadOne(uploadUrl, item)"
                    >
                        {{ t(copy, 'trip.retry') }}
                    </button>
                </div>
                <button
                    type="button"
                    class="absolute right-0 top-0 flex min-h-11 min-w-11 items-center justify-center text-lg text-surface drop-shadow"
                    :aria-label="t(copy, 'trip.remove_photo')"
                    @click="remove(item)"
                >
                    ×
                </button>
            </li>
        </ul>
        <button
            type="button"
            class="min-h-12 w-full rounded-lg border-2 border-dashed border-ink/30 bg-surface px-4 font-heading text-sm font-semibold text-ink"
            @click="pick"
        >
            {{ t(copy, 'trip.add_photos') }}
        </button>
        <input
            ref="input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
            @change="onFiles"
        >
        <p
            v-if="error"
            class="mt-1 text-xs text-status-rejected"
            role="alert"
        >
            {{ error }}
        </p>
    </div>
</template>
