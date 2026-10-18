<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import DepartureForm from '../../components/DepartureForm.vue';
import DepartureList from '../../components/DepartureList.vue';
import DurationPicker from '../../components/DurationPicker.vue';
import ListInput from '../../components/ListInput.vue';
import MoneyInput from '../../components/MoneyInput.vue';
import PhotoManager from '../../components/PhotoManager.vue';
import PublishPanel from '../../components/PublishPanel.vue';
import QuickEditSheet from '../../components/QuickEditSheet.vue';
import TripPreviewCard from '../../components/TripPreviewCard.vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { createAutosave } from '../../lib/autosave.js';
import { sendJson } from '../../lib/http.js';
import { t } from '../../lib/text.js';

/**
 * Trip editor (mockups 1f, 3a, 3b; F-003). Phone first: sections stack and the
 * checklist + "Terbitkan trip" sit in a sticky bottom bar (AC-41); from lg the
 * preview and checklist move to a side column.
 *
 * Drafts autosave (AC-3); published trips save with "Simpan perubahan" (AC-4).
 */
const props = defineProps({
    trip: { type: Object, default: null },
    departures: { type: Object, required: true },
    copyTemplate: { type: Object, default: null },
    checklist: { type: Object, required: true },
    preview: { type: Object, default: null },
    context: { type: Object, required: true },
    options: { type: Object, required: true },
    urls: { type: Object, required: true },
    copy: { type: Object, required: true },
});

const FIELDS = [
    'type', 'booking_mode', 'name', 'short_description', 'duration_label', 'duration_days', 'duration_nights',
    'inclusions', 'exclusions', 'meeting_point', 'meeting_time', 'map_url', 'itinerary', 'terms', 'base_price',
];

function initialForm(trip) {
    if (trip) {
        // Props are reactive proxies; copy plain values.
        return Object.fromEntries(FIELDS.map((field) => [field, JSON.parse(JSON.stringify(trip[field] ?? null))]));
    }

    const preset = props.options.types[0];

    return {
        type: preset.value,
        booking_mode: preset.booking_mode,
        name: '',
        short_description: null,
        duration_label: preset.duration?.duration_label ?? null,
        duration_days: preset.duration?.duration_days ?? null,
        duration_nights: preset.duration?.duration_nights ?? null,
        inclusions: [],
        exclusions: [],
        meeting_point: null,
        meeting_time: null,
        map_url: null,
        itinerary: [],
        terms: null,
        base_price: null,
    };
}

// Local state: the form and what the server last told us.
const form = reactive(initialForm(props.trip));
const tripState = ref(props.trip);
const urlState = ref({ ...props.urls });
const checklistState = ref(props.checklist);
const previewState = ref(props.preview);
const departuresState = ref(props.departures);
const errors = ref({});
const notices = ref([]);
const saveState = ref({ state: tripState.value ? 'saved' : 'idle', savedAt: null });
const publishError = ref(null);
const publishing = ref(false);
const showPreview = ref(false);
const departureSheet = ref(null);
const quickSheet = ref(null);
const typeTouched = ref(Boolean(props.trip));

const isPublished = computed(() => tripState.value?.status === 'published');
const isScheduled = computed(() => form.booking_mode === 'scheduled');
const durationValue = computed(() => ({
    duration_label: form.duration_label,
    duration_days: form.duration_days,
    duration_nights: form.duration_nights,
}));
const itineraryDays = computed(() => Math.min(props.options.limits.max_days, Math.max(1, form.duration_days ?? 1, form.itinerary.length)));
const allDepartures = computed(() => [...departuresState.value.upcoming, ...departuresState.value.past]);
const quickDeparture = computed(() => (quickSheet.value ? allDepartures.value.find((d) => d.id === quickSheet.value) ?? null : null));
const editingDeparture = computed(() => (departureSheet.value?.id ? allDepartures.value.find((d) => d.id === departureSheet.value.id) ?? null : null));

// Inertia reloads (departures, publish) bring fresh server data.
watch(() => props.departures, (value) => (departuresState.value = value));
watch(() => props.checklist, (value) => (checklistState.value = value));
watch(() => props.preview, (value) => (previewState.value = value));
watch(() => props.trip, (value) => {
    if (value && value.status !== tripState.value?.status) {
        tripState.value = value;
    }
});

function applyServer(payload) {
    if (payload.trip) {
        tripState.value = payload.trip;
    }
    if (payload.checklist) {
        checklistState.value = payload.checklist;
    }
    if (payload.preview) {
        previewState.value = payload.preview;
    }
    if (payload.departures) {
        departuresState.value = payload.departures;
    }
    if (payload.photos && tripState.value) {
        tripState.value = { ...tripState.value, photos: payload.photos };
    }
    notices.value = payload.notices ?? notices.value;
}

async function saveDraft(fields) {
    errors.value = {};

    try {
        let payload;

        if (!tripState.value) {
            // AC-2: the first save creates the draft (name only is enough).
            payload = await sendJson('POST', urlState.value.store, { ...form, ...fields });
            urlState.value = { ...urlState.value, ...payload.editor.urls };
            // Client-side only: the page now lives at the trip's URL (refresh and back work).
            router.replace({
                url: payload.editor.urls.edit,
                props: (current) => ({ ...current, ...payload.editor }),
                preserveScroll: true,
                preserveState: true,
            });
        } else {
            payload = await sendJson('PATCH', urlState.value.autosave, fields);
        }

        applyServer(payload);

        return { savedAt: payload.saved_at };
    } catch (error) {
        if (error.status === 422) {
            errors.value = Object.fromEntries(Object.entries(error.body?.errors ?? {}).map(([key, messages]) => [key, messages[0]]));
        }

        throw error;
    }
}

const autosave = createAutosave({
    save: saveDraft,
    onChange: (value) => (saveState.value = value),
});

function changed(fields) {
    Object.assign(form, fields);

    if (isPublished.value) {
        saveState.value = { state: 'dirty', savedAt: saveState.value.savedAt };

        return;
    }

    // Nothing is saved until the trip has a name (AC-2).
    if (!tripState.value && !form.name?.trim()) {
        return;
    }

    autosave.queue(tripState.value ? fields : { ...form });
}

function blurSave() {
    if (!isPublished.value && (tripState.value || form.name?.trim())) {
        autosave.flush();
    }
}

function chooseType(type) {
    const preset = props.options.types.find((option) => option.value === type);
    const fields = { type };

    // AC-1: a type preset fills booking mode and duration on a new trip; all stay editable.
    if (!typeTouched.value || !tripState.value) {
        fields.booking_mode = preset.booking_mode;
        Object.assign(fields, preset.duration ?? { duration_label: null, duration_days: null, duration_nights: null });
    }

    changed(fields);
}

function setItinerary(index, value) {
    const lines = [...form.itinerary];
    while (lines.length <= index) {
        lines.push('');
    }
    lines[index] = value;
    changed({ itinerary: lines });
}

function saveChanges() {
    errors.value = {};
    router.put(urlState.value.update, { ...form }, {
        preserveScroll: true,
        onSuccess: (page) => {
            tripState.value = page.props.trip;
            saveState.value = { state: 'saved', savedAt: saveState.value.savedAt };
        },
        onError: (validation) => (errors.value = validation),
    });
}

function publish() {
    publishError.value = null;
    publishing.value = true;
    router.post(urlState.value.publish, {}, {
        preserveScroll: true,
        onError: (validation) => (publishError.value = validation.publish ?? Object.values(validation)[0]),
        onFinish: () => (publishing.value = false),
    });
}

function unpublish() {
    if (window.confirm(t(props.copy, 'editor.unpublish_confirm'))) {
        router.post(urlState.value.unpublish, {}, { preserveScroll: true });
    }
}

function destroyTrip() {
    if (window.confirm(t(props.copy, 'editor.delete_confirm'))) {
        router.delete(urlState.value.destroy);
    }
}

function deleteDeparture(departure) {
    if (window.confirm(t(props.copy, 'departures.delete_confirm'))) {
        router.delete(departure.urls.destroy, {
            preserveScroll: true,
            only: ['departures', 'checklist', 'preview', 'copyTemplate', 'flash'],
        });
    }
}

function openNewDeparture(copyPrevious = false) {
    departureSheet.value = { id: null, template: copyPrevious ? props.copyTemplate : { capacity: props.copyTemplate?.capacity ?? null } };
}

function onPhotos(payload) {
    applyServer(payload);
}

// AC-3: retry on reconnect; confirm before leaving with unsaved changes.
function onOnline() {
    if (saveState.value.state === 'error') {
        autosave.retry();
    }
}

function onBeforeUnload(event) {
    if (autosave.hasUnsaved() || (isPublished.value && saveState.value.state === 'dirty')) {
        event.preventDefault();
        event.returnValue = '';
    }
}

let removeRouterGuard = null;

onMounted(() => {
    window.addEventListener('online', onOnline);
    window.addEventListener('beforeunload', onBeforeUnload);
    removeRouterGuard = router.on('before', (event) => {
        const leaving = event.detail.visit.method === 'get';
        const unsaved = autosave.hasUnsaved() || (isPublished.value && saveState.value.state === 'dirty');

        if (leaving && unsaved && !window.confirm(t(props.copy, 'editor.leave_confirm'))) {
            event.preventDefault();
        }
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('online', onOnline);
    window.removeEventListener('beforeunload', onBeforeUnload);
    removeRouterGuard?.();
});

const statusLine = computed(() => {
    switch (saveState.value.state) {
        case 'saving':
            return t(props.copy, 'editor.saving');
        case 'error':
        case 'invalid':
            return t(props.copy, 'editor.unsaved');
        case 'saved':
            return saveState.value.savedAt ? t(props.copy, 'editor.saved_at', { time: saveState.value.savedAt }) : null;
        default:
            return null;
    }
});

const pageTitle = computed(() => form.name?.trim() || t(props.copy, 'editor.title_new'));
const inputClass = (field) => [
    'min-h-11 w-full rounded-lg border bg-surface px-3 text-base text-ink',
    errors.value[field] ? 'border-status-rejected' : 'border-ink/20',
];
</script>

<template>
    <Head :title="pageTitle" />
    <SellerLayout hide-bottom-nav>
        <div class="mx-auto max-w-6xl px-4 pb-40 pt-4 lg:pb-10">
            <header class="mb-4">
                <nav class="text-xs text-secondary">
                    <Link
                        :href="urls.index"
                        class="inline-flex min-h-11 items-center text-sogan underline"
                    >
                        {{ t(copy, 'editor.breadcrumb') }}
                    </Link>
                    <span aria-hidden="true"> / </span>
                    <span>{{ pageTitle }}</span>
                </nav>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 class="font-heading text-xl font-semibold text-ink">
                        {{ pageTitle }}
                    </h1>
                    <p
                        v-if="statusLine"
                        class="text-xs"
                        :class="['error', 'invalid'].includes(saveState.state) ? 'text-status-rejected' : 'text-secondary'"
                        aria-live="polite"
                    >
                        {{ statusLine }}
                        <button
                            v-if="saveState.state === 'error'"
                            type="button"
                            class="min-h-11 px-1 text-sogan underline"
                            @click="autosave.retry()"
                        >
                            · {{ t(copy, 'editor.retry') }}
                        </button>
                    </p>
                </div>
                <p
                    v-if="isPublished"
                    class="mt-2 rounded-lg bg-[#E4E9F2] px-3 py-2 text-xs text-status-check"
                >
                    <span aria-hidden="true">◐</span> {{ t(copy, 'editor.published_banner') }}
                </p>
                <p
                    v-for="notice in notices"
                    :key="notice"
                    class="mt-2 rounded-lg bg-[#E4E9F2] px-3 py-2 text-xs text-status-check"
                    role="status"
                >
                    <span aria-hidden="true">◐</span> {{ notice }}
                </p>
            </header>

            <div class="lg:grid lg:grid-cols-[minmax(0,1fr)_320px] lg:gap-8">
                <div class="flex flex-col gap-6">
                    <!-- 01 Foto -->
                    <section class="rounded-xl bg-surface p-4">
                        <h2 class="mb-1 font-heading text-base font-semibold text-ink">
                            <span class="text-secondary">01</span> {{ t(copy, 'editor.sections.photos') }}
                        </h2>
                        <p class="mb-3 text-xs text-secondary">
                            {{ t(copy, 'photos.hint') }}
                        </p>
                        <PhotoManager
                            :photos="tripState?.photos ?? []"
                            :upload-url="urlState.photos"
                            :reorder-url="urlState.photos_reorder"
                            :limits="options.limits"
                            :copy="copy"
                            @changed="onPhotos"
                        />
                    </section>

                    <!-- 02 Detail trip -->
                    <section class="flex flex-col gap-4 rounded-xl bg-surface p-4">
                        <h2 class="font-heading text-base font-semibold text-ink">
                            <span class="text-secondary">02</span> {{ t(copy, 'editor.sections.details') }}
                        </h2>

                        <fieldset>
                            <legend class="mb-2 font-heading text-sm font-semibold text-ink">
                                {{ t(copy, 'editor.type') }}
                            </legend>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                <button
                                    v-for="type in options.types"
                                    :key="type.value"
                                    type="button"
                                    class="min-h-14 rounded-lg border px-3 py-2 text-left"
                                    :class="form.type === type.value ? 'border-ink bg-ink text-surface' : 'border-ink/20 bg-surface text-ink'"
                                    :aria-pressed="form.type === type.value ? 'true' : 'false'"
                                    @click="chooseType(type.value)"
                                >
                                    <span class="block font-heading text-sm font-semibold">{{ t(copy, `editor.types.${type.value}.label`) }}</span>
                                    <span
                                        class="block text-xs"
                                        :class="form.type === type.value ? 'text-surface' : 'text-secondary'"
                                    >{{ t(copy, `editor.types.${type.value}.hint`) }}</span>
                                </button>
                            </div>
                        </fieldset>

                        <div>
                            <label
                                for="trip-name"
                                class="mb-1 block font-heading text-sm font-semibold text-ink"
                            >{{ t(copy, 'editor.name') }}</label>
                            <input
                                id="trip-name"
                                type="text"
                                :class="inputClass('name')"
                                :value="form.name"
                                :maxlength="options.limits.name"
                                :placeholder="t(copy, 'editor.name_placeholder')"
                                autocomplete="off"
                                @input="changed({ name: $event.target.value })"
                                @blur="blurSave"
                            >
                            <p
                                v-if="errors.name"
                                class="mt-1 text-xs text-status-rejected"
                                role="alert"
                            >
                                {{ errors.name }}
                            </p>
                            <p
                                v-else-if="!tripState && !form.name"
                                class="mt-1 text-xs text-secondary"
                            >
                                {{ t(copy, 'editor.name_first') }}
                            </p>
                        </div>

                        <div>
                            <p class="mb-1 font-heading text-sm font-semibold text-ink">
                                {{ t(copy, 'duration.label') }}
                            </p>
                            <DurationPicker
                                :model-value="durationValue"
                                :presets="options.durations"
                                :max-days="options.limits.max_days"
                                :copy="copy"
                                :error="errors.duration_label || errors.duration_days || errors.duration_nights || null"
                                @update:model-value="changed($event)"
                            />
                        </div>

                        <div>
                            <label
                                for="trip-description"
                                class="mb-1 block font-heading text-sm font-semibold text-ink"
                            >{{ t(copy, 'editor.short_description') }} <span class="font-normal text-secondary">({{ t(copy, 'editor.optional') }})</span></label>
                            <textarea
                                id="trip-description"
                                rows="3"
                                :class="inputClass('short_description')"
                                class="py-2"
                                :value="form.short_description ?? ''"
                                :maxlength="options.limits.short_description"
                                @input="changed({ short_description: $event.target.value })"
                                @blur="blurSave"
                            />
                        </div>

                        <ListInput
                            id="trip-inclusions"
                            :model-value="form.inclusions"
                            :label="t(copy, 'editor.inclusions')"
                            :suggestions="options.inclusion_suggestions"
                            :max-items="options.limits.list_items"
                            :max-length="options.limits.list_item"
                            :copy="copy"
                            :error="errors.inclusions || Object.entries(errors).find(([key]) => key.startsWith('inclusions.'))?.[1] || null"
                            @update:model-value="changed({ inclusions: $event })"
                        />
                        <ListInput
                            id="trip-exclusions"
                            :model-value="form.exclusions"
                            :label="t(copy, 'editor.exclusions')"
                            :max-items="options.limits.list_items"
                            :max-length="options.limits.list_item"
                            :copy="copy"
                            :error="errors.exclusions || Object.entries(errors).find(([key]) => key.startsWith('exclusions.'))?.[1] || null"
                            @update:model-value="changed({ exclusions: $event })"
                        />

                        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_140px]">
                            <div>
                                <label
                                    for="trip-meeting-point"
                                    class="mb-1 block font-heading text-sm font-semibold text-ink"
                                >{{ t(copy, 'editor.meeting_point') }}</label>
                                <input
                                    id="trip-meeting-point"
                                    type="text"
                                    :class="inputClass('meeting_point')"
                                    :value="form.meeting_point ?? ''"
                                    :maxlength="options.limits.meeting_point"
                                    @input="changed({ meeting_point: $event.target.value })"
                                    @blur="blurSave"
                                >
                            </div>
                            <div>
                                <label
                                    for="trip-meeting-time"
                                    class="mb-1 block font-heading text-sm font-semibold text-ink"
                                >{{ t(copy, 'editor.meeting_time') }}</label>
                                <input
                                    id="trip-meeting-time"
                                    type="time"
                                    :class="inputClass('meeting_time')"
                                    :value="form.meeting_time ?? ''"
                                    @change="changed({ meeting_time: $event.target.value || null })"
                                >
                            </div>
                            <p
                                v-if="errors.meeting_time"
                                class="text-xs text-status-rejected sm:col-span-2"
                                role="alert"
                            >
                                {{ errors.meeting_time }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="trip-map"
                                class="mb-1 block font-heading text-sm font-semibold text-ink"
                            >{{ t(copy, 'editor.map_url') }} <span class="font-normal text-secondary">({{ t(copy, 'editor.optional') }})</span></label>
                            <input
                                id="trip-map"
                                type="url"
                                inputmode="url"
                                :class="inputClass('map_url')"
                                :value="form.map_url ?? ''"
                                @input="changed({ map_url: $event.target.value })"
                                @blur="blurSave"
                            >
                            <p
                                v-if="errors.map_url"
                                class="mt-1 text-xs text-status-rejected"
                                role="alert"
                            >
                                {{ errors.map_url }}
                            </p>
                        </div>

                        <fieldset>
                            <legend class="mb-1 font-heading text-sm font-semibold text-ink">
                                {{ t(copy, 'editor.itinerary') }}
                            </legend>
                            <p class="mb-2 text-xs text-secondary">
                                {{ t(copy, 'editor.itinerary_hint') }}
                            </p>
                            <div
                                v-for="index in itineraryDays"
                                :key="index"
                                class="mb-2"
                            >
                                <label
                                    :for="`trip-itinerary-${index}`"
                                    class="mb-1 block text-xs font-semibold text-secondary"
                                >{{ t(copy, 'editor.itinerary_day', { n: index }) }}</label>
                                <input
                                    :id="`trip-itinerary-${index}`"
                                    type="text"
                                    :class="inputClass(`itinerary.${index - 1}`)"
                                    :value="form.itinerary[index - 1] ?? ''"
                                    :maxlength="options.limits.itinerary_line"
                                    @input="setItinerary(index - 1, $event.target.value)"
                                    @blur="blurSave"
                                >
                            </div>
                        </fieldset>

                        <div>
                            <label
                                for="trip-terms"
                                class="mb-1 block font-heading text-sm font-semibold text-ink"
                            >{{ t(copy, 'editor.terms') }}</label>
                            <textarea
                                id="trip-terms"
                                rows="4"
                                :class="inputClass('terms')"
                                class="py-2"
                                :value="form.terms ?? ''"
                                :maxlength="options.limits.terms"
                                @input="changed({ terms: $event.target.value })"
                                @blur="blurSave"
                            />
                        </div>
                    </section>

                    <!-- 03 Harga & cara pesan -->
                    <section class="flex flex-col gap-4 rounded-xl bg-surface p-4">
                        <h2 class="font-heading text-base font-semibold text-ink">
                            <span class="text-secondary">03</span> {{ t(copy, 'editor.sections.price') }}
                        </h2>
                        <div>
                            <label
                                for="trip-base-price"
                                class="mb-1 block font-heading text-sm font-semibold text-ink"
                            >{{ t(copy, 'editor.base_price') }}</label>
                            <MoneyInput
                                id="trip-base-price"
                                :model-value="form.base_price"
                                :currency="t(copy, 'money.currency')"
                                :invalid="Boolean(errors.base_price)"
                                @update:model-value="changed({ base_price: $event })"
                                @blur="blurSave"
                            />
                            <p
                                v-if="errors.base_price"
                                class="mt-1 text-xs text-status-rejected"
                                role="alert"
                            >
                                {{ errors.base_price }}
                            </p>
                        </div>
                        <fieldset>
                            <legend class="mb-2 font-heading text-sm font-semibold text-ink">
                                {{ t(copy, 'editor.booking_mode') }}
                            </legend>
                            <div class="grid grid-cols-2 gap-2">
                                <button
                                    v-for="mode in ['scheduled', 'request_date']"
                                    :key="mode"
                                    type="button"
                                    class="min-h-11 rounded-lg border px-3 text-sm font-semibold"
                                    :class="form.booking_mode === mode ? 'border-ink bg-ink text-surface' : 'border-ink/20 bg-surface text-ink'"
                                    :aria-pressed="form.booking_mode === mode ? 'true' : 'false'"
                                    @click="changed({ booking_mode: mode })"
                                >
                                    {{ t(copy, `editor.booking_modes.${mode}`) }}
                                </button>
                            </div>
                            <p
                                v-if="errors.booking_mode"
                                class="mt-1 text-xs text-status-rejected"
                                role="alert"
                            >
                                {{ errors.booking_mode }}
                            </p>
                        </fieldset>
                    </section>

                    <!-- 04 Keberangkatan -->
                    <section class="rounded-xl bg-surface p-4">
                        <h2 class="mb-1 font-heading text-base font-semibold text-ink">
                            <span class="text-secondary">04</span> {{ t(copy, 'editor.sections.departures') }}
                        </h2>
                        <p
                            v-if="!isScheduled"
                            class="text-sm text-secondary"
                        >
                            {{ t(copy, 'departures.request_mode_note') }}
                        </p>
                        <p
                            v-else-if="!tripState"
                            class="text-sm text-secondary"
                        >
                            {{ t(copy, 'departures.need_trip') }}
                        </p>
                        <template v-else>
                            <p class="mb-3 text-xs text-secondary">
                                {{ t(copy, 'departures.price_hint') }}
                            </p>
                            <div
                                v-if="!departuresState.upcoming.length && !departuresState.past.length"
                                class="rounded-lg border border-dashed border-ink/30 p-4 text-center"
                            >
                                <p class="font-heading text-sm font-semibold text-ink">
                                    {{ t(copy, 'departures.empty') }}
                                </p>
                            </div>
                            <DepartureList
                                v-else
                                :upcoming="departuresState.upcoming"
                                :past="departuresState.past"
                                :copy="copy"
                                @quick="quickSheet = $event.id"
                                @edit="departureSheet = { id: $event.id, template: null }"
                                @delete="deleteDeparture"
                            />
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="min-h-11 rounded-lg border border-ink px-4 text-sm font-semibold text-ink"
                                    @click="openNewDeparture(false)"
                                >
                                    {{ t(copy, 'departures.add') }}
                                </button>
                                <button
                                    v-if="copyTemplate"
                                    type="button"
                                    class="min-h-11 rounded-lg px-4 text-sm text-sogan underline"
                                    @click="openNewDeparture(true)"
                                >
                                    {{ t(copy, 'departures.copy') }}
                                </button>
                            </div>
                        </template>
                    </section>

                    <section
                        v-if="tripState"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            v-if="isPublished"
                            type="button"
                            class="min-h-11 rounded-lg border border-ink px-4 text-sm font-semibold text-ink"
                            @click="unpublish"
                        >
                            {{ t(copy, 'editor.unpublish') }}
                        </button>
                        <button
                            v-if="tripState.can_delete"
                            type="button"
                            class="min-h-11 rounded-lg border border-status-rejected px-4 text-sm font-semibold text-status-rejected"
                            @click="destroyTrip"
                        >
                            {{ t(copy, 'editor.delete') }}
                        </button>
                    </section>
                </div>

                <!-- Desktop side column: preview + checklist (1f). -->
                <aside class="hidden lg:block">
                    <div class="sticky top-4 flex flex-col gap-4">
                        <div>
                            <h2 class="mb-2 font-heading text-sm font-semibold text-ink">
                                {{ t(copy, 'preview.title') }}
                            </h2>
                            <TripPreviewCard
                                :preview="previewState"
                                :name="form.name"
                                :copy="copy"
                            />
                        </div>
                        <div class="rounded-xl bg-surface p-4">
                            <PublishPanel
                                :checklist="checklistState"
                                :published="isPublished"
                                :can-publish="Boolean(tripState)"
                                :payment-setup-url="urlState.payment_setup"
                                :busy="publishing"
                                :error="publishError"
                                :copy="copy"
                                @publish="publish"
                            />
                            <button
                                v-if="isPublished"
                                type="button"
                                class="mt-3 min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink"
                                @click="saveChanges"
                            >
                                {{ t(copy, 'editor.save_changes') }}
                            </button>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <!-- Phone: sticky bottom bar with checklist, preview and the one gold action (AC-41, AC-13). -->
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-ink/10 bg-surface px-4 py-3 shadow-[0_-4px_12px_rgba(33,23,16,0.08)] lg:hidden">
            <div
                v-if="showPreview"
                class="mb-3 max-h-[50vh] overflow-y-auto"
            >
                <TripPreviewCard
                    :preview="previewState"
                    :name="form.name"
                    :copy="copy"
                />
                <ul class="mt-3 flex flex-col gap-1 text-sm">
                    <li
                        v-for="item in checklistState.items"
                        :key="item.key"
                        :class="item.done ? 'text-ink' : 'text-secondary'"
                    >
                        <span aria-hidden="true">{{ item.done ? '✓' : '○' }}</span> {{ t(copy, `checklist.items.${item.key}`) }}
                    </li>
                </ul>
            </div>
            <div class="mx-auto flex max-w-md items-center gap-3">
                <button
                    type="button"
                    class="min-h-11 shrink-0 rounded-lg border border-ink px-3 text-sm font-semibold text-ink"
                    :aria-expanded="showPreview ? 'true' : 'false'"
                    @click="showPreview = !showPreview"
                >
                    {{ showPreview ? t(copy, 'preview.close') : t(copy, 'preview.open') }}
                </button>
                <div class="min-w-0 flex-1">
                    <button
                        v-if="isPublished"
                        type="button"
                        class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink"
                        @click="saveChanges"
                    >
                        {{ t(copy, 'editor.save_changes') }}
                    </button>
                    <PublishPanel
                        v-else
                        compact
                        :checklist="checklistState"
                        :can-publish="Boolean(tripState)"
                        :payment-setup-url="urlState.payment_setup"
                        :busy="publishing"
                        :error="publishError"
                        :copy="copy"
                        @publish="publish"
                    />
                </div>
            </div>
        </div>

        <DepartureForm
            v-if="departureSheet"
            :departure="editingDeparture"
            :template="departureSheet.template"
            :duration-days="form.duration_days"
            :meeting-time="form.meeting_time"
            :departures="departuresState.upcoming"
            :context="context"
            :presets="options.deadline_presets"
            :store-url="urlState.departures"
            :settings-url="urlState.settings"
            :copy="copy"
            @close="departureSheet = null"
        />
        <QuickEditSheet
            v-if="quickDeparture"
            :key="quickDeparture.id"
            :departure="quickDeparture"
            :context="context"
            :presets="options.deadline_presets"
            :reload="['departures', 'checklist', 'preview', 'flash']"
            :copy="copy"
            @close="quickSheet = null"
        />
    </SellerLayout>
</template>
