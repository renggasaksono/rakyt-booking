<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import DurationPicker from '../../components/DurationPicker.vue';
import FieldError from '../../components/FieldError.vue';
import ListInput from '../../components/ListInput.vue';
import PendingPhotos from '../../components/PendingPhotos.vue';
import PhotoManager from '../../components/PhotoManager.vue';
import StickyAction from '../../components/StickyAction.vue';
import OnboardingLayout from '../../layouts/OnboardingLayout.vue';
import { sendJson } from '../../lib/http.js';
import { t } from '../../lib/text.js';

/**
 * Langkah 2 dari 4 · Trip (mockup 4c, F-001 C2–C4). Saved as a draft in the
 * seller's organization through F-003's trip rules. No description field.
 */
const props = defineProps({
    trip: { type: Object, default: null },
    options: { type: Object, required: true },
    header: { type: Object, required: true },
    highlight: { type: String, default: null },
    urls: { type: Object, required: true },
    copy: { type: Object, required: true },
    tripCopy: { type: Object, required: true },
});

const form = reactive({
    type: props.trip?.type ?? null,
    name: props.trip?.name ?? '',
    duration_label: props.trip?.duration_label ?? null,
    duration_days: props.trip?.duration_days ?? null,
    duration_nights: props.trip?.duration_nights ?? null,
    inclusions: props.trip?.inclusions ? [...props.trip.inclusions] : [],
});

const photos = ref(props.trip?.photos ?? []);
const urlState = ref({ photos: props.urls.photos, photos_reorder: props.urls.photos_reorder });
const pending = ref(null);
const pendingCount = ref(0);
const errors = ref({});
const processing = ref(false);
const failed = ref(false);

const duration = computed({
    get: () => ({ duration_label: form.duration_label, duration_days: form.duration_days, duration_nights: form.duration_nights }),
    set: (value) => Object.assign(form, value),
});

// C2: choosing a type presets the duration (the seller can change it).
function chooseType(value) {
    form.type = value;
    const preset = props.options.types.find((type) => type.value === value);

    Object.assign(form, preset?.duration ?? { duration_label: null, duration_days: null, duration_nights: null });
}

function onPhotosChanged(payload) {
    photos.value = payload.photos ?? photos.value;
}

async function submit() {
    if (processing.value) {
        return;
    }

    processing.value = true;
    failed.value = false;
    errors.value = {};

    try {
        const payload = await sendJson('POST', props.urls.store, { ...form });
        urlState.value = payload.urls;

        if (pendingCount.value > 0) {
            const { failed: stillFailing } = await pending.value.uploadAll(payload.urls.photos);

            if (stillFailing > 0) {
                // The trip is saved; the failed photos stay with "Coba lagi".
                processing.value = false;

                return;
            }
        }

        router.visit(payload.urls.next);
    } catch (error) {
        if (error.status === 422) {
            errors.value = Object.fromEntries(Object.entries(error.body?.errors ?? {}).map(([key, messages]) => [key, messages[0]]));
        } else if (error.status === 429) {
            errors.value = { rate_limit: error.body?.message };
        } else {
            failed.value = true;
        }

        processing.value = false;
    }
}
</script>

<template>
    <Head :title="t(copy, 'trip.title')" />
    <OnboardingLayout
        :header="header"
        :copy="copy"
        :back-url="urls.back"
    >
        <h1 class="font-heading text-xl font-semibold text-ink">
            {{ t(copy, 'trip.title') }}
        </h1>

        <p
            v-if="$page.props.errors?.publish"
            class="mt-3 rounded-lg bg-status-rejected-tint px-3 py-2 text-sm text-status-rejected"
            role="alert"
        >
            {{ $page.props.errors.publish }}
        </p>

        <form
            id="trip-form"
            class="mt-5 flex flex-col gap-5"
            novalidate
            @submit.prevent="submit"
        >
            <fieldset>
                <legend class="font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'trip.type') }}
                </legend>
                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <label
                        v-for="type in options.types"
                        :key="type.value"
                        class="flex min-h-14 cursor-pointer items-center gap-3 rounded-lg border-2 bg-surface px-3 py-2"
                        :class="form.type === type.value ? 'border-sogan' : 'border-ink/15'"
                    >
                        <input
                            type="radio"
                            name="type"
                            class="h-5 w-5 shrink-0"
                            :value="type.value"
                            :checked="form.type === type.value"
                            @change="chooseType(type.value)"
                        >
                        <span>
                            <span class="block font-heading text-sm font-semibold text-ink">{{ t(copy, `trip.types.${type.value}.label`) }}</span>
                            <span class="block text-xs text-secondary">{{ t(copy, `trip.types.${type.value}.hint`) }}</span>
                        </span>
                    </label>
                </div>
                <FieldError :message="errors.type" />
            </fieldset>

            <section
                :class="highlight === 'cover_photo' ? 'rounded-lg ring-2 ring-status-rejected ring-offset-4 ring-offset-gading' : ''"
            >
                <h2 class="font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'trip.photos') }}
                </h2>
                <p class="mb-2 text-xs text-secondary">
                    {{ t(copy, 'trip.photos_hint') }}
                </p>
                <!-- Before the trip exists, photos wait in the browser (C3). -->
                <PendingPhotos
                    v-if="!urlState.photos || pendingCount > 0"
                    ref="pending"
                    :upload-url="urlState.photos"
                    :limits="options.limits"
                    :copy="copy"
                    :trip-copy="tripCopy"
                    @count="(n) => (pendingCount = n)"
                    @uploaded="onPhotosChanged"
                />
                <PhotoManager
                    v-else
                    :photos="photos"
                    :upload-url="urlState.photos"
                    :reorder-url="urlState.photos_reorder"
                    :limits="options.limits"
                    :copy="tripCopy"
                    @changed="onPhotosChanged"
                />
            </section>

            <div>
                <label
                    for="name"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'trip.name') }}</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    :maxlength="options.limits.name"
                    :placeholder="t(copy, 'trip.name_placeholder')"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                    :class="errors.name || highlight === 'name' ? 'border-status-rejected' : 'border-ink/20'"
                    :aria-invalid="Boolean(errors.name) || undefined"
                    aria-describedby="name-error"
                >
                <FieldError
                    id="name-error"
                    :message="errors.name"
                />
            </div>

            <div>
                <p class="font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'trip.duration') }}
                </p>
                <DurationPicker
                    v-model="duration"
                    class="mt-1"
                    :presets="options.durations"
                    :max-days="options.limits.max_days"
                    :copy="tripCopy"
                    :error="errors.duration_label ?? errors.duration_days ?? errors.duration_nights ?? null"
                />
            </div>

            <ListInput
                id="inclusions"
                v-model="form.inclusions"
                :label="t(copy, 'trip.inclusions')"
                :suggestions="options.inclusion_suggestions"
                :max-items="options.limits.list_items"
                :max-length="options.limits.list_item"
                :copy="tripCopy"
                :error="errors.inclusions ?? Object.entries(errors).find(([key]) => key.startsWith('inclusions.'))?.[1] ?? null"
            />

            <FieldError :message="errors.rate_limit" />
            <FieldError :message="failed ? t(copy, 'save_failed') : null" />
        </form>

        <template #action>
            <StickyAction>
                <button
                    type="submit"
                    form="trip-form"
                    class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                    :disabled="processing"
                    :aria-busy="processing"
                >
                    {{ processing ? t(copy, 'saving') : t(copy, 'trip.submit') }}
                </button>
            </StickyAction>
        </template>
    </OnboardingLayout>
</template>
