<script setup>
import { computed, ref } from 'vue';
import { firstError, sendJson, upload } from '../lib/http.js';
import { t } from '../lib/text.js';

/**
 * Trip photos (mockup 1f §01, AC-7): gallery or camera, progress per photo,
 * first = cover, reorder and remove. The server strips EXIF and checks
 * type, size and the 10-photo limit; the same checks run here first.
 */
const props = defineProps({
    photos: { type: Array, required: true },
    uploadUrl: { type: String, default: null },
    reorderUrl: { type: String, default: null },
    limits: { type: Object, required: true },
    copy: { type: Object, required: true },
});

const emit = defineEmits(['changed']);

const uploads = ref([]);
const error = ref(null);
const input = ref(null);
let sequence = 0;

const count = computed(() => props.photos.length + uploads.value.filter((item) => !item.error).length);

function pick() {
    error.value = null;
    input.value?.click();
}

function check(file, slotsLeft) {
    if (!props.limits.photo_types.includes(file.type)) {
        return t(props.copy, 'errors.photo_type');
    }

    if (file.size > props.limits.photo_max_kb * 1024) {
        return t(props.copy, 'errors.photo_size');
    }

    if (slotsLeft <= 0) {
        return t(props.copy, 'errors.photo_limit', { max: props.limits.photos });
    }

    return null;
}

async function onFiles(event) {
    const files = Array.from(event.target.files ?? []);
    event.target.value = '';

    for (const file of files) {
        const item = { id: ++sequence, name: file.name, progress: 0, error: check(file, props.limits.photos - count.value) };
        uploads.value.push(item);

        if (item.error) {
            continue;
        }

        upload(props.uploadUrl, 'photo', file, (progress) => (item.progress = progress))
            .then((payload) => {
                uploads.value = uploads.value.filter((entry) => entry.id !== item.id);
                emit('changed', payload);
            })
            .catch((failure) => {
                item.error = firstError(failure, t(props.copy, 'photos.failed'));
            });
    }
}

function dismiss(item) {
    uploads.value = uploads.value.filter((entry) => entry.id !== item.id);
}

async function reorder(ids) {
    try {
        emit('changed', await sendJson('PUT', props.reorderUrl, { photos: ids }));
    } catch (failure) {
        error.value = firstError(failure, t(props.copy, 'errors.generic'));
    }
}

function move(index, offset) {
    const ids = props.photos.map((photo) => photo.id);
    const [moved] = ids.splice(index, 1);
    ids.splice(Math.max(0, Math.min(ids.length, index + offset)), 0, moved);
    reorder(ids);
}

async function remove(photo) {
    try {
        emit('changed', await sendJson('DELETE', photo.destroy_url));
    } catch (failure) {
        error.value = firstError(failure, t(props.copy, 'errors.generic'));
    }
}
</script>

<template>
    <div>
        <p
            v-if="!uploadUrl"
            class="rounded-lg border border-dashed border-ink/30 p-4 text-sm text-secondary"
        >
            {{ t(copy, 'photos.save_first') }}
        </p>
        <template v-else>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <li
                    v-for="(photo, index) in photos"
                    :key="photo.id"
                    class="overflow-hidden rounded-lg border border-ink/10 bg-surface"
                >
                    <div class="relative aspect-[4/3] bg-gading">
                        <img
                            :src="photo.url"
                            alt=""
                            class="h-full w-full object-cover"
                            loading="lazy"
                        >
                        <span
                            v-if="index === 0"
                            class="absolute left-2 top-2 rounded-full bg-ink px-2 py-0.5 text-xs font-semibold text-surface"
                        >{{ t(copy, 'photos.cover') }}</span>
                    </div>
                    <div class="flex">
                        <button
                            type="button"
                            class="min-h-11 flex-1 text-sm text-ink disabled:text-secondary"
                            :disabled="index === 0"
                            :aria-label="t(copy, 'photos.move_earlier')"
                            :title="t(copy, 'photos.move_earlier')"
                            @click="move(index, -1)"
                        >
                            ←
                        </button>
                        <button
                            type="button"
                            class="min-h-11 flex-1 text-sm text-ink disabled:text-secondary"
                            :disabled="index === photos.length - 1"
                            :aria-label="t(copy, 'photos.move_later')"
                            :title="t(copy, 'photos.move_later')"
                            @click="move(index, 1)"
                        >
                            →
                        </button>
                        <button
                            type="button"
                            class="min-h-11 flex-1 text-sm text-status-rejected"
                            :aria-label="t(copy, 'photos.remove')"
                            :title="t(copy, 'photos.remove')"
                            @click="remove(photo)"
                        >
                            ×
                        </button>
                    </div>
                </li>
                <li
                    v-for="item in uploads"
                    :key="`upload-${item.id}`"
                    class="flex aspect-[4/3] flex-col justify-center rounded-lg border border-ink/10 bg-surface p-3 text-xs"
                >
                    <p class="truncate text-ink">
                        {{ item.name }}
                    </p>
                    <template v-if="item.error">
                        <p
                            class="mt-1 text-status-rejected"
                            role="alert"
                        >
                            {{ item.error }}
                        </p>
                        <button
                            type="button"
                            class="mt-1 min-h-11 text-left text-sogan underline"
                            :aria-label="t(copy, 'quick_edit.close')"
                            @click="dismiss(item)"
                        >
                            ×
                        </button>
                    </template>
                    <template v-else>
                        <p class="mt-1 text-secondary">
                            {{ t(copy, 'photos.uploading') }} {{ item.progress }}%
                        </p>
                        <div
                            class="mt-2 h-2 rounded-full bg-gading"
                            role="progressbar"
                            :aria-valuenow="item.progress"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-2 rounded-full bg-sogan"
                                :style="{ width: `${item.progress}%` }"
                            />
                        </div>
                    </template>
                </li>
            </ul>

            <div class="mt-3 flex items-center justify-between gap-3">
                <button
                    type="button"
                    class="min-h-11 rounded-lg border border-ink px-4 text-sm font-semibold text-ink disabled:border-ink/30 disabled:text-secondary"
                    :disabled="count >= limits.photos"
                    @click="pick"
                >
                    {{ t(copy, 'photos.add') }}
                </button>
                <span class="text-xs text-secondary">{{ t(copy, 'photos.counter', { n: photos.length, max: limits.photos }) }}</span>
            </div>
            <input
                ref="input"
                type="file"
                class="hidden"
                :accept="limits.photo_types.join(',')"
                multiple
                @change="onFiles"
            >
            <p
                v-if="error"
                class="mt-1 text-xs text-status-rejected"
                role="alert"
            >
                {{ error }}
            </p>
        </template>
    </div>
</template>
