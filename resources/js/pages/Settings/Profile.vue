<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FieldError from '../../components/FieldError.vue';
import StickyAction from '../../components/StickyAction.vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { formatNational, nationalDigits } from '../../lib/phone.js';
import { normalizeSlugInput } from '../../lib/slug.js';
import { t } from '../../lib/text.js';

/**
 * Pengaturan → Profil usaha (F-001 F1, F2, F4). No mockup yet: built from the
 * 4b patterns. The link is read-only after the first publish (Q3).
 */
const props = defineProps({
    profile: { type: Object, required: true },
    slugLocked: { type: Boolean, required: true },
    publicHost: { type: String, required: true },
    timezones: { type: Array, required: true },
    limits: { type: Object, required: true },
    urls: { type: Object, required: true },
    copy: { type: Object, required: true },
    onboardingCopy: { type: Object, required: true },
    backLabel: { type: String, required: true },
});

const form = useForm({
    name: props.profile.name,
    city: props.profile.city,
    whatsapp: formatNational(props.profile.whatsapp),
    slug: props.profile.slug,
    bio: props.profile.bio ?? '',
    timezone: props.profile.timezone,
});

const logoInput = ref(null);
const logoError = ref(null);
const logoBusy = ref(false);

function submit() {
    if (form.processing) {
        return;
    }

    form.transform((data) => {
        const payload = { ...data, whatsapp: nationalDigits(data.whatsapp) === '' ? '' : `+62${nationalDigits(data.whatsapp)}` };

        if (props.slugLocked) {
            delete payload.slug;
        }

        return payload;
    }).put(props.urls.update, { preserveScroll: true });
}

function pickLogo() {
    logoError.value = null;
    logoInput.value?.click();
}

function onLogo(event) {
    const file = event.target.files?.[0];
    event.target.value = '';

    if (!file) {
        return;
    }

    if (!props.limits.logo_types.includes(file.type)) {
        logoError.value = t(props.copy, 'logo_type');

        return;
    }

    if (file.size > props.limits.logo_kb * 1024) {
        logoError.value = t(props.copy, 'logo_size');

        return;
    }

    router.post(props.urls.logo, { logo: file }, {
        forceFormData: true,
        preserveScroll: true,
        onStart: () => (logoBusy.value = true),
        onFinish: () => (logoBusy.value = false),
        onError: (errors) => (logoError.value = errors.logo ?? errors.rate_limit ?? null),
    });
}

function removeLogo() {
    router.delete(props.urls.logo, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t(copy, 'title')" />
    <SellerLayout hide-bottom-nav>
        <div class="mx-auto max-w-md px-4 pb-32 pt-6">
            <Link
                :href="urls.back"
                class="inline-flex min-h-11 items-center text-sm text-sogan underline"
            >
                {{ backLabel }}
            </Link>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                {{ t(copy, 'kicker') }}
            </p>
            <h1 class="font-heading text-xl font-semibold text-ink">
                {{ t(copy, 'title') }}
            </h1>

            <section class="mt-4 rounded-xl bg-surface p-4">
                <h2 class="font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'logo') }}
                </h2>
                <div class="mt-2 flex items-center gap-4">
                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-ink/10 bg-gading">
                        <img
                            v-if="profile.logo_url"
                            :src="profile.logo_url"
                            alt=""
                            class="h-full w-full object-cover"
                        >
                    </div>
                    <div class="flex flex-col items-start gap-1">
                        <button
                            type="button"
                            class="min-h-11 rounded-lg border-2 border-ink px-3 font-heading text-sm font-semibold text-ink disabled:border-ink/30 disabled:text-secondary"
                            :disabled="logoBusy"
                            @click="pickLogo"
                        >
                            {{ profile.logo_url ? t(copy, 'logo_change') : t(copy, 'logo_pick') }}
                        </button>
                        <button
                            v-if="profile.logo_url"
                            type="button"
                            class="min-h-11 text-sm text-status-rejected underline"
                            @click="removeLogo"
                        >
                            {{ t(copy, 'logo_remove') }}
                        </button>
                    </div>
                </div>
                <input
                    ref="logoInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="sr-only"
                    tabindex="-1"
                    aria-hidden="true"
                    @change="onLogo"
                >
                <p class="mt-2 text-xs text-secondary">
                    {{ t(copy, 'logo_hint') }}
                </p>
                <FieldError :message="logoError" />
            </section>

            <form
                id="profile-form"
                class="mt-4 flex flex-col gap-4 rounded-xl bg-surface p-4"
                novalidate
                @submit.prevent="submit"
            >
                <div>
                    <label
                        for="name"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'name') }}</label>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        autocomplete="organization"
                        :maxlength="limits.name"
                        class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                        :class="form.errors.name ? 'border-status-rejected' : 'border-ink/20'"
                    >
                    <FieldError :message="form.errors.name" />
                </div>

                <div>
                    <label
                        for="city"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'city') }}</label>
                    <input
                        id="city"
                        v-model="form.city"
                        type="text"
                        autocomplete="address-level2"
                        :maxlength="limits.city"
                        class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                        :class="form.errors.city ? 'border-status-rejected' : 'border-ink/20'"
                    >
                    <FieldError :message="form.errors.city" />
                </div>

                <div>
                    <label
                        for="whatsapp"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'whatsapp') }}</label>
                    <div
                        class="mt-1 flex min-h-12 items-stretch overflow-hidden rounded-lg border bg-surface"
                        :class="form.errors.whatsapp ? 'border-status-rejected' : 'border-ink/20'"
                    >
                        <span class="flex items-center border-r border-ink/10 bg-gading px-3 text-base text-secondary">+62</span>
                        <input
                            id="whatsapp"
                            v-model="form.whatsapp"
                            type="tel"
                            inputmode="numeric"
                            autocomplete="tel-national"
                            class="min-w-0 flex-1 bg-transparent px-3 text-base text-ink"
                            aria-describedby="whatsapp-hint"
                            @blur="form.whatsapp = formatNational(nationalDigits(form.whatsapp))"
                        >
                    </div>
                    <p
                        id="whatsapp-hint"
                        class="mt-1 text-xs text-secondary"
                    >
                        {{ t(copy, 'whatsapp_hint') }}
                    </p>
                    <FieldError :message="form.errors.whatsapp" />
                </div>

                <div>
                    <label
                        for="slug"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'slug') }}</label>
                    <div
                        class="mt-1 flex min-h-12 items-stretch overflow-hidden rounded-lg border"
                        :class="[form.errors.slug ? 'border-status-rejected' : 'border-ink/20', slugLocked ? 'bg-gading' : 'bg-surface']"
                    >
                        <span class="flex items-center border-r border-ink/10 bg-gading px-3 text-base text-secondary">{{ publicHost }}/</span>
                        <input
                            id="slug"
                            :value="form.slug"
                            type="text"
                            autocapitalize="none"
                            spellcheck="false"
                            :maxlength="limits.slug"
                            :readonly="slugLocked"
                            class="min-w-0 flex-1 bg-transparent px-3 text-base"
                            :class="slugLocked ? 'text-secondary' : 'text-ink'"
                            aria-describedby="slug-hint"
                            @input="form.slug = normalizeSlugInput($event.target.value); $event.target.value = form.slug"
                        >
                    </div>
                    <p
                        id="slug-hint"
                        class="mt-1 text-xs text-secondary"
                    >
                        {{ slugLocked ? t(copy, 'slug_locked') : t(onboardingCopy, 'slug_hint') }}
                    </p>
                    <FieldError :message="form.errors.slug" />
                </div>

                <div>
                    <label
                        for="bio"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'bio') }}</label>
                    <textarea
                        id="bio"
                        v-model="form.bio"
                        rows="3"
                        :maxlength="limits.bio"
                        class="mt-1 w-full rounded-lg border bg-surface px-3 py-2 text-base text-ink"
                        :class="form.errors.bio ? 'border-status-rejected' : 'border-ink/20'"
                        aria-describedby="bio-hint"
                    />
                    <p
                        id="bio-hint"
                        class="mt-1 flex justify-between text-xs text-secondary"
                    >
                        <span>{{ t(copy, 'bio_hint') }}</span>
                        <span>{{ Array.from(form.bio ?? '').length }}/{{ limits.bio }}</span>
                    </p>
                    <FieldError :message="form.errors.bio" />
                </div>

                <div>
                    <label
                        for="timezone"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'timezone') }}</label>
                    <select
                        id="timezone"
                        v-model="form.timezone"
                        class="mt-1 min-h-12 w-full rounded-lg border border-ink/20 bg-surface px-3 text-base text-ink"
                        aria-describedby="timezone-hint"
                    >
                        <option
                            v-for="zone in timezones"
                            :key="zone"
                            :value="zone"
                        >
                            {{ copy.timezones[zone] }}
                        </option>
                    </select>
                    <p
                        id="timezone-hint"
                        class="mt-1 text-xs text-secondary"
                    >
                        {{ t(copy, 'timezone_hint') }}
                    </p>
                    <FieldError :message="form.errors.timezone" />
                </div>

                <FieldError :message="form.errors.rate_limit" />
            </form>
        </div>

        <StickyAction>
            <button
                type="submit"
                form="profile-form"
                class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                :disabled="form.processing"
            >
                {{ t(copy, 'save') }}
            </button>
        </StickyAction>
    </SellerLayout>
</template>
