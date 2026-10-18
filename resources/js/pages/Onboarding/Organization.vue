<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import FieldError from '../../components/FieldError.vue';
import StickyAction from '../../components/StickyAction.vue';
import OnboardingLayout from '../../layouts/OnboardingLayout.vue';
import { saveFailureHandlers, sendJson } from '../../lib/http.js';
import { formatNational, isValidMobile, nationalDigits } from '../../lib/phone.js';
import { debounce, isValidSlug, normalizeSlugInput, slugFromName } from '../../lib/slug.js';
import { t } from '../../lib/text.js';

/**
 * Langkah 1 dari 4 · Usaha (mockup 4b adapted, F-001 B1–B12). The WhatsApp
 * number is a contact field, not verified (ADR-0012).
 */
const props = defineProps({
    organization: { type: Object, default: null },
    slugLocked: { type: Boolean, default: false },
    publicHost: { type: String, required: true },
    limits: { type: Object, required: true },
    header: { type: Object, required: true },
    highlight: { type: String, default: null },
    urls: { type: Object, required: true },
    copy: { type: Object, required: true },
});

const form = useForm({
    name: props.organization?.name ?? '',
    city: props.organization?.city ?? '',
    whatsapp: props.organization?.whatsapp ? formatNational(props.organization.whatsapp) : '',
    slug: props.organization?.slug ?? '',
});

// B4: an existing slug counts as edited; a new one follows the name.
const slugTouched = ref(Boolean(props.organization?.slug));
// idle | checking | available | taken | reserved | invalid
const slugState = ref(props.organization?.slug ? 'available' : 'idle');
const suggestions = ref([]);
const failed = ref(false);
let checkId = 0;

const whatsappInvalid = computed(() => {
    const digits = nationalDigits(form.whatsapp);

    return digits !== '' && digits.length >= 9 && !isValidMobile(digits);
});

const slugMessage = computed(() => {
    switch (slugState.value) {
        case 'checking':
            return { text: t(props.copy, 'organization.slug_checking'), tone: 'text-secondary' };
        case 'available':
            return { text: t(props.copy, 'organization.slug_available'), tone: 'text-status-paid' };
        case 'taken':
            return { text: t(props.copy, 'organization.slug_taken'), tone: 'text-status-rejected' };
        case 'reserved':
            return { text: t(props.copy, 'organization.slug_reserved'), tone: 'text-status-rejected' };
        case 'invalid':
            return { text: t(props.copy, 'organization.slug_invalid'), tone: 'text-status-rejected' };
        default:
            return null;
    }
});

async function checkSlug() {
    const slug = form.slug;
    const id = ++checkId;

    if (!isValidSlug(slug)) {
        slugState.value = slug === '' ? 'idle' : 'invalid';
        suggestions.value = [];

        return;
    }

    slugState.value = 'checking';

    try {
        const query = new URLSearchParams({ slug, name: form.name, city: form.city });
        const result = await sendJson('GET', `${props.urls.slug}?${query}`);

        // Only the latest answer counts.
        if (id === checkId) {
            slugState.value = result.state;
            suggestions.value = result.suggestions ?? [];
        }
    } catch {
        if (id === checkId) {
            slugState.value = 'idle';
        }
    }
}

const scheduleCheck = debounce(checkSlug, 400);
onBeforeUnmount(() => scheduleCheck.cancel());

watch(() => form.name, (name) => {
    if (!slugTouched.value && !props.slugLocked) {
        form.slug = slugFromName(name);
        scheduleCheck();
    }
});

function onSlugInput(event) {
    slugTouched.value = true;
    form.slug = normalizeSlugInput(event.target.value);
    event.target.value = form.slug;
    slugState.value = form.slug === '' ? 'idle' : 'checking';
    scheduleCheck();
}

function useSuggestion(slug) {
    slugTouched.value = true;
    form.slug = slug;
    checkSlug();
}

function onWhatsappInput(event) {
    // Digits and spaces only; a pasted "0812…" or "+62 812…" is cleaned on blur.
    form.whatsapp = event.target.value.replace(/[^\d\s+-]/g, '');
}

function onWhatsappBlur() {
    const digits = nationalDigits(form.whatsapp);
    form.whatsapp = digits === '' ? '' : formatNational(digits);
}

function submit() {
    if (form.processing) {
        return;
    }

    failed.value = false;
    form.transform((data) => ({ ...data, whatsapp: nationalDigits(data.whatsapp) === '' ? '' : `+62${nationalDigits(data.whatsapp)}` }))
        .post(props.urls.store, {
            preserveScroll: true,
            onError: (errors) => {
                // B10: someone took the slug meanwhile — show suggestions.
                if (errors.slug) {
                    checkSlug();
                }
            },
            ...saveFailureHandlers(() => (failed.value = true)),
        });
}
</script>

<template>
    <Head :title="t(copy, 'organization.title')" />
    <OnboardingLayout
        :header="header"
        :copy="copy"
    >
        <h1 class="font-heading text-xl font-semibold text-ink">
            {{ t(copy, 'organization.title') }}
        </h1>
        <p class="mt-1 text-sm text-secondary">
            {{ t(copy, 'organization.intro') }}
        </p>

        <form
            id="organization-form"
            class="mt-5 flex flex-col gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <div>
                <label
                    for="name"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'organization.name') }}</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="organization"
                    :maxlength="limits.name"
                    :placeholder="t(copy, 'organization.name_placeholder')"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                    :class="form.errors.name ? 'border-status-rejected' : 'border-ink/20'"
                    :aria-invalid="Boolean(form.errors.name) || undefined"
                    aria-describedby="name-error"
                >
                <FieldError
                    id="name-error"
                    :message="form.errors.name"
                />
            </div>

            <div>
                <label
                    for="city"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'organization.city') }}</label>
                <input
                    id="city"
                    v-model="form.city"
                    type="text"
                    autocomplete="address-level2"
                    :maxlength="limits.city"
                    :placeholder="t(copy, 'organization.city_placeholder')"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                    :class="form.errors.city ? 'border-status-rejected' : 'border-ink/20'"
                    :aria-invalid="Boolean(form.errors.city) || undefined"
                    aria-describedby="city-error"
                >
                <FieldError
                    id="city-error"
                    :message="form.errors.city"
                />
            </div>

            <div>
                <label
                    for="whatsapp"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'organization.whatsapp') }}</label>
                <div
                    class="mt-1 flex min-h-12 items-stretch overflow-hidden rounded-lg border bg-surface"
                    :class="form.errors.whatsapp || whatsappInvalid ? 'border-status-rejected' : 'border-ink/20'"
                >
                    <span class="flex items-center border-r border-ink/10 bg-gading px-3 text-base text-secondary">
                        {{ t(copy, 'organization.whatsapp_prefix') }}
                    </span>
                    <input
                        id="whatsapp"
                        :value="form.whatsapp"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        :placeholder="t(copy, 'organization.whatsapp_placeholder')"
                        class="min-w-0 flex-1 bg-transparent px-3 text-base text-ink"
                        :aria-invalid="Boolean(form.errors.whatsapp) || whatsappInvalid || undefined"
                        aria-describedby="whatsapp-hint whatsapp-error"
                        @input="onWhatsappInput"
                        @blur="onWhatsappBlur"
                    >
                </div>
                <p
                    id="whatsapp-hint"
                    class="mt-1 text-xs text-secondary"
                >
                    {{ t(copy, 'organization.whatsapp_hint') }}
                </p>
                <FieldError
                    id="whatsapp-error"
                    :message="form.errors.whatsapp ?? (whatsappInvalid ? t(copy, 'organization.whatsapp_invalid') : null)"
                />
            </div>

            <div>
                <label
                    for="slug"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'organization.slug') }}</label>
                <div
                    class="mt-1 flex min-h-12 items-stretch overflow-hidden rounded-lg border bg-surface"
                    :class="form.errors.slug ? 'border-status-rejected' : 'border-ink/20'"
                >
                    <span class="flex items-center border-r border-ink/10 bg-gading px-3 text-base text-secondary">
                        {{ publicHost }}/
                    </span>
                    <input
                        id="slug"
                        :value="form.slug"
                        type="text"
                        autocapitalize="none"
                        autocomplete="off"
                        spellcheck="false"
                        :maxlength="limits.slug"
                        :readonly="slugLocked"
                        class="min-w-0 flex-1 bg-transparent px-3 text-base text-ink"
                        :aria-invalid="Boolean(form.errors.slug) || undefined"
                        aria-describedby="slug-status slug-error"
                        @input="onSlugInput"
                    >
                </div>
                <p
                    v-if="slugLocked"
                    class="mt-1 text-xs text-secondary"
                >
                    {{ t(copy, 'organization.slug_locked') }}
                </p>
                <p
                    v-else-if="slugMessage"
                    id="slug-status"
                    class="mt-1 text-xs font-semibold"
                    :class="slugMessage.tone"
                    aria-live="polite"
                >
                    {{ slugMessage.text }}
                </p>
                <p
                    v-else
                    id="slug-status"
                    class="mt-1 text-xs text-secondary"
                >
                    {{ t(copy, 'organization.slug_hint') }}
                </p>
                <div
                    v-if="suggestions.length && (slugState === 'taken' || slugState === 'reserved')"
                    class="mt-1 flex flex-wrap items-center gap-2"
                >
                    <span class="text-xs text-secondary">{{ t(copy, 'organization.slug_suggestions') }}</span>
                    <button
                        v-for="suggestion in suggestions"
                        :key="suggestion"
                        type="button"
                        class="min-h-11 rounded-full border border-ink/20 bg-surface px-3 text-sm text-ink"
                        @click="useSuggestion(suggestion)"
                    >
                        {{ suggestion }}
                    </button>
                </div>
                <FieldError
                    v-if="form.errors.slug && form.errors.slug !== slugMessage?.text"
                    id="slug-error"
                    :message="form.errors.slug"
                />
            </div>

            <FieldError :message="form.errors.rate_limit" />
            <FieldError :message="failed ? t(copy, 'save_failed') : null" />
        </form>

        <template #action>
            <StickyAction>
                <button
                    type="submit"
                    form="organization-form"
                    class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                    :disabled="form.processing"
                    :aria-busy="form.processing"
                >
                    {{ form.processing ? t(copy, 'saving') : t(copy, 'organization.submit') }}
                </button>
            </StickyAction>
        </template>
    </OnboardingLayout>
</template>
